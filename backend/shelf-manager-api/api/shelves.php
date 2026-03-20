<?php
global $app;

$app->get('/shelves', function ($request, $response) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $result = $db->query("
        SELECT s.*, 
               (SELECT COUNT(*) FROM shelf_items WHERE shelf_id = s.id) as item_count,
               u.username as created_by_name
        FROM shelves s
        LEFT JOIN users u ON s.created_by = u.id
        ORDER BY s.created_at DESC
    ");

    $shelves = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $shelves[] = $row;
    }
    $response->getBody()->write(json_encode($shelves));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/shelves/{id}', function ($request, $response, $args) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM shelf_items WHERE shelf_id = s.id) as item_count,
               u.username as created_by_name
        FROM shelves s
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = :id
    ");
    $stmt->bindValue(':id', $args['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $shelf = $result->fetchArray(SQLITE3_ASSOC);

    if (!$shelf) {
        $response->getBody()->write(json_encode(['error' => 'Prateleira não encontrada']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode($shelf));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/shelves', function ($request, $response) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $data = $request->getParsedBody();
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';

    if (empty($name)) {
        $response->getBody()->write(json_encode(['error' => 'Nome obrigatório']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM shelves WHERE name = :name");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $result = $stmt->execute();
    if ($result->fetchArray()) {
        $response->getBody()->write(json_encode(['error' => 'Prateleira já existe']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $db->prepare("INSERT INTO shelves (name, description, created_by) VALUES (:name, :description, :created_by)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':created_by', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $id = $db->lastInsertRowID();
    $shelf = $db->querySingle("SELECT *, 0 as item_count FROM shelves WHERE id = $id", true);
    $response->getBody()->write(json_encode($shelf));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->put('/shelves/{id}', function ($request, $response, $args) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM shelves WHERE id = :id");
    $stmt->bindValue(':id', $args['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $shelf = $result->fetchArray(SQLITE3_ASSOC);
    if (!$shelf) {
        $response->getBody()->write(json_encode(['error' => 'Prateleira não encontrada']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    if ($shelf['created_by'] != $user['id'] && !$user['isAdmin']) {
        $response->getBody()->write(json_encode(['error' => 'Sem permissão']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data = $request->getParsedBody();
    $name = $data['name'] ?? $shelf['name'];
    $description = $data['description'] ?? $shelf['description'];

    $stmt = $db->prepare("UPDATE shelves SET name = :name, description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':id', $args['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $updated = $db->querySingle("SELECT * FROM shelves WHERE id = {$args['id']}", true);
    $response->getBody()->write(json_encode($updated));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/shelves/{id}', function ($request, $response, $args) {
    $user = authenticate($request);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Não autorizado']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    if (!$user['isAdmin']) {
        $response->getBody()->write(json_encode(['error' => 'Apenas admin pode excluir']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM shelves WHERE id = :id");
    $stmt->bindValue(':id', $args['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $response->getBody()->write(json_encode(['success' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});