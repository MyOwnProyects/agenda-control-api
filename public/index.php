<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Postgresql as PdoPostgres;
use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

//  MIDDLAWARE
use Middleware\AuthMiddleware;
use Middleware\JsonMiddleware;

// Incluir el autoload de Composer para cargar las clases automáticamente
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar la configuración desde config.php
$config = require '../config.php';

// Crear el contenedor de inyección de dependencias
$di = new FactoryDefault();

if ($config['database']['local']){
    $di->set('db', function () use ($config) {
        return new PdoPostgres([
            'host'      => $config['database']['host'],
            'username'  => $config['database']['username'],
            'password'  => $config['database']['password'],
            'dbname'    => $config['database']['dbname'],
            'port'      => $config['database']['port']
        ]);
    });
}else {
    $di->setShared('db', function () use ($config) {
        return new PdoPostgres([
            'host'      => $config['database']['host'],
            'username'  => $config['database']['username'],
            'password'  => $config['database']['password'],
            'dbname'    => $config['database']['dbname'],
            'port'      => $config['database']['port'],
            'sslmode'   => 'require',
            'sslrootcert' => $config['database']['sslrootcert'],
            'options'   => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        ]);
    });
}

// 🔹 Registrar el servicio 'request' en el DI (Corrección del error)
$di->setShared('request', function () {
    return new \Phalcon\Http\Request();
});

// Crear la aplicación Micro y pasarle el contenedor DI
$app = new Micro($di);

// Registrar el middleware y pasar $app manualmente
$app->before(function () use ($app) {
    $middleware = new AuthMiddleware();
    return $middleware($app);
});

// Registrar el middleware de JSON
$app->before(function () use ($app) {
    $middleware = new JsonMiddleware();
    return $middleware($app);
});

// Incluir todas las rutas de la carpeta Rutas
foreach (glob(__DIR__ . '/..//Rutas/*.php') as $routeFile) {
    $route = require $routeFile;
    $route($app, $di); // Registrar las rutas en $app
}

// Definir el manejador para rutas no encontradas
// Obtener la URI original
$originalUri = $_SERVER["REQUEST_URI"];

// Normalizar la URI eliminando el prefijo "/api" si existe
$normalizedUri = (strpos($originalUri, '/api') === 0) ? substr($originalUri, 4) : $originalUri;

// Definir el manejador para rutas no encontradas
$app->notFound(function () use ($originalUri) {
    $response = new Response();
    $response->setStatusCode(404, "Not Found");
    $response->setJsonContent([
        'status'  => 'error',
        'message' => 'La ruta solicitada no existe.',
        'ruta'    => $originalUri
    ]);
    return $response;
});

// Manejar la solicitud con la URI normalizada
$app->handle($normalizedUri);

