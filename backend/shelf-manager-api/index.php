<?php
// Habilitar CORS para desenvolvimento
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

// Middleware para processar JSON
$app->addBodyParsingMiddleware();

// Incluir banco de dados
require __DIR__ . '/config/database.php';

// Incluir o middleware de autenticação (definição da função authenticate)
require __DIR__ . '/api/auth_middleware.php';

// Incluir arquivos de rotas
require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/shelves.php';
require __DIR__ . '/api/items.php';
require __DIR__ . '/api/history.php';
require __DIR__ . '/api/products.php';

$app->run();