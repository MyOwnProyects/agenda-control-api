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
    $app->get('/ctlocaciones/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM ctlocaciones a WHERE 1 = 1";
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

    $app->post('/ctlocaciones/create', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $clave      = $request->getPost('clave') ?? null;
            $nombre     = $request->getPost('nombre') ?? null;
            $direccion  = $request->getPost('direccion') ?? null;
            $telefono   = $request->getPost('telefono') ?? null;
            $celular    = $request->getPost('celular') ?? null;
            $lista_servicios    = $request->getPost('lista_servicios') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctlocaciones WHERE clave = :clave";
    
            $result = $db->query($phql, ['clave' => $clave]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO servicio
            $phql = "INSERT INTO ctlocaciones (
                                    clave,
                                    nombre,
                                    direccion,
                                    telefono,
                                    celular
                                ) 
                     VALUES (
                                :clave, 
                                :nombre, 
                                :direccion,
                                :telefono,
                                :celular
                            ) RETURNING id";
    
            $values = [
                'clave'     => $clave,
                'nombre'    => $nombre,
                'direccion' => $direccion,
                'telefono'  => $telefono,
                'celular'   => $celular
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
                throw new Exception('Error al crear la locación');
            }

            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctlocaciones_servicios (id_locacion, id_servicio,costo,duracion) 
                     VALUES (:id_locacion, :id_servicio, :costo, :duracion)";
    
            foreach ($lista_servicios as $servicio) {
                $conexion->query($phql, [
                    'id_locacion'   => $id,
                    'id_servicio'   => $servicio['id_servicio'],
                    'costo'         => $servicio['costo'],
                    'duracion'      => $servicio['duracion'],
                ]);
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

    $app->delete('/ctlocaciones/delete', function () use ($app, $db) {
        try{

            $id     = $this->request->getPost('id');

            $phql   = "DELETE FROM ctlocaciones WHERE id = :id";
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

    $app->put('/ctlocaciones/update', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id         = $request->getPost('id') ?? null;
            $clave      = $request->getPost('clave') ?? null;
            $nombre     = $request->getPost('nombre') ?? null;
            $direccion  = $request->getPost('direccion') ?? null;
            $telefono   = $request->getPost('telefono') ?? null;
            $celular    = $request->getPost('celular') ?? null;
            $lista_servicios    = $request->getPost('lista_servicios') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS

            if (empty($id)) {
                throw new Exception('Parámetro "ID" vacío');
            }

            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctlocaciones WHERE clave = :clave AND id <> :id";
    
            $result = $db->query($phql, ['clave' => $clave,'id' => $id]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO servicio
            $phql = "UPDATE ctlocaciones SET
                                    clave = :clave,
                                    nombre = :nombre,
                                    direccion = :direccion,
                                    telefono = :telefono,
                                    celular = :celular
                            WHERE id = :id ";
    
            $values = [
                'id'        => $id,
                'clave'     => $clave,
                'nombre'    => $nombre,
                'direccion' => $direccion,
                'telefono'  => $telefono,
                'celular'   => $celular
            ];
    
            $result = $conexion->execute($phql, $values);

            //  SE BORRAN LOS SERVICIOS ACTUALES
            $phql = "DELETE FROM ctlocaciones_servicios WHERE id_locacion = :id_locacion";
            $result = $conexion->execute($phql, ['id_locacion' => $id]);

            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctlocaciones_servicios (id_locacion, id_servicio,costo,duracion) 
                     VALUES (:id_locacion, :id_servicio, :costo, :duracion)";
    
            foreach ($lista_servicios as $servicio) {
                $conexion->query($phql, [
                    'id_locacion'   => $id,
                    'id_servicio'   => $servicio['id_servicio'],
                    'costo'         => $servicio['costo'],
                    'duracion'      => $servicio['duracion'],
                ]);
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
};
