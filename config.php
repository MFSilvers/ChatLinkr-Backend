<?php

// Production configuration
define('APP_NAME', 'ChatLinkr');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production'); // Change to 'development' for dev

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '5432');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'chatlinkr');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// JWT configuration
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? $_ENV['SESSION_SECRET']);

// Validate JWT secret
if (!JWT_SECRET) {
    throw new Exception('JWT_SECRET or SESSION_SECRET environment variable is required');
}
define('JWT_EXPIRY', 86400); // 24 hours

// CORS configuration
define('CORS_ORIGIN', $_ENV['CORS_ORIGIN'] ?? '*');
define('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// Security settings
define('PASSWORD_MIN_LENGTH', 6);
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 50);
define('MESSAGE_MAX_LENGTH', 1000);

// Rate limiting (requests per minute)
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60);

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt']);

// Logging
define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'error');
define('LOG_FILE', __DIR__ . '/../logs/app.log');

// Create logs directory if it doesn't exist
$log_dir = dirname(LOG_FILE);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Error handling based on environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_FILE);
}
