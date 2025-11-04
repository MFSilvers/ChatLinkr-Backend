<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/cors.php';

setCorsHeaders();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

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

$payload = verifyToken();

if (!$payload) {
    sendResponse(401, ['error' => 'Non autorizzato']);
}

$user_id = $payload['user_id'];

switch ($method) {
    case 'GET':
        try {
            $search = $_GET['search'] ?? '';
            
            if ($search) {
                $stmt = $db->prepare("
                    SELECT id, username, 
                        CASE 
                            WHEN last_seen > NOW() - INTERVAL '5 minutes' THEN 1
                            ELSE 0
                        END as is_online
                    FROM users 
                    WHERE id != ? AND (username ILIKE ? OR email ILIKE ?)
                    ORDER BY username
                    LIMIT 20
                ");
                $searchTerm = "%{$search}%";
                $stmt->execute([$user_id, $searchTerm, $searchTerm]);
            } else {
                $stmt = $db->prepare("
                    SELECT id, username, 
                        CASE 
                            WHEN last_seen > NOW() - INTERVAL '5 minutes' THEN 1
                            ELSE 0
                        END as is_online
                    FROM users 
                    WHERE id != ?
                    ORDER BY username
                    LIMIT 50
                ");
                $stmt->execute([$user_id]);
            }
            
            $users = $stmt->fetchAll();
            sendResponse(200, ['users' => $users]);
        } catch (PDOException $e) {
            sendResponse(500, ['error' => 'Errore nel recupero utenti']);
        }
        break;
        
    case 'PUT':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['username'])) {
                sendResponse(400, ['error' => 'Username richiesto']);
            }
            
            $new_username = trim($input['username']);
            
            // Validazione username
            if (strlen($new_username) < 3) {
                sendResponse(400, ['error' => 'Username deve essere di almeno 3 caratteri']);
            }
            
            if (strlen($new_username) > 20) {
                sendResponse(400, ['error' => 'Username non può superare i 20 caratteri']);
            }
            
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
                sendResponse(400, ['error' => 'Username può contenere solo lettere, numeri e underscore']);
            }
            
            // Controlla se l'username è già in uso
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$new_username, $user_id]);
            
            if ($stmt->fetch()) {
                sendResponse(409, ['error' => 'Username già in uso']);
            }
            
            // Aggiorna l'username
            $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$new_username, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                sendResponse(200, [
                    'message' => 'Username aggiornato con successo',
                    'username' => $new_username
                ]);
            } else {
                sendResponse(500, ['error' => 'Errore durante l\'aggiornamento']);
            }
            
        } catch (PDOException $e) {
            sendResponse(500, ['error' => 'Errore del database']);
        }
        break;
        
    default:
        sendResponse(405, ['error' => 'Metodo non consentito']);
}
