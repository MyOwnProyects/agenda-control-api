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
            $omitir_dias_inhabiles  = $request->getQuery('omitir_dias_inhabiles') ?? null;
            $fecha_inicio           = $request->getQuery('fecha_inicio') ?? null;
            $fecha_termino          = $request->getQuery('fecha_termino') ?? null;
            $arr_return         = array();
            $arr_dias_inhabiles = array();

            //  SI VIENE LA BANDERA DE OMITIR DIAS SE DEBEN DE SEGUIR LOS SIGUIENTES PASOS
            //  1. Del rango de semana se buscan si hay dias inhabiles
            //  2. De dichos dias se obtienen los dias de la semana (Lnes,martes...)
            //  3. Al hacer realizar la busqueda no se agregan al array final
            //      la informacion de ese día

            if ($omitir_dias_inhabiles){
                $phql   = " SELECT * FROM tbfechas_bloqueo_agenda 
                            WHERE(
                                    (fecha_inicio BETWEEN :fecha_inicio AND :fecha_termino) OR 
                                    (fecha_termino BETWEEN :fecha_inicio AND :fecha_termino) OR
                                    (fecha_inicio <= :fecha_inicio AND fecha_termino >= :fecha_termino)
                                )";
                $values = array(
                    'fecha_inicio'  => $fecha_inicio,
                    'fecha_termino' => $fecha_termino
                );
                
                if (is_numeric($id_profesional)){
                    $phql   .= " AND id_locacion IS NULL AND id_profesional = :id_profesional ";
                    $values['id_profesional']   = $id_profesional;
                }

                if (is_numeric($id_locacion) && empty($id_profesional)){
                    $phql   .= " AND id_profesional IS NULL AND id_locacion = :id_locacion ";
                    $values['id_locacion']  = $id_locacion;
                }

                $result = $db->query($phql,$values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result){
                    while($data = $result->fetch()){
                        // Convertir las fechas a objetos DateTime
                        $fecha_inicio_peticion  = new DateTime($fecha_inicio);
                        $fecha_termino_peticion = new DateTime($fecha_termino);
                        $fecha_inicio_loop      = new DateTime($data['fecha_inicio']);
                        $fecha_termino_loop     = new DateTime($data['fecha_termino']);
                        
                        // Iterar día por día
                        while ($fecha_inicio_loop <= $fecha_termino_loop) {
                            $fecha_actual = $fecha_inicio_loop->format('Y-m-d');
                        
                            if ($fecha_actual >= $fecha_inicio_peticion ||
                                $fecha_actual <= $fecha_termino_peticion){
                                if (!in_array($fecha_actual, $arr_dias_inhabiles)) {
                                    $arr_dias_inhabiles[] = $fecha_inicio_loop->format('N');
                                }
                            }
                            
                            // Sumar un día
                            $fecha_inicio_loop->modify('+1 day');
                        }
                    }
                }

            }

            $phql   = "SELECT 
                            a.id,
                            a.id_locacion,
                            a.id_profesional ,
                            TO_CHAR(a.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(a.hora_termino, 'HH24:MI') AS hora_termino,
                            a.titulo,
                            b.intervalo_citas
                        FROM tbhorarios_atencion a
                        LEFT JOIN ctlocaciones b ON a.id_locacion = b.id
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
                        if (in_array($data_dias['dia'], $arr_dias_inhabiles) && is_numeric($id_locacion) && !empty($id_profesional)) {
                            continue;
                        }
                        $tmp_array['dias'][]    = $data_dias;
                    }
                }

                if (count($tmp_array['dias']) == 0){
                    continue;
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

    $app->get('/tbhorarios_atencion/get_dias_inhabiles', function () use ($app, $db, $request) {
        try {
    
            $id             = $request->getQuery('id') ?? null;
            $id_locacion    = $request->getQuery('id_locacion');
            $id_profesional = $request->getQuery('id_profesional') ?? null;
            $omitir_dias_inhabiles  = $request->getQuery('omitir_dias_inhabiles') ?? null;
            $fecha_inicio           = $request->getQuery('fecha_inicio') ?? null;
            $fecha_termino          = $request->getQuery('fecha_termino') ?? null;
            $arr_return         = array();
            $arr_dias_inhabiles = array();

            //  SI VIENE LA BANDERA DE OMITIR DIAS SE DEBEN DE SEGUIR LOS SIGUIENTES PASOS
            //  1. Del rango de semana se buscan si hay dias inhabiles
            //  2. De dichos dias se obtienen los dias de la semana (Lnes,martes...)
            //  3. Al hacer realizar la busqueda no se agregan al array final
            //      la informacion de ese día

            $phql   = " SELECT * FROM tbfechas_bloqueo_agenda 
                        WHERE(
                                (fecha_inicio BETWEEN :fecha_inicio AND :fecha_termino) OR 
                                (fecha_termino BETWEEN :fecha_inicio AND :fecha_termino) OR
                                (fecha_inicio <= :fecha_inicio AND fecha_termino >= :fecha_termino)
                            )";
            $values = array(
                'fecha_inicio'  => $fecha_inicio,
                'fecha_termino' => $fecha_termino
            );
            
            if (is_numeric($id_profesional)){
                $phql   .= " AND id_locacion IS NULL AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            if (is_numeric($id_locacion) && empty($id_profesional)){
                $phql   .= " AND id_profesional IS NULL AND id_locacion = :id_locacion ";
                $values['id_locacion']  = $id_locacion;
            }

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    // Convertir las fechas a objetos DateTime
                    $fecha_inicio_peticion  = new DateTime($fecha_inicio);
                    $fecha_termino_peticion = new DateTime($fecha_termino);
                    $fecha_inicio_loop      = new DateTime($data['fecha_inicio']);
                    $fecha_termino_loop     = new DateTime($data['fecha_termino']);
                    
                    // Iterar día por día
                    while ($fecha_inicio_loop <= $fecha_termino_loop) {
                        $fecha_actual = $fecha_inicio_loop->format('Y-m-d');
                    
                        if ($fecha_actual >= $fecha_inicio_peticion ||
                            $fecha_actual <= $fecha_termino_peticion){
                            if (!in_array($fecha_inicio_loop->format('N'), $arr_dias_inhabiles)) {
                                $arr_dias_inhabiles[] = $fecha_inicio_loop->format('N');
                            }
                        }
                        
                        // Sumar un día
                        $fecha_inicio_loop->modify('+1 day');
                    }
                }
            }
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent($arr_dias_inhabiles);
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