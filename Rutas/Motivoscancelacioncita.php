<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {
    
    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    $app->get('/ctmotivos_cancelacion_cita/show', function () use ($app,$db,$request) {
        try{

            //  SE BUSCA SI EXISTE UN REGISTRO DE APERTURA DE AGENDA
            $phql   = "SELECT * FROM ctmotivos_cancelacion_cita WHERE visible = 1 ORDER BY clave ASC";

            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_return = array();
            if ($result){
                while($data = $result->fetch()){
                    $arr_return[]   = $data;
                }
            }

            $response = new Response();
            $response->setJsonContent($arr_return);
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
};