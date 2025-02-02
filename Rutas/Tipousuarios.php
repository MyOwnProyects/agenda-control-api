<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;

return function (Micro $app,$di) {

    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    // Ruta principal para obtener todos los usuarios
    $app->get('/cttipo_usuarios/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            $activo = $request->getQuery('activo');
            $get_permisos   = $request->getQuery('get_permisos');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM cttipo_usuarios a WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if ($clave != null && $clave != '') {
                $phql           .= " AND lower(a.clave) ILIKE :clave";
                $values['clave'] = "%".mb_strtolower($clave, 'UTF-8')."%";
            }

            if ($nombre != null && $nombre != ''){
                $phql               .= " AND lower(a.nombre) ILIKE :nombre";
                $values['nombre']   = "%".mb_strtolower($nombre, 'UTF-8')."%";
            }

            if (is_numeric($activo)){
                $phql               .= " AND activo = :activo";
                $values['activo']   = $activo;
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['label_estatus']       = $row['activo'] == 1 ? 'Activo' : 'Inactivo'; 
                $row['label_disponible']    = $row['disponible_agenda'] == 1 ? 'SI' : 'NO'; 
                //  SE BUSCAN TODOS LOS PERMISOS QUE EL TIPO DE USUARIO TIENE 
                if (!empty($get_permisos) && is_numeric($id)){
                    $phql   = "SELECT * FROM ctpermisos_tipo_usuario WHERE id_tipo_usuario = :id";
                    $result_permisos    = $db->query($phql,$values);
                    $result_permisos->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_permisos){
                        while($data_permisos = $result_permisos->fetch()){
                            $row['permisos'][$data_permisos['id_permiso']] = $data_permisos;
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
            $response->setStatusCode(400, 'Created');
            return $response;
        }
        
    });

    $app->post('/cttipo_usuarios/create', function () use ($app, $db) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $clave          = $this->request->getPost('clave') ?? null;
            $nombre         = $this->request->getPost('nombre') ?? null;
            $descripcion    = $this->request->getPost('descripcion') ?? null;
            $disponible_agenda  = $this->request->getPost('disponible_agenda') ?? null;
            $lista_permisos     = $this->request->getPost('lista_permisos') ?? [];
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "nombre" vacío');
            }
    
            if (empty($lista_permisos) || !is_array($lista_permisos)) {
                throw new Exception('Lista de permisos vacía o inválida');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM cttipo_usuarios WHERE clave = :clave";
    
            $result = $db->query($phql, ['clave' => $clave]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "INSERT INTO cttipo_usuarios (clave, nombre, descripcion,disponible_agenda) 
                     VALUES (:clave, :nombre, :descripcion,:disponible_agenda) RETURNING id";
    
            $values = [
                'clave'       => $clave,
                'nombre'      => $nombre,
                'descripcion' => $descripcion != null && !empty(trim($descripcion)) ? trim($descripcion) : null,
                'disponible_agenda' => $disponible_agenda
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id_tipo_usuario = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_tipo_usuario = $data['id'];
                }
            }
    
            if (!$id_tipo_usuario) {
                throw new Exception('Error al insertar el usuario');
            }
    
            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctpermisos_tipo_usuario (id_permiso, id_tipo_usuario) 
                     VALUES (:id_permiso, :id_tipo_usuario)";
    
            foreach ($lista_permisos as $permiso) {
                $db->query($phql, [
                    'id_permiso'     => $permiso,
                    'id_tipo_usuario' => $id_tipo_usuario
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

    $app->delete('/cttipo_usuarios/change_estatus', function () use ($app, $db) {
        try{
            //  SE UTILIZARA UN BORRADO LOGICO PARA EVITAR DEJAR
            //  A LOS USUARIOS SIN UN TIPO
            $id_tipo_usuario    = $this->request->getPost('id');
            $activo             = '';
            $flag_exists        = false;

            $phql   = "SELECT * FROM cttipo_usuarios WHERE id = :id_tipo_usuario";
            $result = $db->query($phql, array('id_tipo_usuario' => $id_tipo_usuario));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $activo = $row['activo'];
            }

            if ($activo == ''){
                throw new Exception("Registro inexistente en el catalogo");
            }

            $activo = $activo == 1 ? 0 : 1;

            //  EN CASO DE DESACTIVAR SOLO SE CAMBIA EL ESTATUS DEL REGISTRO
            $phql   = "UPDATE cttipo_usuarios SET activo = :activo WHERE id = :id";
            $result = $db->execute($phql, array(
                'activo'    => $activo,
                'id'        => $id_tipo_usuario
            ));

            //  SI SE DESACTIVA Y EL REGISTRO NO ESTA EN USO, ESTE SE BORRARA
            if ($activo == 0){
                $phql   = "SELECT 1 FROM ctusuarios 
                            WHERE id_tipo_usuario = :id_tipo_usuario LIMIT 1";

                $result = $db->query($phql, array('id_tipo_usuario' => $id_tipo_usuario));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                while ($row = $result->fetch()) {
                    $flag_exists    = true;
                }

                if (!$flag_exists){
                    $phql   = "DELETE FROM cttipo_usuarios WHERE id = :id";
                    $result = $db->execute($phql, array('id' => $id_tipo_usuario));
                }
            }

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

    $app->put('/cttipo_usuarios/update', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id             = $request->getPost('id') ?? null;
            $clave          = $request->getPost('clave') ?? null;
            $nombre         = $request->getPost('nombre') ?? null;
            $descripcion    = $request->getPost('descripcion') ?? null;
            $disponible_agenda  = $request->getPost('disponible_agenda') ?? null;
            $lista_permisos     = $request->getPost('lista_permisos') ?? [];
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS

            if (empty($id)) {
                throw new Exception('Parámetro "id" vacío');
            }

            if (empty($clave)) {
                throw new Exception('Parámetro "clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "nombre" vacío');
            }
    
            if (empty($lista_permisos) || !is_array($lista_permisos)) {
                throw new Exception('Lista de permisos vacía o inválida');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM cttipo_usuarios WHERE clave = :clave AND id <> :id";
    
            $result = $db->query($phql, ['clave' => $clave, 'id' => $id]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "UPDATE cttipo_usuarios SET clave = :clave, nombre = :nombre, descripcion = :descripcion, disponible_agenda = :disponible_agenda WHERE id = :id";
    
            $values = [
                'id'            => $id,
                'clave'         => $clave,
                'nombre'        => $nombre,
                'disponible_agenda' => $disponible_agenda,
                'descripcion'       => $descripcion != null && !empty(trim($descripcion)) ? trim($descripcion) : null
            ];
    
            $result = $conexion->execute($phql, $values);

            //  SE BORRAR LOS PERMISOS ACTUALES
            $phql   = "DELETE FROM ctpermisos_tipo_usuario WHERE id_tipo_usuario = :id";
            $result = $conexion->execute($phql, array('id' => $id));

            // INSERTAR LOS PERMISOS
            $phql = "INSERT INTO ctpermisos_tipo_usuario (id_permiso, id_tipo_usuario) 
                     VALUES (:id_permiso, :id_tipo_usuario)";
    
            foreach ($lista_permisos as $permiso) {
                $db->query($phql, [
                    'id_permiso'     => $permiso,
                    'id_tipo_usuario' => $id
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
            $response->setStatusCode(400, 'ERROR');
            return $response;
        }
    });
    
};
