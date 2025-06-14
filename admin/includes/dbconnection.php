<?php
require_once 'config.php';

try {
    $con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$con) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    // Set charset to prevent SQL injection
    mysqli_set_charset($con, "utf8mb4");
    
} catch (Exception $e) {
    error_log($e->getMessage());
    die("A database error occurred. Please try again later.");
}
?>
