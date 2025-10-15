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

$payload = verifyToken();

if (!$payload) {
    sendResponse(401, ['error' => 'Non autorizzato']);
}

$user_id = $payload['user_id'];

// Log per debugging
error_log("User ID from token: " . $user_id);

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        if ($action === 'conversations') {
            try {
                $stmt = $db->prepare("
                    SELECT 
                        conv.contact_id,
                        u.username as contact_username,
                        CASE 
                            WHEN u.last_seen > NOW() - INTERVAL '5 minutes' THEN 1
                            ELSE 0
                        END as contact_online,
                        (SELECT message FROM messages 
                         WHERE (sender_id = ? AND receiver_id = conv.contact_id) 
                            OR (sender_id = conv.contact_id AND receiver_id = ?)
                         ORDER BY created_at DESC LIMIT 1) as last_message,
                        (SELECT created_at FROM messages 
                         WHERE (sender_id = ? AND receiver_id = conv.contact_id) 
                            OR (sender_id = conv.contact_id AND receiver_id = ?)
                         ORDER BY created_at DESC LIMIT 1) as last_message_time,
                        (SELECT COUNT(*) FROM messages 
                         WHERE sender_id = conv.contact_id AND receiver_id = ? AND is_read = FALSE) as unread_count
                    FROM (
                        SELECT DISTINCT CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as contact_id
                        FROM messages m
                        WHERE m.sender_id = ? OR m.receiver_id = ?
                    ) conv
                    JOIN users u ON u.id = conv.contact_id
                    ORDER BY last_message_time DESC
                ");
                $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
                
                $conversations = $stmt->fetchAll();
                sendResponse(200, ['conversations' => $conversations]);
            } catch (PDOException $e) {
                sendResponse(500, ['error' => 'Errore nel recupero conversazioni', 'details' => $e->getMessage()]);
            }
        } elseif ($action === 'history') {
            $contact_id = $_GET['contact_id'] ?? 0;
            
            if (!$contact_id) {
                sendResponse(400, ['error' => 'ID contatto richiesto']);
            }
            
            try {
                $stmt = $db->prepare("
                    SELECT m.*, 
                           u.username as sender_username
                    FROM messages m
                    JOIN users u ON u.id = m.sender_id
                    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                       OR (m.sender_id = ? AND m.receiver_id = ?)
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
                
                $messages = $stmt->fetchAll();
                
                $stmt = $db->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?");
                $stmt->execute([$contact_id, $user_id]);
                
                sendResponse(200, ['messages' => $messages]);
            } catch (PDOException $e) {
                sendResponse(500, ['error' => 'Errore nel recupero messaggi']);
            }
        } else {
            sendResponse(404, ['error' => 'Azione non trovata']);
        }
        break;
        
    case 'POST':
        $receiver_id = $input['receiver_id'] ?? 0;
        $message = $input['message'] ?? '';
        
        if (!$receiver_id || empty($message)) {
            sendResponse(400, ['error' => 'Destinatario e messaggio richiesti']);
        }
        
        if ($receiver_id == $user_id) {
            sendResponse(400, ['error' => 'Non puoi inviare messaggi a te stesso']);
        }
        
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$receiver_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, ['error' => 'Destinatario non trovato']);
            }
            
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?) RETURNING id, created_at");
            $stmt->execute([$user_id, $receiver_id, $message]);
            $result = $stmt->fetch();
            
            // Log per debugging
            error_log("Message saved - sender_id: " . $user_id . ", receiver_id: " . $receiver_id . ", message: " . $message);
            
            sendResponse(201, [
                'success' => true,
                'message' => [
                    'id' => $result['id'],
                    'sender_id' => $user_id,
                    'receiver_id' => $receiver_id,
                    'message' => $message,
                    'created_at' => $result['created_at']
                ]
            ]);
        } catch (PDOException $e) {
            sendResponse(500, ['error' => 'Errore nell\'invio del messaggio']);
        }
        break;
        
    default:
        sendResponse(405, ['error' => 'Metodo non consentito']);
}
