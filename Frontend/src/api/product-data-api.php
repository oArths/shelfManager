<?php
// ==================== CONFIGURAÇÃO CORS ====================
$allowed_origins = ['http://localhost:5173', 'http://127.0.0.1:5173', 'https://seudominio.com'];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// ================================================================================

/**
 * Plugin Name: Product Data API for Shelf Manager
 * Description: API para fornecer dados de produtos do WooCommerce
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==================== FUNÇÕES DE CONSULTA ====================
// Definidas antes do registro das rotas para garantir disponibilidade

function search_products($request) { /* ... */ } // mantém o mesmo conteúdo

function search_products_by_sku($request) {
    global $wpdb;
    $sku = sanitize_text_field($request->get_param('sku'));
    if (empty($sku) || strlen($sku) < 2) {
        return new WP_Error('invalid_sku', 'SKU inválido', ['status' => 400]);
    }
    $limit = min(50, (int) ($request->get_param('limit') ?: 20));
    $sku_like = '%' . $wpdb->esc_like($sku) . '%';
    $query = $wpdb->prepare("
        SELECT 
            p.ID as id,
            p.post_title as name,
            m.sku,
            m.min_price as price,
            m.stock_quantity as stock,
            m.stock_status,
            p.guid as product_url,
            (SELECT guid FROM {$wpdb->posts} WHERE post_parent = p.ID AND post_type = 'attachment' AND post_mime_type LIKE 'image/%%' LIMIT 1) as main_image
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
        WHERE p.post_type = 'product' AND p.post_status = 'publish' AND m.sku LIKE %s
        ORDER BY m.sku ASC
        LIMIT %d
    ", $sku_like, $limit);
    $products = $wpdb->get_results($query);
    return [
        'success' => true,
        'sku' => $sku,
        'count' => count($products),
        'data' => $products
    ];
}

function get_products_by_ids($request) { /* ... */ }
function get_product_by_id($request) { /* ... */ }
function list_products($request) { /* ... */ }
function get_products_count($request) { /* ... */ }
function get_product_images($request) { /* ... */ }

// ==================== REGISTRO DAS ROTAS ====================
add_action('rest_api_init', function() {
    // Verifica se a função existe (debug)
    if (!function_exists('search_products_by_sku')) {
        error_log('ERRO: search_products_by_sku não está definida!');
    }

    register_rest_route('shelf-products/v1', '/search', [
        'methods' => 'GET',
        'callback' => 'search_products',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('shelf-products/v1', '/sku-search', [
        'methods' => 'GET',
        'callback' => 'search_products_by_sku',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('shelf-products/v1', '/by-id/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_product_by_id',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('shelf-products/v1', '/list', [
        'methods' => 'GET',
        'callback' => 'list_products',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('shelf-products/v1', '/count', [
        'methods' => 'GET',
        'callback' => 'get_products_count',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('shelf-products/v1', '/by-ids', [
        'methods' => 'POST',
        'callback' => 'get_products_by_ids',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('shelf-products/v1', '/images/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_product_images',
        'permission_callback' => '__return_true'
    ]);
});