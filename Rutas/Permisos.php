<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    // Ruta principal para obtener todos los usuarios
    $app->get('/ctpermisos/show', function () use ($app,$db,$request) {
        try{
            $id = $request->getQuery('id');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM ctpermisos a WHERE 1 = 1";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if ($request->hasQuery('fromcatalog')){
                $phql   .= " AND a.publico = 0 AND a.visible = 1 ";
            }

            $phql   .= ' ORDER BY a.label_controlador,a.label_accion ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                if ($request->hasQuery('fromcatalog')){
                    $data[$row['controlador']][]    = $row;
                } else {
                    $data[] = $row;
                }
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($data);
            $response->setStatusCode(200, 'OK');
            return $response;
        }catch (\Exception $e){
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
        
    });

    // Puedes agregar más rutas aquí relacionadas con `cttipo_usuarios`
};
