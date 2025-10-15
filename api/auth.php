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

switch ($method) {
    case 'POST':
        $action = $input['action'] ?? '';
        
        if ($action === 'register') {
            $username = $input['username'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($username) || empty($email) || empty($password)) {
                sendResponse(400, ['error' => 'Tutti i campi sono richiesti']);
            }
            
            if (strlen($password) < 6) {
                sendResponse(400, ['error' => 'La password deve essere di almeno 6 caratteri']);
            }
            
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            try {
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                
                $user_id = $db->lastInsertId();
                
                $payload = [
                    'user_id' => $user_id,
                    'username' => $username,
                    'exp' => time() + (86400 * 7)
                ];
                
                $token = JWT::encode($payload);
                
                sendResponse(201, [
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user_id,
                        'username' => $username,
                        'email' => $email
                    ]
                ]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'users_username_key') !== false) {
                    sendResponse(400, ['error' => 'Username già in uso']);
                } elseif (strpos($e->getMessage(), 'users_email_key') !== false) {
                    sendResponse(400, ['error' => 'Email già in uso']);
                }
                sendResponse(500, ['error' => 'Errore durante la registrazione']);
            }
        } elseif ($action === 'login') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                sendResponse(400, ['error' => 'Username e password richiesti']);
            }
            
            try {
                $stmt = $db->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    sendResponse(401, ['error' => 'Credenziali non valide']);
                }
                
                $stmt = $db->prepare("UPDATE users SET is_online = TRUE, last_seen = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                $payload = [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'exp' => time() + (86400 * 7)
                ];
                
                $token = JWT::encode($payload);
                
                sendResponse(200, [
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email']
                    ]
                ]);
            } catch (PDOException $e) {
                sendResponse(500, ['error' => 'Errore durante il login']);
            }
        } elseif ($action === 'logout') {
            $payload = verifyToken();
            
            if (!$payload) {
                sendResponse(401, ['error' => 'Non autorizzato']);
            }
            
            try {
                $stmt = $db->prepare("UPDATE users SET is_online = FALSE, last_seen = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$payload['user_id']]);
                
                sendResponse(200, ['success' => true, 'message' => 'Logout effettuato']);
            } catch (PDOException $e) {
                sendResponse(500, ['error' => 'Errore durante il logout']);
            }
        } else {
            sendResponse(404, ['error' => 'Azione non trovata']);
        }
        break;
        
    case 'GET':
        $payload = verifyToken();
        
        if (!$payload) {
            sendResponse(401, ['error' => 'Non autorizzato']);
        }
        
        try {
            $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id = ?");
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                sendResponse(200, ['user' => $user]);
            } else {
                sendResponse(404, ['error' => 'Utente non trovato']);
            }
        } catch (PDOException $e) {
            sendResponse(500, ['error' => 'Errore server']);
        }
        break;
        
    default:
        sendResponse(405, ['error' => 'Metodo non consentito']);
}
