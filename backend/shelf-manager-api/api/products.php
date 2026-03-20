<?php
global $app;
require_once __DIR__ . '/ProductServiceMySQL.php';

$productService = new ProductServiceMySQL();

// Endpoint de busca de produtos
$app->get('/products/search', function ($request, $response) use ($productService) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $params = $request->getQueryParams();
    $q = $params['q'] ?? '';
    $limit = isset($params['limit']) ? (int)$params['limit'] : 20;

    if (strlen($q) < 2) {
        $response->getBody()->write(json_encode(['data' => []]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $products = $productService->searchProducts($q, $limit);

    // Adicionar informação de localização
    $db = getDB();
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
        if ($shelf) {
            $product['in_shelf'] = $shelf['shelf_id'];
            $product['shelf_name'] = $shelf['shelf_name'];
        } else {
            $product['in_shelf'] = null;
            $product['shelf_name'] = null;
        }
    }

    $response->getBody()->write(json_encode(['success' => true, 'data' => $products]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Endpoint de batch status (já existente, mantido)
$app->post('/products/batch-status', function ($request, $response) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $product_ids = $request->getParsedBody();
    if (!is_array($product_ids) || empty($product_ids)) {
        $response->getBody()->write(json_encode([]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $db = getDB();
    $stmt = $db->prepare("
        SELECT si.product_id, si.shelf_id, s.name as shelf_name
        FROM shelf_items si
        JOIN shelves s ON si.shelf_id = s.id
        WHERE si.product_id IN ($placeholders)
    ");

    foreach ($product_ids as $i => $pid) {
        $stmt->bindValue($i+1, $pid, SQLITE3_INTEGER);
    }
    $result = $stmt->execute();

    $responseData = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $responseData[$row['product_id']] = [
            'shelf_id' => $row['shelf_id'],
            'shelf_name' => $row['shelf_name']
        ];
    }
    $response->getBody()->write(json_encode($responseData));
    return $response->withHeader('Content-Type', 'application/json');
});