<?php
use Phalcon\Mvc\Micro;
use Phalcon\Di\FactoryDefault;
use Phalcon\Http\Response;
use Phalcon\Db\Adapter\Pdo\Postgresql as PdoPostgres;

// Crear el contenedor de inyección de dependencias
$di = new FactoryDefault();
$di->set('db', function () {
    return new PdoPostgres([
        'host'      => 'db',          // Nombre del servicio de la base de datos en docker-compose.yml
        'username'  => 'user',        // Usuario definido en environment
        'password'  => 'password',    // Contraseña definida en environment
        'dbname'    => 'agenda_control', // Nombre de la base de datos definida en environment
        'port'      => 5432           // Puerto interno de PostgreSQL
    ]);
});



// Crear la aplicación Micro y pasarle el contenedor DI
$app = new Micro($di);

// Definir un servicio básico para manejar rutas
$app->get('/', function () use ($app) {
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $app->getDI()->get('db');
    
    // Definir el query SQL
    $phql = "SELECT * FROM cttipo_usuarios";

    // Ejecutar el query y obtener el resultado
    $result = $db->query($phql);
    $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);


    // Recorrer los resultados
    $data = [];
    while ($row = $result->fetch()) {
        $data[] = $row;
    }

    // Devolver los datos en formato JSON
    $response = new Response();
    $response->setJsonContent($data);
    return $response;
});

// Manejar la solicitud
$app->handle($_SERVER["REQUEST_URI"]);
