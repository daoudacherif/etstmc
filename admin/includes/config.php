<?php
// Database configuration
define('DB_HOST', 'mysql.hostinger.com');
define('DB_USER', 'u451994146_root');
define('DB_PASS', 'Daoudacherif4321');
define('DB_NAME', 'u451994146_etstmc');

// Security settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_UPPERCASE', true);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log'); 