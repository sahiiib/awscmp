PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS aws_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_name TEXT NOT NULL UNIQUE,
    owner TEXT,
    access_key_id TEXT NOT NULL,
    secret_access_key TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ec2_instances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    aws_account_id INTEGER,
    instance_id TEXT NOT NULL,
    region TEXT NOT NULL,
    state TEXT,
    name TEXT,
    public_ip TEXT,
    private_ip TEXT,
    instance_type TEXT,
    launch_time TEXT,
    tags TEXT,                -- JSON
    security_groups TEXT,     -- JSON
    iam_instance_profile TEXT,
    vpc_id TEXT,
    subnet_id TEXT,
    FOREIGN KEY (aws_account_id) REFERENCES aws_accounts(id) ON DELETE CASCADE,
    UNIQUE(instance_id, region, aws_account_id)
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT DEFAULT 'user',   -- admin / user
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    action TEXT NOT NULL,
    details TEXT,
    user_id INTEGER
);
