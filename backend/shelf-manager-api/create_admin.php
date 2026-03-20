<?php
require 'config/database.php';

$db = getDB();

// Verifica se já existe admin
$stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
$result = $stmt->execute();
if ($result->fetchArray()) {
    echo "Admin já existe!\n";
    exit;
}

// Cria admin com senha 123456
$hash = password_hash('123456', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO users (username, password_hash, isAdmin) VALUES ('admin', :hash, 1)");
$stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
$stmt->execute();

echo "Admin criado com sucesso! Usuário: admin, Senha: 123456\n";