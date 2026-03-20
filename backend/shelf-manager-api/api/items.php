<?php
global $app;
require_once __DIR__ . '/ProductServiceMySQL.php';

$productService = new ProductServiceMySQL();

$app->get('/shelves/{shelf_id}/items', function ($request, $response, $args) use ($productService) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM shelf_items WHERE shelf_id = :shelf_id ORDER BY added_at DESC");
    $stmt->bindValue(':shelf_id', $args['shelf_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $items = [];
    $productIds = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row;
        $productIds[] = $row['product_id'];
    }

    if (!empty($productIds)) {
        $productsData = $productService->getProductsBatch(array_unique($productIds));
        foreach ($items as &$item) {
            $item['product_data'] = $productsData[$item['product_id']] ?? null;
        }
    }

    $response->getBody()->write(json_encode($items));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/shelves/{shelf_id}/items', function ($request, $response, $args) use ($productService) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $data = $request->getParsedBody();
    $product_id = $data['product_id'] ?? 0;
    $quantity = $data['quantity'] ?? 1;

    $db = getDB();

    // Verifica se prateleira existe
    $stmt = $db->prepare("SELECT id FROM shelves WHERE id = :id");
    $stmt->bindValue(':id', $args['shelf_id'], SQLITE3_INTEGER);
    if (!$stmt->execute()->fetchArray()) {
        $response->getBody()->write(json_encode(['error' => 'Prateleira não encontrada']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // Verifica se produto já está em alguma prateleira
    $stmt = $db->prepare("SELECT id FROM shelf_items WHERE product_id = :pid");
    $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
    if ($stmt->execute()->fetchArray()) {
        $response->getBody()->write(json_encode(['error' => 'Produto já está em uma prateleira']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }

    // Verifica se produto existe no MySQL
    $product = $productService->getProduct($product_id);
    if (!$product) {
        $response->getBody()->write(json_encode(['error' => 'Produto não encontrado no PrestaShop']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $db->prepare("INSERT INTO shelf_items (shelf_id, product_id, quantity, added_by) VALUES (:shelf_id, :product_id, :quantity, :added_by)");
    $stmt->bindValue(':shelf_id', $args['shelf_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':product_id', $product_id, SQLITE3_INTEGER);
    $stmt->bindValue(':quantity', $quantity, SQLITE3_INTEGER);
    $stmt->bindValue(':added_by', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $item_id = $db->lastInsertRowID();

    // Histórico de entrada
    $stmt = $db->prepare("INSERT INTO item_history (product_id, shelf_id, entrada) VALUES (:product_id, :shelf_id, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':product_id', $product_id, SQLITE3_INTEGER);
    $stmt->bindValue(':shelf_id', $args['shelf_id'], SQLITE3_INTEGER);
    $stmt->execute();

    $item = $db->querySingle("SELECT * FROM shelf_items WHERE id = $item_id", true);
    $item['product_data'] = $product;
    $response->getBody()->write(json_encode($item));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/shelves/{shelf_id}/items/{product_id}', function ($request, $response, $args) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM shelf_items WHERE shelf_id = :shelf_id AND product_id = :product_id");
    $stmt->bindValue(':shelf_id', $args['shelf_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':product_id', $args['product_id'], SQLITE3_INTEGER);
    $stmt->execute();

    if ($db->changes() === 0) {
        $response->getBody()->write(json_encode(['error' => 'Item não encontrado']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // Atualiza histórico com saída
    $stmt = $db->prepare("UPDATE item_history SET saida = CURRENT_TIMESTAMP WHERE product_id = :product_id AND shelf_id = :shelf_id AND saida IS NULL");
    $stmt->bindValue(':product_id', $args['product_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':shelf_id', $args['shelf_id'], SQLITE3_INTEGER);
    $stmt->execute();

    $response->getBody()->write(json_encode(['success' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/items/move', function ($request, $response) use ($productService) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $data = $request->getParsedBody();
    $product_id = $data['product_id'] ?? 0;
    $to_shelf_id = $data['to_shelf_id'] ?? 0;

    $db = getDB();

    // Descobre onde está
    $stmt = $db->prepare("SELECT * FROM shelf_items WHERE product_id = :pid");
    $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
    $current = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$current) {
        $response->getBody()->write(json_encode(['error' => 'Produto não está em nenhuma prateleira']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $from_shelf_id = $current['shelf_id'];

    // Verifica se destino existe
    $stmt = $db->prepare("SELECT id FROM shelves WHERE id = :id");
    $stmt->bindValue(':id', $to_shelf_id, SQLITE3_INTEGER);
    if (!$stmt->execute()->fetchArray()) {
        $response->getBody()->write(json_encode(['error' => 'Prateleira destino não encontrada']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // Opcional: verificar se produto existe no MySQL
    $product = $productService->getProduct($product_id);
    if (!$product) {
        $response->getBody()->write(json_encode(['error' => 'Produto não encontrado no PrestaShop']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $db->prepare("UPDATE shelf_items SET shelf_id = :to_shelf_id, updated_at = CURRENT_TIMESTAMP WHERE product_id = :pid");
    $stmt->bindValue(':to_shelf_id', $to_shelf_id, SQLITE3_INTEGER);
    $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Fecha histórico anterior
    $stmt = $db->prepare("UPDATE item_history SET saida = CURRENT_TIMESTAMP WHERE product_id = :pid AND shelf_id = :from_shelf_id AND saida IS NULL");
    $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
    $stmt->bindValue(':from_shelf_id', $from_shelf_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Novo histórico
    $stmt = $db->prepare("INSERT INTO item_history (product_id, shelf_id, entrada) VALUES (:pid, :to_shelf_id, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':pid', $product_id, SQLITE3_INTEGER);
    $stmt->bindValue(':to_shelf_id', $to_shelf_id, SQLITE3_INTEGER);
    $stmt->execute();

    $response->getBody()->write(json_encode(['success' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});