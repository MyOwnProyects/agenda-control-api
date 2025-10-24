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
            $id_agenda_cita         = $request->getQuery('id_agenda_cita') ?? null;
            
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

            if (!empty($id_agenda_cita)){
                $phql   .= ' AND EXISTS (
                                SELECT 1 FROM tbagenda_citas tac 
                                WHERE tac.id = :id_agenda_cita AND tac.id_paciente = a.id 
                            )';
                $values['id_agenda_cita']   = $id_agenda_cita;
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
                $values['nombre'] = "%".FuncionesGlobales::ToLower($nombre)."%";
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
            $fecha_nacimiento   = $request->getPost('fecha_nacimiento') ?? null;
            $genero             = $request->getPost('genero') ?? null;
            $correo_electronico = $request->getPost('correo_electronico') ?? null;
            $direccion          = $request->getPost('direccion') ?? null;
    
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

            if (!FuncionesGlobales::validarTelefono($celular)){
                throw new Exception('Parámetro "Celular" invalido');
            }

            if (!empty($correo_electronico) && !FuncionesGlobales::validarCorreo($correo_electronico)){
                throw new Exception('Parámetro "Correo electronico" invalido.');
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
                        fecha_nacimiento = :fecha_nacimiento,
                        genero = :genero,
                        correo_electronico = :correo_electronico,
                        direccion = :direccion
                    WHERE id = :id";
    
            $values = [
                'primer_apellido'   => $primer_apellido,
                'segundo_apellido'  => $segundo_apellido,
                'nombre'            => $nombre,
                'celular'           => $celular,
                'fecha_nacimiento'  => $fecha_nacimiento,
                'genero'            => $genero,
                'correo_electronico'    => $correo_electronico,
                'direccion'             => $direccion,
                'id'                    => $id            
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
                                id_usuario_cancelacion      = :id_usuario_cancelacion,
                                fecha_cancelacion = now()
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
            $id_paciente    = $request->getPost('id_paciente') ?? null;
            $obj_info       = $request->getPost('obj_info') ?? null;
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

    $app->delete('/ctpacientes/delete', function () use ($app, $db,$request) {
        try{

            //  REGISTRO A BORRAR
            $id     = $request->getPost('id_paciente');

            //  SE BUSCA SI EL PACIENTE ESTA COMO ACTIVO
            $phql   = "SELECT * FROM ctpacientes WHERE id = :id AND estatus = 1";
            $result = $db->query($phql,array('id' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    throw new Exception('El paciente solo puede ser borrado si esta en estatus de INACTIVO');
                }
            }

            //  SE BUSCA SI TIENE CITAS REGISTRADAS
            $phql   = "SELECT COUNT(*) as registros_historicos FROM tbagenda_citas WHERE id_paciente = :id_paciente";
            $result = $db->query($phql,array('id_paciente' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    if ($data['registros_historicos'] > 0){
                        throw new Exception('El paciente cuenta con '.$data['registros_historicos'].' registros de citas, ya sean futuras o historicas.');
                    }
                }
            }

            $phql   = "DELETE FROM ctpacientes WHERE id = :id";
            $result = $db->execute($phql, array('id' => $id));

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

    $app->get('/ctpacientes/get_digital_record', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente        = $request->getQuery('id_paciente') ?? null;
            $id_agenda_cita     = $request->getQuery('id_agenda_cita') ?? null;
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            $arr_return = array(
                'info_paciente' => array(),
                'citas_activas' => 0,
                'areas_enfoque' => array(),
                'info_citas_programadas'    => array(),
                'tipo_archivos'             => array(),
                'archivos'                  => array()
            );
            
            if (empty($id_paciente) && empty($id_agenda_cita)){
                throw new Exception("Parametro de id invalido");
            }

            if (!empty($id_agenda_cita)){
                $phql   = "SELECT * FROM tbagenda_citas WHERE id = :id_agenda_cita";
                $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                // Recorrer los resultados
                $flag_exists    = false;
                while ($row = $result->fetch()) {
                    $flag_exists    = true;
                    $id_paciente    = $row['id_paciente'];
                }

                if (!$flag_exists){
                    throw new Exception("Cita inexistente en la agenda", 404);
                }
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            c.nombre as locacion_registro,
                            a.fecha_nacimiento,
                            CASE 
                                WHEN fecha_nacimiento IS NOT NULL THEN
                                    EXTRACT(YEAR FROM AGE(CURRENT_DATE, fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(CURRENT_DATE, fecha_nacimiento))::text, 1, '0')
                                ELSE NULL
                            END AS edad_actual
                        FROM ctpacientes a 
                        LEFT JOIN ctlocaciones c ON a.id_locacion_registro = c.id
                        WHERE a.id = :id_paciente ";
            $values = array();
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVO' : 'INACTIVO';
                $arr_return['info_paciente']    = $row;
            }

            //  SE BUSCA SI TIENE CITAS PROGRAMADAS
            $phql   = " SELECT  
                            d.nombre as nombre_locacion,
                            (e.primer_apellido|| ' ' ||COALESCE(e.segundo_apellido,'')||' '||e.nombre) as nombre_profesional,
                            f.clave as clave_servicio,
                            f.codigo_color,
                            c.dia,
                            TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(c.hora_termino, 'HH24:MI') AS hora_termino
                        FROM tbcitas_programadas a 
                        LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
                        LEFT JOIN tbcitas_programadas_servicios_horarios c ON b.id = c.id_cita_programada_servicio
                        LEFT JOIN ctlocaciones d ON a.id_locacion = d.id
                        LEFT JOIN ctprofesionales e ON b.id_profesional = e.id
                        LEFT JOIN ctservicios f ON b.id_servicio = f.id

                        WHERE a.id_paciente = :id_paciente AND b.id IS NOT NULL
                        ORDER BY c.dia,c.hora_inicio
                        ";

            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                $arr_return['info_citas_programadas'][] = $row;
            }

            //  SE BUSCA SI EL PACIENTE TIENE UN DIAGNOSTICO
            $arr_return['diagnosticos'] = array();
            $phql   = " SELECT 
                            a.presento_evidencia,
                            b.* 
                        FROM tbpacientes_diagnosticos a
                        LEFT JOIN cttranstornos_neurodesarrollo b ON a.id_transtorno = b.id
                        WHERE a.id_paciente = :id_paciente 
                        ORDER BY b.clave ASC";

            $result_diagnosticos    = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result_diagnosticos->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result_diagnosticos){
                while($data_diagnostico = $result_diagnosticos->fetch()){
                    $arr_return['diagnosticos'][]   = $data_diagnostico;
                }
            }

            //  EL USUARIO TIENE AL MENOS UNA CITA ACTIVA O AL MENOS CITA PROGRAMADA CAPTURADA,
            //  DE NO SER ASI NO PODRA ACCEDER A VER SUS NOTAS
            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_profesional = null;
            while ($row = $result->fetch()) {
                $id_profesional = $row['id_profesional'];
            }

            if ($id_profesional != null){
                $phql   = " SELECT 
                                1 as flag_cita 
                            FROM tbagenda_citas 
                            WHERE id_paciente = :id_paciente AND id_profesional = :id_profesional
                            AND activa <> 0
                            
                            UNION 
                            
                            SELECT 
                                1 as flag_cita 
                            FROM tbcitas_programadas a 
                            LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
                            WHERE a.id_paciente = :id_paciente AND b.id_profesional = :id_profesional
                            ";
            
                $result_diagnosticos    = $db->query($phql,array(
                    'id_paciente'       => $id_paciente,
                    'id_profesional'    => $id_profesional
                ));
                $result_diagnosticos->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result_diagnosticos){
                    while($data_diagnostico = $result_diagnosticos->fetch()){
                        $arr_return['citas_activas']    = 1;
                        break;
                    }
                }
            }

            //  AREAS DE ENFOQUE
            $phql   = " SELECT 
                            b.id as id_subarea_enfoque,
                            b.nombre as nombre_subarea,
                            c.clave,
                            c.nombre as nombre_area,
                            a.id_profesional_registro 
                        FROM tbpacientes_areas_refuerzo a
                        LEFT JOIN ctareas_enfoque_subarea b ON a.id_subarea_enfoque = b.id
                        LEFT JOIN ctareas_enfoque c ON b.id_area_enfoque = c.id
                        WHERE a.id_paciente = :id_paciente
                        ORDER BY c.clave,b.nombre";

            $result_engoque = $db->query($phql,array(
                'id_paciente'       => $id_paciente
            ));
            $result_engoque->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result_engoque){
                while($data_enfoque = $result_engoque->fetch()){
                    if (!isset($arr_return['areas_enfoque'][$data_enfoque['clave']])){
                        $arr_return['areas_enfoque'][$data_enfoque['clave']]['info']    = array(
                            'nombre'    => $data_enfoque['nombre_area']
                        );
                    }

                    $arr_return['areas_enfoque'][$data_enfoque['clave']]['subarea'][]   = $data_enfoque; 
                }
            }

            //  OBTENER ARCHIVOS DEL PACIENTE
            $phql   = " SELECT 
                            a.*,
                            (b.primer_apellido||' '||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_completo,
                            d.nombre as nombre_tipo_archivo,
                            d.clave as clave_tipo_archivo
                        FROM tbpacientes_archivos a
                        LEFT JOIN ctpacientes b ON a.id_paciente = b.id
                        LEFT JOIN tbagenda_citas c ON a.id_agenda_cita = c.id
                        LEFT JOIN cttipo_archivos d ON a.id_tipo_archivo = d.id
                        WHERE a.id_paciente = :id_paciente
                        ORDER BY d.nombre,a.nombre_original;";

            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $row['fecha_registro']      = FuncionesGlobales::formatearFecha($row['fecha_registro']);
                $arr_return['archivos'][]   = $row;
            }

            //  OBTIENEN TODAS LAS CATEGORIAS DE DOCUMENTOS
            $phql   = "SELECT * FROM cttipo_archivos ORDER BY clave;";
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $arr_return['tipo_archivos'][]  = $row;
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

    $app->post('/ctpacientes/save_file', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id_paciente        = $request->getPost('id_paciente');
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $id_tipo_archivo    = $request->getPost('id_tipo_archivo');
            $nombre_archivo     = $request->getPost('nombre_archivo');
            $nombre_original    = $request->getPost('nombre_original');
            $observaciones      = $request->getPost('observaciones') ?? null;
            $id_agenda_cita     = $request->getPost('id_agenda_cita') ?? null;

            if ($id_agenda_cita == ''){
                $id_agenda_cita = null;
            }

            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_usuario = null;
            while ($row = $result->fetch()) {
                $id_usuario = $row['id'];
            }

            //  SE CREA EL REGISTRO
            $phql   = " INSERT INTO tbpacientes_archivos (
                                        id_paciente,
                                        id_agenda_cita,
                                        id_tipo_archivo,
                                        nombre_archivo,
                                        nombre_original,
                                        id_usuario_captura,
                                        observaciones
                                    )
                        VALUES (
                                    :id_paciente,
                                    :id_agenda_cita,
                                    :id_tipo_archivo,
                                    :nombre_archivo,
                                    :nombre_original,
                                    :id_usuario_captura,
                                    :observaciones
                                )";

            $values = array(
                'id_paciente'           => $id_paciente,
                'id_agenda_cita'        => $id_agenda_cita,
                'id_tipo_archivo'       => $id_tipo_archivo,
                'nombre_archivo'        => $nombre_archivo,
                'nombre_original'       => $nombre_original,
                'id_usuario_captura'    => $id_usuario,
                'observaciones'         => $observaciones,
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

    $app->delete('/ctpacientes/delete_file', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id_paciente    = $request->getPost('id_paciente');
            $id_archivo     = $request->getPost('id_archivo');
            $nombre_archivo = null;

            $values = array(
                'id_paciente'   => $id_paciente,
                'id'            => $id_archivo,
            );

            //  SE OBTIENE EL NOMBRE DEL ARCHIVO
            $phql   = "SELECT * FROM tbpacientes_archivos WHERE id = :id AND id_paciente = :id_paciente";
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $nombre_archivo = $row['nombre_archivo'];
            }

            if (empty($nombre_archivo)){
                throw new Exception("Error: archivo inexistente", 404);
                
            }

            //  SE CREA EL REGISTRO
            $phql   = " DELETE FROM tbpacientes_archivos WHERE id = :id AND id_paciente = :id_paciente";

            $result = $db->execute($phql,$values);
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK','nombre_archivo' => $nombre_archivo));
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/ctpacientes/show_file', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id                 = $request->getQuery('id');
            $id_paciente        = $request->getQuery('id_paciente');
            $id_agenda_cita     = $request->getQuery('id_agenda_cita') ?? null;
            $arr_return         = array();

            //  SE CREA EL REGISTRO
            $phql   = " SELECT 
                            a.*,
                            (b.primer_apellido||' '||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_completo,
                            d.nombre as nombre_tipo_archivo,
                            d.clave as clave_tipo_archivo
                        FROM tbpacientes_archivos a
                        LEFT JOIN ctpacientes b ON a.id_paciente = b.id
                        LEFT JOIN tbagenda_citas c ON a.id_agenda_cita = c.id
                        LEFT JOIN cttipo_archivos d ON a.id_tipo_archivo = d.id
                        WHERE a.id_paciente = :id_paciente";

            $values = array(
                'id_paciente'   => $id_paciente
            );

            if (!empty($id)){
                $phql           .= ' AND a.id = :id ';
                $values['id']   = $id;
            }

            $phql   .= ' ORDER BY d.nombre,a.nombre_original ';

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $arr_return = array();
            while ($row = $result->fetch()) {
                $arr_return[]   = $row;
            }
    
            // RESPUESTA JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
            $response->setStatusCode(200, 'OK');
            return $response;
            
        } catch (\Exception $e) {
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/ctareas_enfoque/show', function () use ($app,$db,$request) {
        try{
            // Ejecutar el query y obtener el resultado
            $phql   = " SELECT 
                            a.*, 
                            b.id as id_subarea_enfoque,
                            b.nombre as nombre_subarea
                        FROM ctareas_enfoque a  
                        LEFT JOIN ctareas_enfoque_subarea b ON a.id = b.id_area_enfoque
                        ORDER BY a.nombre ASC,b.nombre";
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $arr_return = array();
            while ($row = $result->fetch()) {
                if (!isset($arr_return[$row['clave']])){
                    $arr_return[$row['clave']]['info']  = array(
                        'clave'         => $row['clave'],
                        'nombre'        => $row['nombre'],
                        'descripcion'   => $row['descripcion'],
                    );
                }

                $arr_return[$row['clave']]['subarea'][] = array(
                    'id_subarea_enfoque'    => $row['id_subarea_enfoque'],
                    'nombre'                => $row['nombre_subarea'],
                );
                
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

    $app->post('/ctpacientes/save_subareas_focus', function () use ($app,$db,$request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            //  OBTENER PARAMETROS
            $id_paciente    = $request->getPost('id_paciente') ?? null;
            $obj_info       = $request->getPost('obj_info') ?? array();
            $usuario_solicitud  = $request->getPost('usuario_solicitud');

            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_profesional = null;
            while ($row = $result->fetch()) {
                $id_profesional = $row['id_profesional'];
            }

            if ($id_profesional == null){
                throw new Exception("Solo los profesionales pueden capturar este registro", 401);
            }

            //  SE BORRAN TODOS LOS REGISTROS DEL PACIENTE Y PROFESIONAL
            $phql   = "DELETE FROM tbpacientes_areas_refuerzo WHERE id_paciente = :id_paciente AND id_profesional_registro = :id_profesional";
            $result = $conexion->execute($phql,array(
                'id_paciente'       => $id_paciente,
                'id_profesional'    => $id_profesional,
            ));

            //  SE CREAN LOS NUEVOS REGISTROS
            $phql   = "INSERT INTO tbpacientes_areas_refuerzo (id_paciente,id_profesional_registro,id_subarea_enfoque)
                        VALUES (:id_paciente,:id_profesional,:id_subarea_enfoque)";

            foreach($obj_info as $id_subarea_enfoque){
                $result = $conexion->execute($phql,array(
                    'id_paciente'           => $id_paciente,
                    'id_profesional'        => $id_profesional,
                    'id_subarea_enfoque'    => $id_subarea_enfoque
                ));
            }
            
            $conexion->commit();

            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
            $response->setStatusCode(200, 'OK');
            return $response;
        }catch (\Exception $e){
            $conexion->rollback();
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
        
    });

    $app->get('/ctpacientes/get_clinical_data', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente        = $request->getQuery('id_paciente') ?? null;
            $id_agenda_cita     = $request->getQuery('id_agenda_cita') ?? null;
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            
            $arr_return = array(
                'info_paciente'         => array(),
                'motivo_consulta'       => array(),
                'exploracion_fisica'    => array(),
                'info_cita'             => array()
            );
            
            if (empty($id_paciente) && empty($id_agenda_cita)){
                throw new Exception("Parametro de id invalido");
            }

            if (!empty($id_agenda_cita)){
                $phql   = "SELECT * FROM tbagenda_citas WHERE id = :id_agenda_cita";
                $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                // Recorrer los resultados
                $flag_exists    = false;
                while ($row = $result->fetch()) {
                    $flag_exists    = true;
                    $id_paciente    = $row['id_paciente'];
                    $arr_return['info_cita']    = $row;
                }

                if (!$flag_exists){
                    throw new Exception("Cita inexistente en la agenda", 404);
                }
            }
        
            // Definir el query SQL
            $phql   = "SELECT  
                            a.*,
                            (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                            c.nombre as locacion_registro,
                            a.fecha_nacimiento,
                            CASE 
                                WHEN fecha_nacimiento IS NOT NULL THEN
                                    EXTRACT(YEAR FROM AGE(CURRENT_DATE, fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(CURRENT_DATE, fecha_nacimiento))::text, 1, '0')
                                ELSE NULL
                            END AS edad_actual
                        FROM ctpacientes a 
                        LEFT JOIN ctlocaciones c ON a.id_locacion_registro = c.id
                        WHERE a.id = :id_paciente ";
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVO' : 'INACTIVO';
                $arr_return['info_paciente']    = $row;
            }

            //  MOTIVOS DE CONSULTA
            $phql   = " SELECT  
                            a.*
                        FROM tbmotivo_consulta a 
                        LEFT JOIN tbagenda_citas b ON a.id_agenda_cita = b.id
                        WHERE a.id_agenda_cita = :id_agenda_cita ";

            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,array(
                'id_agenda_cita'    => $id_agenda_cita
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $row['fecha_registro']              = FuncionesGlobales::formatearFecha($row['fecha_registro']);
                $arr_return['motivo_consulta'][]    = $row;
            }

            //  EXPLORACION FISICA
            $phql   = " SELECT  
                            a.*
                        FROM tbexploracion_fisica a 
                        LEFT JOIN tbagenda_citas b ON a.id_agenda_cita = b.id
                        WHERE a.id_agenda_cita = :id_agenda_cita";
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,array(
                'id_agenda_cita'    => $id_agenda_cita
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $row['fecha_registro']              = FuncionesGlobales::formatearFecha($row['fecha_registro']);
                $arr_return['exploracion_fisica'][] = $row;
            }

            return json_encode($arr_return);
    
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

    $app->post('/ctpacientes/save_exploracion_fisica', function () use ($app,$db,$request) {
        try {
    
            //  OBTENER PARAMETROS
            $id_paciente    = $request->getPost('id_paciente') ?? null;
            $id_agenda_cita = $request->getPost('id_agenda_cita') ?? null;
            $obj_info       = $request->getPost('obj_info') ?? array();
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $imc                = null;

            if (empty($id_paciente) && empty($id_agenda_cita)){
                throw new Exception("Parametro de id invalido");
            }

            if (!empty($id_agenda_cita)){
                $phql   = "SELECT * FROM tbagenda_citas WHERE id = :id_agenda_cita";
                $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                // Recorrer los resultados
                $flag_exists    = false;
                while ($row = $result->fetch()) {
                    $flag_exists    = true;
                    $id_paciente    = $row['id_paciente'];
                }

                if (!$flag_exists){
                    throw new Exception("Cita inexistente en la agenda", 404);
                }
            }

            if (isset($obj_info['peso']) && isset($obj_info['altura'])){
                $imc    = FuncionesGlobales::calcularIMC($obj_info['peso'],$obj_info['altura']);
            }

            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_profesional = null;
            while ($row = $result->fetch()) {
                $id_profesional = $row['id_profesional'];
            }

            //  SI EXISTEN REGISTROS CAPTURADOS DEBE DE SER UNA EDICION
            $phql   = "SELECT * FROM tbexploracion_fisica WHERE id_agenda_cita = :id_agenda_cita";
            $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $flag_exists    = false;
            while ($row = $result->fetch()) {
                $flag_exists    = true;
                //  SE HACE EL UPDATE
                $phql   = "UPDATE tbexploracion_fisica SET 
                                peso = :peso,
                                altura = :altura,
                                imc = :imc,
                                temperatura = :temperatura,
                                frecuencia_cardiaca = :frecuencia_cardiaca,
                                frecuencia_respiratoria = :frecuencia_respiratoria,
                                presion_arterial_sistolica = :presion_arterial_sistolica,
                                presion_arterial_diastolica = :presion_arterial_diastolica,
                                saturacion_oxigeno = :saturacion_oxigeno,
                                alergias = :alergias,
                                observaciones = :observaciones
                            WHERE id = :id";
                
                $values = array(
                    'peso'                          => isset($obj_info['peso']) ? $obj_info['peso'] : null,
                    'altura'                        => isset($obj_info['altura']) ? $obj_info['altura'] : null,
                    'imc'                           => $imc,
                    'temperatura'                   => isset($obj_info['temperatura']) ? $obj_info['temperatura'] : null,
                    'frecuencia_cardiaca'           => isset($obj_info['frecuencia_cardiaca']) ? $obj_info['frecuencia_cardiaca'] : null,
                    'frecuencia_respiratoria'       => isset($obj_info['frecuencia_respiratoria']) ? $obj_info['frecuencia_respiratoria'] : null,
                    'presion_arterial_sistolica'    => isset($obj_info['presion_arterial_sistolica']) ? $obj_info['presion_arterial_sistolica'] : null,
                    'presion_arterial_diastolica'   => isset($obj_info['presion_arterial_diastolica']) ? $obj_info['presion_arterial_diastolica'] : null,
                    'saturacion_oxigeno'            => isset($obj_info['saturacion_oxigeno']) ? $obj_info['saturacion_oxigeno'] : null,
                    'alergias'                      => isset($obj_info['alergias']) ? $obj_info['alergias'] : null,
                    'observaciones'                 => isset($obj_info['observaciones']) ? $obj_info['observaciones'] : null,
                    'id'                            => $row['id']
                );

                $result = $db->query($phql,$values);

            }

            //  SE HACE EL INSERT
            if (!$flag_exists){
                $phql   = "INSERT INTO tbexploracion_fisica (
                                id_paciente,
                                id_agenda_cita,
                                peso,
                                altura,
                                imc,
                                temperatura,
                                frecuencia_cardiaca,
                                frecuencia_respiratoria,
                                presion_arterial_sistolica,
                                presion_arterial_diastolica,
                                saturacion_oxigeno,
                                alergias,
                                observaciones,
                                id_profesional_registro
                            )
                            VALUES (
                                :id_paciente,
                                :id_agenda_cita,
                                :peso,
                                :altura,
                                :imc,
                                :temperatura,
                                :frecuencia_cardiaca,
                                :frecuencia_respiratoria,
                                :presion_arterial_sistolica,
                                :presion_arterial_diastolica,
                                :saturacion_oxigeno,
                                :alergias,
                                :observaciones,
                                :id_profesional_registro
                            )";
                
                $values = array(
                    'id_paciente'                   => $id_paciente,
                    'id_agenda_cita'                => $id_agenda_cita,
                    'peso'                          => isset($obj_info['peso']) ? $obj_info['peso'] : null,
                    'altura'                        => isset($obj_info['altura']) ? $obj_info['altura'] : null,
                    'imc'                           => $imc,
                    'temperatura'                   => isset($obj_info['temperatura']) ? $obj_info['temperatura'] : null,
                    'frecuencia_cardiaca'           => isset($obj_info['frecuencia_cardiaca']) ? $obj_info['frecuencia_cardiaca'] : null,
                    'frecuencia_respiratoria'       => isset($obj_info['frecuencia_respiratoria']) ? $obj_info['frecuencia_respiratoria'] : null,
                    'presion_arterial_sistolica'    => isset($obj_info['presion_arterial_sistolica']) ? $obj_info['presion_arterial_sistolica'] : null,
                    'presion_arterial_diastolica'   => isset($obj_info['presion_arterial_diastolica']) ? $obj_info['presion_arterial_diastolica'] : null,
                    'saturacion_oxigeno'            => isset($obj_info['saturacion_oxigeno']) ? $obj_info['saturacion_oxigeno'] : null,
                    'alergias'                      => isset($obj_info['alergias']) ? $obj_info['alergias'] : null,
                    'observaciones'                 => isset($obj_info['observaciones']) ? $obj_info['observaciones'] : null,
                    'id_profesional_registro'       => $id_profesional
                );

                $result = $db->query($phql,$values);
            }

            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
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

    // Ruta principal para obtener todos los usuarios
    $app->get('/ctpacientes/show_exploracion_fisica', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente        = $request->getQuery('id_paciente');
            $id_profesional     = $request->getQuery('id_profesional');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            $id_agenda_cita     = $request->getQuery('id_agenda_cita') ?? null;
            
            // Definir el query SQL
            $phql   = " SELECT  
                            a.*
                        FROM tbexploracion_fisica a 
                        LEFT JOIN tbagenda_citas b ON a.id_agenda_cita = b.id
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($id_agenda_cita)){
                $phql           .= " AND a.id_agenda_cita = :id_agenda_cita ";
                $values['id_agenda_cita']   = $id_agenda_cita;
            }
    
            if (!empty($id_paciente)){
                $phql           .= " AND (a.id_paciente = :id_paciente OR b.id_paciente = :id_paciente)";
                $values['id_paciente']  = $id_paciente;
            }

            $phql   .= " ORDER BY a.fecha_registro DESC ";

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['fecha_registro']  = FuncionesGlobales::formatearFecha($row['fecha_registro']);
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

    // Ruta principal para obtener todos los usuarios
    $app->get('/ctpacientes/show_motivo_consulta', function () use ($app,$db,$request) {
        try{
            //  PARAMETROS
            $id_paciente        = $request->getQuery('id_paciente');
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            $id_agenda_cita     = $request->getQuery('id_agenda_cita') ?? null;
            
            // Definir el query SQL
            $phql   = " SELECT  
                            a.*
                        FROM tbmotivo_consulta a 
                        LEFT JOIN tbagenda_citas b ON a.id_agenda_cita = b.id
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($id_agenda_cita)){
                $phql           .= " AND a.id_agenda_cita = :id_agenda_cita ";
                $values['id_agenda_cita']   = $id_agenda_cita;
            }
    
            if (!empty($id_paciente)){
                $phql           .= " AND (a.id_paciente = :id_paciente OR b.id_paciente = :id_paciente)";
                $values['id_paciente']  = $id_paciente;
            }

            $phql   .= " ORDER BY a.fecha_registro DESC ";

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['fecha_registro']  = FuncionesGlobales::formatearFecha($row['fecha_registro']);
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

    $app->post('/ctpacientes/save_motivo_consulta', function () use ($app,$db,$request) {
        try {
    
            //  OBTENER PARAMETROS
            $id_paciente    = $request->getPost('id_paciente') ?? null;
            $id_agenda_cita = $request->getPost('id_agenda_cita') ?? null;
            $obj_info       = $request->getPost('obj_info') ?? array();
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $id_usuario_registro    = null;

            if (empty($id_paciente) && empty($id_agenda_cita)){
                throw new Exception("Parametro de id invalido");
            }

            if (!empty($id_agenda_cita)){
                $phql   = " SELECT 
                                *,
                                TO_CHAR(hora_inicio, 'HH24:MI') AS start,
                                TO_CHAR(hora_termino, 'HH24:MI') AS end
                            FROM tbagenda_citas WHERE id = :id_agenda_cita";
                $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                // Recorrer los resultados
                $flag_exists    = false;
                while ($row = $result->fetch()) {
                    $flag_exists    = true;
                    $id_paciente    = $row['id_paciente'];
                }

                if (!$flag_exists){
                    throw new Exception("Cita inexistente en la agenda", 404);
                }
            }

            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $id_usuario_registro    = $row['id'];
            }

            //  SI EXISTEN REGISTROS CAPTURADOS DEBE DE SER UNA EDICION
            $phql   = "SELECT * FROM tbmotivo_consulta WHERE id_agenda_cita = :id_agenda_cita";
            $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $flag_exists    = false;
            while ($row = $result->fetch()) {
                $flag_exists    = true;
                //  SE HACE EL UPDATE
                $phql   = "UPDATE tbmotivo_consulta SET 
                                motivo_consulta = :motivo_consulta,
                                padecimiento_actual = :padecimiento_actual,
                                antecedentes_relevantes = :antecedentes_relevantes
                            WHERE id = :id ";
                
                $values = array(
                    'motivo_consulta'           => $obj_info['motivo_consulta'],
                    'padecimiento_actual'       => $obj_info['padecimiento_actual'],
                    'antecedentes_relevantes'   => $obj_info['antecedentes_relevantes'],
                    'id'                        => $row['id']
                );

                $result = $db->query($phql,$values);

            }

            //  SE HACE EL INSERT
            if (!$flag_exists){
                $phql   = "INSERT INTO tbmotivo_consulta (
                                id_paciente,
                                id_agenda_cita,
                                motivo_consulta,
                                padecimiento_actual,
                                antecedentes_relevantes,
                                id_usuario_registro
                            )
                            VALUES (
                                :id_paciente,
                                :id_agenda_cita,
                                :motivo_consulta,
                                :padecimiento_actual,
                                :antecedentes_relevantes,
                                :id_usuario_registro
                            )";
                
                $values = array(
                    'id_paciente'               => $id_paciente,
                    'id_agenda_cita'            => $id_agenda_cita,
                    'motivo_consulta'           => $obj_info['motivo_consulta'],
                    'padecimiento_actual'       => $obj_info['padecimiento_actual'],
                    'antecedentes_relevantes'   => $obj_info['antecedentes_relevantes'],
                    'id_usuario_registro'       => $id_usuario_registro
                );

                $result = $db->query($phql,$values);
            }

            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
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