<?php

/**
 * Plugin Name: Product Data API for Shelf Manager
 * Description: API para fornecer dados de produtos do WooCommerce
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==================== ENDPOINTS DE PRODUTOS ====================
add_action('rest_api_init', 'register_product_data_endpoints');

function register_product_data_endpoints()
{

    // 🔍 1. BUSCAR PRODUTOS (nome, SKU ou descrição)
    register_rest_route('shelf-products/v1', '/search', [
        'methods' => 'GET',
        'callback' => 'search_products',
        'permission_callback' => '__return_true'
    ]);

    // 🏷️ 2. BUSCAR PRODUTO POR SKU
    register_rest_route('shelf-products/v1', '/sku-search', [
        'methods' => 'GET',
        'callback' => 'get_product_by_sku',
        'permission_callback' => '__return_true'
    ]);

    // 🔢 3. BUSCAR PRODUTO POR ID
    register_rest_route('shelf-products/v1', '/by-id/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_product_by_id',
        'permission_callback' => '__return_true'
    ]);

    // 📊 4. LISTAR PRODUTOS COM PAGINAÇÃO
    register_rest_route('shelf-products/v1', '/list', [
        'methods' => 'GET',
        'callback' => 'list_products',
        'permission_callback' => '__return_true'
    ]);

    // 📈 5. CONTAGEM DE PRODUTOS
    register_rest_route('shelf-products/v1', '/count', [
        'methods' => 'GET',
        'callback' => 'get_products_count',
        'permission_callback' => '__return_true'
    ]);
    // 🔢 6. BUSCAR MÚLTIPLOS PRODUTOS POR IDS
    register_rest_route('shelf-products/v1', '/by-ids', [
        'methods' => 'POST',
        'callback' => 'get_products_by_ids',
        'permission_callback' => '__return_true'
    ]);

    // 🖼️ 7. IMAGENS DO PRODUTO
    register_rest_route('shelf-products/v1', '/images/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_product_images',
        'permission_callback' => '__return_true'
    ]);
}

// // ==================== FUNÇÃO DE AUTENTICAÇÃO ====================
// function check_product_api_auth($request)
// {
//     // Método 1: API Key no header
//     $api_key = $request->get_header('X-API-Key');
//     $valid_key = 'SHELF_MANAGER_2024';


//     if ($api_key === $valid_key) {
//         return true;
//     }

//     // Método 2: Se estiver logado no WordPress
//     if (is_user_logged_in() && current_user_can('read')) {
//         return true;
//     }

//     return new WP_Error(
//         'rest_forbidden',
//         'Acesso não autorizado',
//         ['status' => 401]
//     );
// }

// ==================== FUNÇÕES DE CONSULTA ====================

function search_products($request)
{
    global $wpdb;

    $search = sanitize_text_field($request->get_param('q') ?: '');
    $limit = min(50, (int) ($request->get_param('limit') ?: 20));
    $in_stock = $request->get_param('in_stock');
    $category = $request->get_param('category');

    if (strlen($search) < 2 && empty($category)) {
        return new WP_Error(
            'invalid_search',
            'Digite pelo menos 2 caracteres para busca',
            ['status' => 400]
        );
    }

    $where_conditions = ["p.post_type = 'product'", "p.post_status = 'publish'"];
    $params = [];

    if (!empty($search)) {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_conditions[] = "(p.post_title LIKE %s OR m.sku LIKE %s OR p.post_content LIKE %s)";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }

    if ($in_stock === 'true') {
        $where_conditions[] = "m.stock_status = 'instock'";
    } elseif ($in_stock === 'false') {
        $where_conditions[] = "m.stock_status = 'outofstock'";
    }

    if (!empty($category)) {
        $where_conditions[] = "t.slug = %s";
        $params[] = $category;

        $query = "
            SELECT 
                p.ID as id,
                p.post_title as name,
                p.post_content as description,
                p.post_excerpt as short_description,
                m.sku,
                m.min_price as price,
                m.max_price,
                m.stock_quantity as stock,
                m.stock_status,
                m.average_rating,
                m.rating_count,
                m.total_sales,
                p.guid as product_url,
                p.post_date as created_at,
                p.post_modified as updated_at,
                (SELECT guid 
                 FROM {$wpdb->posts} 
                 WHERE post_parent = p.ID 
                   AND post_type = 'attachment' 
                   AND post_mime_type LIKE 'image/%%'
                 LIMIT 1) as main_image
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY p.post_title
            LIMIT %d
        ";

        $params[] = $limit;
    } else {
        $query = "
            SELECT 
                p.ID as id,
                p.post_title as name,
                p.post_content as description,
                p.post_excerpt as short_description,
                m.sku,
                m.min_price as price,
                m.max_price,
                m.stock_quantity as stock,
                m.stock_status,
                m.average_rating,
                m.rating_count,
                m.total_sales,
                p.guid as product_url,
                p.post_date as created_at,
                p.post_modified as updated_at,
                (SELECT guid 
                 FROM {$wpdb->posts} 
                 WHERE post_parent = p.ID 
                   AND post_type = 'attachment' 
                   AND post_mime_type LIKE 'image/%%'
                 LIMIT 1) as main_image
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY 
                CASE WHEN m.sku LIKE %s THEN 1
                     WHEN p.post_title LIKE %s THEN 2
                     ELSE 3
                END,
                p.post_title
            LIMIT %d
        ";

        if (!empty($search)) {
            $params = array_merge($params, [$search_term, $search_term, $limit]);
        } else {
            $params[] = $limit;
        }
    }

    $products = $wpdb->get_results(
        $wpdb->prepare($query, $params),
    );

    return [
        'success' => true,
        'search' => $search,
        'count' => count($products),
        'data' => $products
    ];
}

function search_products_by_sku($request)
{
    global $wpdb;

    // Termo enviado via query param
    $term = sanitize_text_field($request->get_param('term'));

    if (empty($term) || strlen($term) < 2) {
        return new WP_Error(
            'invalid_term',
            'Informe pelo menos 2 caracteres para buscar SKU',
            ['status' => 400]
        );
    }

    // Limite opcional
    $limit = min(50, (int) ($request->get_param('limit') ?: 20));

    // Busca parcial com LIKE
    $sku_like = '%' . $wpdb->esc_like($term) . '%';

    $query = "
        SELECT 
            p.ID as id,
            p.post_title as name,
            m.sku,
            m.min_price as price,
            m.stock_quantity as stock,
            m.stock_status,
            p.guid as product_url
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m
            ON p.ID = m.product_id
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          AND m.sku LIKE %s
        ORDER BY m.sku ASC
        LIMIT %d
    ";

    $products = $wpdb->get_results(
        $wpdb->prepare($query, $sku_like, $limit),

    );

    return [
        'success' => true,
        'term' => $term,
        'count' => count($products),
        'data' => $products
    ];
}
function get_products_by_ids($request)
{
    global $wpdb;

    $ids = $request->get_json_params();

    if (!is_array($ids) || empty($ids)) {
        return new WP_Error(
            'invalid_ids',
            'Envie um array de IDs',
            ['status' => 400]
        );
    }

    // Sanitizar IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);

    if (empty($ids)) {
        return new WP_Error(
            'invalid_ids',
            'IDs inválidos',
            ['status' => 400]
        );
    }

    // Criar placeholders dinâmicos
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    $query = "
        SELECT 
            p.ID as id,
            p.post_title as name,
            p.post_content as description,
            p.post_excerpt as short_description,
            m.sku,
            m.min_price as price,
            m.max_price,
            m.stock_quantity as stock,
            m.stock_status,
            m.average_rating,
            m.rating_count,
            m.total_sales,
            p.guid as product_url,
            p.post_date as created_at,
            p.post_modified as updated_at,
            (SELECT guid 
             FROM {$wpdb->posts} 
             WHERE post_parent = p.ID 
               AND post_type = 'attachment' 
               AND post_mime_type LIKE 'image/%%'
             LIMIT 1) as main_image
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m 
            ON p.ID = m.product_id
        WHERE p.ID IN ($placeholders)
          AND p.post_type = 'product'
          AND p.post_status = 'publish'
    ";

    $products = $wpdb->get_results(
        $wpdb->prepare($query, $ids),
    );

    return [
        'success' => true,
        'count' => count($products),
        'data' => $products
    ];
}
function get_product_by_id($request)
{
    global $wpdb;

    $product_id = (int) $request['id'];

    $query = "
        SELECT 
            p.ID as id,
            p.post_title as name,
            p.post_content as description,
            p.post_excerpt as short_description,
            m.sku,
            m.min_price as price,
            m.max_price,
            m.stock_quantity as stock,
            m.stock_status,
            m.average_rating,
            m.rating_count,
            m.total_sales,
            p.guid as product_url,
            p.post_date as created_at,
            p.post_modified as updated_at,
            (SELECT guid 
             FROM {$wpdb->posts} 
             WHERE post_parent = p.ID 
               AND post_type = 'attachment' 
               AND post_mime_type LIKE 'image/%%'
             LIMIT 1) as main_image,
            (SELECT GROUP_CONCAT(t.name SEPARATOR '|')
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_relationships} tr ON t.term_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat') as categories
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
        WHERE p.ID = %d
          AND p.post_type = 'product' 
          AND p.post_status = 'publish'
    ";

    $product = $wpdb->get_row(
        $wpdb->prepare($query, $product_id),
    );

    if (!$product) {
        return new WP_Error(
            'product_not_found',
            'Produto não encontrado',
            ['status' => 404]
        );
    }

    return [
        'success' => true,
        'data' => $product
    ];
}

function list_products($request)
{
    global $wpdb;

    $page = max(1, (int) ($request->get_param('page') ?: 1));
    $limit = min(100, (int) ($request->get_param('limit') ?: 50));
    $offset = ($page - 1) * $limit;
    $in_stock = $request->get_param('in_stock');

    $where_conditions = ["p.post_type = 'product'", "p.post_status = 'publish'"];

    if ($in_stock === 'true') {
        $where_conditions[] = "m.stock_status = 'instock'";
    } elseif ($in_stock === 'false') {
        $where_conditions[] = "m.stock_status = 'outofstock'";
    }

    $query = "
        SELECT 
            p.ID as id,
            p.post_title as name,
            p.post_content as description,
            m.sku,
            m.min_price as price,
            m.stock_quantity as stock,
            m.stock_status,
            m.average_rating,
            p.guid as product_url,
            (SELECT guid 
             FROM {$wpdb->posts} 
             WHERE post_parent = p.ID 
               AND post_type = 'attachment' 
               AND post_mime_type LIKE 'image/%%'
             LIMIT 1) as main_image
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
    ";

    $products = $wpdb->get_results(
        $wpdb->prepare($query, $limit, $offset),
    );

    // Contar total
    $count_query = "
        SELECT COUNT(*) as total
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
        WHERE " . implode(' AND ', $where_conditions);

    $total = $wpdb->get_var($count_query);

    return [
        'success' => true,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        ],
        'data' => $products
    ];
}

function get_products_count($request)
{
    global $wpdb;

    $in_stock = $request->get_param('in_stock');

    $where_conditions = ["p.post_type = 'product'", "p.post_status = 'publish'"];

    if ($in_stock === 'true') {
        $where_conditions[] = "m.stock_status = 'instock'";
    } elseif ($in_stock === 'false') {
        $where_conditions[] = "m.stock_status = 'outofstock'";
    }

    $query = "
        SELECT COUNT(*) as total
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup m ON p.ID = m.product_id
        WHERE " . implode(' AND ', $where_conditions);

    $total = $wpdb->get_var($query);

    return [
        'success' => true,
        'count' => (int) $total,
        'filter' => $in_stock ?: 'all'
    ];
}

function get_product_images($request)
{
    global $wpdb;

    $product_id = (int) $request['id'];

    $images = $wpdb->get_results(
        $wpdb->prepare("
            SELECT 
                ID as image_id,
                guid as image_url,
                post_title as alt_text,
                post_mime_type as mime_type
            FROM {$wpdb->posts}
            WHERE post_parent = %d
              AND post_type = 'attachment'
              AND post_mime_type LIKE 'image/%%'
            ORDER BY ID
        ", $product_id),
    );

    return [
        'success' => true,
        'product_id' => $product_id,
        'count' => count($images),
        'data' => $images
    ];
}
