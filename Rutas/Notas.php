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
    $app->get('/tbnotas/count', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_nota        = $request->getQuery('id_nota') ?? null;
            $id_paciente    = $request->getQuery('id_paciente');
            $id_profesional = $request->getQuery('id_profesional');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            // Definir el query SQL
            $phql   = " SELECT COUNT(*) as num_rows FROM tbnotas a 
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($id_nota)){
                $phql           .= " AND a.id = :id_nota ";
                $values['id_nota']  = $id_nota;
            }
    
            if (!empty($id_paciente)){
                $phql           .= " AND id_paciente = :id_paciente ";
                $values['id_paciente']  = $id_paciente;
            }

            if (empty($id_profesional)){
                $phql   .= " AND (EXISTS (
                                    SELECT 1 FROM ctusuarios t1 
                                    WHERE t1.clave = :usuario_solicitud
                                    AND a.id_profesional = t1.id_profesional
                                ) OR a.nota_privada = 0
                            )";

                $values['usuario_solicitud']    = $usuario_solicitud;
            } else {
                $phql           .= " AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $num_rows   = 0;
            while ($row = $result->fetch()) {
                $num_rows   = $row['num_rows'];
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($num_rows);
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

    // Ruta principal para obtener todos los usuarios
    $app->get('/tbnotas/show', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente    = $request->getQuery('id_paciente');
            $id_profesional = $request->getQuery('id_profesional');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            $id_nota            = $request->getQuery('id_nota') ?? null;
            
            // Definir el query SQL
            $phql   = " SELECT  
                            a.*,
                            (b.primer_apellido||' '||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_profesional
                        FROM tbnotas a 
                        LEFT JOIN ctprofesionales b ON a.id_profesional = b.id
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($id_nota)){
                $phql           .= " AND a.id = :id_nota ";
                $values['id_nota']  = $id_nota;
            }
    
            if (!empty($id_paciente)){
                $phql           .= " AND id_paciente = :id_paciente ";
                $values['id_paciente']  = $id_paciente;
            }

            if (empty($id_profesional)){
                $phql   .= " AND (EXISTS (
                                    SELECT 1 FROM ctusuarios t1 
                                    WHERE t1.clave = :usuario_solicitud
                                    AND a.id_profesional = t1.id_profesional
                                ) OR a.nota_privada = 0
                            )";

                $values['usuario_solicitud']    = $usuario_solicitud;
            } else {
                $phql           .= " AND id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            $phql   .= " ORDER BY a.fecha_creacion DESC ";

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                if ($id_nota == null){
                    $row['texto']   = '';
                }
                $row['label_fecha_creacion']    = FuncionesGlobales::formatearFecha($row['fecha_creacion']);
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

    $app->post('/tbnotas/create', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id_paciente        = $request->getPost('id_paciente');
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $texto              = $request->getPost('texto');
            $titulo             = $request->getPost('titulo');
            $nota_privada       = $request->getPost('nota_privada');

            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_profesional = null;
            while ($row = $result->fetch()) {
                $id_profesional = $row['id_profesional'];
            }

            if ($id_profesional == null || !is_numeric($id_profesional)){
                throw new Exception("Usuario sin registro de profesional");
            }

            $texto  = FuncionesGlobales::clear_text_html($texto);

            //  SE CREA EL REGISTRO
            $phql   = "INSERT INTO tbnotas (id_paciente,id_profesional,nota_privada,titulo,texto)
                        VALUES (:id_paciente,:id_profesional,:nota_privada,:titulo,:texto)";

            $values = array(
                'id_paciente'       => $id_paciente,
                'id_profesional'    => $id_profesional,
                'nota_privada'      => $nota_privada,
                'titulo'            => $titulo,
                'texto'             => $texto
            );

            $result = $db->execute($phql,$values);
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->put('/tbnotas/update', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id_nota            = $request->getPost('id_nota');
            $id_paciente        = $request->getPost('id_paciente');
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $texto              = $request->getPost('texto');
            $titulo             = $request->getPost('titulo');
            $nota_privada       = $request->getPost('nota_privada');

            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_profesional = null;
            while ($row = $result->fetch()) {
                $id_profesional = $row['id_profesional'];
            }

            if ($id_profesional == null || !is_numeric($id_profesional)){
                throw new Exception("Usuario sin registro de profesional");
            }

            //  SE BUSCA QUE LA NOTA A EDITAR SEA DEL PROFESIONAL
            $phql   = "SELECT * FROM tbnotas WHERE id = :id_nota";
            $result = $db->query($phql,array('id_nota' => $id_nota));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $flag_exist = false;
            while ($row = $result->fetch()) {
                $flag_exist = true;
                if ($row['id_profesional'] != $id_profesional){
                    throw new Exception("No puedes editar una nota que haya sido creada por otro profesional", 400);   
                }
            }

            if (!$flag_exist){
                throw new Exception("No existe registro de la nota a editar.", 404); 
            }

            $texto  = FuncionesGlobales::clear_text_html($texto);

            //  SE CREA EL REGISTRO
            $phql   = "UPDATE tbnotas SET titulo = :titulo, texto = :texto, nota_privada = :nota_privada WHERE id = :id_nota";

            $values = array(
                'nota_privada'  => $nota_privada,
                'titulo'        => $titulo,
                'texto'         => $texto,
                'id_nota'       => $id_nota
            );

            $result = $db->execute($phql,$values);
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->delete('/tbnotas/delete', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id_nota            = $request->getPost('id_nota');
            $usuario_solicitud  = $request->getPost('usuario_solicitud');

            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_profesional = null;
            while ($row = $result->fetch()) {
                $id_profesional = $row['id_profesional'];
            }

            if ($id_profesional == null || !is_numeric($id_profesional)){
                throw new Exception("Usuario sin registro de profesional");
            }

            //  SE BUSCA QUE LA NOTA A EDITAR SEA DEL PROFESIONAL
            $phql   = "SELECT * FROM tbnotas WHERE id = :id_nota";
            $result = $db->query($phql,array('id_nota' => $id_nota));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $flag_exist = false;
            while ($row = $result->fetch()) {
                $flag_exist = true;
                if ($row['id_profesional'] != $id_profesional){
                    throw new Exception("No puedes borrar una nota que haya sido creada por otro profesional", 400);   
                }
            }

            if (!$flag_exist){
                throw new Exception("No existe registro de la nota a borrar.", 404); 
            }

            //  SE CREA EL REGISTRO
            $phql   = "DELETE FROM tbnotas WHERE id = :id_nota";

            $values = array(
                'id_nota'       => $id_nota
            );

            $result = $db->execute($phql,$values);
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });
};