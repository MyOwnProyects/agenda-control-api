<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

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
            $phql = "INSERT INTO tbbitacora_movimientos (clave_usuario,controlador,accion,ip_cliente,mensaje,data) 
                     VALUES (:clave_usuario,:controlador,:accion,:ip_cliente,:mensaje,:data)";
    
            $values = [
                'clave_usuario' => $clave_usuario,
                'controlador'   => $controlador,
                'accion'        => $accion,
                'ip_cliente'    => $ip_cliente,
                'mensaje'       => $mensaje,
                'data'          => empty($data) ? null : json_encode($data)
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
    $app->get('/tbbitacora_movimientos/show', function () use ($app,$db,$request) {
        try{
            $controlador    = $request->getQuery('controlador');
            $accion         = $request->getQuery('accion');
            $clave_usuario  = $request->getQuery('clave_usuario');
        
            // Definir el query SQL
            $phql   = "SELECT * FROM tbbitacora_movimientos WHERE 1 = 1 ";
            $values = array();
    
            if (!empty($clave_usuario)){
                $phql           .= " AND a.clave_usuario = :clave_usuario";
                $values['clave_usuario']    = $clave_usuario;
            }

            if (!empty($controlador)){
                $phql           .= " AND a.controlador = :controlador";
                $values['controlador']  = $controlador;
            }

            if (!empty($accion)){
                $phql           .= " AND a.accion = :accion";
                $values['accion']   = $accion;
            }

            $phql   .= " ORDER BY fecha_hora DESC";
    
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