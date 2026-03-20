<?php
function getMySQLConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=localhost;dbname=prestashop;charset=utf8mb4',
                'root',
                ''
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Erro MySQL: ' . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}