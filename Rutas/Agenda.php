<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    // Ruta principal para obtener todos los usuarios
    $app->get('/tbagenda_citas/count', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT 
                            COUNT(1) as num_registros
                        FROM tbagenda_citas a 
                        WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (!empty($clave) && (empty($accion) || $accion != 'login')) {
                $phql           .= " AND lower(a.clave) ILIKE :clave";
                $values['clave'] = "%".FuncionesGlobales::ToLower($clave)."%";
            }

            if (!empty($accion) && $accion == 'login'){
                $phql               .= " AND a.clave = :username";
                $values['username'] = $username;
            }

            if (!empty($nombre)) {
                $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                $values['nombre'] = "%".FuncionesGlobales::ToLower($nombre)."%";
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
            $response->setContent($e->getMessage());
            $response->setStatusCode(400, 'Created');
            return $response;
        }
        
    });

    // Ruta principal para obtener todos los registros
    $app->get('/tbagenda_citas/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM tbagenda_citas a WHERE 1 = 1";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (!empty($clave) && (empty($accion) || $accion != 'login')) {
                $phql           .= " AND lower(a.clave) ILIKE :clave";
                $values['clave'] = "%".FuncionesGlobales::ToLower($clave)."%";
            }

            if (!empty($nombre)) {
                $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                $values['nombre'] = "%".FuncionesGlobales::ToLower($nombre)."%";
            }

            if ($request->hasQuery('onlyallowed')){
                $phql   .= ' AND EXISTS (
                                SELECT 1 FROM ctusuarios_locaciones t1 
                                LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                                WHERE a.id = t1.id_locacion AND t2.clave = :usuario_solicitud
                            )';
                $values['usuario_solicitud']    = $usuario_solicitud;
            }

            $phql   .= ' ORDER BY a.clave,a.nombre ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['servicios']   = array();
                if ($request->hasQuery('get_servicios')){
                    $phql   = "SELECT a.id_servicio,b.clave,b.nombre,a.costo,a.duracion FROM ctlocaciones_servicios a 
                                LEFT JOIN ctservicios b ON a.id_servicio = b.id
                                WHERE a.id_locacion = :id_locacion
                                ORDER BY b.clave,b.nombre";
                    $result_servicios   = $db->query($phql,array('id_locacion' => $row['id']));
                    $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_servicios){
                        while($data_servicios = $result_servicios->fetch()){
                            $data_servicios['duracion_minutos'] = $data_servicios['duracion'] / 60;
                            $row['servicios'][] = $data_servicios;
                        }
                    }
                }

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

    $app->get('/tbapertura_agenda/show', function () use ($app,$db,$request) {
        try{

            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $zona_horario   = date_default_timezone_get();

            //  SE BUSCA SI EXISTE UN REGISTRO DE APERTURA DE AGENDA
            $phql   = "SELECT t2.fecha_limite as last_fecha_limite,t2.has_record,current_date as fecha_actual from (
                        SELECT fecha_limite,has_record from (
                        SELECT fecha_limite::DATE, 1 as has_record FROM tbapertura_agenda 
                        WHERE id_locacion = :id_locacion ORDER BY fecha_limite DESC LIMIT 1
                        )t1
                        UNION ALL
                        SELECT (current_date)::DATE as fecha_limite, 0 as has_record
                        ) t2 order by has_record desc limit 1";

            $values = array(
                'id_locacion'   => $id_locacion
            );

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_return = array();
            if ($result){
                while($data = $result->fetch()){
                    $arr_return = $data;
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

    $app->post('/tbapertura_agenda/save', function () use ($app,$db,$request) {
        try{

            $id_locacion    = $request->getPost('id_locacion') ?? null;
            $fecha_inicio   = $request->getPost('fecha_inicio') ?? null;
            $fecha_termino  = $request->getPost('fecha_termino') ?? null;
            $clave_usuario  = $request->getPost('usuario_solicitud') ?? null;

            try{
                //  SE AGENDAN LAS CITAS DEL PACIENTE
                $phql   = "SELECT * FROM fn_programar_citas(null,:id_locacion,:fecha_inicio,:fecha_termino,:clave_usuario);";
                $values = array(
                    'id_locacion'   => $id_locacion,
                    'fecha_inicio'  => $fecha_inicio,
                    'fecha_termino' => $fecha_termino,
                    'clave_usuario' => $clave_usuario
                );

                $result_citas   = $db->execute($phql,$values);
            }catch(\Exception $err){
                throw new \Exception(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
            }
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
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
