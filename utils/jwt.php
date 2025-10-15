<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

class JWT {
    private static $secret_key = null;
    
    private static function getSecretKey() {
        if (self::$secret_key === null) {
            // Load environment variables if not already loaded
            if (!isset($_ENV['SESSION_SECRET'])) {
                $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
                $dotenv->load();
            }
            
            self::$secret_key = $_ENV['SESSION_SECRET'] ?? getenv('SESSION_SECRET');
            
            if (!self::$secret_key) {
                throw new Exception('SESSION_SECRET environment variable is required');
            }
        }
        return self::$secret_key;
    }
    
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);
        
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        if ($base64UrlSignature !== $signatureProvided) {
            return false;
        }
        
        $payloadArray = json_decode($payload, true);
        
        if (isset($payloadArray['exp']) && $payloadArray['exp'] < time()) {
            return false;
        }
        
        return $payloadArray;
    }
    
    private static function base64UrlEncode($text) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
}
