<?php
define('DB_PATH', '/data/monitor.db');
define('APP_NAME', 'AWS EC2 Monitor');

function get_db() {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

session_start();
?>
