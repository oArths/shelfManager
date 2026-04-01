<?php

/**
 * Plugin Name: Shelf Manager API
 * Description: API para gerenciamento de prateleiras
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==================== CONFIGURAÇÕES ====================
define('SHELF_DB_PATH', WP_CONTENT_DIR . '/uploads/shelf-manager/database.db');
define('SHELF_SECRET_KEY', 'SHELF_MANAGER_2024');
define('SHELF_WP_API_URL', 'https://lebrecho.com.br/wp-json/shelf-products/v1');
define('SHELF_WP_API_KEY', 'SHELF_MANAGER_2024');
define('SHELF_CACHE_TTL', 3600);

// ==================== INICIALIZAÇÃO ====================
register_activation_hook(__FILE__, 'shelf_manager_activate');
add_action('init', 'shelf_manager_init');
add_action('template_redirect', 'shelf_manager_handle_request');

function shelf_manager_activate()
{
    shelf_manager_init_db();
    shelf_manager_create_admin();
    flush_rewrite_rules();
}

function shelf_manager_init()
{
    add_rewrite_rule('^api/shelf/(.*)$', 'index.php?shelf_api=$matches[1]', 'top');
    add_filter('query_vars', function ($vars) {
        $vars[] = 'shelf_api';
        return $vars;
    });
}

// ==================== CORS ====================
function shelf_manager_cors_headers()
{
    $allowed_origins = ['http://localhost:5173', 'http://127.0.0.1:5173', "https://lebrecho.com.br", "http://lebrecho.com.br"];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// ==================== BANCO DE DADOS ====================
function shelf_manager_get_db()
{
    static $db = null;

    if ($db === null) {
        $db_dir = dirname(SHELF_DB_PATH);
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0755, true);
        }

        $db = new SQLite3(SHELF_DB_PATH);
        $db->exec("PRAGMA foreign_keys = ON");
        $db->exec("PRAGMA journal_mode = WAL");
    }

    return $db;
}

function shelf_manager_init_db()
{
    $db = shelf_manager_get_db();

    // Tabela users
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            isAdmin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Tabela shelves
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

    // Tabela shelf_items
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

    // Tabela item_history
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

    // Índices
    $db->exec("CREATE INDEX IF NOT EXISTS idx_shelf_items_product ON shelf_items(product_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_shelf_items_shelf ON shelf_items(shelf_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_item_history_product ON item_history(product_id)");
}

function shelf_manager_create_admin()
{
    $db = shelf_manager_get_db();

    $result = $db->query("SELECT id FROM users WHERE username = 'admin'");
    if (!$result->fetchArray()) {
        $hash = password_hash('senhadificil', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, isAdmin) VALUES ('admin', :hash, 1)");
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
}
function base64url_decode($data)
{
    $remainder = strlen($data) % 4;

    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}
// ==================== AUTENTICAÇÃO ====================
function shelf_manager_authenticate()
{
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($auth_header)) {
        return null;
    }

    $token = str_replace('Bearer ', '', $auth_header);

    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $header = $parts[0];
        $payload = json_decode(base64url_decode($parts[1]), true);
        $signature = $parts[2];

        $expected_signature = hash_hmac('sha256', "$parts[0].$parts[1]", SHELF_SECRET_KEY, true);
        $expected_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expected_signature));

        if (!hash_equals($expected_signature, $signature)) {
            return null;
        }

        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return [
            'id' => $payload['id'],
            'username' => $payload['sub'],
            'isAdmin' => $payload['isAdmin']
        ];
    } catch (Exception $e) {
        return null;
    }
}
function shelf_manager_create_token($username, $user_id, $is_admin)
{
    $payload = [
        'sub' => $username,
        'id' => $user_id,
        'isAdmin' => $is_admin,
        'exp' => time() + 3600
    ];

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload_encoded = json_encode($payload);

    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_encoded));

    $signature = hash_hmac('sha256', "$base64_header.$base64_payload", SHELF_SECRET_KEY, true);
    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return "$base64_header.$base64_payload.$base64_signature";
}

// ==================== FUNÇÕES AUXILIARES ====================
function shelf_manager_send_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function shelf_manager_get_input()
{
    $input = json_decode(file_get_contents('php://input'), true);
    return $input ?? [];
}

// ==================== INTEGRAÇÃO WORDPRESS ====================
function shelf_manager_fetch_product($product_id)
{
    $cache_key = "shelf_product_{$product_id}";
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get(
        SHELF_WP_API_URL . "/by-id/{$product_id}",
        [
            'headers' => ['X-API-Key' => SHELF_WP_API_KEY],
            'timeout' => 10,
            'sslverify' => false
        ]
    );

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $product = ($body['success'] ?? false) ? ($body['data'] ?? null) : null;

    if ($product) {
        set_transient($cache_key, $product, SHELF_CACHE_TTL);
    }

    return $product;
}

function shelf_manager_search_products($search, $search_type = 'name', $limit = 20)
{
    $endpoint = $search_type === 'sku'
        ? SHELF_WP_API_URL . "/sku-search"
        : SHELF_WP_API_URL . "/search";

    $params = $search_type === 'sku'
        ? ['sku' => $search]
        : ['q' => $search, 'limit' => $limit];

    $response = wp_remote_get(
        add_query_arg($params, $endpoint),
        [
            'headers' => ['X-API-Key' => SHELF_WP_API_KEY],
            'timeout' => 10,
            'sslverify' => false
        ]
    );

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return ($body['success'] ?? false) ? ($body['data'] ?? []) : [];
}

// ==================== HANDLER PRINCIPAL ====================
function shelf_manager_handle_request()
{
    $endpoint = get_query_var('shelf_api');

    if (empty($endpoint)) {
        return;
    }

    shelf_manager_cors_headers();

    $method = $_SERVER['REQUEST_METHOD'];
    $input = shelf_manager_get_input();
    $path_parts = explode('/', trim($endpoint, '/'));
    $user = shelf_manager_authenticate();

    // Rotas
    if ($path_parts[0] === 'auth') {
        shelf_manager_handle_auth($method, $input, $path_parts);
    } elseif ($path_parts[0] === 'shelves') {
        shelf_manager_handle_shelves($method, $input, $path_parts, $user);
    } elseif ($path_parts[0] === 'items') {
        shelf_manager_handle_items($method, $input, $path_parts, $user);
    } elseif ($path_parts[0] === 'products') {
        shelf_manager_handle_products($method, $input, $path_parts, $user);
    } elseif ($path_parts[0] === 'dashboard') {
        shelf_manager_handle_dashboard($method, $user);
    } elseif ($endpoint === 'health') {
        shelf_manager_handle_health();
    } else {
        shelf_manager_handle_root();
    }
}

// ==================== HANDLERS ====================
function shelf_manager_handle_auth($method, $input, $path_parts)
{
    if ($method === 'POST') {
        if (isset($path_parts[1]) && $path_parts[1] === 'login') {
            shelf_manager_handle_login($input);
        } elseif (isset($path_parts[1]) && $path_parts[1] === 'register') {
            shelf_manager_handle_register($input);
        }
    }
    shelf_manager_send_json(['error' => 'Rota não encontrada'], 404);
}

function shelf_manager_handle_login($input)
{
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        shelf_manager_send_json(['error' => 'Usuário e senha são obrigatórios'], 400);
    }

    $db = shelf_manager_get_db();
    $stmt = $db->prepare("SELECT id, username, password_hash, isAdmin FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        shelf_manager_send_json(['error' => 'Credenciais inválidas'], 401);
    }

    $token = shelf_manager_create_token($user['username'], $user['id'], (bool)$user['isAdmin']);
    shelf_manager_send_json(['access_token' => $token, 'token_type' => 'bearer']);
}

function shelf_manager_handle_register($input)
{
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $isAdmin = isset($input['isAdmin']) ? (int)$input['isAdmin'] : 0;

    if (empty($username) || empty($password)) {
        shelf_manager_send_json(['error' => 'Campos obrigatórios'], 400);
    }

    $db = shelf_manager_get_db();

    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($result->fetchArray()) {
        shelf_manager_send_json(['error' => 'Usuário já existe'], 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, isAdmin) VALUES (:username, :hash, :isAdmin)");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':isAdmin', $isAdmin, SQLITE3_INTEGER);
    $stmt->execute();

    $user_id = $db->lastInsertRowID();

    shelf_manager_send_json([
        'success' => true,
        'data' => ['id' => $user_id, 'username' => $username, 'isAdmin' => (bool)$isAdmin]
    ]);
}

function shelf_manager_handle_shelves($method, $input, $path_parts, $user)
{
    if (!$user) {
        shelf_manager_send_json(['error' => 'Não autorizado'], 401);
    }

    $db = shelf_manager_get_db();
    $shelf_id = $path_parts[1] ?? null;

    if ($method === 'GET') {
        if ($shelf_id) {
            // GET /shelves/{id}
            $stmt = $db->prepare("
                SELECT s.*, COUNT(si.id) as item_count, u.username as created_by_name
                FROM shelves s
                LEFT JOIN shelf_items si ON s.id = si.shelf_id
                LEFT JOIN users u ON s.created_by = u.id
                WHERE s.id = :id
                GROUP BY s.id
            ");
            $stmt->bindValue(':id', $shelf_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $shelf = $result->fetchArray(SQLITE3_ASSOC);

            if (!$shelf) {
                shelf_manager_send_json(['error' => 'Prateleira não encontrada'], 404);
            }
            shelf_manager_send_json($shelf);
        } else {
            // GET /shelves
            $result = $db->query("
                SELECT s.*, COUNT(si.id) as item_count, u.username as created_by_name
                FROM shelves s
                LEFT JOIN shelf_items si ON s.id = si.shelf_id
                LEFT JOIN users u ON s.created_by = u.id
                GROUP BY s.id
                ORDER BY s.created_at DESC
            ");
            $shelves = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $shelves[] = $row;
            }
            shelf_manager_send_json($shelves);
        }
    } elseif ($method === 'POST') {
        // POST /shelves
        $name = $input['name'] ?? '';
        $description = $input['description'] ?? '';

        if (empty($name)) {
            shelf_manager_send_json(['error' => 'Nome obrigatório'], 400);
        }

        $stmt = $db->prepare("SELECT id FROM shelves WHERE name = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result->fetchArray()) {
            shelf_manager_send_json(['error' => 'Prateleira com este nome já existe'], 400);
        }

        $stmt = $db->prepare("INSERT INTO shelves (name, description, created_by) VALUES (:name, :description, :created_by)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':created_by', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();

        $new_id = $db->lastInsertRowID();
        $shelf = $db->querySingle("SELECT *, 0 as item_count FROM shelves WHERE id = $new_id", true);
        shelf_manager_send_json($shelf);
    } elseif ($method === 'PUT' && $shelf_id) {
        // PUT /shelves/{id}
        $stmt = $db->prepare("SELECT created_by FROM shelves WHERE id = :id");
        $stmt->bindValue(':id', $shelf_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $shelf = $result->fetchArray(SQLITE3_ASSOC);

        if (!$shelf) {
            shelf_manager_send_json(['error' => 'Prateleira não encontrada'], 404);
        }

        if ($shelf['created_by'] != $user['id'] && !$user['isAdmin']) {
            shelf_manager_send_json(['error' => 'Sem permissão'], 403);
        }

        $updates = [];
        if (isset($input['name'])) {
            $updates[] = "name = '" . SQLite3::escapeString($input['name']) . "'";
        }
        if (isset($input['description'])) {
            $updates[] = "description = '" . SQLite3::escapeString($input['description']) . "'";
        }

        if (!empty($updates)) {
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            $db->exec("UPDATE shelves SET " . implode(', ', $updates) . " WHERE id = $shelf_id");
        }

        $updated = $db->querySingle("SELECT * FROM shelves WHERE id = $shelf_id", true);
        shelf_manager_send_json($updated);
    } elseif ($method === 'DELETE' && $shelf_id) {
        // DELETE /shelves/{id}
        if (!$user['isAdmin']) {
            shelf_manager_send_json(['error' => 'Apenas administradores podem excluir'], 403);
        }

        $db->exec("DELETE FROM shelves WHERE id = $shelf_id");
        shelf_manager_send_json(['success' => true]);
    }
}

function shelf_manager_handle_items($method, $input, $path_parts, $user)
{
    if (!$user) {
        shelf_manager_send_json(['error' => 'Não autorizado'], 401);
    }

    $db = shelf_manager_get_db();

    // GET /shelves/{shelf_id}/items
    if ($method === 'GET' && isset($path_parts[1]) && $path_parts[1] === 'shelves' && isset($path_parts[2]) && $path_parts[3] === 'items') {
        $shelf_id = (int)$path_parts[2];

        $stmt = $db->prepare("SELECT * FROM shelf_items WHERE shelf_id = :shelf_id ORDER BY added_at DESC");
        $stmt->bindValue(':shelf_id', $shelf_id, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $items = [];
        $product_ids = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
            $product_ids[] = $row['product_id'];
        }

        if (!empty($product_ids)) {
            $product_ids = array_unique($product_ids);
            foreach ($product_ids as $pid) {
                $product_data = shelf_manager_fetch_product($pid);
                if ($product_data) {
                    foreach ($items as &$item) {
                        if ($item['product_id'] == $pid) {
                            $item['product_data'] = $product_data;
                        }
                    }
                }
            }
        }

        shelf_manager_send_json($items);
    }
    // POST /shelves/{shelf_id}/items
    elseif ($method === 'POST' && isset($path_parts[1]) && $path_parts[1] === 'shelves' && isset($path_parts[2]) && $path_parts[3] === 'items') {
        $shelf_id = (int)$path_parts[2];
        $product_id = $input['product_id'] ?? 0;
        $quantity = $input['quantity'] ?? 1;

        // Verifica prateleira
        $stmt = $db->prepare("SELECT id FROM shelves WHERE id = :id");
        $stmt->bindValue(':id', $shelf_id, SQLITE3_INTEGER);
        if (!$stmt->execute()->fetchArray()) {
            shelf_manager_send_json(['error' => 'Prateleira não encontrada'], 404);
        }

        // Verifica se produto já está em alguma prateleira
        $stmt = $db->prepare("SELECT id FROM shelf_items WHERE product_id = :pid");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        if ($stmt->execute()->fetchArray()) {
            shelf_manager_send_json(['error' => 'Produto já está em uma prateleira'], 409);
        }

        // Valida produto no WordPress
        $product = shelf_manager_fetch_product($product_id);
        if (!$product) {
            shelf_manager_send_json(['error' => 'Produto não encontrado no WordPress'], 404);
        }

        // Insere item
        $stmt = $db->prepare("INSERT INTO shelf_items (shelf_id, product_id, quantity, added_by) VALUES (:shelf_id, :product_id, :quantity, :added_by)");
        $stmt->bindValue(':shelf_id', $shelf_id, SQLITE3_INTEGER);
        $stmt->bindValue(':product_id', $product_id, SQLITE3_INTEGER);
        $stmt->bindValue(':quantity', $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(':added_by', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();

        $item_id = $db->lastInsertRowID();

        // Histórico
        $stmt = $db->prepare("INSERT INTO item_history (product_id, shelf_id, entrada) VALUES (:pid, :sid, CURRENT_TIMESTAMP)");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $stmt->bindValue(':sid', $shelf_id, SQLITE3_INTEGER);
        $stmt->execute();

        $item = $db->querySingle("SELECT * FROM shelf_items WHERE id = $item_id", true);
        $item['product_data'] = $product;
        shelf_manager_send_json($item);
    }
    // DELETE /shelves/{shelf_id}/items/{product_id}
    elseif ($method === 'DELETE' && isset($path_parts[1]) && $path_parts[1] === 'shelves' && isset($path_parts[2]) && $path_parts[3] === 'items' && isset($path_parts[4])) {
        $shelf_id = (int)$path_parts[2];
        $product_id = (int)$path_parts[4];

        $stmt = $db->prepare("DELETE FROM shelf_items WHERE shelf_id = :sid AND product_id = :pid");
        $stmt->bindValue(':sid', $shelf_id, SQLITE3_INTEGER);
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $stmt->execute();

        if ($db->changes() === 0) {
            shelf_manager_send_json(['error' => 'Item não encontrado'], 404);
        }

        $stmt = $db->prepare("UPDATE item_history SET saida = CURRENT_TIMESTAMP WHERE product_id = :pid AND shelf_id = :sid AND saida IS NULL");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $stmt->bindValue(':sid', $shelf_id, SQLITE3_INTEGER);
        $stmt->execute();

        shelf_manager_send_json(['success' => true]);
    }
    // POST /items/move
    elseif ($method === 'POST' && isset($path_parts[1]) && $path_parts[1] === 'move') {
        $product_id = $input['product_id'] ?? 0;
        $to_shelf_id = $input['to_shelf_id'] ?? 0;

        // Obtém localização atual
        $stmt = $db->prepare("SELECT * FROM shelf_items WHERE product_id = :pid");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $current = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$current) {
            shelf_manager_send_json(['error' => 'Produto não está em nenhuma prateleira'], 404);
        }

        $from_shelf_id = $current['shelf_id'];

        // Verifica prateleira destino
        $stmt = $db->prepare("SELECT id FROM shelves WHERE id = :id");
        $stmt->bindValue(':id', $to_shelf_id, SQLITE3_INTEGER);
        if (!$stmt->execute()->fetchArray()) {
            shelf_manager_send_json(['error' => 'Prateleira destino não encontrada'], 404);
        }

        // Move item
        $stmt = $db->prepare("UPDATE shelf_items SET shelf_id = :to_sid, updated_at = CURRENT_TIMESTAMP WHERE product_id = :pid");
        $stmt->bindValue(':to_sid', $to_shelf_id, SQLITE3_INTEGER);
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Fecha histórico anterior
        $stmt = $db->prepare("UPDATE item_history SET saida = CURRENT_TIMESTAMP WHERE product_id = :pid AND shelf_id = :from_sid AND saida IS NULL");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $stmt->bindValue(':from_sid', $from_shelf_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Novo histórico
        $stmt = $db->prepare("INSERT INTO item_history (product_id, shelf_id, entrada) VALUES (:pid, :to_sid, CURRENT_TIMESTAMP)");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $stmt->bindValue(':to_sid', $to_shelf_id, SQLITE3_INTEGER);
        $stmt->execute();

        shelf_manager_send_json(['success' => true]);
    }
    // GET /items/{product_id}/history
    elseif ($method === 'GET' && isset($path_parts[1]) && isset($path_parts[2]) && $path_parts[2] === 'history') {
        $product_id = (int)$path_parts[1];

        $stmt = $db->prepare("
            SELECT ih.*, s.name as shelf_name
            FROM item_history ih
            JOIN shelves s ON ih.shelf_id = s.id
            WHERE ih.product_id = :pid
            ORDER BY ih.entrada DESC
        ");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $history = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $history[] = $row;
        }
        shelf_manager_send_json($history);
    }
}

function shelf_manager_handle_products($method, $input, $path_parts, $user)
{
    if (!$user) {
        shelf_manager_send_json(['error' => 'Não autorizado'], 401);
    }

    $db = shelf_manager_get_db();

    // GET /products/search
    if ($method === 'GET' && isset($path_parts[1]) && $path_parts[1] === 'search') {
        $q = $_GET['q'] ?? '';
        $type = $_GET['type'] ?? 'name';
        $limit = (int)($_GET['limit'] ?? 20);

        if (strlen($q) < 2) {
            shelf_manager_send_json(['success' => true, 'data' => []]);
        }

        $products = shelf_manager_search_products($q, $type, $limit);

        foreach ($products as &$product) {
            $stmt = $db->prepare("
                SELECT si.shelf_id, s.name as shelf_name
                FROM shelf_items si
                JOIN shelves s ON si.shelf_id = s.id
                WHERE si.product_id = :pid
            ");
            $stmt->bindValue(':pid', $product['id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $shelf = $result->fetchArray(SQLITE3_ASSOC);

            $product['in_shelf'] = $shelf['shelf_id'] ?? null;
            $product['shelf_name'] = $shelf['shelf_name'] ?? null;
        }

        shelf_manager_send_json(['success' => true, 'data' => $products, 'count' => count($products)]);
    }
    // GET /products/check/{id}
    elseif ($method === 'GET' && isset($path_parts[1]) && $path_parts[1] === 'check' && isset($path_parts[2])) {
        $product_id = (int)$path_parts[2];

        $stmt = $db->prepare("
            SELECT si.shelf_id, s.name as shelf_name
            FROM shelf_items si
            JOIN shelves s ON si.shelf_id = s.id
            WHERE si.product_id = :pid
        ");
        $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $shelf = $result->fetchArray(SQLITE3_ASSOC);

        $product_data = shelf_manager_fetch_product($product_id);

        shelf_manager_send_json([
            'success' => true,
            'product_id' => $product_id,
            'in_shelf' => $shelf['shelf_id'] ?? null,
            'shelf_name' => $shelf['shelf_name'] ?? null,
            'product_exists_in_wp' => $product_data !== null,
            'product_data' => $product_data
        ]);
    }
    // POST /products/batch-status
    elseif ($method === 'POST' && isset($path_parts[1]) && $path_parts[1] === 'batch-status') {
        $product_ids = $input;

        if (!is_array($product_ids) || empty($product_ids)) {
            shelf_manager_send_json([]);
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $db->prepare("
            SELECT product_id, shelf_id, s.name as shelf_name
            FROM shelf_items si
            JOIN shelves s ON si.shelf_id = s.id
            WHERE product_id IN ($placeholders)
        ");

        foreach ($product_ids as $i => $pid) {
            $stmt->bindValue($i + 1, $pid, SQLITE3_INTEGER);
        }

        $result = $stmt->execute();
        $response = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $response[$row['product_id']] = [
                'shelf_id' => $row['shelf_id'],
                'shelf_name' => $row['shelf_name']
            ];
        }
        shelf_manager_send_json($response);
    }
}

function shelf_manager_handle_dashboard($method, $user)
{
    if (!$user) {
        shelf_manager_send_json(['error' => 'Não autorizado'], 401);
    }

    $db = shelf_manager_get_db();

    $total_shelves = $db->querySingle("SELECT COUNT(*) FROM shelves");
    $total_items = $db->querySingle("SELECT COUNT(*) FROM shelf_items");
    $used_shelves = $db->querySingle("SELECT COUNT(DISTINCT shelf_id) FROM shelf_items");
    $empty_shelves = $total_shelves - $used_shelves;

    shelf_manager_send_json([
        'success' => true,
        'stats' => [
            'total_shelves' => $total_shelves,
            'total_items' => $total_items,
            'used_shelves' => $used_shelves,
            'empty_shelves' => $empty_shelves,
            'avg_items_per_shelf' => $used_shelves > 0 ? round($total_items / $used_shelves, 1) : 0
        ]
    ]);
}

function shelf_manager_handle_health()
{
    try {
        $db = shelf_manager_get_db();
        $db->querySingle("SELECT 1");
        shelf_manager_send_json([
            'status' => 'healthy',
            'database' => 'connected',
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        shelf_manager_send_json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ], 500);
    }
}

function shelf_manager_handle_root()
{
    shelf_manager_send_json([
        'service' => 'Shelf Manager API',
        'version' => '1.0.0',
        'status' => 'online',
        'endpoints' => [
            'auth' => ['POST /auth/login', 'POST /auth/register'],
            'shelves' => ['GET /shelves', 'POST /shelves', 'PUT /shelves/{id}', 'DELETE /shelves/{id}'],
            'items' => ['GET /shelves/{id}/items', 'POST /shelves/{id}/items', 'DELETE /shelves/{id}/items/{pid}', 'POST /items/move'],
            'products' => ['GET /products/search', 'GET /products/check/{id}', 'POST /products/batch-status'],
            'dashboard' => ['GET /dashboard/stats'],
            'history' => ['GET /items/{product_id}/history'],
        ]
    ]);
}
