<?php
function getDB() {
    // Criar a pasta database se não existir
    if (!file_exists(__DIR__ . '/../database')) {
        mkdir(__DIR__ . '/../database', 0777, true);
    }
    
    $db = new SQLite3(__DIR__ . '/../database/shelves.db');
    $db->exec("PRAGMA foreign_keys = ON");

    // Cria tabelas se não existirem
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            isAdmin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS shelves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS shelf_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shelf_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER DEFAULT 1,
            added_by INTEGER,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(product_id),
            FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE CASCADE,
            FOREIGN KEY (added_by) REFERENCES users(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS item_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            shelf_id INTEGER NOT NULL,
            entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
            saida DATETIME,
            FOREIGN KEY (product_id) REFERENCES shelf_items(product_id) ON DELETE CASCADE,
            FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE CASCADE
        )
    ");

    // Índices para performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_shelf_items_product ON shelf_items(product_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_shelf_items_shelf ON shelf_items(shelf_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_item_history_product ON item_history(product_id)");

    return $db;
}