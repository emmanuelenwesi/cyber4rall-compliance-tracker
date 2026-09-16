<?php
require_once __DIR__ . '/../config.php';

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Sorry, something went wrong connecting to the database. Please try again shortly.');
        }
    }
    return $conn;
}
