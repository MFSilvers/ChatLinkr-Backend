<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get database configuration from environment variables
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 5432;
    $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $password = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
    $sslmode = $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?? 'require';

    $response = [
        'status' => 'testing',
        'environment' => [
            'DB_HOST' => $host ? 'SET' : 'NOT SET',
            'DB_PORT' => $port ? 'SET' : 'NOT SET',
            'DB_NAME' => $dbname ? 'SET' : 'NOT SET',
            'DB_USER' => $user ? 'SET' : 'NOT SET',
            'DB_PASS' => $password ? 'SET' : 'NOT SET',
            'DB_SSLMODE' => $sslmode ? 'SET' : 'NOT SET'
        ]
    ];

    // Validate required parameters
    if (!$host || !$dbname || !$user) {
        $response['error'] = 'Database configuration incomplete';
        $response['missing'] = [];
        if (!$host) $response['missing'][] = 'DB_HOST';
        if (!$dbname) $response['missing'][] = 'DB_NAME';
        if (!$user) $response['missing'][] = 'DB_USER';
        
        http_response_code(400);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }

    // Test database connection
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
    
    $response['connection_test'] = [
        'dsn' => "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode",
        'attempting_connection' => true
    ];

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 10
    ]);

    $response['status'] = 'success';
    $response['connection_test']['result'] = 'SUCCESS';
    $response['connection_test']['message'] = 'Database connection successful';

    // Test a simple query
    $stmt = $pdo->query("SELECT version() as version");
    $result = $stmt->fetch();
    $response['database_info'] = $result;

} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['connection_test']['result'] = 'FAILED';
    $response['connection_test']['error'] = $e->getMessage();
    $response['connection_test']['error_code'] = $e->getCode();
    
    http_response_code(500);
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['connection_test']['result'] = 'FAILED';
    $response['connection_test']['error'] = $e->getMessage();
    
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>

