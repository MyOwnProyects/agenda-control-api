<?php
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Postgresql as PdoPostgres;
use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

// Cargar la configuración desde config.php
$config = require 'config.php';

// Crear el contenedor de inyección de dependencias
$di = new FactoryDefault();
$di->set('db', function () use ($config) {
    return new PdoPostgres([
        'host'      => $config['database']['host'],
        'username'  => $config['database']['username'],
        'password'  => $config['database']['password'],
        'dbname'    => $config['database']['dbname'],
        'port'      => $config['database']['port']
    ]);
});

// Crear la aplicación Micro y pasarle el contenedor DI
$app = new Micro($di);

// Incluir todas las rutas de la carpeta Rutas
foreach (glob(__DIR__ . '/Rutas/*.php') as $routeFile) {
    $route = require $routeFile;
    $route($app); // Registrar las rutas en $app
}

// Definir el manejador para rutas no encontradas
$app->notFound(function () {
    $response = new Response();
    $response->setStatusCode(404, "Not Found");
    $response->setJsonContent([
        'status'  => 'error',
        'message' => 'La ruta solicitada no existe.'
    ]);
    return $response;
});

// Manejar la solicitud
$app->handle($_SERVER["REQUEST_URI"]);
