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
    $app->get('/ctusuarios/count', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $id_tipo_usuario    = $request->getQuery('id_tipo_usuario');
            $accion             = $request->getQuery('accion') ?? null;
            $username           = $request->getQuery('username') ?? null;
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT 
                            COUNT(1) as num_registros
                        FROM ctusuarios a 
                        LEFT JOIN cttipo_usuarios b ON a.id_tipo_usuario = b.id 
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

            if (!empty($id_tipo_usuario)){
                $phql                       .= " AND a.id_tipo_usuario = :id_tipo_usuario";
                $values['id_tipo_usuario']  = $id_tipo_usuario;
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

    // Ruta principal para obtener todos los usuarios
    $app->get('/ctusuarios/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $id_tipo_usuario    = $request->getQuery('id_tipo_usuario');
            $accion             = $request->getQuery('accion') ?? null;
            $username           = $request->getQuery('username') ?? null;
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT 
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            b.clave as clave_tipo_usuario, 
                            b.nombre as nombre_tipo_usuario 
                        FROM ctusuarios a 
                        LEFT JOIN cttipo_usuarios b ON a.id_tipo_usuario = b.id 
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

            if (!empty($accion) && $accion == 'login'){
                $phql               .= " AND a.clave = :username";
                $values['username'] = $username;
            }

            if (!empty($nombre)) {
                $phql           .= " AND lower(a.nombre) ILIKE :nombre";
                $values['clave'] = "%".FuncionesGlobales::ToLower($nombre)."%";
            }

            if (!empty($id_tipo_usuario)){
                $phql                       .= " AND a.id_tipo_usuario = :id_tipo_usuario";
                $values['id_tipo_usuario']  = $id_tipo_usuario;
            }

            $phql   .= " ORDER BY a.primer_apellido ASC,a.segundo_apellido ASC,a.nombre";

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['contrasena']      = $request->hasQuery('fromCatalog') || $request->hasQuery('fromCatalogProfessional') ? null : $row['contrasena'];
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVO' : 'INACTIVO';
                $data[] = $row;
            }

            if (count($data) == 0){
                throw new Exception('Busqueda sin resultados');
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($data);
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

    $app->get('/ctusuarios/get_info_usuario', function () use ($app,$db,$request) {
        try{
            $id_usuario = $request->getQuery('id_usuario');
            
            if ($id_usuario == null || !is_numeric($id_usuario)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = " SELECT b.id,b.controlador,b.accion FROM ctpermisos_usuarios a 
                        LEFT JOIN ctpermisos b ON a.id_permiso = b.id
                        WHERE a.id_usuario = :id_usuario
                        UNION
                        SELECT a.id,a.controlador,a.accion FROM ctpermisos a WHERE a.publico = 1";
            $values = array(
                'id_usuario'    => $id_usuario
            );
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $data[$row['id']]   = $row;
            }

            if (count($data) == 0){
                throw new Exception('Busqueda sin resultados');
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($data);
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

    $app->post('/ctusuarios/create', function () use ($app, $db, $request) {
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
            $id_tipo_usuario    = $request->getPost('id_tipo_usuario') ?? null;
            $lista_permisos     = $request->getPost('lista_permisos') ?? null;
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($contrasena)) {
                throw new Exception('Parámetro "Contrasena" vacío');
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

            if (empty($id_tipo_usuario)) {
                throw new Exception('Parámetro "Tipo usuario" vacío');
            }
    
            if (empty($lista_permisos) || !is_array($lista_permisos)) {
                throw new Exception('Lista de permisos vacía o inválida');
            }

            //  VALIDAR PARAMETROS
            $hash_contrasena    = hash('sha256', $contrasena);

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }

            if (!empty($correo_electronico) && !FuncionesGlobales::validarCorreo($correo_electronico)){
                throw new Exception('Parámetro "Correo electronico" invalido.');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctusuarios WHERE clave = :clave";
    
            $result = $db->query($phql, ['clave' => $celular]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "INSERT INTO ctusuarios (
                                    clave, 
                                    contrasena, 
                                    primer_apellido,
                                    segundo_apellido,
                                    nombre,
                                    celular,
                                    correo_electronico,
                                    id_tipo_usuario
                                ) 
                     VALUES (
                                :clave, 
                                :contrasena, 
                                :primer_apellido,
                                :segundo_apellido,
                                :nombre,
                                :celular,
                                :correo_electronico,
                                :id_tipo_usuario
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
                
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id_usuario = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_usuario = $data['id'];
                }
            }
    
            if (!$id_usuario) {
                throw new Exception('Error al insertar el usuario');
            }
    
            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctpermisos_usuarios (id_permiso, id_usuario) 
                     VALUES (:id_permiso, :id_usuario)";
    
            foreach ($lista_permisos as $permiso) {
                $conexion->query($phql, [
                    'id_permiso'    => $permiso,
                    'id_usuario'    => $id_usuario
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

    $app->put('/ctusuarios/change_status', function () use ($app, $db) {
        try{
            //  SE UTILIZARA UN BORRADO LOGICO PARA EVITAR DEJAR
            //  A LOS USUARIOS SIN UN TIPO
            $id_usuario     = $this->request->getPost('id');
            $estatus        = '';
            $flag_exists    = false;

            $phql   = "SELECT * FROM ctusuarios WHERE id = :id_usuario";
            $result = $db->query($phql, array('id_usuario' => $id_usuario));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $estatus    = $row['estatus'];
            }

            if ($estatus == ''){
                throw new Exception("Registro inexistente en el catalogo");
            }

            $estatus = $estatus == 1 ? 0 : 1;

            //  EN CASO DE DESACTIVAR SOLO SE CAMBIA EL ESTATUS DEL REGISTRO
            $phql   = "UPDATE ctusuarios SET estatus = :estatus WHERE id = :id";
            $result = $db->execute($phql, array(
                'estatus'   => $estatus,
                'id'        => $id_usuario
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

    $app->delete('/ctusuarios/delete', function () use ($app, $db) {
        try{
            //  SE UTILIZARA UN BORRADO LOGICO PARA EVITAR DEJAR
            //  A LOS USUARIOS SIN UN TIPO
            $id     = $this->request->getPost('id');

            $phql   = "DELETE FROM ctusuarios WHERE id = :id";
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

    $app->put('/ctusuarios/update', function () use ($app, $db, $request) {
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
            $id_tipo_usuario    = $request->getPost('id_tipo_usuario') ?? null;
            $lista_permisos     = $request->getPost('lista_permisos') ?? null;
    
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

            if (empty($id_tipo_usuario)) {
                throw new Exception('Parámetro "Tipo usuario" vacío');
            }
    
            if (empty($lista_permisos) || !is_array($lista_permisos)) {
                throw new Exception('Lista de permisos vacía o inválida');
            }

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }

            if (!empty($correo_electronico) && !FuncionesGlobales::validarCorreo($correo_electronico)){
                throw new Exception('Parámetro "Correo electronico" invalido.');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "UPDATE ctusuarios SET
                                    primer_apellido = :primer_apellido,
                                    segundo_apellido = :segundo_apellido,
                                    nombre = :nombre,
                                    celular = :celular,
                                    correo_electronico = :correo_electronico,
                                    id_tipo_usuario = :id_tipo_usuario WHERE id = :id";
    
            $values = [
                'primer_apellido'       => $primer_apellido,
                'segundo_apellido'      => $segundo_apellido,
                'nombre'                => $nombre,
                'celular'               => $celular,
                'correo_electronico'    => $correo_electronico,
                'id_tipo_usuario'       => $id_tipo_usuario,
                'id'                    => $id
            ];
    
            $result = $conexion->execute($phql, $values);

            //  SE BORRAR LOS PERMISOS ACTUALES
            $phql   = "DELETE FROM ctpermisos_usuarios WHERE id_usuario = :id";
            $result = $conexion->execute($phql, array('id' => $id));
    
            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctpermisos_usuarios (id_permiso, id_usuario) 
                     VALUES (:id_permiso, :id_usuario)";
    
            foreach ($lista_permisos as $permiso) {
                $conexion->query($phql, [
                    'id_permiso'    => $permiso,
                    'id_usuario'    => $id
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
