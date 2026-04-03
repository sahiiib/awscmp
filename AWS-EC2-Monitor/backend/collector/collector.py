#!/usr/bin/env python3
import boto3
import sqlite3
import json
import time
import threading
from datetime import datetime
from botocore.exceptions import ClientError
from concurrent.futures import ThreadPoolExecutor, as_completed
from filelock import FileLock, Timeout

DB_PATH = "/data/monitor.db"
LOCK_PATH = "/tmp/collector.lock"   # Prevents overlapping runs

def get_db():
    """Short-lived connection optimized for WAL concurrency"""
    conn = sqlite3.connect(DB_PATH, timeout=45)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA busy_timeout=45000")
    conn.execute("PRAGMA synchronous=NORMAL")
    conn.execute("PRAGMA cache_size=-32000")      # ~128 MB cache
    conn.execute("PRAGMA temp_store=MEMORY")
    return conn

def log_action(action: str, details: str = ""):
    """Tolerant logging - never blocks the collector"""
    for attempt in range(15):
        try:
            conn = get_db()
            conn.execute(
                "INSERT INTO logs (action, details) VALUES (?, ?)",
                (action[:250], str(details)[:900])
            )
            conn.commit()
            conn.close()
            return
        except sqlite3.OperationalError as e:
            if "locked" in str(e).lower() or "busy" in str(e).lower():
                time.sleep(0.3 * (attempt + 1))
                continue
            print(f"Log DB error: {e}")
            break
        except Exception as e:
            print(f"Log unexpected error: {e}")
            break
        finally:
            try:
                if 'conn' in locals():
                    conn.close()
            except:
                pass
    print(f"WARNING: Failed to log action: {action}")

def process_region(session, acc_id, region):
    """Fetch all instances in one region (thread-safe)"""
    instances = []
    try:
        ec2_reg = session.client("ec2", region_name=region)
        paginator = ec2_reg.get_paginator("describe_instances")

        for page in paginator.paginate():
            for res in page.get("Reservations", []):
                for inst in res.get("Instances", []):
                    name = next(
                        (t["Value"] for t in inst.get("Tags", []) if t.get("Key") == "Name"),
                        ""
                    )
                    tags_dict = {t["Key"]: t["Value"] for t in inst.get("Tags", [])}
                    sg_list = [g["GroupId"] for g in inst.get("SecurityGroups", [])]

                    instances.append((
                        acc_id,
                        inst["InstanceId"],
                        region,
                        inst["State"]["Name"],
                        name,
                        inst.get("PublicIpAddress"),
                        inst.get("PrivateIpAddress"),
                        inst["InstanceType"],
                        inst.get("LaunchTime").isoformat() if inst.get("LaunchTime") else None,
                        json.dumps(tags_dict),
                        json.dumps(sg_list),
                        inst.get("IamInstanceProfile", {}).get("Arn", ""),
                        inst.get("VpcId"),
                        inst.get("SubnetId")
                    ))
    except Exception as e:
        print(f"  Region {region} error: {str(e)[:120]}")
    return instances

def collect_ec2_data():
    print(f"[{datetime.now()}] Starting EC2 collection...")

    lock = FileLock(LOCK_PATH, timeout=5)
    try:
        with lock:   # Only one collector runs at a time
            conn = get_db()
            cursor = conn.cursor()

            cursor.execute("SELECT id, account_name, access_key_id, secret_access_key FROM aws_accounts")
            accounts = cursor.fetchall()
            conn.close()

            if not accounts:
                print("No AWS accounts configured.")
                log_action("Collection skipped", "No accounts found")
                return

            for acc in accounts:
                acc_id = acc["id"]
                acc_name = acc["account_name"]
                print(f"Processing account: {acc_name}")

                try:
                    session = boto3.Session(
                        aws_access_key_id=acc["access_key_id"],
                        aws_secret_access_key=acc["secret_access_key"]
                    )

                    # Get regions once
                    ec2_global = session.client("ec2", region_name="us-east-1")
                    regions = [r["RegionName"] for r in ec2_global.describe_regions()["Regions"]]

                    all_instances = []
                    max_workers = min(10, len(regions))   # Safe concurrency

                    with ThreadPoolExecutor(max_workers=max_workers) as executor:
                        future_to_region = {
                            executor.submit(process_region, session, acc_id, region): region
                            for region in regions
                        }
                        for future in as_completed(future_to_region):
                            all_instances.extend(future.result())

                    # Bulk upsert - very fast
                    if all_instances:
                        conn = get_db()
                        cursor = conn.cursor()
                        cursor.executemany("""
                            INSERT OR REPLACE INTO ec2_instances 
                            (aws_account_id, instance_id, region, state, name, public_ip, private_ip,
                             instance_type, launch_time, tags, security_groups, iam_instance_profile,
                             vpc_id, subnet_id)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                        """, all_instances)
                        conn.commit()
                        conn.close()

                    log_action(f"Collected data for account: {acc_name}", f"{len(all_instances)} instances across {len(regions)} regions")
                    print(f"✅ {acc_name} completed ({len(all_instances)} instances)")

                except ClientError as e:
                    err_str = str(e)
                    if "UnauthorizedOperation" in err_str:
                        msg = "Unauthorized - ensure IAM keys have ec2:DescribeRegions and ec2:DescribeInstances permissions"
                        print(f"❌ {acc_name}: {msg}")
                        log_action(f"ERROR {acc_name}", msg)
                    else:
                        print(f"❌ {acc_name}: {err_str[:150]}")
                        log_action(f"ERROR {acc_name}", err_str)
                except Exception as e:
                    print(f"❌ {acc_name}: {str(e)[:150]}")
                    log_action(f"ERROR {acc_name}", str(e))

            log_action("Full collection cycle completed")
            print(f"[{datetime.now()}] Collection finished successfully.")

    except Timeout:
        print(f"[{datetime.now()}] Collector already running - skipping this run.")
        log_action("Collection skipped", "Another instance is already running (lock timeout)")
    except Exception as e:
        print(f"Critical error: {e}")
        log_action("Critical collection error", str(e))

if __name__ == "__main__":
    collect_ec2_data()