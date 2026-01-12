<?php
/**
 * Funções JWT Simples
 */

/**
 * Codifica JWT
 */
function jwtEncode($payload, $secret = JWT_SECRET) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Decodifica JWT
 */
function jwtDecode($jwt, $secret = JWT_SECRET) {
    $tokenParts = explode('.', $jwt);
    
    if (count($tokenParts) !== 3) {
        return false;
    }
    
    $header = base64UrlDecode($tokenParts[0]);
    $payload = base64UrlDecode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];
    
    // Verificar assinatura
    $base64UrlHeader = $tokenParts[0];
    $base64UrlPayload = $tokenParts[1];
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);
    
    if ($base64UrlSignature !== $signatureProvided) {
        return false;
    }
    
    return json_decode($payload);
}

/**
 * Valida JWT
 */
function jwtValidate($jwt, $secret = JWT_SECRET) {
    return jwtDecode($jwt, $secret) !== false;
}

/**
 * Base64 URL Encode
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL Decode
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}
