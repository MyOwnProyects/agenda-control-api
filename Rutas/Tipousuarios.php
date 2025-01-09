<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

return function (Micro $app) {
    // Ruta principal para obtener todos los usuarios
    $app->get('/tipousuarios', function () use ($app) {
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

    // Puedes agregar más rutas aquí relacionadas con `cttipo_usuarios`
};
