<?php
/**
 * Plugin Name: PrestaShop Explorer
 * Description: Ferramenta para explorar schema do banco PrestaShop
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==================== CONFIGURAÇÃO ====================
define('PS_DB_NAME', 'u566423239_u2ZB7');
define('PS_DB_USER', 'u566423239_p7MP2');
define('PS_DB_PASSWORD', 's42VCpT5IC');
define('PS_DB_HOST', '127.0.0.1');

// ==================== ENDPOINTS DE DIAGNÓSTICO ====================
add_action('rest_api_init', 'register_prestashop_explorer_endpoints');

function register_prestashop_explorer_endpoints() {
    
    // 🔍 1. TESTE DE CONEXÃO
    register_rest_route('prestashop-explorer/v1', '/test-connection', [
        'methods' => 'GET',
        'callback' => 'test_prestashop_connection',
        'permission_callback' => '__return_true'
    ]);
    
    // 📊 2. LISTAR TODAS AS TABELAS
    register_rest_route('prestashop-explorer/v1', '/tables', [
        'methods' => 'GET',
        'callback' => 'list_all_tables',
        'permission_callback' => '__return_true'
    ]);
    
    // 🔎 3. DESCOBRIR PREFIXO DAS TABELAS
    register_rest_route('prestashop-explorer/v1', '/discover-prefix', [
        'methods' => 'GET',
        'callback' => 'discover_table_prefix',
        'permission_callback' => '__return_true'
    ]);
    
    // 📋 4. VER ESTRUTURA DE UMA TABELA
    register_rest_route('prestashop-explorer/v1', '/table/(?P<table_name>[a-zA-Z0-9_]+)', [
        'methods' => 'GET',
        'callback' => 'get_table_structure',
        'permission_callback' => '__return_true'
    ]);
    
    // 🎯 5. BUSCAR TABELAS DE PRODUTOS
    register_rest_route('prestashop-explorer/v1', '/find-product-tables', [
        'methods' => 'GET',
        'callback' => 'find_product_tables',
        'permission_callback' => '__return_true'
    ]);
    
    // 🔬 6. VER AMOSTRA DE DADOS
    register_rest_route('prestashop-explorer/v1', '/table/(?P<table_name>[a-zA-Z0-9_]+)/sample', [
        'methods' => 'GET',
        'callback' => 'get_table_sample',
        'permission_callback' => '__return_true'
    ]);
    
    // 📈 7. ESTATÍSTICAS DO BANCO
    register_rest_route('prestashop-explorer/v1', '/database-stats', [
        'methods' => 'GET',
        'callback' => 'get_database_stats',
        'permission_callback' => '__return_true'
    ]);
}

// ==================== FUNÇÕES DE CONEXÃO ====================

function connect_to_prestashop_db() {
    static $connection = null;
    
    if ($connection === null) {
        $connection = new mysqli(
            PS_DB_HOST,
            PS_DB_USER,
            PS_DB_PASSWORD,
            PS_DB_NAME
        );
        
        if ($connection->connect_error) {
            return [
                'success' => false,
                'error' => $connection->connect_error,
                'errno' => $connection->connect_errno
            ];
        }
        
        $connection->set_charset('utf8mb4');
    }
    
    return $connection;
}

// ==================== ENDPOINT HANDLERS ====================

function test_prestashop_connection($request) {
    $connection = connect_to_prestashop_db();
    
    if (is_array($connection) && isset($connection['error'])) {
        return [
            'success' => false,
            'message' => 'Falha na conexão',
            'error' => $connection['error'],
            'credentials_used' => [
                'host' => PS_DB_HOST,
                'user' => PS_DB_USER,
                'database' => PS_DB_NAME,
                'password_length' => strlen(PS_DB_PASSWORD)
            ]
        ];
    }
    
    // Testar consulta simples
    $result = $connection->query("SELECT 1 as test, VERSION() as version");
    
    if ($result) {
        $row = $result->fetch_assoc();
        return [
            'success' => true,
            'message' => 'Conexão estabelecida com sucesso!',
            'database' => [
                'name' => PS_DB_NAME,
                'host' => PS_DB_HOST,
                'mysql_version' => $row['version'],
                'connection_test' => $row['test']
            ]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Conexão OK mas query falhou',
            'error' => $connection->error
        ];
    }
}

function list_all_tables($request) {
    $connection = connect_to_prestashop_db();
    if (is_array($connection) && isset($connection['error'])) {
        return new WP_Error('db_error', $connection['error'], ['status' => 500]);
    }
    
    $result = $connection->query("SHOW TABLES");
    $tables = [];
    
    while ($row = $result->fetch_array()) {
        $table_name = $row[0];
        $tables[] = $table_name;
    }
    
    // Agrupar por prefixo
    $grouped_tables = [];
    foreach ($tables as $table) {
        // Tentar detectar prefixo (parte antes do primeiro underscore)
        $parts = explode('_', $table, 2);
        $prefix = count($parts) > 1 ? $parts[0] . '_' : 'no_prefix_';
        
        if (!isset($grouped_tables[$prefix])) {
            $grouped_tables[$prefix] = [];
        }
        $grouped_tables[$prefix][] = $table;
    }
    
    return [
        'success' => true,
        'count' => count($tables),
        'tables' => $tables,
        'grouped_by_prefix' => $grouped_tables,
        'most_common_prefix' => array_keys($grouped_tables)[0] ?? null
    ];
}

function discover_table_prefix($request) {
    $connection = connect_to_prestashop_db();
    if (is_array($connection) && isset($connection['error'])) {
        return new WP_Error('db_error', $connection['error'], ['status' => 500]);
    }
    
    $result = $connection->query("SHOW TABLES");
    $tables = [];
    
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    // Analisar prefixos comuns do PrestaShop
    $prestashop_core_tables = [
        'product', 'category', 'customer', 'order', 'cart',
        'address', 'manufacturer', 'supplier', 'attribute',
        'feature', 'stock', 'image', 'specific_price'
    ];
    
    $prefix_candidates = [];
    
    foreach ($tables as $table) {
        foreach ($prestashop_core_tables as $core_table) {
            if (strpos($table, $core_table) !== false) {
                // Extrair prefixo (tudo antes do nome da tabela)
                $prefix = str_replace($core_table, '', $table);
                if (strpos($prefix, '_') !== false) {
                    $prefix_candidates[] = $prefix;
                }
            }
        }
    }
    
    // Contar frequência dos prefixos
    $prefix_counts = array_count_values($prefix_candidates);
    arsort($prefix_counts);
    
    // Verificar as tabelas mais prováveis
    $likely_tables = [];
    $most_likely_prefix = key($prefix_counts) ?: '';
    
    if ($most_likely_prefix) {
        foreach ($prestashop_core_tables as $core_table) {
            $full_table_name = $most_likely_prefix . $core_table;
            if (in_array($full_table_name, $tables)) {
                $likely_tables[$core_table] = $full_table_name;
            }
        }
    }
    
    return [
        'success' => true,
        'all_tables_count' => count($tables),
        'prefix_analysis' => [
            'candidates' => $prefix_counts,
            'most_likely_prefix' => $most_likely_prefix ?: 'Não identificado',
            'confidence' => $most_likely_prefix ? 'ALTA' : 'BAIXA'
        ],
        'likely_prestashop_tables' => $likely_tables,
        'recommendation' => $most_likely_prefix ? 
            "Use o prefixo: '{$most_likely_prefix}'" : 
            "Verifique manualmente as tabelas"
    ];
}

function get_table_structure($request) {
    $connection = connect_to_prestashop_db();
    if (is_array($connection) && isset($connection['error'])) {
        return new WP_Error('db_error', $connection['error'], ['status' => 500]);
    }
    
    $table_name = $connection->real_escape_string($request['table_name']);
    
    // Verificar se tabela existe
    $check = $connection->query("SHOW TABLES LIKE '$table_name'");
    if ($check->num_rows === 0) {
        return new WP_Error('table_not_found', "Tabela '$table_name' não existe", ['status' => 404]);
    }
    
    // Obter estrutura
    $result = $connection->query("DESCRIBE `$table_name`");
    $columns = [];
    
    while ($row = $result->fetch_assoc()) {
        $columns[] = [
            'field' => $row['Field'],
            'type' => $row['Type'],
            'null' => $row['Null'],
            'key' => $row['Key'],
            'default' => $row['Default'],
            'extra' => $row['Extra']
        ];
    }
    
    // Obter número de linhas
    $count_result = $connection->query("SELECT COUNT(*) as total FROM `$table_name`");
    $row_count = $count_result->fetch_assoc()['total'];
    
    // Obter chaves estrangeiras (se houver)
    $fk_result = $connection->query("
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = '$table_name' 
        AND TABLE_SCHEMA = '" . PS_DB_NAME . "'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreign_keys = [];
    while ($row = $fk_result->fetch_assoc()) {
        $foreign_keys[] = $row;
    }
    
    return [
        'success' => true,
        'table' => $table_name,
        'row_count' => (int)$row_count,
        'columns' => $columns,
        'column_count' => count($columns),
        'foreign_keys' => $foreign_keys,
        'primary_keys' => array_filter($columns, function($col) {
            return $col['key'] === 'PRI';
        })
    ];
}

function find_product_tables($request) {
    $connection = connect_to_prestashop_db();
    if (is_array($connection) && isset($connection['error'])) {
        return new WP_Error('db_error', $connection['error'], ['status' => 500]);
    }
    
    $result = $connection->query("SHOW TABLES");
    $all_tables = [];
    
    while ($row = $result->fetch_array()) {
        $all_tables[] = $row[0];
    }
    
    // Padrões de nomes de tabelas de produtos
    $product_patterns = ['product', 'produto', 'item', 'sku', 'inventory', 'stock'];
    $category_patterns = ['category', 'categoria', 'cat'];
    
    $found_tables = [
        'products' => [],
        'categories' => [],
        'inventory' => [],
        'prices' => [],
        'images' => []
    ];
    
    foreach ($all_tables as $table) {
        $table_lower = strtolower($table);
        
        foreach ($product_patterns as $pattern) {
            if (strpos($table_lower, $pattern) !== false) {
                if (strpos($table_lower, 'category') === false && 
                    strpos($table_lower, 'cat_') === false) {
                    $found_tables['products'][] = $table;
                }
                break;
            }
        }
        
        foreach ($category_patterns as $pattern) {
            if (strpos($table_lower, $pattern) !== false) {
                $found_tables['categories'][] = $table;
                break;
            }
        }
        
        if (strpos($table_lower, 'stock') !== false || 
            strpos($table_lower, 'inventory') !== false) {
            $found_tables['inventory'][] = $table;
        }
        
        if (strpos($table_lower, 'price') !== false) {
            $found_tables['prices'][] = $table;
        }
        
        if (strpos($table_lower, 'image') !== false || 
            strpos($table_lower, 'img') !== false) {
            $found_tables['images'][] = $table;
        }
    }
    
    // Remover duplicados
    foreach ($found_tables as &$tables) {
        $tables = array_unique($tables);
    }
    
    return [
        'success' => true,
        'table_categories' => $found_tables,
        'recommendations' => [
            'main_product_table' => $found_tables['products'][0] ?? null,
            'main_category_table' => $found_tables['categories'][0] ?? null
        ]
    ];
}

function get_table_sample($request) {
    $connection = connect_to_prestashop_db();
    if (is_array($connection) && isset($connection['error'])) {
        return new WP_Error('db_error', $connection['error'], ['status' => 500]);
    }
    
    $table_name = $connection->real_escape_string($request['table_name']);
    $limit = min(10, (int) ($request->get_param('limit') ?: 5));
    
    // Verificar se tabela existe
    $check = $connection->query("SHOW TABLES LIKE '$table_name'");
    if ($check->num_rows === 0) {
        return new WP_Error('table_not_found', "Tabela '$table_name' não existe", ['status' => 404]);
    }
    
    // Obter amostra de dados
    $result = $connection->query("SELECT * FROM `$table_name` LIMIT $limit");
    
    $sample = [];
    while ($row = $result->fetch_assoc()) {
        // Limitar o tamanho dos valores para não sobrecarregar a resposta
        $trimmed_row = [];
        foreach ($row as $key => $value) {
            if (is_string($value) && strlen($value) > 100) {
                $trimmed_row[$key] = substr($value, 0, 100) . '...';
            } else {
                $trimmed_row[$key] = $value;
            }
        }
        $sample[] = $trimmed_row;
    }
    
    // Obter informações sobre a tabela
    $desc_result = $connection->query("DESCRIBE `$table_name`");
    $column_names = [];
    while ($row = $desc_result->fetch_assoc()) {
        $column_names[] = $row['Field'];
    }
    
    return [
        'success' => true,
        'table' => $table_name,
        'sample_size' => count($sample),
        'columns' => $column_names,
        'sample_data' => $sample,
        'note' => 'Valores longos foram truncados para visualização'
    ];
}

function get_database_stats($request) {
    $connection = connect_to_prestashop_db();
    if (is_array($connection) && isset($connection['error'])) {
        return new WP_Error('db_error', $connection['error'], ['status' => 500]);
    }
    
    // Tamanho do banco
    $size_result = $connection->query("
        SELECT 
            table_schema as database_name,
            SUM(data_length + index_length) / 1024 / 1024 as size_mb,
            COUNT(*) as table_count
        FROM information_schema.tables 
        WHERE table_schema = '" . PS_DB_NAME . "'
    ");
    
    $size_info = $size_result->fetch_assoc();
    
    // Tabelas maiores
    $big_tables_result = $connection->query("
        SELECT 
            table_name,
            ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb,
            table_rows
        FROM information_schema.tables 
        WHERE table_schema = '" . PS_DB_NAME . "'
        ORDER BY (data_length + index_length) DESC
        LIMIT 10
    ");
    
    $big_tables = [];
    while ($row = $big_tables_result->fetch_assoc()) {
        $big_tables[] = $row;
    }
    
    return [
        'success' => true,
        'database' => PS_DB_NAME,
        'overview' => [
            'total_size_mb' => round($size_info['size_mb'], 2),
            'table_count' => $size_info['table_count'],
            'connection_status' => 'OK'
        ],
        'largest_tables' => $big_tables,
        'timestamp' => current_time('mysql')
    ];
}

// ==================== ATIVAÇÃO ====================

register_activation_hook(__FILE__, function() {
    // Cria tabela de logs se quiser
    global $wpdb;
    $table_name = $wpdb->prefix . 'prestashop_explorer_logs';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(50),
        table_name VARCHAR(100),
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        details TEXT
    )";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});