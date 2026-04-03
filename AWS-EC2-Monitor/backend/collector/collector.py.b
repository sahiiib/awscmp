#!/usr/bin/env python3
import boto3
import sqlite3
import json
import time
from datetime import datetime
from botocore.exceptions import ClientError

DB_PATH = "/data/monitor.db"

def get_db():
    conn = sqlite3.connect(DB_PATH, timeout=30)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA busy_timeout=30000")   # 30 seconds
    conn.execute("PRAGMA synchronous=NORMAL")
    return conn

def log_action(action: str, details: str = ""):
    """Very tolerant logging - never crash the collector"""
    for attempt in range(12):   # ~15-20 seconds total retry
        try:
            conn = get_db()
            conn.execute(
                "INSERT INTO logs (action, details) VALUES (?, ?)",
                (action[:200], str(details)[:800])
            )
            conn.commit()
            conn.close()
            return
        except sqlite3.OperationalError as e:
            if any(x in str(e).lower() for x in ["locked", "busy", "database is locked"]):
                time.sleep(0.4 * (attempt + 1))
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
    print(f"WARNING: Failed to log: {action}")

def collect_ec2_data():
    print(f"[{datetime.now()}] Starting EC2 collection...")
    try:
        conn = get_db()
        cursor = conn.cursor()

        cursor.execute("SELECT id, account_name, access_key_id, secret_access_key FROM aws_accounts")
        accounts = cursor.fetchall()

        if not accounts:
            print("No AWS accounts found.")
            log_action("Collection skipped - no accounts")
            conn.close()
            return

        for acc in accounts:
            acc_id = acc["id"]
            acc_name = acc["account_name"]
            try:
                print(f"Processing: {acc_name}")
                session = boto3.Session(
                    aws_access_key_id=acc["access_key_id"],
                    aws_secret_access_key=acc["secret_access_key"]
                )

                ec2 = session.client("ec2", region_name="us-east-1")
                regions = [r["RegionName"] for r in ec2.describe_regions()["Regions"]]

                for region in regions:
                    ec2_reg = session.client("ec2", region_name=region)
                    paginator = ec2_reg.get_paginator("describe_instances")

                    for page in paginator.paginate():
                        for res in page.get("Reservations", []):
                            for inst in res.get("Instances", []):
                                cursor.execute("""
                                    INSERT OR REPLACE INTO ec2_instances 
                                    (aws_account_id, instance_id, region, state, name, public_ip, private_ip,
                                     instance_type, launch_time, tags, security_groups, iam_instance_profile, vpc_id, subnet_id)
                                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                                """, (
                                    acc_id, inst["InstanceId"], region, inst["State"]["Name"],
                                    next((t["Value"] for t in inst.get("Tags", []) if t["Key"]=="Name"), ""),
                                    inst.get("PublicIpAddress"), inst.get("PrivateIpAddress"),
                                    inst["InstanceType"],
                                    inst.get("LaunchTime").isoformat() if inst.get("LaunchTime") else None,
                                    json.dumps({t["Key"]:t["Value"] for t in inst.get("Tags",[])}),
                                    json.dumps([g["GroupId"] for g in inst.get("SecurityGroups",[])]),
                                    inst.get("IamInstanceProfile",{}).get("Arn",""),
                                    inst.get("VpcId"), inst.get("SubnetId")
                                ))

                log_action(f"Collected data for account: {acc_name}")
                print(f"✅ {acc_name} done")

            except Exception as e:
                err = str(e)
                print(f"❌ Error {acc_name}: {err[:150]}")
                log_action(f"ERROR account {acc_name}", err)

        conn.commit()
        conn.close()
        log_action("Full collection cycle completed")
        print(f"[{datetime.now()}] Collection finished.")

    except Exception as e:
        print(f"Critical error: {e}")
        log_action("Critical error", str(e))

if __name__ == "__main__":
    collect_ec2_data()