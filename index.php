<?php

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request URI and method
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($request_uri, PHP_URL_PATH);

// Route API requests
if (strpos($path, '/api/') === 0) {
    $api_path = substr($path, 4); // Remove '/api' prefix
    
    // Route to appropriate API file
    switch ($api_path) {
        case '/auth.php':
        case '/auth':
            require_once __DIR__ . '/api/auth.php';
            break;
            
        case '/messages.php':
        case '/messages':
            require_once __DIR__ . '/api/messages.php';
            break;
            
        case '/users.php':
        case '/users':
            require_once __DIR__ . '/api/users.php';
            break;
            
        case '/update_status.php':
        case '/update_status':
            require_once __DIR__ . '/api/update_status.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
            break;
    }
} else {
    // Default response for root path
    echo json_encode([
        'name' => 'ChatLinkr API',
        'version' => '1.0.0',
        'status' => 'running',
        'endpoints' => [
            'POST /api/auth.php?action=register' => 'User registration',
            'POST /api/auth.php?action=login' => 'User login',
            'POST /api/auth.php?action=logout' => 'User logout',
            'GET /api/auth.php' => 'Current user info',
            'GET /api/messages.php?action=conversations' => 'Get conversations',
            'GET /api/messages.php?action=history&contact_id=X' => 'Get message history',
            'POST /api/messages.php' => 'Send message',
            'GET /api/users.php' => 'Get users list',
            'GET /api/users.php?search=query' => 'Search users',
            'POST /api/update_status.php' => 'Update user status'
        ]
    ]);
}
