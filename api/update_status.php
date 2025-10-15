<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/cors.php';

setCorsHeaders();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

function sendResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function verifyToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        return null;
    }
    
    $token = str_replace('Bearer ', '', $authHeader);
    return JWT::decode($token);
}

if ($method === 'POST') {
    $payload = verifyToken();
    
    if (!$payload) {
        sendResponse(401, ['error' => 'Non autorizzato']);
    }
    
    $user_id = $payload['user_id'];
    $is_online = $input['is_online'] ?? false;
    
    try {
        $stmt = $db->prepare("UPDATE users SET is_online = ?, last_seen = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$is_online, $user_id]);
        
        sendResponse(200, ['success' => true]);
    } catch (PDOException $e) {
        sendResponse(500, ['error' => 'Errore aggiornamento stato']);
    }
} else {
    sendResponse(405, ['error' => 'Metodo non consentito']);
}
