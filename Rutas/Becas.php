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
    $app->get('/becas/count', function () use ($app,$db,$request) {
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
                        FROM ctbecas a 
                        WHERE 1 = 1 AND a.visible = 1 ";
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
    $app->get('/becas/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM ctbecas a WHERE 1 = 1 AND a.visible = 1";
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
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVA' : 'INACTIVA';
                $row['label_tipo_beca'] = $row['tipo_beca'] == 1 ? 'IMPORTE' : 'PORCENTAJE';
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

    $app->post('/becas/save', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id_beca        = $request->getPost('id') ?? null;
            $clave          = $request->getPost('clave') ?? null;
            $nombre         = $request->getPost('nombre') ?? null;
            $descripcion    = $request->getPost('descripcion') ?? null;
            $tipo_beca      = $request->getPost('tipo_beca') ?? null;
            $indefinida     = $request->getPost('indefinida');
            $usuario_solicitud      = $request->getPost('usuario_solicitud');
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (!is_numeric($tipo_beca)) {
                throw new Exception('Parámetro "tipo beca" No valido');
            }

            if (!is_numeric($indefinida)) {
                throw new Exception('Parámetro "Indefinida" vacío');
            }

            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave_usuario";
            $result = $db->query($phql,array('clave_usuario' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $id_usuario_solicitud   = null;
            if ($result){
                while($data = $result->fetch()){
                    $id_usuario_solicitud   = $data['id'];
                }
            }

            //  SE DESACTIVA LA BECA EN CASO DE SER EDICION
            if (is_numeric($id_beca)){
                $phql   = "UPDATE ctbecas SET estatus = 0 , visible = 0 WHERE id = :id_beca";
                $result = $conexion->query($phql,array('id_beca' => $id_beca));
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctbecas WHERE clave = :clave AND visible = 1 ";
    
            $result = $db->query($phql, array('clave' => $clave));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO servicio
            $phql = "INSERT INTO ctbecas (
                                    clave,
                                    nombre,
                                    descripcion,
                                    id_usuario_captura,
                                    tipo_beca,
                                    indefinida
                                ) 
                     VALUES (
                                :clave, 
                                :nombre, 
                                :descripcion,
                                :id_usuario_captura,
                                :tipo_beca,
                                :indefinida
                            ) RETURNING id";
    
            $values = [
                'clave'         => $clave,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion,
                'id_usuario_captura'    => $id_usuario_solicitud,
                'tipo_beca'             => $tipo_beca,
                'indefinida'            => $indefinida
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id = $data['id'];
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

    $app->put('/becas/change_status', function () use ($app, $db,$request) {
        try{

            $id             = $request->getPost('id');
            $status_actual  = $request->getPost('status_actual');
            $nuevo_status   = $request->getPost('nuevo_status');

            //  ESTATUS ACTUAl
            $phql   = "SELECT * FROM ctbecas WHERE id = :id";
            $result = $db->query($phql, array('id' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                if ($row['estatus'] != $status_actual){
                    throw new Exception('El estatus del servicio ya no es el indicado, favor de refrescar la vista.');
                }
            }

            $phql   = "UPDATE ctbecas SET estatus = :nuevo_status WHERE id = :id";
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

     $app->delete('/becas/delete', function () use ($app, $db,$request) {
        try{
            $id     = $request->getPost('id');

            $phql   = "UPDATE ctbecas SET estatus = 0, visible = 0 WHERE id = :id";
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

    //  ASIGNACION DE BECAS
    // Ruta principal para obtener todos los usuarios
    $app->get('/paciente_becas/count', function () use ($app,$db,$request) {
        try{

            $id             = $request->getQuery('id');
            $id_paciente    = $request->getQuery('clave');
        
            // Definir el query SQL
            $phql   = "SELECT 
                            COUNT(1) as num_registros
                        FROM tbpaciente_becas a 
                        WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (is_numeric($id_paciente)){
                $phql                   .= " AND a.id_paciente = :id_paciente";
                $values['id_paciente']  = $id_paciente;
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
    $app->get('/paciente_becas/show', function () use ($app,$db,$request) {
        try{

            $id             = $request->getQuery('id');
            $id_paciente    = $request->getQuery('clave');
        
            // Definir el query SQL
            $phql   = " SELECT 
                            a.*,
                            (b.clave|| ' - ' || b.nombre) as beca,
                            COALESCE(c.monto_usado,0) as monto_usado
                        FROM tbpaciente_becas a 
                        LEFT JOIN ctbecas b ON a.id_beca = b.id
                        LEFT JOIN LATERAL (
                            SELECT SUM(t1.monto) AS monto_usado 
                            FROM tbabonos_movimientos t1
                            LEFT JOIN tbabonos t2 ON t1.id_abono = t2.id
                            WHERE a.id = t2.id_paciente_beca 
                            AND (t1.estatus = 1 OR (t1.estatus = 0 AND t1.tipo_cancelacion = 2))
                        ) c ON TRUE
                        WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (is_numeric($id_paciente)){
                $phql                   .= "AND a.id_paciente = :id_paciente";
                $values['id_paciente']  = $id_paciente;
            }
            
            $phql   .= ' ORDER BY a.fecha_captura DESC ';

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['label_estatus']           = $row['estatus'] == 1 ? 'ACTIVA' : 'INACTIVA';
                $row['label_tipo_beca']         = $row['tipo_beca'] == 1 ? 'IMPORTE' : 'PORCENTAJE';
                $row_['label_fecha_captura']    = FuncionesGlobales::formatearFecha($row['label_fecha_captura'],'d/m/Y H:i');
                $data[]                         = $row;
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
};
