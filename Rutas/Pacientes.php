<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    $app->get('/ctpacientes/count', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $primer_apellido    = $request->getQuery('primer_apellido');
            $segundo_apellido   = $request->getQuery('segundo_apellido');
            $nombre             = $request->getQuery('nombre');
            $id_servicio        = $request->getQuery('id_servicio');
            $id_locacion_registro   = $request->getQuery('id_locacion_registro');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            COUNT(1) as num_registros
                        FROM ctpacientes a
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

            if (!empty($primer_apellido)) {
                $phql           .= " AND lower(a.primer_apellido) ILIKE :primer_apellido";
                $values['primer_apellido']  = "%".FuncionesGlobales::ToLower($primer_apellido)."%";
            }

            if (!empty($segundo_apellido)) {
                $phql           .= " AND lower(a.segundo_apellido) ILIKE :segundo_apellido";
                $values['segundo_apellido'] = "%".FuncionesGlobales::ToLower($segundo_apellido)."%";
            }

            if (!empty($nombre)) {
                $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                $values['nombre']   = "%".FuncionesGlobales::ToLower($nombre)."%";
            }

            if (!empty($id_servicio)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctpacientes_servicios t1
                                WHERE t1.id_servicio = :id_servicio AND a.id = t1.id_profesional
                            )";

                $values['id_servicio']  = $id_servicio;
            }

            if (is_numeric($id_locacion_registro)){
                $phql           .= " AND a.id_locacion_registro = :id_locacion_registro";
                $values['id_locacion_registro']   = $id_locacion_registro;
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

    // Ruta principal para obtener todos los registros
    $app->get('/ctpacientes/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $primer_apellido    = $request->getQuery('primer_apellido');
            $segundo_apellido   = $request->getQuery('segundo_apellido');
            $nombre             = $request->getQuery('nombre');
            $id_servicio        = $request->getQuery('id_servicio');
            $id_locacion_registro   = $request->getQuery('id_locacion_registro');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            COALESCE(b.num_servicios,0) as num_servicios,
                            c.nombre as locacion_registro
                        FROM ctpacientes a 
                        LEFT JOIN (
                            SELECT t1.id_paciente, COUNT(1) as num_servicios
                            FROM ctpacientes_servicios t1
                            GROUP BY t1.id_paciente
                        ) b ON a.id = b.id_paciente
                        LEFT JOIN ctlocaciones c ON a.id_locacion_registro = c.id
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

            if (!empty($primer_apellido)) {
                $phql           .= " AND lower(a.primer_apellido) ILIKE :primer_apellido";
                $values['primer_apellido']  = "%".FuncionesGlobales::ToLower($primer_apellido)."%";
            }

            if (!empty($segundo_apellido)) {
                $phql           .= " AND lower(a.segundo_apellido) ILIKE :segundo_apellido";
                $values['segundo_apellido'] = "%".FuncionesGlobales::ToLower($segundo_apellido)."%";
            }

            if (!empty($nombre)) {
                $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                $values['clave'] = "%".FuncionesGlobales::ToLower($nombre)."%";
            }

            if (!empty($id_servicio)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctpacientes_servicios t1
                                WHERE t1.id_servicio = :id_servicio AND a.id = t1.id_profesional
                            )";

                $values['id_servicio']  = $id_servicio;
            }

            if (is_numeric($id_locacion_registro)){
                $phql           .= " AND a.id_locacion_registro = :id_locacion_registro";
                $values['id_locacion_registro']   = $id_locacion_registro;
            }

            $phql   .= ' ORDER BY a.clave,a.primer_apellido,a.segundo_apellido,a.nombre ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVO' : 'INACTIVO';
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
    
    $app->post('/ctpacientes/create', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $primer_apellido    = $request->getPost('primer_apellido') ?? null;
            $segundo_apellido   = $request->getPost('segundo_apellido') ?? null;
            $nombre             = $request->getPost('nombre') ?? null;
            $celular            = $request->getPost('celular') ?? null;
            $id_locacion_registro   = $request->getPost('id_locacion_registro') ?? null;
           
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($primer_apellido)) {
                throw new Exception('Parámetro "Primer apellido" vacío');
            }

            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (empty($celular)) {
                throw new Exception('Parámetro "Celular" vacío');
            }

            if (empty($id_locacion_registro)) {
                throw new Exception('Parámetro "Locacion" vacío');
            }

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctpacientes WHERE clave = :clave";
    
            $result = $db->query($phql, ['clave' => $celular]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "INSERT INTO ctpacientes (
                                    clave, 
                                    primer_apellido,
                                    segundo_apellido,
                                    nombre,
                                    celular,
                                    id_locacion_registro
                                ) 
                     VALUES (
                                :clave, 
                                :primer_apellido,
                                :segundo_apellido,
                                :nombre,
                                :celular,
                                :id_locacion_registro
                            ) RETURNING id";
    
            $values = [
                'clave'                 => $celular,
                'primer_apellido'       => $primer_apellido,
                'segundo_apellido'      => $segundo_apellido,
                'nombre'                => $nombre,
                'celular'               => $celular,
                'id_locacion_registro'  => $id_locacion_registro,
                
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id_paciente    = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_paciente    = $data['id'];
                }
            }
    
            if (!$id_paciente) {
                throw new Exception('Error al crear el registro');
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