<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

echo json_encode([
    'status' => 'success',
    'message' => 'Backend Railway funzionante!',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'json' => extension_loaded('json')
    ],
    'environment' => [
        'DB_HOST' => isset($_ENV['DB_HOST']) ? 'SET' : 'NOT_SET',
        'DB_NAME' => isset($_ENV['DB_NAME']) ? 'SET' : 'NOT_SET',
        'DB_USER' => isset($_ENV['DB_USER']) ? 'SET' : 'NOT_SET',
        'SESSION_SECRET' => isset($_ENV['SESSION_SECRET']) ? 'SET' : 'NOT_SET'
    ]
]);
?>
