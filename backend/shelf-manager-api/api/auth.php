<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

global $app;

$app->post('/auth/login', function ($request, $response) {
    $data = $request->getParsedBody();
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    // Validação básica
    if (empty($username) || empty($password)) {
        $response->getBody()->write(json_encode(['error' => 'Usuário e senha são obrigatórios']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Logs para depuração (remova em produção)
    error_log("=== Tentativa de login ===");
    error_log("Username: " . $username);
    error_log("Usuário encontrado: " . ($user ? 'sim' : 'não'));
    if ($user) {
        error_log("Hash no banco: " . $user['password_hash']);
        error_log("Senha fornecida: " . $password);
        $verify = password_verify($password, $user['password_hash']);
        error_log("Resultado password_verify: " . ($verify ? 'true' : 'false'));
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $response->getBody()->write(json_encode(['error' => 'Credenciais inválidas']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $payload = [
        'id' => $user['id'],
        'username' => $user['username'],
        'isAdmin' => (bool)$user['isAdmin'],
        'exp' => time() + 3600
    ];

    $jwt = JWT::encode($payload, 'sua_chave_secreta_aqui', 'HS256');
    $response->getBody()->write(json_encode(['access_token' => $jwt, 'token_type' => 'bearer']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/auth/register', function ($request, $response) {
    $data = $request->getParsedBody();
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response->getBody()->write(json_encode(['error' => 'Campos obrigatórios']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    if ($result->fetchArray()) {
        $response->getBody()->write(json_encode(['error' => 'Usuário já existe']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = isset($data['isAdmin']) ? (int)$data['isAdmin'] : 0;

    $stmt = $db->prepare("INSERT INTO users (username, password_hash, isAdmin) VALUES (:username, :hash, :isAdmin)");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':isAdmin', $isAdmin, SQLITE3_INTEGER);
    $stmt->execute();

    $userId = $db->lastInsertRowID();
    $responseData = [
        'success' => true,
        'data' => ['id' => $userId, 'username' => $username, 'isAdmin' => (bool)$isAdmin]
    ];
    $response->getBody()->write(json_encode($responseData));
    return $response->withHeader('Content-Type', 'application/json');
});