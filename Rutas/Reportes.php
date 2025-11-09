<?php 

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {
    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    // Ruta principal para obtener todos los registros
    $app->get('/dashboard_menu/show', function () use ($app,$db,$request) {
        try{
            $clave          = $request->getQuery('clave');
            $nombre         = $request->getQuery('nombre');
            $from_catalog   = $request->getQuery('from_catalog') ?? null;
        
            // Definir el query SQL
            $phql   = "SELECT * FROM ctvariables_sistema a WHERE 1 = 1";
            $values = array();

            if ($from_catalog){
                if (!empty($clave) && (empty($accion) || $accion != 'login')) {
                    $phql           .= " AND lower(a.clave) ILIKE :clave";
                    $values['clave'] = "%".FuncionesGlobales::ToLower($clave)."%";
                }
    
                if (!empty($nombre)) {
                    $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                    $values['nombre'] = "%".FuncionesGlobales::ToLower($nombre)."%";
                }
            } else {
                if (!empty($clave) && (empty($accion) || $accion != 'login')) {
                    $phql           .= " AND a.clave = :clave ";
                    $values['clave'] = $clave;
                }
    
                if (!empty($nombre)) {
                    $phql           .= " AND a.nombre = :nombre";
                    $values['nombre'] = $nombre;
                }
            }
            
            $phql   .= ' ORDER BY a.clave,a.nombre ';

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $data[] = $row;
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
};