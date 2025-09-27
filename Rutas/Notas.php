<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    $app->post('/tbnotas/create', function () use ($app, $db, $request) {
        try {
            
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $conexion->rollback();
            
            return (new Response())->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(400, 'Bad Request');
        }
    });

    // Ruta principal para obtener todos los usuarios
    $app->get('/tbnotas/count', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente    = $request->getQuery('id_paciente');
            $id_profesional = $request->getQuery('id_profesional');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            // Definir el query SQL
            $phql   = " SELECT COUNT(*) as num_rows FROM tbnotas a 
                        WHERE 1 = 1 ";
            $values = array();
    
            if (!empty($id_paciente)){
                $phql           .= " AND id_paciente = :id_paciente ";
                $values['id_paciente']  = $id_paciente;
            }

            if (empty($id_profesional)){
                $phql   .= " AND (EXISTS (
                                    SELECT 1 FROM ctusuarios t1 
                                    WHERE t1.clave = :usuario_solicitud
                                    AND a.id_profesional = t1.id_profesional
                                ) OR a.nota_privada = 0
                            )";

                $values['usuario_solicitud']    = $usuario_solicitud;
            } else {
                $phql           .= " AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            $phql   .= " ORDER BY a.fecha_creacion DESC ";
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $num_rows   = 0;
            while ($row = $result->fetch()) {
                $num_rows   = $row['num_rows'];
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($num_rows);
            $response->setStatusCode(200, 'OK');
            return $response;
        }catch (\Exception $e){
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'Created');
            return $response;
        }
        
    });

    // Ruta principal para obtener todos los usuarios
    $app->get('/tbnotas/show', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente    = $request->getQuery('id_paciente');
            $id_profesional = $request->getQuery('id_profesional');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            // Definir el query SQL
            $phql   = " SELECT  
                            a.*,
                            (d.primer_apellido||' '||COALESCE(d.segundo_apellido,'')||' '||d.nombre) as profesional,
                        FROM tbnotas a 
                        LEFT JOIN ctprofesionales b ON a.id_profesional = b.id
                        WHERE 1 = 1 ";
            $values = array();
    
            if (!empty($id_paciente)){
                $phql           .= " AND id_paciente = :id_paciente ";
                $values['id_paciente']  = $id_paciente;
            }

            if (empty($id_profesional)){
                $phql   .= " AND (EXISTS (
                                    SELECT 1 FROM ctusuarios t1 
                                    WHERE t1.clave = :usuario_solicitud
                                    AND a.id_profesional = t1.id_profesional
                                ) OR a.nota_privada = 0
                            )";

                $values['usuario_solicitud']    = $usuario_solicitud;
            } else {
                $phql           .= " AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            $phql   .= " ORDER BY a.fecha_creacion DESC ";

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
            $response->setStatusCode(400, 'Created');
            return $response;
        }
        
    });
};