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
    $app->get('/plantillas_mensajes/show', function () use ($app,$db,$request) {
        try{
            
            $phql = "SELECT * FROM ctplantillas_mensajes ORDER BY tipo_mensaje";
    
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $arr_return = array();
            while ($row = $result->fetch()) {
                $arr_return[]   = $row;
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

    $app->get('/plantillas_mensajes/generar_mensaje', function () use ($app,$db,$request) {
        try{

            $id_agenda_cita = $request->getPost('id_agenda_cita') ?? null;

            //  SE GENERA LA INFORMACION DE LA CITA
            $phql   = " SELECT  
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_profesional,
                            a.fecha_cita as fecha_nueva,
                            TO_CHAR(a.hora_inicio, 'HH24:MI') AS hora,
                            b.fecha_cita as fecha_anterior,
                            d.nombre as locacion,
                            d.latitud,
                            d.longitud
                        FROM tbagenda_citas a 
                        LEFT JOIN tbagenda_citas b ON a.id_cita_reprogramada = b.id
                        LEFT JOIN ctprofesionales c ON a.id_profesional = c.id
                        LEFT JOIN ctlocaciones d ON a.id_locacion = d.id
                        WHERE a.id = :id_agenda_cita";
            
            $phql = "SELECT * FROM ctplantillas_mensajes ORDER BY tipo_mensaje";
    
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $arr_return = array();
            while ($row = $result->fetch()) {
                //  $mapsUrl = 'https://maps.google.com/?q=' . $latitud . ',' . $longitud;
                $arr_return[]   = $row;
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