<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    $app->get('/tbfechas_bloqueo_agenda/count', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $fecha_inicio   = $request->getQuery('fecha_inicio') ?? null;
            $fecha_termino  = $request->getQuery('fecha_termino') ?? null;
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $id_motivo_cancelacion_cita = $request->getQuery('id_motivo_cancelacion_cita') ?? null;
            $id_profesional             = $request->getQuery('id_profesional') ?? null;
            $usuario_solicitud          = $request->getQuery('usuario_solicitud');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            COUNT(1) as num_registros
                        FROM tbfechas_bloqueo_agenda a
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($fecha_inicio) || !empty($fecha_termino)){
                if (!empty($fecha_inicio) && !empty($fecha_termino)){
                    $phql   .= " AND ((a.fecha_inicio BETWEEN :fecha_inicio AND :fecha_termino) 
                                        OR  (a.fecha_termino BETWEEN :fecha_inicio AND :fecha_termino)
                                    ) ";
                    $values['fecha_inicio']     = $fecha_inicio;
                    $values['fecha_termino']    = $fecha_termino;
                } else {
                    if (!empty($fecha_inicio)){
                        $phql   .= " AND a.fecha_inicio >= :fecha_inicio ";
                        $values['fecha_inicio']     = $fecha_inicio;
                    }

                    if (!empty($fecha_termino)){
                        $phql   .= " AND a.fecha_termino <= :fecha_termino ";
                        $values['fecha_termino']    = $fecha_termino;
                    }
                }
            }

            if (!empty($id_motivo_cancelacion_cita)){
                $phql   .= " AND a.id_motivo_cancelacion_cita = :id_motivo_cancelacion_cita ";
                $values['id_motivo_cancelacion_cita']   = $id_motivo_cancelacion_cita;
            }

            if (!empty($id_profesional)){
                $phql   .= " AND a.id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            if (!empty($id_locacion)){
                $phql   .= ' AND id_locacion IS NULL ';
            } else {
                $phql   .= " AND (a.id_locacion IS NULL OR EXISTS (
                            SELECT 1 FROM ctusuarios_locaciones t1 
                            LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                            WHERE t2.clave = :usuario_solicitud AND a.id_locacion = t1.id_locacion 
                        ))";
                $values['usuario_solicitud']    = $usuario_solicitud;
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $num_registros  = 0;
            while ($row = $result->fetch()) {
                $num_registros  = $row['num_registros'];
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($num_registros);
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

    $app->get('/tbfechas_bloqueo_agenda/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $fecha_inicio   = $request->getQuery('fecha_inicio') ?? null;
            $fecha_termino  = $request->getQuery('fecha_termino') ?? null;
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $id_motivo_cancelacion_cita = $request->getQuery('id_motivo_cancelacion_cita') ?? null;
            $id_profesional             = $request->getQuery('id_profesional') ?? null;
            $usuario_solicitud          = $request->getQuery('usuario_solicitud');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            a.*,
                            COALESCE(b.nombre,'TODAS') as nombre_locacion,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_profesional,
                            d.clave as usuario_captura
                        FROM tbfechas_bloqueo_agenda a
                        LEFT JOIN ctlocaciones b ON a.id_locacion = b.id
                        LEFT JOIN ctprofesionales c ON a.id_profesional = c.id
                        LEFT JOIN ctusuarios d ON a.id_usuario_captura = d.id
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($fecha_inicio) || !empty($fecha_termino)){
                if (!empty($fecha_inicio) && !empty($fecha_termino)){
                    $phql   .= " AND ((a.fecha_inicio BETWEEN :fecha_inicio AND :fecha_termino) 
                                        OR  (a.fecha_termino BETWEEN :fecha_inicio AND :fecha_termino)
                                    ) ";
                    $values['fecha_inicio']     = $fecha_inicio;
                    $values['fecha_termino']    = $fecha_termino;
                } else {
                    if (!empty($fecha_inicio)){
                        $phql   .= " AND a.fecha_inicio >= :fecha_inicio ";
                        $values['fecha_inicio']     = $fecha_inicio;
                    }

                    if (!empty($fecha_termino)){
                        $phql   .= " AND a.fecha_termino <= :fecha_termino ";
                        $values['fecha_termino']    = $fecha_termino;
                    }
                }
            }

            if (!empty($id_motivo_cancelacion_cita)){
                $phql   .= " AND a.id_motivo_cancelacion_cita = :id_motivo_cancelacion_cita ";
                $values['id_motivo_cancelacion_cita']   = $id_motivo_cancelacion_cita;
            }

            if (!empty($id_profesional)){
                $phql   .= " AND a.id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            if (!empty($id_locacion)){
                $phql   .= ' AND id_locacion IS NULL ';
            } else {
                $phql   .= " AND (a.id_locacion IS NULL OR EXISTS (
                            SELECT 1 FROM ctusuarios_locaciones t1 
                            LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                            WHERE t2.clave = :usuario_solicitud AND a.id_locacion = t1.id_locacion 
                        ))";
                $values['usuario_solicitud']    = $usuario_solicitud;
            }
            

            $phql   .= ' ORDER BY a.fecha_inicio DESC ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data   = array();
            while ($row = $result->fetch()) {

                $row['label_fecha']     = FuncionesGlobales::formatearFecha($row['fecha_inicio']);
                if ($row['fecha_inicio'] != $row['fecha_termino']){
                    $row['label_fecha'] = FuncionesGlobales::formatearFecha($row['fecha_inicio']) .' - '.FuncionesGlobales::formatearFecha($row['fecha_termino']);
                }

                $row['label_tipo_bloqueo']  = $row['tipo_bloqueo'] == 1 ? 'Locación' : 'Profesional';

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

    $app->post('/tbfechas_bloqueo_agenda/save', function () use ($app, $db, $request) {
        $conexion = $db;
        try {

            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id_registro        = $request->getPost('id') ?? null;
            $accion             = $request->getPost('accion') ?? null;
            $id_locacion        = $request->getPost('id_locacion') ?? null;
            $tipo_bloqueo       = $request->getPost('tipo_bloqueo') ?? null;
            $fecha_inicio       = $request->getPost('fecha_inicio') ?? null;
            $fecha_termino      = $request->getPost('fecha_termino') ?? null;
            $id_motivo_cancelacion_cita = $request->getPost('id_motivo_cancelacion_cita') ?? null;
            $label_bloqueo              = $request->getPost('label_bloqueo') ?? null;
            $id_profesional             = $request->getPost('id_profesional') ?? null;
            $usuario_solicitud          = $request->getPost('usuario_solicitud');
            $id_usuario_captura         = null;

            if (empty($accion)){
                throw new Exception('Parámetro "Accion" vacío');
            }
           
            if ($accion == 'update' && (empty($id_registro) || !is_numeric($id_registro))){
                throw new Exception('Parámetro "Identificador" vacío');
            }
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($tipo_bloqueo)) {
                throw new Exception('Parámetro "Tipo bloqueo" vacío');
            }

            if (empty($fecha_inicio)) {
                throw new Exception('Parámetro "Fecha de inicio" vacío');
            }

            if (empty($fecha_termino)) {
                throw new Exception('Parámetro "Fecha de termino" vacío');
            }

            //  SE VALIDA QUE LA FECHA NO SEA MENOR A LA VARIABLE DE CONFIURACION
            $phql   = " SELECT 
                            CASE 
                                WHEN ((current_date - valor::INT) > :fecha_inicio::DATE) 
                                THEN 0 
                                ELSE 1 
                            END AS flag_fecha_valida  ,
                            (current_date - valor::INT) as fecha_valida,
                            valor
                        FROM ctvariables_sistema 
                        WHERE clave = 'dias_movimientos_citas_vencidas';";

            $result = $db->query($phql, [
                'fecha_inicio' => $fecha_inicio  // Ya en formato yyyy-mm-dd
            ]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                if ($row['flag_fecha_valida'] == 0){
                    throw new Exception('La fecha no puede ser menor a: '.FuncionesGlobales::formatearFecha($row['fecha_valida'],'d-m-Y'));
                }
            }

            //  SE BORRA EL REGISTRO ANTERIOR
            if ($accion == 'update'){
                $phql   = "DELETE FROM tbfechas_bloqueo_agenda WHERE id = :id";
                $result = $conexion->query($phql,array('id' => $id_registro));
            }

            $id_locacion    = is_numeric($id_locacion) && $id_locacion > 0 ? $id_locacion : null;

            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $id_usuario_captura = $row['id'];
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "   SELECT * FROM tbfechas_bloqueo_agenda a
                        WHERE ((fecha_inicio = :fecha_inicio AND fecha_termino = :fecha_termino) OR 
                             (:fecha_inicio BETWEEN fecha_inicio AND fecha_termino) OR
                             (:fecha_termino BETWEEN fecha_inicio AND fecha_termino))
                        ";

            $values = array(
                'fecha_inicio'  => $fecha_inicio,
                'fecha_termino' => $fecha_termino
            );

            if ($tipo_bloqueo == 1){

                $phql   .= " AND tipo_bloqueo = 1 AND ((id_locacion IS NOT NULL AND id_locacion = :id_locacion) OR (id_locacion IS NULL AND id_profesional IS NULL)) ";
                $values['id_locacion']  = $id_locacion;

                $result = $db->query($phql, $values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                while ($row = $result->fetch()) {
                    throw new Exception('Registro ya existente en el catalogo');
                }

                // INSERTAR NUEVO USUARIO
                $phql = "INSERT INTO tbfechas_bloqueo_agenda (
                                        id_locacion, 
                                        tipo_bloqueo,
                                        fecha_inicio,
                                        fecha_termino,
                                        label_bloqueo,
                                        id_usuario_captura
                                    ) 
                        VALUES (
                                    :id_locacion, 
                                    1,
                                    :fecha_inicio,
                                    :fecha_termino,
                                    :label_bloqueo,
                                    :id_usuario_captura
                                )";
        
                $values = [
                    'id_locacion'           => $id_locacion,
                    'fecha_inicio'          => $fecha_inicio,
                    'fecha_termino'         => $fecha_termino,
                    'label_bloqueo'         => $label_bloqueo,
                    'id_usuario_captura'    => $id_usuario_captura
                ];
        
                $result = $conexion->execute($phql, $values);

            }

            if ($tipo_bloqueo == 2){
                if (!is_numeric($id_profesional)){
                    throw new Exception('Parametro "Profesional" vacio');
                }

                //  SE VERIFICA SI NO HAY UN REGISTRO YA CREADO
                $phql   .= " AND tipo_bloqueo = 2 AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;

                $result = $db->query($phql, $values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                while ($row = $result->fetch()) {
                    throw new Exception('Registro ya existente en el catalogo para el profesional');
                }

                // INSERTAR NUEVO USUARIO
                $phql = "INSERT INTO tbfechas_bloqueo_agenda (
                                        tipo_bloqueo,
                                        fecha_inicio,
                                        fecha_termino,
                                        id_motivo_cancelacion_cita,
                                        label_bloqueo,
                                        id_profesional,
                                        id_usuario_captura
                                    ) 
                        VALUES (
                                    2,
                                    :fecha_inicio,
                                    :fecha_termino,
                                    :id_motivo_cancelacion_cita,
                                    :label_bloqueo,
                                    :id_profesional,
                                    :id_usuario_captura
                                )";
        
                $values = [
                    'fecha_inicio'          => $fecha_inicio,
                    'fecha_termino'         => $fecha_termino,
                    'id_motivo_cancelacion_cita'    => $id_motivo_cancelacion_cita,
                    'id_profesional'                => $id_profesional,
                    'label_bloqueo'                 => $label_bloqueo,
                    'id_usuario_captura'            => $id_usuario_captura
                ];
        
                $result = $conexion->execute($phql, $values);
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

    $app->post('/tbfechas_bloqueo_agenda/delete', function () use ($app, $db, $request) {
        try {
    
            // OBTENER DATOS JSON
            $id_registro        = $request->getPost('id') ?? null;

            if (empty($id_registro) || !is_numeric($id_registro)){
                throw new Exception('Parámetro "Identificador" vacío');
            }

            //  SE BORRA EL REGISTRO ANTERIOR
            $phql   = "DELETE FROM tbfechas_bloqueo_agenda WHERE id = :id";
            $result = $db->query($phql,array('id' => $id_registro));
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
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