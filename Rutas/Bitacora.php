<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    $app->post('/tbbitacora_movimientos/create', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $clave_usuario  = $request->getPost('usuario_solicitud');
            $controlador    = $request->getPost('controlador');
            $accion         = $request->getPost('accion');
            $ip_cliente     = $request->getPost('ip_cliente');
            $mensaje        = $request->getPost('mensaje');
            $data           = $request->getPost('data');
            $bfp            = $request->getPost('bfp') ?? null;
            $navegador      = $request->getPost('navegador') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave_usuario)) {
                throw new Exception('Parámetro "clave usuario" vacío');
            }
    
            if (empty($controlador)) {
                throw new Exception('Parámetro "controlador" vacío');
            }
            
            if (empty($accion)) {
                throw new Exception('Parámetro "accion" vacío');
            }

            if (empty($mensaje)) {
                throw new Exception('Parámetro "mensaje" vacío');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT 1 FROM ctbitacora_acciones WHERE controlador = :controlador AND accion = :accion";
    
            $result = $db->query($phql, array(
                'controlador'   => $controlador,
                'accion'        => $accion
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $flag_exist = false;
            while ($row = $result->fetch()) {
                $flag_exist = true;
            }

            if (!$flag_exist){
                $phql   = "INSERT INTO ctbitacora_acciones (controlador,accion) VALUES (:controlador,:accion)";

                $values = array(
                    'controlador'   => $controlador,
                    'accion'        => $accion
                );

                $result = $conexion->execute($phql, $values);
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "INSERT INTO tbbitacora_movimientos (clave_usuario,controlador,accion,ip_cliente,mensaje,data,bfp,navegador) 
                     VALUES (:clave_usuario,:controlador,:accion,:ip_cliente,:mensaje,:data,:bfp,:navegador)";
    
            $values = [
                'clave_usuario' => $clave_usuario,
                'controlador'   => $controlador,
                'accion'        => $accion,
                'ip_cliente'    => $ip_cliente,
                'mensaje'       => $mensaje,
                'data'          => empty($data) ? null : json_encode($data),
                'bfp'           => $bfp,
                'navegador'     => $navegador
            ];
    
            $result = $conexion->query($phql, $values);
    
            $conexion->commit();
    
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
    $app->get('/tbbitacora_movimientos/count', function () use ($app,$db,$request) {
        try{
            $controlador    = $request->getQuery('controlador');
            $accion         = $request->getQuery('accion');
            $clave_usuario  = $request->getQuery('clave_usuario');
            $fecha_inicio   = $request->getQuery('fecha_inicio');
            $fecha_limite   = $request->getQuery('fecha_limite');
            $mensaje        = $request->getQuery('mensaje');
        
            // Definir el query SQL
            $phql   = "SELECT COUNT(*) as num_rows FROM tbbitacora_movimientos WHERE 1 = 1 ";
            $values = array();
    
            if (!empty($clave_usuario)){
                $phql           .= " AND clave_usuario = :clave_usuario";
                $values['clave_usuario']    = $clave_usuario;
            }

            if (!empty($controlador)){
                $phql           .= " AND controlador = :controlador";
                $values['controlador']  = $controlador;
            }

            if (!empty($accion)){
                $phql           .= " AND accion = :accion";
                $values['accion']   = $accion;
            }

            if (empty($fecha_inicio) && empty($fecha_termino)){
                throw new Exception('Ingrese un rango de fechas');
            } else {
                $phql   .= " AND fecha_hora::DATE BETWEEN :fecha_inicio AND :fecha_limite ";
                $values['fecha_inicio'] = $fecha_inicio;
                $values['fecha_limite'] = $fecha_limite;
            }

            if (!empty($mensaje)){
                $phql   .= " AND lower(mensaje) ILIKE :mensaje ";
                $values['mensaje'] = "%".FuncionesGlobales::ToLower($mensaje)."%";
            }
    
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
    $app->get('/tbbitacora_movimientos/show', function () use ($app,$db,$request) {
        try{
            $controlador    = $request->getQuery('controlador');
            $accion         = $request->getQuery('accion');
            $clave_usuario  = $request->getQuery('clave_usuario');
            $fecha_inicio   = $request->getQuery('fecha_inicio');
            $fecha_limite   = $request->getQuery('fecha_limite');
            $mensaje        = $request->getQuery('mensaje');
        
            // Definir el query SQL
            $phql   = "SELECT * FROM tbbitacora_movimientos WHERE 1 = 1 ";
            $values = array();
    
            if (!empty($clave_usuario)){
                $phql           .= " AND clave_usuario = :clave_usuario";
                $values['clave_usuario']    = $clave_usuario;
            }

            if (!empty($controlador)){
                $phql           .= " AND controlador = :controlador";
                $values['controlador']  = $controlador;
            }

            if (!empty($accion)){
                $phql           .= " AND accion = :accion";
                $values['accion']   = $accion;
            }

            if (empty($fecha_inicio) && empty($fecha_termino)){
                //throw new Exception('Ingrese un rango de fechas');
            } else {
                $phql   .= " AND fecha_hora::DATE BETWEEN :fecha_inicio AND :fecha_limite ";
                $values['fecha_inicio'] = $fecha_inicio;
                $values['fecha_limite'] = $fecha_limite;
            }

            if (!empty($mensaje)){
                $phql   .= " AND lower(mensaje) ILIKE :mensaje ";
                $values['mensaje'] = "%".FuncionesGlobales::ToLower($mensaje)."%";
            }

            $phql   .= " ORDER BY fecha_hora DESC";

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

    // Ruta principal para obtener todos los usuarios
    $app->get('/ctbitacora_acciones/show', function () use ($app,$db,$request) {
        try{
            $controlador            = $request->getQuery('controlador');
        
            // Definir el query SQL
            $phql   = "SELECT controlador,accion FROM ctbitacora_acciones WHERE 1 = 1 ";
            $values = array();

            $phql   .= " ORDER BY controlador,accion DESC";
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = array(
                'lista_controladores'   => array(),
                'todos_registros'       => array()
            );
            while ($row = $result->fetch()) {
                if (!in_array($row['controlador'],$data['lista_controladores'])){
                    $data['lista_controladores'][]  = $row['controlador'];
                }

                $data['todos_registros'][$row['controlador']][] = $row;
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