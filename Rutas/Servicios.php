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
    $app->get('/ctservicios/count', function () use ($app,$db,$request) {
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
                        FROM ctservicios a 
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
    $app->get('/ctservicios/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            
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

            if (!empty($clave) && (empty($accion) || $accion != 'login')) {
                $phql           .= " AND lower(a.clave) ILIKE :clave";
                $values['clave'] = "%".FuncionesGlobales::ToLower($clave)."%";
            }

            if (!empty($nombre)) {
                $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                $values['nombre'] = "%".FuncionesGlobales::ToLower($nombre)."%";
            }

            if (!empty($id_locacion)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctlocaciones_servicios t1
                                WHERE t1.id_locacion = :id_locacion AND a.id = t1.id_servicio
                            )";
                $values['id_locacion']  = $id_locacion;
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
                $row['duracion_minutos']    = $row['duracion'] / 60;
                $row['label_activo']        = $row['activo'] == 1 ? 'SI' : 'NO';
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
            $codigo_color   = $request->getPost('codigo_color') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (!is_numeric($costo)) {
                throw new Exception('Parámetro "Costo" No valido');
            }

            if (empty($duracion)) {
                throw new Exception('Parámetro "Duracion" vacío');
            }

            if (empty($duracion)) {
                throw new Exception('Parámetro "codigo de color" vacío');
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
                                    duracion,
                                    codigo_color
                                ) 
                     VALUES (
                                :clave, 
                                :nombre, 
                                :descripcion,
                                :costo,
                                :duracion,
                                :codigo_color
                            ) RETURNING id";
    
            $values = [
                'clave'         => $clave,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion,
                'costo'         => FuncionesGlobales::formatearDecimal($costo),
                'duracion'      => $duracion * 60,
                'codigo_color'  => $codigo_color
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

    $app->put('/ctservicios/change_status', function () use ($app, $db,$request) {
        try{

            $id             = $request->getPost('id');
            $status_actual  = $request->getPost('status_actual');
            $nuevo_status   = $request->getPost('nuevo_status');

            //  ESTATUS ACTUAl
            $phql   = "SELECT * FROM ctservicios WHERE id = :id";
            $result = $db->query($phql, array('id' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                if ($row['activo'] != $status_actual){
                    throw new Exception('El estatus del servicio ya no es el indicado, favor de refrescar la vista.');
                }
            }

            $phql   = "UPDATE ctservicios SET activo = :nuevo_status WHERE id = :id";
            $result = $db->execute($phql, array('id' => $id,'nuevo_status' => $nuevo_status));

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

     $app->delete('/ctservicios/delete', function () use ($app, $db,$request) {
        try{

            $id     = $request->getPost('id');

            //  SE BUSCA SI EXISTE UNA CITA AGENDADA CON ESTE SERVICIO
            $phql   = "SELECT 1 FROM tbagenda_citas_servicios WHERE id_servicio = :id";
            $result = $db->query($phql, array('id' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('EL servicio no puede ser borrado ya que afectaria registros historicos.');
            }

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
            $codigo_color   = $request->getPost('codigo_color') ?? null;
    
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

            if (empty($codigo_color)){
                throw new Exception('Parámetro "Codigo de color" vacío');
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
                                    duracion = :duracion,
                                    codigo_color = :codigo_color
                                WHERE id = :id";
    
            $values = [
                'id'            => $id,
                'clave'         => $clave,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion,
                'costo'         => FuncionesGlobales::formatearDecimal($costo),
                'duracion'      => $duracion * 60,
                'codigo_color'  => $codigo_color
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
