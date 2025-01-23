<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    // Ruta principal para obtener todos los usuarios
    $app->get('/ctusuarios/show', function () use ($app,$db,$request) {
        try{
            $id         = $request->getQuery('id');
            $username   = $request->getQuery('username');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT a.*,b.clave as clave_tipo_usuario, b.nombre as nombre_tio_usuario FROM ctusuarios a 
                        LEFT JOIN cttipo_usuarios b ON a.id_tipo_usuario = b.id 
                        WHERE 1 = 1";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if ($username != null && $username != ''){
                $phql               .= " AND lower(a.clave) = :clave";
                $values['clave']    = mb_strtolower($username, 'UTF-8');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $data[] = $row;
            }

            if (count($data) == 0){
                throw new Exception('Busqueda sin resultados');
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($data);
            $response->setStatusCode(200, 'OK');
            return $response;
        }catch (\Exception $e){
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setContent($e->getMessage());
            $response->setStatusCode(400, 'Created');
            return $response;
        }
        
    });

    $app->get('/ctusuarios/get_info_usuario', function () use ($app,$db,$request) {
        try{
            $id_usuario = $request->getQuery('id_usuario');
            
            if ($id_usuario == null || !is_numeric($id_usuario)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = " SELECT b.controlador,b.accion FROM ctpermisos_usuarios a 
                        LEFT JOIN ctpermisos b ON a.id_permiso = b.id
                        WHERE a.id_usuario = :id_usuario
                        UNION
                        SELECT a.controlador,a.accion FROM ctpermisos a WHERE a.publico = 1";
            $values = array(
                'id_usuario'    => $id_usuario
            );
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $data[] = $row;
            }

            if (count($data) == 0){
                throw new Exception('Busqueda sin resultados');
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($data);
            $response->setStatusCode(200, 'OK');
            return $response;
        }catch (\Exception $e){
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setContent($e->getMessage());
            $response->setStatusCode(400, 'Created');
            return $response;
        }
        
    });
};
