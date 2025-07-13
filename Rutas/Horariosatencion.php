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
    $app->post('/tbhorarios_atencion/save_opening_hours', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            $id_locacion    = $request->getPost('id_locacion') ?? null;
            $id_profesional = $request->getPost('id_profesional') ?? null;
            $obj_info           = $request->getPost('obj_info') ?? null;

            //  SE BORRA EL HORARIO DE ATENCION ACTUAL
            $phql   = "DELETE FROM tbhorarios_atencion WHERE id_locacion = :id_locacion ";
            $values = array(
                'id_locacion'   => $id_locacion
            );

            if (!empty($id_profesional)){
                $phql   .= " AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            } else {
                $phql   .= " AND id_profesional IS NULL ";
            }

            $conexion->execute($phql,$values);

            //  SE RECORRE EL OBJETO Y SE CREAN LOS REGISTROS
            foreach($obj_info as $horario_atencion){
                $phql   = "SELECT * FROM tbhorarios_atencion 
                            WHERE id_locacion = :id_locacion AND hora_inicio = :hora_inicio AND hora_termino = :hora_termino";
                $values = array(
                    'id_locacion'       => $id_locacion,
                    'hora_inicio'       => $horario_atencion['hora_inicio'],
                    'hora_termino'      => $horario_atencion['hora_termino']
                );

                if (!empty($id_profesional)){
                    $phql   .= " AND id_profesional = :id_profesional ";
                    $values['id_profesional']   = $id_profesional;
                } else {
                    $phql   .= " AND id_profesional IS NULL ";
                }

                $result = $db->query($phql,$values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
                $id_horario_atencion    = null;
                while ($data = $result->fetch()) {
                    $id_horario_atencion    = $data['id'];
                }

                //  SE CREA EL REGISTRO EN CASO DE QUE ESTE NO EXISTA
                if ($id_horario_atencion == null){
                    $phql   = "INSERT INTO tbhorarios_atencion (id_locacion,id_profesional,hora_inicio,hora_termino,titulo)
                                VALUES (:id_locacion,:id_profesional,:hora_inicio,:hora_termino,:titulo) RETURNING *";

                    if (!empty($id_profesional)){
                        $values['id_profesional']   = $id_profesional;
                    } else {
                        $values['id_profesional']   = null;
                    }
                    
                    $values['titulo']           = $horario_atencion['titulo'];

                    $result_create  = $conexion->query($phql,$values);
                    $result_create->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    while ($data_create = $result_create->fetch()) {
                        $id_horario_atencion    = $data_create['id'];
                    }
                }

                //  SE RECORRE EL REGISTRO DE DIAS
                $phql   = "INSERT INTO tbhorarios_atencion_dias (id_horario_atencion,dia)
                                VALUES (:id_horario_atencion,:dia)";
                foreach($horario_atencion['dias'] as $dia){
                    $result_create_dia  = $conexion->query($phql,array(
                        'id_horario_atencion'   => $id_horario_atencion,
                        'dia'                   => $dia
                    ));
                }
            }
            
    
            $conexion->commit();
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $conexion->rollback();
            
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/tbhorarios_atencion/get_opening_hours', function () use ($app, $db, $request) {
        try {
    
            $id             = $request->getQuery('id') ?? null;
            $id_locacion    = $request->getQuery('id_locacion');
            $id_profesional = $request->getQuery('id_profesional') ?? null;
            $arr_return     = array();

            $phql   = "SELECT 
                            id,
                            id_locacion,
                            id_profesional ,
                            TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(hora_termino, 'HH24:MI') AS hora_termino,
                            titulo
                        FROM tbhorarios_atencion a
                        WHERE id_locacion = :id_locacion ";

            $values = array(
                'id_locacion'   => $id_locacion
            );

            if (!empty($id_profesional)){
                $phql   .= " AND a.id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;

                if (!empty($id)){
                    $phql   = " AND EXISTS (
                                    SELECT 1 FROM tbhorarios_atencion t1
                                    WHERE t1.hora_inicio BETWEEN a.hora_inicio AND a.hora_termino
                                    AND t1.hora_termino BETWEEN a.hora_inicio AND a.hora_termino
                                );";
                }

            } else {
                $phql   .= " AND a.id_profesional IS NULL ";

                if (!empty($id)){
                    $phql   .= " AND a.id = :id ";
                    $values['id']   = $id;
                }
            }

            

            $phql   .= ' ORDER BY a.id ASC';

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $id_horario_atencion    = null;
            while ($data = $result->fetch()) {
                $tmp_array          = $data;
                $tmp_array['dias']  = array();

                //  SE BUSCAN LOS DIAS ASIGNADOS
                $phql   = "SELECT dia FROM tbhorarios_atencion_dias 
                            WHERE id_horario_atencion = :id_horario_atencion";

                $result_dias    = $db->query($phql,array(
                    'id_horario_atencion'   => $data['id']
                ));
                $result_dias->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result_dias){
                    while($data_dias = $result_dias->fetch()){
                        $tmp_array['dias'][]    = $data_dias;
                    }
                }

                $arr_return[]   = $tmp_array;
            }
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });
};