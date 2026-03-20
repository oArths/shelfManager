<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticate($request) {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) return null;

    $token = str_replace('Bearer ', '', $authHeader);
    try {
        $decoded = JWT::decode($token, new Key('sua_chave_secreta_aqui', 'HS256'));
        return (array)$decoded;
    } catch (Exception $e) {
        return null;
    }
}