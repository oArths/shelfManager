# Queries otimizadas para WooCommerce/WP

PRODUCT_SEARCH_QUERY = """
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
     FROM wp_posts 
     WHERE post_parent = p.ID 
       AND post_type = 'attachment' 
       AND post_mime_type LIKE 'image/%'
     LIMIT 1) as main_image
FROM wp_posts p
LEFT JOIN wp_wc_product_meta_lookup m ON p.ID = m.product_id
WHERE p.post_type = 'product' 
  AND p.post_status = 'publish'
  AND (
    p.post_title LIKE ?
    OR m.sku LIKE ?
    OR p.post_content LIKE ?
  )
ORDER BY 
    CASE WHEN m.sku LIKE ? THEN 1
         WHEN p.post_title LIKE ? THEN 2
         ELSE 3
    END,
    p.post_title
LIMIT ?
"""

PRODUCT_BY_SKU_QUERY = """
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
    p.post_modified as updated_at
FROM wp_posts p
LEFT JOIN wp_wc_product_meta_lookup m ON p.ID = m.product_id
WHERE p.post_type = 'product' 
  AND p.post_status = 'publish'
  AND m.sku = ?
"""

PRODUCTS_PAGINATED_QUERY = """
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
     FROM wp_posts 
     WHERE post_parent = p.ID 
       AND post_type = 'attachment' 
       AND post_mime_type LIKE 'image/%'
     LIMIT 1) as main_image
FROM wp_posts p
LEFT JOIN wp_wc_product_meta_lookup m ON p.ID = m.product_id
WHERE p.post_type = 'product' 
  AND p.post_status = 'publish'
ORDER BY p.ID DESC
LIMIT ? OFFSET ?
"""

PRODUCT_COUNT_QUERY = """
SELECT COUNT(*) as total
FROM wp_posts p
LEFT JOIN wp_wc_product_meta_lookup m ON p.ID = m.product_id
WHERE p.post_type = 'product' 
  AND p.post_status = 'publish'
"""

PRODUCT_IMAGES_QUERY = """
SELECT 
    ID as image_id,
    guid as image_url,
    post_title as alt_text,
    post_mime_type as mime_type
FROM wp_posts
WHERE post_parent = ?
  AND post_type = 'attachment'
  AND post_mime_type LIKE 'image/%'
ORDER BY ID
"""

PRODUCT_CATEGORIES_QUERY = """
SELECT 
    t.name as category_name,
    t.slug as category_slug,
    COUNT(p.ID) as product_count
FROM wp_terms t
JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
JOIN wp_term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
JOIN wp_posts p ON tr.object_id = p.ID
WHERE tt.taxonomy = 'product_cat'
  AND p.post_type = 'product'
  AND p.post_status = 'publish'
GROUP BY t.term_id
ORDER BY t.name
"""

