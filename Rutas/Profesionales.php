<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    $app->get('/ctprofesionales/count', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $id_servicio    = $request->getQuery('id_servicio');
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            COUNT(1) as num_registros
                        FROM ctprofesionales a 
                        LEFT JOIN ctusuarios b ON a.id = b.id_profesional
                        LEFT JOIN cttipo_usuarios c ON b.id_tipo_usuario = c.id
                        WHERE 1 = 1";
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
                $values['clave'] = "%".FuncionesGlobales::ToLower($nombre)."%";
            }

            if (!empty($id_servicio)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                                WHERE t1.id_servicio = :id_servicio AND a.id = t1.id_profesional
                            )";

                $values['id_servicio']  = $id_servicio;
            }

            if (!empty($id_locacion)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                                WHERE t1.id_locacion = :id_locacion AND a.id = t1.id_profesional
                            )";

                $values['id_locacion']  = $id_locacion;
            }

            $phql   .= " AND EXISTS (
                SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                LEFT JOIN ctusuarios_locaciones t2 ON t1.id_locacion = t2.id_locacion 
                LEFT JOIN ctusuarios t3 ON t2.id_usuario = t3.id
                WHERE t3.clave = :usuario_solicitud AND  t1.id_profesional = a.id
            )";
            $values['usuario_solicitud']    = $usuario_solicitud;
    
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
    $app->get('/ctprofesionales/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $id_servicio    = $request->getQuery('id_servicio');
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            c.clave as clave_tipo_usuario, 
                            c.nombre as nombre_tipo_usuario,
                            a.estatus
                        FROM ctprofesionales a 
                        LEFT JOIN ctusuarios b ON a.id = b.id_profesional
                        LEFT JOIN cttipo_usuarios c ON b.id_tipo_usuario = c.id
                        WHERE 1 = 1";
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

            if (!empty($id_servicio)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                                WHERE t1.id_servicio = :id_servicio AND a.id = t1.id_profesional
                            )";

                $values['id_servicio']  = $id_servicio;
            }

            if (!empty($id_locacion)){
                $phql   .= " AND EXISTS (
                                SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                                WHERE t1.id_locacion = :id_locacion AND a.id = t1.id_profesional
                            )";

                $values['id_locacion']  = $id_locacion;
            }

            $phql   .= " AND EXISTS (
                            SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                            LEFT JOIN ctusuarios_locaciones t2 ON t1.id_locacion = t2.id_locacion 
                            LEFT JOIN ctusuarios t3 ON t2.id_usuario = t3.id
                            WHERE t3.clave = :usuario_solicitud AND  t1.id_profesional = a.id
                        )";
            $values['usuario_solicitud']    = $usuario_solicitud;

            $phql   .= ' ORDER BY a.clave,a.nombre ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['label_estatus_usuario']   = 'S/A';

                if ($row['estatus'] === 1){
                    $row['label_estatus_usuario']   = 'ACTIVO';
                }

                if ($row['estatus'] === 0){
                    $row['label_estatus_usuario']   = 'INACTIVO';
                }

                $aqui   = 1;

                if ($request->hasQuery('get_locaciones') || $request->hasQuery('location_allower')){
                    $row['locaciones_servicios']    = array();
                    $phql   = "SELECT * FROM ctprofesionales_locaciones_servicios a
                                WHERE a.id_profesional = :id_profesional";

                    $values = array('id_profesional' => $id);

                    if ($request->hasQuery('location_allower')){
                        $phql   .= ' AND EXISTS (
                                        SELECT 1 FROM ctusuarios_locaciones t1 
                                        LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                                        WHERE a.id_locacion = t1.id_locacion AND t2.clave = :usuario_solicitud
                                    )';
                        $values['usuario_solicitud']    = $usuario_solicitud;
                    }
                    
                    $result_locaciones  = $db->query($phql,$values);
                    $result_locaciones->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_locaciones){
                        while($data_locaciones = $result_locaciones->fetch()){
                            $row['locaciones_servicios'][$data_locaciones['id_locacion']][$data_locaciones['id_servicio']] = $data_locaciones;
                        }
                    }
                }

                if ($request->hasQuery('only_locations')){
                    $row['locaciones']  = array();
                    $phql   = "SELECT DISTINCT b.* FROM ctprofesionales_locaciones_servicios a
                                LEFT JOIN ctlocaciones b ON a.id_locacion = b.id
                                WHERE id_profesional = :id_profesional";
                    $values = array('id_profesional' => $id);

                    if ($request->hasQuery('location_allower')){
                        $phql   .= ' AND EXISTS (
                                        SELECT 1 FROM ctusuarios_locaciones t1 
                                        LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                                        WHERE a.id_locacion = t1.id_locacion AND t2.clave = :usuario_solicitud
                                    )';
                        $values['usuario_solicitud']    = $usuario_solicitud;
                    }
                    
                    $result_locaciones  = $db->query($phql,$values);
                    $result_locaciones->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_locaciones){
                        while($data_locaciones = $result_locaciones->fetch()){
                            $row['locaciones'][]    = $data_locaciones;
                        }
                    }
                }

                if ($request->hasQuery('get_servicios')){
                    $phql   = " SELECT 
                                    t2.id as id_servicio,
                                    t2.clave,
                                    t2.nombre,
                                    t3.duracion,
                                    t3.costo
                                FROM ctprofesionales_locaciones_servicios t1
                                LEFT JOIN ctservicios t2 ON t1.id_servicio = t2.id
                                LEFT JOIN ctlocaciones_servicios t3 ON t1.id_locacion = t3.id_locacion AND t1.id_servicio = t3.id_servicio
                                WHERE t1.id_locacion = :id_locacion AND t1.id_profesional = :id_profesional
                                ORDER BY t2.clave ASC";
                    
                    $values = array(
                        'id_locacion'       => $id_locacion,
                        'id_profesional'    => $row['id']
                    );

                    
                    $result_servicios   = $db->query($phql,$values);
                    $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_servicios){
                        while($data_servicios   = $result_servicios->fetch()){
                            $data_servicios['duracion_minutos'] = $data_servicios['duracion'] / 60;
                            $row['locacion_servicios'][]        = $data_servicios;
                        }
                    }
                    
                }

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

    $app->post('/ctprofesionales/create', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $contrasena         = $request->getPost('contrasena') ?? null;
            $primer_apellido    = $request->getPost('primer_apellido') ?? null;
            $segundo_apellido   = $request->getPost('segundo_apellido') ?? null;
            $nombre             = $request->getPost('nombre') ?? null;
            $celular            = $request->getPost('celular') ?? null;
            $correo_electronico = $request->getPost('correo_electronico') ?? null;
            $direccion          = $request->getPost('direccion') ?? null;
            $titulo_profesional = $request->getPost('titulo_profesional') ?? null;
            $cedula_profesional = $request->getPost('cedula_profesional') ?? null;
            $lista_locaciones   = $request->getPost('lista_locaciones') ?? null;

            if (empty($celular)) {
                throw new Exception('Parámetro "Celular" vacío');
            }

            //  SE VERIFICA QUE NO EXISTA UN USUARIO CON ESTE NUMERO, DE SER ASI
            //  SE OBTIENEN LOS DATOS DE ESTE.
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql, ['clave' => $celular]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $flag_exist_user    = false;
            $id_usuario         = null;
            while ($data = $result->fetch()) {

                if (is_numeric($data['id_profesional'])){
                    throw new Exception('Ya existe un usuario creado con registro de profesional asignado');
                }

                $primer_apellido    = $data['primer_apellido'];
                $segundo_apellido   = $data['segundo_apellido'];
                $nombre             = $data['nombre'];
                $celular            = $data['celular'];
                $correo_electronico = $data['correo_electronico'];

                $flag_exist_user    = true;
                $id_usuario         = $data['id'];
            }

    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (!$flag_exist_user && empty($contrasena)) {
                throw new Exception('Parámetro "Contrasena" vacío');
            }
    
            if (empty($primer_apellido)) {
                throw new Exception('Parámetro "Primer apellido" vacío');
            }

            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            //  VALIDAR PARAMETROS
            //$hash_contrasena    = hash('sha256', $contrasena);

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }

            if (!empty($correo_electronico) && !FuncionesGlobales::validarCorreo($correo_electronico)){
                throw new Exception('Parámetro "Correo electronico" invalido.');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctprofesionales WHERE clave = :clave";
    
            $result = $db->query($phql, ['clave' => $celular]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "INSERT INTO ctprofesionales (
                                    clave, 
                                    primer_apellido,
                                    segundo_apellido,
                                    nombre,
                                    celular,
                                    correo_electronico,
                                    direccion,
                                    titulo,
                                    cedula_profesional
                                ) 
                     VALUES (
                                :clave,
                                :primer_apellido,
                                :segundo_apellido,
                                :nombre,
                                :celular,
                                :correo_electronico,
                                :direccion,
                                :titulo,
                                :cedula_profesional
                            ) RETURNING id";
    
            $values = [
                'clave'                 => $celular,
                'primer_apellido'       => $primer_apellido,
                'segundo_apellido'      => $segundo_apellido,
                'nombre'                => $nombre,
                'celular'               => $celular,
                'correo_electronico'    => $correo_electronico,
                'direccion'             => $direccion,
                'titulo'                => $titulo_profesional,
                'cedula_profesional'    => $cedula_profesional,
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id_profesional = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_profesional = $data['id'];
                }
            }
    
            if (!$id_profesional) {
                throw new Exception('Error al insertar el usuario');
            }
    
            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctprofesionales_locaciones_servicios (id_profesional, id_locacion,id_servicio) 
                     VALUES (:id_profesional,:id_locacion,:id_servicio)";
    
            foreach ($lista_locaciones as $locacion) {
                $conexion->query($phql, [
                    'id_profesional'    => $id_profesional,
                    'id_locacion'       => $locacion['id_locacion'],
                    'id_servicio'       => $locacion['id_servicio']
                ]);
            }

            //  SE CREA EL REGISTRO DEL USUARIO, EN CASO DE QUE ESTE NO EXISTA
            if (!$flag_exist_user){
                //  SE BUSCA EL ID DEL PERFIL DE PROFESIONAL
                $phql   = "SELECT * FROM cttipo_usuarios WHERE clave = 'PROF'";

                $result = $conexion->query($phql);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                $id_tipo_usuario    = null;
                if ($result) {
                    while ($data = $result->fetch()) {
                        $id_tipo_usuario = $data['id'];
                    }
                }

                $hash_contrasena    = hash('sha256', $contrasena);
                
                // INSERTAR NUEVO USUARIO
                $phql = "INSERT INTO ctusuarios (
                                clave, 
                                contrasena, 
                                primer_apellido,
                                segundo_apellido,
                                nombre,
                                celular,
                                correo_electronico,
                                id_tipo_usuario,
                                id_profesional
                            ) 
                VALUES (
                            :clave, 
                            :contrasena, 
                            :primer_apellido,
                            :segundo_apellido,
                            :nombre,
                            :celular,
                            :correo_electronico,
                            :id_tipo_usuario,
                            :id_profesional
                        ) RETURNING id";

                $values = [
                    'clave'                 => $celular,
                    'contrasena'            => $hash_contrasena,
                    'primer_apellido'       => $primer_apellido,
                    'segundo_apellido'      => $segundo_apellido,
                    'nombre'                => $nombre,
                    'celular'               => $celular,
                    'correo_electronico'    => $correo_electronico,
                    'id_tipo_usuario'       => $id_tipo_usuario,
                    'id_profesional'        => $id_profesional
                ];

                $result = $conexion->query($phql,$values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                $id_usuario = null;
                if ($result) {
                    while ($data = $result->fetch()) {
                        $id_usuario = $data['id'];
                    }
                }

                if ( $id_usuario != null){
                    $phql   = "INSERT INTO ctpermisos_usuarios (id_usuario,id_permiso)
                                SELECT :id_usuario as id_usuario,id_permiso 
                                FROM ctpermisos_tipo_usuario
                                WHERE id_tipo_usuario = :id_tipo_usuario";
                    
                    $result = $conexion->query($phql,array(
                        'id_usuario'        => $id_usuario,
                        'id_tipo_usuario'   => $id_tipo_usuario
                    ));
                }
            } else {
                //  SI YA EXISTE EL USUARIO Y NO TIENE ID_PROFESIONAL ASIGNADO, ESTE SE ASIGNA
                $phql   = "UPDATE ctusuarios SET id_profesional = :id_profesional WHERE id = :id_usuario";
                $result = $conexion->execute($phql,array(
                    'id_usuario'        => $id_usuario,
                    'id_profesional'    => $id_profesional
                ));
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
            $response->setStatusCode(404, 'OK');
            return $response;
        }
    });

    $app->delete('/ctprofesionales/delete', function () use ($app, $db) {
        $conexion = $db;
        try{
            $conexion->begin();
            $id     = $this->request->getPost('id');

            $phql   = "DELETE FROM ctprofesionales WHERE id = :id";
            $result = $db->execute($phql, array('id' => $id));

            //  SE BUSCA SI EXISTE UN USUARIO ASIGNADO
            $phql   = "UPDATE ctusuarios SET id_profesional = null WHERE id_profesional = :id_profesional";
            $result = $db->execute($phql, array('id_profesional' => $id));

            $conexion->commit();

            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;

        }catch (\Exception $e) {
            $conexion->rollback();
            return (new Response())->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(400, 'Bad Request');
        }
    });

    $app->put('/ctprofesionales/update', function () use ($app, $db, $request) {
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
            $phql = "SELECT * FROM ctprofesionales WHERE clave = :clave AND id <> :id";
    
            $result = $db->query($phql, ['clave' => $clave, 'id' => $id]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO SERVICO
            $phql = "UPDATE ctprofesionales SET
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

    $app->put('/ctprofesionales/change_status', function () use ($app, $db) {
        try{
            //  SE UTILIZARA UN BORRADO LOGICO PARA EVITAR DEJAR
            //  A LOS USUARIOS SIN UN TIPO
            $id             = $this->request->getPost('id');
            $estatus        = '';
            $flag_exists    = false;

            $phql   = "SELECT * FROM ctprofesionales WHERE id = :id";
            $result = $db->query($phql, array('id' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $estatus    = $row['estatus'];
            }

            if ($estatus == ''){
                throw new Exception("Registro inexistente en el catalogo");
            }

            $estatus = $estatus == 1 ? 0 : 1;

            //  EN CASO DE DESACTIVAR SOLO SE CAMBIA EL ESTATUS DEL REGISTRO
            $phql   = "UPDATE ctprofesionales SET estatus = :estatus WHERE id = :id";
            $result = $db->execute($phql, array(
                'estatus'   => $estatus,
                'id'        => $id
            ));

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

    $app->put('/ctprofesionales/update', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id                 = $request->getPost('id') ?? null;
            $primer_apellido    = $request->getPost('primer_apellido') ?? null;
            $segundo_apellido   = $request->getPost('segundo_apellido') ?? null;
            $nombre             = $request->getPost('nombre') ?? null;
            $celular            = $request->getPost('celular') ?? null;
            $correo_electronico = $request->getPost('correo_electronico') ?? null;
            $direccion          = $request->getPost('direccion') ?? null;
            $titulo_profesional = $request->getPost('titulo_profesional') ?? null;
            $cedula_profesional = $request->getPost('cedula_profesional') ?? null;
            $lista_locaciones   = $request->getPost('lista_locaciones') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS

            if (empty($id)) {
                throw new Exception('Parámetro "Identificador" vacío');
            }

            if (empty($primer_apellido)) {
                throw new Exception('Parámetro "Primer apellido" vacío');
            }

            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (empty($celular)) {
                throw new Exception('Parámetro "Celular" vacío');
            }

            if (empty($titulo_profesional)) {
                throw new Exception('Parámetro "Titulo" vacío');
            }

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }

            if (!empty($correo_electronico) && !FuncionesGlobales::validarCorreo($correo_electronico)){
                throw new Exception('Parámetro "Correo electronico" invalido.');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "UPDATE ctprofesionales SET
                                    primer_apellido = :primer_apellido,
                                    segundo_apellido = :segundo_apellido,
                                    nombre = :nombre,
                                    celular = :celular,
                                    correo_electronico = :correo_electronico,
                                    direccion = :direccion,
                                    titulo = :titulo,
                                    cedula_profesional = :cedula_profesional
                                    
                            WHERE id = :id";
    
            $values = [
                'primer_apellido'       => $primer_apellido,
                'segundo_apellido'      => $segundo_apellido,
                'nombre'                => $nombre,
                'celular'               => $celular,
                'correo_electronico'    => $correo_electronico,
                'direccion'       => $direccion,
                'titulo'       => $titulo_profesional,
                'cedula_profesional'       => $cedula_profesional,
                'id'                    => $id
            ];
    
            $result = $conexion->execute($phql, $values);

            //  SE BORRAR LOS PERMISOS ACTUALES
            $phql   = "DELETE FROM ctprofesionales_locaciones_servicios WHERE id_profesional = :id";
            $result = $conexion->execute($phql, array('id' => $id));
            // INSERTAR LAS LOCACIONES
            $phql = "INSERT INTO ctprofesionales_locaciones_servicios (id_profesional, id_locacion,id_servicio) 
                     VALUES (:id_profesional,:id_locacion,:id_servicio)";
    
            foreach ($lista_locaciones as $locacion) {
                $conexion->query($phql, [
                    'id_profesional'    => $id,
                    'id_locacion'       => $locacion['id_locacion'],
                    'id_servicio'       => $locacion['id_servicio']
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
