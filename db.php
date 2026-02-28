<?php
// Database connection details
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "smart_shop";
$port = 3306;

// Check for portable configuration
if (file_exists(__DIR__ . '/portable_config.php')) {
    include __DIR__ . '/portable_config.php';
    if (isset($PORTABLE_DB_PORT)) {
        $port = $PORTABLE_DB_PORT;
    }
} elseif (getenv('DB_PORT')) {
    $port = getenv('DB_PORT');
}

// Check for MySQLi extension
if (!extension_loaded('mysqli')) {
    die("<h3>Error: MySQLi extension is not loaded.</h3><p>Please enable the 'mysqli' extension in your php.ini configuration or check your PHP installation.</p>");
}

// Create connection
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable exceptions
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
} catch (mysqli_sql_exception $e) {
    // If database does not exist (Error 1049), redirect to installation
    if ($e->getCode() == 1049) {
        header("Location: install.php");
        exit();
    }
    die("Connection failed: " . $e->getMessage());
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
