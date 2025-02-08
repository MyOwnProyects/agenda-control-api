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
    $app->get('/ctservicios/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM ctservicios a WHERE 1 = 1";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            $phql   .= ' ORDER BY a.clave,a.nombre ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['duracion_minutos']    = $row['duracion'] / 60;
                $data[]                     = $row;
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

    $app->post('/ctservicios/create', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $clave          = $request->getPost('clave') ?? null;
            $nombre         = $request->getPost('nombre') ?? null;
            $descripcion    = $request->getPost('descripcion') ?? null;
            $costo          = $request->getPost('costo') ?? null;
            $duracion       = $request->getPost('duracion') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (empty($costo)) {
                throw new Exception('Parámetro "Costo" vacío');
            }

            if (empty($duracion)) {
                throw new Exception('Parámetro "Duracion" vacío');
            }

            if (!FuncionesGlobales::validarCantidadMonetaria($costo)){
                throw new Exception('Costo no valido');
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctservicios WHERE clave = :clave";
    
            $result = $db->query($phql, ['clave' => $clave]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO servicio
            $phql = "INSERT INTO ctservicios (
                                    clave,
                                    nombre,
                                    descripcion,
                                    costo,
                                    duracion
                                ) 
                     VALUES (
                                :clave, 
                                :nombre, 
                                :descripcion,
                                :costo,
                                :duracion
                            ) RETURNING id";
    
            $values = [
                'clave'         => $clave,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion,
                'costo'         => FuncionesGlobales::formatearDecimal($costo),
                'duracion'      => $duracion * 60
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id= $data['id'];
                }
            }
    
            if (!$id) {
                throw new Exception('Error al crear el servicio');
            }
    
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

    $app->delete('/ctservicios/delete', function () use ($app, $db) {
        try{

            $id     = $this->request->getPost('id');

            $phql   = "DELETE FROM ctservicios WHERE id = :id";
            $result = $db->execute($phql, array('id' => $id));

            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;

        }catch (\Exception $e) {
            return (new Response())->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(400, 'Bad Request');
        }
    });

    $app->put('/ctservicios/update', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id             = $request->getPost('id') ?? null;
            $clave          = $request->getPost('clave') ?? null;
            $nombre         = $request->getPost('nombre') ?? null;
            $descripcion    = $request->getPost('descripcion') ?? null;
            $costo          = $request->getPost('costo') ?? null;
            $duracion       = $request->getPost('duracion') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (empty($costo)) {
                throw new Exception('Parámetro "Costo" vacío');
            }

            if (empty($duracion)) {
                throw new Exception('Parámetro "Duracion" vacío');
            }

            if (!FuncionesGlobales::validarCantidadMonetaria($costo)){
                throw new Exception('Costo no valido');
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctservicios WHERE clave = :clave AND id <> :id";
    
            $result = $db->query($phql, ['clave' => $clave, 'id' => $id]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO SERVICO
            $phql = "UPDATE ctservicios SET
                                    clave = :clave,
                                    nombre = :nombre,
                                    descripcion = :descripcion,
                                    costo = :costo,
                                    duracion = :duracion
                                WHERE id = :id";
    
            $values = [
                'id'            => $id,
                'clave'         => $clave,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion,
                'costo'         => FuncionesGlobales::formatearDecimal($costo),
                'duracion'      => $duracion * 60
            ];
    
            $result = $conexion->execute($phql, $values);

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
};
