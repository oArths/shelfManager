<?php
require_once __DIR__ . '/../config/database_mysql.php';

class ProductServiceMySQL {
    private $pdo;

    public function __construct() {
        $this->pdo = getMySQLConnection();
    }

    /**
     * Busca um produto por ID no banco PrestaShop
     */
    public function getProduct($productId) {
        if (!$this->pdo) return null;

        $sql = "
            SELECT p.id_product, pl.name, p.reference as sku, p.price,
                   sa.quantity as stock,
                   (SELECT CONCAT('https://lebrecho.com.br/img/p/', i.id_image, '.jpg') 
                    FROM ps_image i WHERE i.id_product = p.id_product AND i.cover = 1 LIMIT 1) as main_image
            FROM ps_product p
            LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = 1
            LEFT JOIN ps_stock_available sa ON p.id_product = sa.id_product
            WHERE p.id_product = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            return [
                'id' => (int)$product['id_product'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => (float)$product['price'],
                'stock' => $product['stock'],
                'main_image' => $product['main_image'] ?? null,
            ];
        }
        return null;
    }

    /**
     * Busca produtos por termo (nome ou SKU)
     */
    public function searchProducts($term, $limit = 20) {
        if (!$this->pdo) return [];

        $sql = "
            SELECT p.id_product, pl.name, p.reference as sku, p.price,
                   sa.quantity as stock,
                   (SELECT CONCAT('https://lebrecho.com.br/img/p/', i.id_image, '.jpg') 
                    FROM ps_image i WHERE i.id_product = p.id_product AND i.cover = 1 LIMIT 1) as main_image
            FROM ps_product p
            LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = 1
            LEFT JOIN ps_stock_available sa ON p.id_product = sa.id_product
            WHERE p.active = 1 AND (pl.name LIKE :term OR p.reference LIKE :term)
            LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        $term = "%$term%";
        $stmt->bindParam(':term', $term);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => (int)$row['id_product'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'price' => (float)$row['price'],
                'stock' => $row['stock'],
                'main_image' => $row['main_image'] ?? null,
            ];
        }
        return $products;
    }

    /**
     * Busca múltiplos produtos por IDs (para uso em lote)
     */
    public function getProductsBatch(array $productIds) {
        if (empty($productIds) || !$this->pdo) return [];

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "
            SELECT p.id_product, pl.name, p.reference as sku, p.price,
                   sa.quantity as stock,
                   (SELECT CONCAT('https://lebrecho.com.br/img/p/', i.id_image, '.jpg') 
                    FROM ps_image i WHERE i.id_product = p.id_product AND i.cover = 1 LIMIT 1) as main_image
            FROM ps_product p
            LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = 1
            LEFT JOIN ps_stock_available sa ON p.id_product = sa.id_product
            WHERE p.id_product IN ($placeholders)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($productIds);
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[$row['id_product']] = [
                'id' => (int)$row['id_product'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'price' => (float)$row['price'],
                'stock' => $row['stock'],
                'main_image' => $row['main_image'] ?? null,
            ];
        }
        return $products;
    }
}