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
            $estatus            = $request->getQuery('estatus') ?? null;
            
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

            if (!empty($estatus)){
                $phql               .= " AND a.estatus = :estatus";
                $values['estatus']  = $estatus;
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
            $estatus            = $request->getQuery('estatus') ?? null;
            
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

            if (!empty($estatus)){
                $phql               .= " AND a.estatus = :estatus";
                $values['estatus']  = $estatus;
            }

            $phql   .= " AND EXISTS (
                            SELECT 1 FROM ctprofesionales_locaciones_servicios t1
                            LEFT JOIN ctusuarios_locaciones t2 ON t1.id_locacion = t2.id_locacion 
                            LEFT JOIN ctusuarios t3 ON t2.id_usuario = t3.id
                            WHERE t3.clave = :usuario_solicitud AND  t1.id_profesional = a.id
                        )";
            $values['usuario_solicitud']    = $usuario_solicitud;

            $phql   .= ' ORDER BY a.primer_apellido,a.nombre ';

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
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
                                    t3.costo,
                                    t2.codigo_color,
                                    t2.activo
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
                            $row['servicios'][]                 = $data_servicios;
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

            $id_locaciones  = array();
    
            foreach ($lista_locaciones as $locacion) {
                if (!in_array($locacion['id_locacion'],$id_locaciones)){
                    $id_locaciones[]    = $locacion['id_locacion'];
                }
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
                    //  SE ASIGNAN LAS LOCACIONES AL USUARIO
                    foreach($id_locaciones as $usuario_locacion){
                        $phql = "INSERT INTO ctusuarios_locaciones (id_locacion, id_usuario) 
                                    VALUES (:id_locacion, :id_usuario)";

                        $conexion->query($phql, [
                            'id_locacion'   => $usuario_locacion,
                            'id_usuario'    => $id_usuario
                        ]);
                    }


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

    $app->delete('/ctprofesionales/delete', function () use ($app, $db,$request) {
        $conexion = $db;
        try{
            $conexion->begin();
            $id     = $request->getPost('id');

            $phql   = "SELECT 1 FROM tbagenda_citas WHERE id_profesional = :id_profesional LIMIT 1";
            $result = $conexion->query($phql,array('id_profesional' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            if ($result) {
                while ($data = $result->fetch()) {
                    throw new Exception('Existen registros historicos que evitan continuar con el borrado del profesional');
                }
            }

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
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
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
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->put('/ctprofesionales/change_status', function () use ($app, $db,$request) {
        try{
            //  SE UTILIZARA UN BORRADO LOGICO PARA EVITAR DEJAR
            //  A LOS USUARIOS SIN UN TIPO
            $id             = $request->getPost('id');
            $estatus        = '';
            $last_estatus   = $request->getPost('estatus');
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

            if ($estatus != $last_estatus){
                throw new Exception("El estatus actual del profesional a cambiado, refresca la vista para verificar esta información");
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
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
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
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/ctprofesionales/get_horario_fijo', function () use ($app, $db,$request) {
        
        try{
            
            $id_profesional = $request->getQuery('id_profesional');
            $arr_return     = array();

            $arr_dias   = array(
                1   => 'Lunes',
                2   => 'Martes',
                3   => 'Miercoles',
                4   => 'Jueves',
                5   => 'Viernes',
                6   => 'Sabado',
                7   => 'Domingo'
            );

            $phql   = " SELECT  
                            a.id as id_cita_programada,
                            a.id_locacion,
                            a.id_paciente,
                            b.id_profesional,
                            d.nombre as nombre_locacion,
                            (g.primer_apellido|| ' ' ||COALESCE(g.segundo_apellido,'')||' '||g.nombre) as nombre_paciente,
                            f.clave as clave_servicio,
                            f.codigo_color,
                            c.dia,
                            TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(c.hora_termino, 'HH24:MI') AS hora_termino,
                            b.id_servicio,
                            c.id as id_cita_programada_servicio_horario,
                            (e.primer_apellido|| ' ' ||COALESCE(e.segundo_apellido,'')||' '||e.nombre) as nombre_profesional,
                            CASE 
                                WHEN g.fecha_nacimiento IS NOT NULL THEN
                                    EXTRACT(YEAR FROM AGE(CURRENT_DATE, g.fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(CURRENT_DATE, g.fecha_nacimiento))::text, 2, '0')
                                ELSE NULL
                            END AS edad_actual
                        FROM tbcitas_programadas a 
                        LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
                        LEFT JOIN tbcitas_programadas_servicios_horarios c ON b.id = c.id_cita_programada_servicio
                        LEFT JOIN ctlocaciones d ON a.id_locacion = d.id
                        LEFT JOIN ctprofesionales e ON b.id_profesional = e.id
                        LEFT JOIN ctservicios f ON b.id_servicio = f.id
                        LEFT JOIN ctpacientes g ON a.id_paciente = g.id

                        WHERE b.id_profesional = :id_profesional AND b.id IS NOT NULL
                        ORDER BY c.dia,c.hora_inicio, e.primer_apellido,e.segundo_apellido,e.nombre ";
            $result = $db->query($phql,array('id_profesional' => $id_profesional));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            if ($result) {
                while ($data = $result->fetch()) {
                    $edad_actual                = $data['edad_actual'] != null ? '('.$data['edad_actual'].')' : '(S/A)';
                    $data['label_dia']          = $arr_dias[$data['dia']];
                    $data['nombre_paciente']    = $data['nombre_paciente'].' '.$edad_actual;
                    $arr_return[]       = $data;
                }
            }

            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
            $response->setStatusCode(200, 'OK');
            return $response;

        }catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/ctprofesionales/verificar_disponibilidad', function () use ($app, $db,$request) {
        
        //  SE EJECUTA FUNCION PARA VALIDAR EMPALMADOS
        $conexion   = $db;
        try{

            $conexion->begin();

            $arr_dias   = array(
                1   => 'Lunes',
                2   => 'Martes',
                3   => 'Miercoles',
                4   => 'Jueves',
                5   => 'Viernes',
                6   => 'Sabado',
                7   => 'Domingo'
            );

            $id_profesional = $request->getQuery('id_profesional');
            $id_paciente    = null;
            $dia            = null;
            $label_dia      = null;
            $hora_inicio    = null;
            $hora_termino   = null;
            $id_cita_programada_servicio_horario = $request->getQuery('id_cita_programada_servicio_horario');


            //  OBTENCION DE INFORMACION DE LA CITA PROGRAMADA
            $phql   = " SELECT  
                            a.id_paciente,
                            c.hora_inicio,
                            c.hora_termino,
                            c.dia
                        FROM tbcitas_programadas a 
                        LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
                        LEFT JOIN tbcitas_programadas_servicios_horarios c ON b.id = c.id_cita_programada_servicio
                        WHERE c.id = :id_cita_programada_servicio_horario";
            $values = array(
                'id_cita_programada_servicio_horario'   => $id_cita_programada_servicio_horario
            );

            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $flag_create    = false;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_paciente    = $data['id_paciente'];
                    $dia            = $data['dia'];
                    $hora_inicio    = $data['hora_inicio'];
                    $hora_termino   = $data['hora_termino'];
                }
            }

            //  DELETE PARA EVITAR MOSTRAR EMPALADO
            $phql   = "DELETE FROM tbcitas_programadas_servicios_horarios WHERE id = :id ";
            $result = $conexion->query($phql, array('id' => $id_cita_programada_servicio_horario));

            $phql   = "SELECT * FROM fn_validar_citas_programadas(:id_profesional, :id_paciente, :dia,:label_dia, :hora_inicio, :hora_termino)";
            $values = array(
                'id_profesional'    => $id_profesional,
                'id_paciente'       => $id_paciente,
                'dia'               => $dia,
                'label_dia'         => $arr_dias[$dia],
                'hora_inicio'       => $hora_inicio,
                'hora_termino'      => $hora_termino
            );

            $result_horario = $conexion->query($phql, $values);
            $result_horario->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $flag_create    = false;
            if ($result_horario) {
                while ($data_horario = $result_horario->fetch()) {
                    $flag_create    = true;
                }
            }

            $conexion->rollback();
            return json_encode(array('RESULTADO' => $flag_create));         
        }catch(\Exception $err){
            $conexion->rollback();
            $response = new Response();
            $response->setJsonContent(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->post('/ctprofesionales/update_horario_fijo', function () use ($app, $db,$request) {
        
        try{

            $arr_dias   = array(
                1   => 'Lunes',
                2   => 'Martes',
                3   => 'Miercoles',
                4   => 'Jueves',
                5   => 'Viernes',
                6   => 'Sabado',
                7   => 'Domingo'
            );

            $id_profesional = $request->getPost('id_profesional');
            $id_cita_programada_servicio_horario    = $request->getPost('id_cita_programada_servicio_horario');
            $id_cita_programada_servicio            = null;

            $phql   = " SELECT 
                            id_cita_programada_servicio 
                        FROM tbcitas_programadas_servicios_horarios a 
                        WHERE id = :id";

            $result = $db->query($phql, array('id' => $id_cita_programada_servicio_horario));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $flag_create    = false;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_cita_programada_servicio    = $data['id_cita_programada_servicio'];
                }
            }

            $phql   = "UPDATE tbcitas_programadas_servicios SET id_profesional = :id_profesional WHERE id = :id_cita_programada_servicio";
            $result = $db->execute($phql, array(
                'id_cita_programada_servicio'   => $id_cita_programada_servicio,
                'id_profesional'                => $id_profesional
            ));

            return json_encode(array('MSG' => 'OK'));         
        }catch(\Exception $err){
            $conexion->rollback();
            $response = new Response();
            $response->setJsonContent(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/ctprofesionales/get_pacientes_asignados', function () use ($app, $db,$request) {
        
        try{
            
            $id_profesional = $request->getQuery('id_profesional');
            $arr_return     = array();

            $arr_dias   = array(
                1   => 'Lunes',
                2   => 'Martes',
                3   => 'Miercoles',
                4   => 'Jueves',
                5   => 'Viernes',
                6   => 'Sabado',
                7   => 'Domingo'
            );

            $phql   = " SELECT  
                            a.id as id_cita_programada,
                            a.id_locacion,
                            g.clave as clave_paciente,
                            a.id_paciente,
                            b.id_profesional,
                            d.nombre as nombre_locacion,
                            (g.primer_apellido|| ' ' ||COALESCE(g.segundo_apellido,'')||' '||g.nombre) as nombre_paciente,
                            f.clave as clave_servicio,
                            f.codigo_color,
                            c.dia,
                            TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(c.hora_termino, 'HH24:MI') AS hora_termino,
                            b.id_servicio,
                            c.id as id_cita_programada_servicio_horario,
                            (e.primer_apellido|| ' ' ||COALESCE(e.segundo_apellido,'')||' '||e.nombre) as nombre_profesional,
                            CASE 
                                WHEN g.fecha_nacimiento IS NOT NULL THEN
                                    EXTRACT(YEAR FROM AGE(CURRENT_DATE, g.fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(CURRENT_DATE, g.fecha_nacimiento))::text, 2, '0')
                                ELSE NULL
                            END AS edad_actual
                        FROM tbcitas_programadas a 
                        LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
                        LEFT JOIN tbcitas_programadas_servicios_horarios c ON b.id = c.id_cita_programada_servicio
                        LEFT JOIN ctlocaciones d ON a.id_locacion = d.id
                        LEFT JOIN ctprofesionales e ON b.id_profesional = e.id
                        LEFT JOIN ctservicios f ON b.id_servicio = f.id
                        LEFT JOIN ctpacientes g ON a.id_paciente = g.id

                        WHERE b.id_profesional = :id_profesional AND b.id IS NOT NULL
                        AND g.estatus = 1
                        ORDER BY g.primer_apellido,g.segundo_apellido,g.nombre,c.dia,c.hora_inicio ";
            $result = $db->query($phql,array('id_profesional' => $id_profesional));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            if ($result) {
                while ($data = $result->fetch()) {
                    $edad_actual                = $data['edad_actual'] != null ? '('.$data['edad_actual'].')' : '(S/A)';
                    $data['label_dia']          = $arr_dias[$data['dia']];
                    $data['nombre_paciente']    = $data['nombre_paciente'].' '.$edad_actual;
                    
                    //  AGRUPACION POR PACIENTE
                    $arr_return[$data['clave_paciente']]['id_paciente'] = $data['id_paciente'];
                    $arr_return[$data['clave_paciente']]['nombre']      = $data['nombre_paciente'];
                    
                    if (!isset($arr_return[$data['clave_paciente']]['citas'])){
                        $arr_return[$data['clave_paciente']]['citas']   = '';
                    } else {
                        $arr_return[$data['clave_paciente']]['citas']   .= ', ';
                    }

                    $arr_return[$data['clave_paciente']]['citas'] .= $data['label_dia'].' '.$data['hora_inicio'].' - '.$data['hora_termino'];
                    
                }
            }

            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
            $response->setStatusCode(200, 'OK');
            return $response;

        }catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });
};