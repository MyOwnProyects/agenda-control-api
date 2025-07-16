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
            $usuario_solicitud       = $request->getQuery('usuario_solicitud');
            
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
                                SELECT 1 FROM tbcitas_programadas t1
                                LEFT JOIN tbcitas_programadas_servicios t2 ON t1.id = t2.id_cita_programada
                                WHERE t2.id_servicio = :id_servicio AND a.id = t1.id_paciente
                            )";

                $values['id_servicio']  = $id_servicio;
            }

            if (is_numeric($id_locacion_registro)){
                $phql           .= " AND a.id_locacion_registro = :id_locacion_registro";
                $values['id_locacion_registro']   = $id_locacion_registro;
            }

            $phql   .= " AND EXISTS (
                            SELECT 1 FROM ctusuarios_locaciones t1 
                            LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                            WHERE t2.clave = :usuario_solicitud AND a.id_locacion_registro = t1.id_locacion 
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
    $app->get('/ctpacientes/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $primer_apellido    = $request->getQuery('primer_apellido');
            $segundo_apellido   = $request->getQuery('segundo_apellido');
            $nombre             = $request->getQuery('nombre');
            $id_servicio        = $request->getQuery('id_servicio');
            $id_locacion_registro   = $request->getQuery('id_locacion_registro');
            $usuario_solicitud      = $request->getQuery('usuario_solicitud');
            $get_diagnoses          = $request->getQuery('get_diagnoses') ?? null;
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            COALESCE(b.num_servicios,0) as num_servicios,
                            c.nombre as locacion_registro,
                            a.fecha_nacimiento,
                            CASE 
                                WHEN fecha_nacimiento IS NOT NULL THEN
                                    EXTRACT(YEAR FROM AGE(CURRENT_DATE, fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(CURRENT_DATE, fecha_nacimiento))::text, 1, '0')
                                ELSE NULL
                            END AS edad_actual

                        FROM ctpacientes a 
                        LEFT JOIN (
                            SELECT t1.id_paciente, COUNT(1) as num_servicios
                            FROM tbcitas_programadas t1
                            LEFT JOIN tbcitas_programadas_servicios t2 ON t1.id = t2.id_cita_programada
                            WHERE t2.id IS NOT NULL
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
                                SELECT 1 FROM tbcitas_programadas t1
                                LEFT JOIN tbcitas_programadas_servicios t2 ON t1.id = t2.id_cita_programada
                                WHERE t2.id_servicio = :id_servicio AND a.id = t1.id_paciente
                            )";

                $values['id_servicio']  = $id_servicio;
            }


            if (is_numeric($id_locacion_registro)){
                $phql           .= " AND a.id_locacion_registro = :id_locacion_registro";
                $values['id_locacion_registro']   = $id_locacion_registro;
            }

            $phql   .= " AND EXISTS (
                SELECT 1 FROM ctusuarios_locaciones t1 
                LEFT JOIN ctusuarios t2 ON t1.id_usuario = t2.id
                WHERE t2.clave = :usuario_solicitud AND a.id_locacion_registro = t1.id_locacion 
            )";
            $values['usuario_solicitud']    = $usuario_solicitud;

            $phql   .= ' ORDER BY a.primer_apellido,a.segundo_apellido,a.nombre ';

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVO' : 'INACTIVO';
                $row['diagnosticos']    = array();
                if (!empty($get_diagnoses)){
                    $phql   = " SELECT a.presento_evidencia,b.* FROM tbpacientes_diagnosticos a
                                LEFT JOIN cttranstornos_neurodesarrollo b ON a.id_transtorno = b.id
                                WHERE a.id_paciente = :id_paciente ORDER BY b.clave ASC";
                    $result_diagnosticos    = $db->query($phql,array(
                        'id_paciente'   => $row['id']
                    ));
                    $result_diagnosticos->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_diagnosticos){
                        while($data_diagnostico = $result_diagnosticos->fetch()){
                            $row['diagnosticos'][]  = $data_diagnostico;
                        }
                    }
                }
                $row['diagnosticos_registrados']    = count($row['diagnosticos']) > 0 ? 'SI' : 'NO';
                $data[]                             = $row;
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
            $fecha_nacimiento       = $request->getPost('fecha_nacimiento') ?? null;
           
    
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
            $phql = "SELECT * FROM ctpacientes a
                    WHERE lower(a.primer_apellido) ILIKE :primer_apellido AND
                          lower(a.segundo_apellido) ILIKE :segundo_apellido AND
                          lower(a.nombre) ILIKE :nombre ";

            $values = array(
                'primer_apellido'   => "%".FuncionesGlobales::ToLower($primer_apellido)."%",
                'segundo_apellido'  => "%".FuncionesGlobales::ToLower($segundo_apellido)."%",
                'nombre'            => "%".FuncionesGlobales::ToLower($nombre)."%",
            );
    
            $result = $db->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('El paciente ya se encuentra registrado');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "INSERT INTO ctpacientes (
                                    clave, 
                                    primer_apellido,
                                    segundo_apellido,
                                    nombre,
                                    celular,
                                    id_locacion_registro,
                                    fecha_nacimiento
                                ) 
                     VALUES (
                                (select fn_crear_clave_paciente()), 
                                :primer_apellido,
                                :segundo_apellido,
                                :nombre,
                                :celular,
                                :id_locacion_registro,
                                :fecha_nacimiento
                            ) RETURNING id";
    
            $values = [
                'primer_apellido'       => $primer_apellido,
                'segundo_apellido'      => $segundo_apellido,
                'nombre'                => $nombre,
                'celular'               => $celular,
                'id_locacion_registro'  => $id_locacion_registro,
                'fecha_nacimiento'      => $fecha_nacimiento
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
            
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->put('/ctpacientes/save_express', function () use ($app, $db, $request) {
        try {
    
            // OBTENER DATOS JSON
            $id                 = $request->getPost('id') ?? null;
            $primer_apellido    = $request->getPost('primer_apellido') ?? null;
            $segundo_apellido   = $request->getPost('segundo_apellido') ?? null;
            $nombre             = $request->getPost('nombre') ?? null;
            $celular            = $request->getPost('celular') ?? null;
            $id_locacion_registro   = $request->getPost('id_locacion_registro') ?? null;
            $fecha_nacimiento       = $request->getPost('fecha_nacimiento') ?? null;
           
    
            //  VERIFICACION DE PARAMETROS

            if (empty($id)) {
                throw new Exception('Par&aacute;metro "Identificador" vac&iacute;o');
            }

            if (empty($primer_apellido)) {
                throw new Exception('Par&aacute;metro "Primer apellido" vac&iacute;o');
            }

            if (empty($nombre)) {
                throw new Exception('Par&aacute;metro "Nombre" vac&iacute;o');
            }

            if (empty($celular)) {
                throw new Exception('Par&aacute;metro "Celular" vac&iacute;o');
            }

            if (empty($id_locacion_registro)) {
                throw new Exception('Par&aacute;metro "Locacion" vac&iacute;o');
            }

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }
    
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctpacientes a
                    WHERE lower(a.primer_apellido) ILIKE :primer_apellido AND
                          lower(a.segundo_apellido) ILIKE :segundo_apellido AND
                          lower(a.nombre) ILIKE :nombre AND id <> :id";

            $values = array(
                'primer_apellido'   => "%".FuncionesGlobales::ToLower($primer_apellido)."%",
                'segundo_apellido'  => "%".FuncionesGlobales::ToLower($segundo_apellido)."%",
                'nombre'            => "%".FuncionesGlobales::ToLower($nombre)."%",
                'id'                => $id
            );
    
            $result = $db->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('El paciente ya se encuentra registrado');
            }
    
            // INSERTAR NUEVO USUARIO
            $phql = "UPDATE ctpacientes SET 
                        primer_apellido = :primer_apellido,
                        segundo_apellido = :segundo_apellido,
                        nombre = :nombre,
                        celular = :celular,
                        fecha_nacimiento = :fecha_nacimiento
                    WHERE id = :id";
    
            $values = [
                'primer_apellido'   => $primer_apellido,
                'segundo_apellido'  => $segundo_apellido,
                'nombre'            => $nombre,
                'celular'           => $celular,
                'fecha_nacimiento'  => $fecha_nacimiento,
                'id'                => $id            
            ];
    
            $result = $db->execute($phql, $values);
    
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

    $app->post('/ctpacientes/save_program_date', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id_paciente    = $request->getPost('id_paciente') ?? null;
            $id_locacion    = $request->getPost('id_locacion') ?? null;
            $obj_info       = $request->getPost('obj_info') ?? null;
            $generar_citas  = $request->getPost('generar_citas') ?? null;
            $fecha_inicio   = $request->getPost('fecha_inicio') ?? null;
            $fecha_termino  = $request->getPost('fecha_termino') ?? null;
            $clave_usuario  = $request->getPost('usuario_solicitud') ?? null;
            $id_cita_programada_servicio_horario    = $request->getPost('id_cita_programada_servicio_horario') ?? null;

            $arr_dias   = array(
                1   => 'Lunes',
                2   => 'Martes',
                3   => 'Miercoles',
                4   => 'Jueves',
                5   => 'Viernes',
                6   => 'Sabado',
                7   => 'Domingo'
            );
            
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($id_paciente)) {
                throw new Exception('Parámetro "Paciente" vacío');
            }

            if (empty($id_locacion)){
                throw new Exception('Parámetro "Locación" vacío');
            }

            $aqui   = 1;
            //  AL TRAER ESTE ID SIGNIFICA QUE ES UNA EDICION
            if (!empty($id_cita_programada_servicio_horario) && is_numeric($id_cita_programada_servicio_horario)){
                $id_cita_programada_servicio    = null;

                $phql   = "SELECT * FROM tbcitas_programadas_servicios_horarios WHERE id = :id";
                $result = $db->query($phql, array('id' => $id_cita_programada_servicio_horario));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                if ($result) {
                    while ($data = $result->fetch()) {
                        $id_cita_programada_servicio = $data['id_cita_programada_servicio'];
                    }
                }

                $phql   = "DELETE FROM tbcitas_programadas_servicios_horarios WHERE id = :id";
                $result = $conexion->query($phql, array('id' => $id_cita_programada_servicio_horario));

                //  SE VERIFICA QUE SOLO EXISTA UN REGISTRO DE SERVICIO->HORARIO
                $phql   = "SELECT COUNT(*) as num_registros 
                            FROM tbcitas_programadas_servicios a 
                            LEFT JOIN tbcitas_programadas_servicios_horarios b ON a.id = b.id_cita_programada_servicio
                            WHERE a.id = :id AND b.id IS NOT NULL";
                $result = $db->query($phql, array('id' => $id_cita_programada_servicio));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                $flag_delete    = true;
                if ($result) {
                    while ($data = $result->fetch()) {
                        if ($data['num_registros'] > 0){
                            $flag_delete    = false;
                        }
                    }
                }

                if ($flag_delete){
                    $phql   = "DELETE FROM tbcitas_programadas_servicios WHERE id = :id";
                    $result = $conexion->query($phql, array('id' => $id_cita_programada_servicio));
                }

            }

            // if (empty($obj_info)) {
            //     throw new Exception('Lista de servicios vacia');
            // }

            //  SE BUSCA ID DE TBCITAS_PROGRAMADAS, DE NO EXISTIR ESTA SE CREA
            $phql   = " SELECT * FROM tbcitas_programadas 
                        WHERE id_paciente = :id_paciente AND id_locacion = :id_locacion";
            $values = array(
                'id_paciente'   => $id_paciente,
                'id_locacion'   => $id_locacion
            );

            $result = $db->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id_cita_programada = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_cita_programada = $data['id'];
                    // foreach($obj_info as $info_cita){
                    //     //  SE BORRAN LOS REGISTROS DE SERVICIOS Y HORARIOS
                        // $phql   = "DELETE FROM tbcitas_programadas_servicios 
                        // WHERE id_cita_programada = :id_cita_programada AND id_servicio = :id_servicio";
                        // $result_delete  = $conexion->execute($phql,array(
                        //     'id_cita_programada'    => $id_cita_programada,
                        //     'id_servicio'           => $info_cita['id_servicio']
                        // ));
                    // }
                }
            }

            if ($id_cita_programada == null){

                $phql   = "INSERT INTO tbcitas_programadas (id_paciente,id_locacion) 
                            VALUES (:id_paciente,:id_locacion) RETURNING *";
                $values = array(
                    'id_paciente'   => $id_paciente,
                    'id_locacion'   => $id_locacion
                );

                $result = $conexion->query($phql, $values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                $id_cita_programada = null;
                if ($result) {
                    while ($data = $result->fetch()) {
                        $id_cita_programada = $data['id'];
                    }
                }
            }
    
            //  SE RECORRE EL OBJ CON LAS NUEVAS CITAS
            foreach($obj_info as $info_cita){

                $id_profesional = $info_cita['id_profesional'];
                $id_servicio    = $info_cita['id_servicio'];
                $id_cita_programada_servicio    = null;

                //  SE BUSCA SI EXISTE EL REGISTRO DE CLASE
                $phql   = "SELECT * FROM tbcitas_programadas_servicios 
                            WHERE id_cita_programada = :id_cita_programada AND id_servicio = :id_servicio AND id_profesional = :id_profesional LIMIT 1";
                $result_servicios   = $conexion->query($phql, array(
                    'id_cita_programada'    => $id_cita_programada,
                    'id_servicio'           => $id_servicio,
                    'id_profesional'        => $id_profesional
                ));
                $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result_servicios) {
                    while ($data_servicios = $result_servicios->fetch()) {
                        $id_cita_programada_servicio    = $data_servicios['id'];
                    }
                }

                if ($id_cita_programada_servicio == null){
                    //  SE CREA EL REGISTRO DE TBCITAS_PROGRAMADAS_SERVICIOS
                    $phql   = "INSERT INTO tbcitas_programadas_servicios (id_cita_programada,id_servicio,id_profesional)
                                VALUES (:id_cita_programada,:id_servicio,:id_profesional) RETURNING *";

                    $result_servicios   = $conexion->query($phql, array(
                        'id_cita_programada'    => $id_cita_programada,
                        'id_servicio'           => $id_servicio,
                        'id_profesional'        => $id_profesional
                    ));
                    $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_servicios) {
                        while ($data_servicios = $result_servicios->fetch()) {
                            $id_cita_programada_servicio    = $data_servicios['id'];
                        }
                    }
                }
                

                //  SE RECORRE LA ESTRUCTURA DE LAS HORAS DE LAS CITAS
                foreach($info_cita['horarios'] as $horario){
                    //  SE EJECUTA FUNCION PARA VALIDAR EMPALMADOS
                    try{
                        $phql   = "SELECT * FROM fn_validar_citas_programadas(:id_profesional, :id_paciente, :dia,:label_dia, :hora_inicio, :hora_termino)";
                        $values = array(
                            'id_profesional'    => $id_profesional,
                            'id_paciente'       => $id_paciente,
                            'dia'               => $horario['dia'],
                            'label_dia'         => $arr_dias[$horario['dia']],
                            'hora_inicio'       => $horario['hora_inicio'],
                            'hora_termino'      => $horario['hora_termino']
                        );

                        $result_horario = $conexion->query($phql, $values);
                        $result_horario->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
                
                        $flag_create    = false;
                        if ($result_horario) {
                            while ($data_horario = $result_horario->fetch()) {
                                $flag_create    = true;
                            }
                        }

                        if ($flag_create){
                            //  SE VERIFICA SI EXISTE EL REGISTRO DE TBCITAS_PROGRAMADAS
                            $phql   = "INSERT INTO tbcitas_programadas_servicios_horarios (id_cita_programada_servicio,dia,hora_inicio,hora_termino)
                                        VALUES (:id_cita_programada_servicio,:dia,:hora_inicio,:hora_termino)";
                            $result_insert_horario  = $conexion->query($phql, array(
                                'id_cita_programada_servicio'   => $id_cita_programada_servicio,
                                'dia'                           => $horario['dia'],
                                'hora_inicio'                   => $horario['hora_inicio'],
                                'hora_termino'                  => $horario['hora_termino']
                            ));
                        }
                    }catch(\Exception $err){
                        throw new \Exception(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
                    }
                    
                }
            }

            if ($generar_citas != null && $fecha_inicio!= null && $fecha_termino!= null){
                try{
                    //  SE AGENDAN LAS CITAS DEL PACIENTE
                    $phql   = "SELECT * FROM fn_programar_citas(:id_paciente,:id_locacion,:fecha_inicio,:fecha_termino,:clave_usuario);";
                    $values = array(
                        'id_paciente'   => $id_paciente,
                        'id_locacion'   => $id_locacion,
                        'fecha_inicio'  => $fecha_inicio,
                        'fecha_termino' => $fecha_termino,
                        'clave_usuario' => $clave_usuario
                    );

                    $result_citas   = $conexion->execute($phql,$values);
                }catch(\Exception $err){
                    throw new \Exception(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
                }
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

    // Ruta principal para obtener todos los registros
    $app->get('/ctpacientes/get_program_date', function () use ($app,$db,$request) {

        try{

            $id_paciente    = $request->getQuery('id_paciente') ?? null;
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $id_profesional = $request->getQuery('id_profesional') ?? null;
            $from_catalog   = $request->getQuery('from_catalog') ?? null;

            if (empty($id_paciente) && empty($id_locacion)){
                throw new Exception('Parámetros vacíos');
            }

            //  SE VERIFICA QUE LA LOCACION TENGA REGISTRADO EL HORARIO DE ATENCION
            if ($from_catalog != null){
                $has_openning_hours = false;
                $phql   = "SELECT 1 FROM ctlocaciones_horarios_atencion WHERE id_locacion = :id_locacion LIMIT 1";
                $result = $db->query($phql,array('id_locacion' => $id_locacion));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
                if ($result){
                    while($data = $result->fetch()){
                        $has_openning_hours = true;
                    }
                }

                if (!$has_openning_hours){
                    throw new Exception('No existe un horario de atenci&oacute;n asignado a la locaci&oacute;n');
                }
            }
        
            // Definir el query SQL
            $phql   = " SELECT
                            a.*,
                            b.id as id_cita_programada_servicio,
                            c.id as id_cita_programada_servicio_horario,
                            b.id_servicio,
                            e.nombre as servicio,
                            b.id_profesional,
                            (d.primer_apellido||' '||COALESCE(d.segundo_apellido,'')||' '||d.nombre) as profesional,
                            c.dia,
                            TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(c.hora_termino, 'HH24:MI') AS hora_termino,
                            TRUNC(f.duracion / 60, 0) AS duracion,
                            g.nombre as nombre_locacion,
                            e.codigo_color
                        FROM tbcitas_programadas a 
                        LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
                        LEFT JOIN tbcitas_programadas_servicios_horarios c ON b.id = c.id_cita_programada_servicio
                        LEFT JOIN ctprofesionales d ON b.id_profesional = d.id
                        LEFT JOIN ctservicios e ON b.id_servicio = e.id
                        LEFT JOIN ctlocaciones_servicios f ON a.id_locacion = f.id_locacion AND b.id_servicio = f.id_servicio
                        LEFT JOIN ctlocaciones g ON a.id_locacion = g.id
                        WHERE 1 = 1 AND b.id IS NOT NULL AND c.id IS NOT NULL";
            $values = array();
    
            if (is_numeric($id_paciente)){
                $phql                   .= " AND a.id_paciente = :id_paciente ";
                $values['id_paciente']  = $id_paciente;
            }

            if (is_numeric($id_profesional)){
                $phql                       .= " AND b.id_profesional = :id_profesional ";
                $values['id_profesional']   = $id_profesional;
            }

            if (is_numeric($id_locacion)){
                $phql                   .= " AND a.id_locacion = :id_locacion ";
                $values['id_locacion']  = $id_locacion;
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $row    = [];
            while ($data = $result->fetch()) {
                $row[$data['id_cita_programada_servicio']]['codigo_color']      = $data['codigo_color'];
                $row[$data['id_cita_programada_servicio']]['id_servicio']       = $data['id_servicio'];
                $row[$data['id_cita_programada_servicio']]['duracion']          = $data['duracion'];
                $row[$data['id_cita_programada_servicio']]['id_profesional']    = $data['id_profesional'];
                $row[$data['id_cita_programada_servicio']]['servicio']          = $data['servicio'];
                $row[$data['id_cita_programada_servicio']]['profesional']       = $data['profesional'];
                $row[$data['id_cita_programada_servicio']]['nombre_locacion']   = $data['nombre_locacion'];
                $row[$data['id_cita_programada_servicio']]['horarios'][]                            = array(
                    'dia'           => $data['dia'],
                    'hora_inicio'   => $data['hora_inicio'],
                    'hora_termino'  => $data['hora_termino'],
                    'id_cita_programada_servicio_horario'   => $data['id_cita_programada_servicio_horario'],
                );
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($row);
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

    $app->post('/ctpacientes/delete_program_date', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id = $request->getPost('id_cita_programada_servicio_horario') ?? null;

            $clave_usuario  = $request->getPost('usuario_solicitud') ?? null;

            //  SE BUSCA EL REGISTRO DEL SERVICIO, ESTE EN CASO DE QUE QUE VACIO EL REGISTRO            
            $id_cita_programada_servicio    = null;
            $phql   = "SELECT * FROM tbcitas_programadas_servicios_horarios WHERE id = :id";

            $result = $db->query($phql,array(
                'id'    => $id
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
            if($result){
                while($data = $result->fetch()){
                    $id_cita_programada_servicio    = $data['id_cita_programada_servicio'];
                }
            }

            //  SE BORRA EL REGISTRO
            $phql   = "DELETE FROM tbcitas_programadas_servicios_horarios WHERE id = :id";
            $conexion->execute($phql,array('id' => $id));

            //  SE BUSCA SI EL SERVICIO TIENE DIAS , DE LO CONTRARIO SE BORRA
            $phql   = "SELECT COUNT(*) as num_records FROM tbcitas_programadas_servicios_horarios WHERE id_cita_programada_servicio = :id_cita_programada_servicio";
            $result = $db->query($phql,array(
                'id_cita_programada_servicio'   => $id_cita_programada_servicio
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $flag_count = 0;
            if($result){
                while($data = $result->fetch()){
                    $flag_count = $data['num_records'];
                }
            }

            if( $flag_count == 0){
                $phql   = "DELETE FROM tbcitas_programadas_servicios WHERE id = :id_cita_programada_servicio";
                $conexion->execute($phql,array('id_cita_programada_servicio' => $id_cita_programada_servicio));
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

    $app->put('/ctpacientes/change_status', function () use ($app, $db,$request) {
        $conexion = $db; 
        try{
            $conexion->begin();
            //  SE UTILIZARA UN BORRADO LOGICO PARA EVITAR DEJAR
            //  A LOS USUARIOS SIN UN TIPO
            $id                 = $request->getPost('id');
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $estatus            = '';
            $flag_exists        = false;

            $phql   = "SELECT * FROM ctpacientes WHERE id = :id";
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
            $phql   = "UPDATE ctpacientes SET estatus = :estatus WHERE id = :id";
            $result = $conexion->execute($phql, array(
                'estatus'   => $estatus,
                'id'        => $id
            ));

            if ($estatus != 1){
                //  SI SE INACTIVA AL PACIENTE, SE BORRAN TODAS SUS CITAS PROGRAMADAS
                //  TODO VERIFICAR SI SE CANCELAN LAS CITAS ACTIVAS DE LA AGENDA
                $phql   = "DELETE FROM tbcitas_programadas_servicios WHERE id IN (
                            SELECT a.id from tbcitas_programadas_servicios a
                            LEFT JOIN tbcitas_programadas b ON a.id_cita_programada = b.id 
                            WHERE b.id_paciente = :id_paciente
                        );";
                $result_delete  = $conexion->execute($phql,array(
                    'id_paciente'   => $id
                ));

                //  SE BUSCA EL ID DE BAJA POR CATALOGO
                $phql   = "SELECT * FROM ctmotivos_cancelacion_cita WHERE clave = 'BAJ'";
                $result = $db->query($phql);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                $id_motivo_cancelacion  = null;
                while ($row = $result->fetch()) {
                    $id_motivo_cancelacion  = $row['id'];
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

                if ($id_usuario_solicitud == null){
                    throw new Exception('Usuario inexistente en el catalogo');
                }

                //  SE CANCELAN LAS CITAS AGENDADAS
                $phql = "   UPDATE tbagenda_citas 
                            SET 
                                activa = 0, 
                                id_motivo_cancelacion = :id_motivo_cancelacion, 
                                observaciones_cancelacion   = 'CANCELACION POR BAJA DE PACIENTE',
                                id_usuario_cancelacion      = :id_usuario_cancelacion
                            WHERE id_paciente = :id_paciente AND activa = 1 AND fecha_cita >= current_date";

                $result_delete  = $conexion->execute($phql,array(
                    'id_paciente'               => $id,
                    'id_motivo_cancelacion'     => $id_motivo_cancelacion,
                    'id_usuario_cancelacion'    => $id_usuario_solicitud
                ));
            }

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

    $app->get('/ctpacientes/fill_combo', function () use ($app,$db,$request) {
        try{
            //  CADENAA BUSCAR
            $cadena = $request->getQuery('cadena');

            //  QUERY DE BUSQUEDA
            $phql   = "SELECT * FROM (
                        SELECT  
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo
                        FROM ctpacientes a
                        WHERE estatus = 1
                        ) t1
                        WHERE (lower(t1.celular) ILIKE :cadena ) OR (lower(t1.nombre_completo) ILIKE :cadena)
                        ORDER BY t1.nombre_completo ASC;";

            $result = $db->query($phql,array('cadena' => "%".FuncionesGlobales::ToLower($cadena)."%",));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_return     = array();

            $arr_return[]   = array(
                'id'                => '-1',
                'nombre_completo'   => 'Nuevo registro',
                'celular'           => '',
                'nombre'            => '',
                'primer_apellido'   => '',
                'segundo_apellido'  => '',
            );
            
            if ($result){
                while($data = $result->fetch()){
                    $arr_return[]   = $data;
                }
            }

            return json_encode($arr_return);
        }catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/cttranstornos_neurodesarrollo/show', function () use ($app,$db,$request) {
        try{
            // Ejecutar el query y obtener el resultado
            $phql   = "SELECT * FROM cttranstornos_neurodesarrollo ORDER BY clave ASC";
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $arr_return = array();
            while ($row = $result->fetch()) {
                $arr_return[]   = $row;
            }
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
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

    $app->post('/ctpacientes/save_diagnoses', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            //  OBTENER PARAMETROS
            $id_paciente    = $app->request->getPost('id_paciente') ?? null;
            $obj_info       = $app->request->getPost('obj_info') ?? null;
            $usuario_solicitud  = $request->getPost('usuario_solicitud');

            if (empty($id_paciente)){
                throw new Exception('Par&aacute;metro Identificador vac&iacute;o');
            }

            //  SE BUSCA LA INFORMACION DEL PACIENTE
            $phql   = "SELECT * FROM ctpacientes WHERE id = :id_paciente";
            $result = $db->query($phql,array('id_paciente' => $id_paciente));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $flag_exists    = false;
            while ($row = $result->fetch()) {
                $flag_exists    = true;
            }

            if (!$flag_exists){
                throw new Exception('Paciente inexistente en el catalogo');
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

            if ($id_usuario_solicitud == null){
                throw new Exception('Usuario inexistente en el catalogo');
            }

            //  SE BORRAN LOS REGISTROS
            $phql   = " DELETE FROM tbpacientes_diagnosticos 
                        WHERE id_paciente = :id_paciente";

            $result = $conexion->execute($phql,array(
                'id_paciente'   => $id_paciente,
            ));

            //  SE AGREGAN LOS REGISTROS
            foreach($obj_info as $diagnostico){
                $phql   = " INSERT INTO tbpacientes_diagnosticos (id_transtorno,id_paciente,presento_evidencia,id_usuario_registro)
                            VALUES (:id_transtorno,:id_paciente,:presento_evidencia,:id_usuario_registro);";

                $result = $conexion->execute($phql,array(
                    'id_paciente'   => $id_paciente,
                    'id_transtorno' => $diagnostico['id_transtorno'],
                    'presento_evidencia'    => $diagnostico['presento_evidencia'],
                    'id_usuario_registro'   => $id_usuario_solicitud
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
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });
};