<?php
global $app;

$app->get('/items/{product_id}/history', function ($request, $response, $args) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT h.*, s.name as shelf_name
        FROM item_history h
        JOIN shelves s ON h.shelf_id = s.id
        WHERE h.product_id = :pid
        ORDER BY h.entrada DESC
    ");
    $stmt->bindValue(':pid', $args['product_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $history = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $history[] = $row;
    }
    $response->getBody()->write(json_encode($history));
    return $response->withHeader('Content-Type', 'application/json');
});