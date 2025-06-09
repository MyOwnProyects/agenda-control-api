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
    $app->get('/tbagenda_citas/count', function () use ($app,$db,$request) {
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
                        FROM tbagenda_citas a 
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
    $app->get('/tbagenda_citas/show', function () use ($app,$db,$request) {
        try{
            $id             = $request->getQuery('id') ?? null;
            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $rango_fechas   = $request->getQuery('rango_fechas') ?? null;
            $activa         = $request->getQuery('activa') ?? null;
            $id_profesional = $request->getQuery('id_profesional') ?? null;
            $usuario_solicitud  = $request->getQuery('usuario_solicitud');
            $get_servicios      = $request->getQuery('get_servicios') ?? null;

            // Definir el query SQL
            $phql   = " SELECT  
                            a.id as id_agenda_cita,
                            a.id_cita_programada,
                            a.asistencia,
                            a.dia as day,
                            a.fecha_cita,
                            TO_CHAR(a.hora_inicio, 'HH24:MI') AS start,
                            TO_CHAR(a.hora_termino, 'HH24:MI') AS end,
                            CEIL(EXTRACT(EPOCH FROM (a.hora_termino - a.hora_inicio)) / 60) AS duracion,
                            (b.primer_apellido|| ' ' ||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_completo,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_profesional,
                            a.id_profesional,
                            b.celular,
                            b.primer_apellido,
                            COALESCE(b.segundo_apellido,'') as segundo_apellido,
                            b.nombre,
                            a.total,
                            a.id_cita_reagendada,
                            a.id_paciente
                        FROM tbagenda_citas a 
                        LEFT JOIN ctpacientes b ON a.id_paciente = b.id
                        LEFT JOIN ctprofesionales c ON a.id_profesional = c.id
                        WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (!empty($id_locacion)) {
                $phql           .= " AND a.id_locacion = :id_locacion ";
                $values['id_locacion']  = $id_locacion;
            }

            if (!empty($rango_fechas)){
                $phql   .= " AND a.fecha_cita BETWEEN :fecha_inicio AND :fecha_termino ";
                $values['fecha_inicio']     = $rango_fechas['fecha_inicio'];
                $values['fecha_termino']    = $rango_fechas['fecha_termino'];
            }

            if (is_numeric($activa)){
                $phql               .= " AND a.activa = :activa";
                $values['activa']   = $activa;
            }

            if (is_numeric($id_profesional)){
                $phql           .= " AND a.id_profesional = :id_profesional";
                $values['id_profesional']   = $id_profesional;
            }

            $phql   .= ' ORDER BY a.fecha_cita,a.hora_inicio,a.hora_termino ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['servicios']   = array();
                if (!empty($get_servicios)){
                    $phql   = " SELECT 
                                    a.*,
                                    b.nombre as nombre_servicio
                                FROM tbagenda_citas_servicios a 
                                LEFT JOIN ctservicios b ON a.id_servicio = b.id
                                WHERE a.id_agenda_cita = :id_agenda_cita";
                    $result_servicios = $db->query($phql,array('id_agenda_cita' => $row['id_agenda_cita']));
                    $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    if ($result_servicios){
                        while($data_servicios = $result_servicios->fetch()){
                            $data_servicios['duracion'] = $data_servicios['duracion'] / 60;
                            $row['servicios'][]         = $data_servicios;
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

    $app->get('/tbapertura_agenda/show', function () use ($app,$db,$request) {
        try{

            $id_locacion    = $request->getQuery('id_locacion') ?? null;
            $zona_horario   = date_default_timezone_get();

            //  SE BUSCA SI EXISTE UN REGISTRO DE APERTURA DE AGENDA
            $phql   = "SELECT t2.fecha_limite as last_fecha_limite,t2.has_record,current_date as fecha_actual from (
                        SELECT fecha_limite,has_record from (
                        SELECT fecha_limite::DATE, 1 as has_record FROM tbapertura_agenda 
                        WHERE id_locacion = :id_locacion ORDER BY fecha_limite DESC LIMIT 1
                        )t1
                        UNION ALL
                        SELECT (current_date)::DATE as fecha_limite, 0 as has_record
                        ) t2 order by has_record desc limit 1";

            $values = array(
                'id_locacion'   => $id_locacion
            );

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_return = array();
            if ($result){
                while($data = $result->fetch()){
                    $arr_return = $data;
                }
            }

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

    $app->post('/tbapertura_agenda/save', function () use ($app,$db,$request) {
        try{

            $id_locacion    = $request->getPost('id_locacion') ?? null;
            $id_paciente    = $request->getPost('id_paciente') ?? null;
            $fecha_inicio   = $request->getPost('fecha_inicio') ?? null;
            $fecha_termino  = $request->getPost('fecha_termino') ?? null;
            $clave_usuario  = $request->getPost('usuario_solicitud') ?? null;

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

                $result_citas   = $db->execute($phql,$values);
            }catch(\Exception $err){
                throw new \Exception(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
            }
    
            // RESPUESTA JSON
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

    $app->delete('/tbagenda_citas/cancelar_cita', function () use ($app, $db,$request) {
        try{

            $id_agenda_cita         = $request->getPost('id_agenda_cita');
            $id_motivo_cancelacion  = $request->getPost('id_motivo_cancelacion');
            $observaciones_cancelacion  = $request->getPost('observaciones_cancelacion');
            $usuario_solicitud          = $request->getPost('usuario_solicitud');

            if (empty($id_agenda_cita)){
                throw new Exception('Parametro identificar de cita vacio');
            }

            //  SE BUSCA EL ID DEL USUARIO
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

            //  SE VERIFICA QUE LA CITA SE ENCUENTRE ACTIVA
            $phql   = "SELECT *  FROM tbagenda_citas WHERE id = :id_agenda_cita AND activa = 1";
            
            $result = $db->query($phql,array('id_agenda_cita' => $id_agenda_cita));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $flag_activa    = false;
            if ($result){
                while($data = $result->fetch()){
                    $flag_activa    = true;
                }
            }

            if (!$flag_activa){
                throw new Exception('La cita ya se encuentra cancelada');
            }

            $phql   = " UPDATE tbagenda_citas SET 
                            activa = 0,
                            id_motivo_cancelacion = :id_motivo_cancelacion,
                            observaciones_cancelacion = :observaciones_cancelacion,
                            id_usuario_cancelacion = :id_usuario_cancelacion,
                            fecha_cancelacion = NOW()  
                        WHERE id = :id";
            $values = array(
                'id'                        => $id_agenda_cita,
                'id_motivo_cancelacion'     => $id_motivo_cancelacion,
                'observaciones_cancelacion' => $observaciones_cancelacion,
                'id_usuario_cancelacion'    => $id_usuario_solicitud
            );
            $result = $db->execute($phql, $values);

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

    $app->put('/tbagenda_citas/modificar_asistencia', function () use ($app, $db, $request) {
        try {
    
            // OBTENER DATOS JSON
            $id_agenda_cita = $request->getPost('id_agenda_cita');
            $estatus_asistencia_actual   = $request->getPost('estatus_asistencia_actual');
            $nuevo_estatus_asistencia    = $request->getPost('nuevo_estatus_asistencia');
            
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($id_agenda_cita)) {
                throw new Exception('Parámetro "ID" vacío');
            }

            if (!is_numeric($estatus_asistencia_actual)) {
                throw new Exception('Parámetro "Estatus actual" vacío');
            }
    
            if (!is_numeric($nuevo_estatus_asistencia)) {
                throw new Exception('Parámetro "Nuevo estatus" vacío');
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM tbagenda_citas WHERE id = :id_agenda_cita AND asistencia <> :estatus_asistencia_actual";
    
            $result = $db->query($phql, array(
                'id_agenda_cita'    => $id_agenda_cita,
                'estatus_asistencia_actual' => $estatus_asistencia_actual
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('El estatus de la asistencia ha sido modificado previamentem, te sugerimos refrescar la vista.');
            }
    
            // INSERTAR NUEVO servicio
            $phql = "UPDATE tbagenda_citas SET
                                    asistencia = :nuevo_estatus_asistencia
                            WHERE id = :id_agenda_cita ";
    
            $values = [
                'id_agenda_cita'    => $id_agenda_cita,
                'nuevo_estatus_asistencia'  => $nuevo_estatus_asistencia,
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

    $app->post('/tbagenda_citas/save', function () use ($app,$db,$request) {
        $conexion = $db; 
        try{
            $conexion->begin();
            //  SE VERIFICAN LOS CAMPOS OBLIGATORIOS
            $id_paciente        = $request->getPost('id_paciente');
            $celular            = $request->getPost('celular');
            $primer_apellido    = $request->getPost('primer_apellido');
            $segundo_apellido   = $request->getPost('segundo_apellido');
            $nombre             = $request->getPost('nombre');
            $servicios          = $request->getPost('servicios');
            $id_profesional     = $request->getPost('id_profesional');
            $id_locacion        = $request->getPost('id_locacion');
            $fecha_cita         = $request->getPost('fecha_cita');
            $dia                = $request->getPost('dia');
            $hora_inicio        = $request->getPost('hora_inicio');
            $hora_termino       = $request->getPost('hora_termino');
            $usuario_solicitud  = $request->getPost('usuario_solicitud');
            $id_agenda_cita_anterior    = $request->getPost('id_agenda_cita');
            $accion                     = $request->getPost('accion');
            $id_cita_programada         = null;

            //  SE VERIFICAN LOS CAMPOS OBLIGATORIOS
            if (empty($id_profesional) || !is_numeric($id_profesional)){
                throw new Exception('Identificador de Profesional vacio o no valido');
            }

            if (empty($id_locacion) || !is_numeric($id_locacion)){
                throw new Exception('Identificador de locacion vacio o no valido');
            }

            if (empty($dia) || !is_numeric($id_locacion)){
                throw new Exception('Dia vacio o no valido');
            }

            if (empty($fecha_cita)){
                throw new Exception('Fecha de cita vacia o no valida');
            }

            $fecha_cita = DateTime::createFromFormat('d/m/Y', $fecha_cita)->format('Y-m-d');

            if (empty($hora_inicio)){
                throw new Exception('Fecha de cita vacia o no valida');
            }

            if (empty($hora_termino)){
                throw new Exception('Fecha de cita vacia o no valida');
            }

            if (count($servicios) == 0){
                throw new Exception('Fecha de cita vacia o no valida');
            }

            //  SE BUSCA EL ID DEL USUARIO
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

            //  SI TRAE ID_AGENDA_CITA, ESTA SE CANCELA POR EL MOTIVO INDICADO
            if (!empty($id_agenda_cita_anterior)){
                $phql   = "SELECT * FROM tbagenda_citas WHERE id = :id_agenda_cita AND activa = 1 ";
                $result = $db->query($phql, array(
                    'id_agenda_cita'   => $id_agenda_cita_anterior
                ));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                $flag_exist = false;
                if ($result){
                    while($data = $result->fetch()){
                        $flag_exist = true;
                        $id_paciente        = $data['id_paciente'];
                        $id_cita_programada = $data['id_cita_programada'];

                        //  SI VIENE EL CHECK DE CAMBIO DE DIA SE MARCA COMO REAGENDADO
                        $clave_cancelacion  = $accion == 'reagendar_cita' ? 'REA' : 'HOS';

                        //  SE OBTIENE EL ID DEL MOTIVO CON CLAVE CAS
                        $phql   = "SELECT * FROM ctmotivos_cancelacion_cita WHERE clave = :clave ";
                        $result_motivo  = $db->query($phql,array('clave' => $clave_cancelacion));
                        $result_motivo->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                        $id_motivo_cancelacion  = null;
                        while($data_motivo = $result_motivo->fetch()){
                            $id_motivo_cancelacion  = $data['id'];
                        }

                        if ($id_motivo_cancelacion == null){
                            throw new Exception('Error al obtener el motivo de la cancelacion');
                        }

                        $phql   = " UPDATE tbagenda_citas SET 
                                        activa = 0, 
                                        id_motivo_cancelacion = :id_motivo_cancelacion, 
                                        fecha_cancelacion = now(),
                                        id_usuario_cancelacion = :id_usuario_cancelacion
                                    WHERE id = :id";
                        $values = array(
                            'id_motivo_cancelacion'     => $id_motivo_cancelacion,
                            'id_usuario_cancelacion'    => $id_usuario_solicitud,
                            'id'                        => $id_agenda_cita_anterior
                        );

                        $result_update  = $conexion->execute($phql,$values);
                    }
                }

                if (!$flag_exist){
                    throw new Exception("No se puede editar la cita indicada ya que no se encuentra activa");
                }
            }
            

            //  SE VERIFICA SI EL ID PACIENTE EXISTE Y NO ESTA DADO DE BAJA
            if (!empty($id_paciente)){
                $phql   = "SELECT * FROM ctpacientes WHERE id = :id_paciente";

                $result = $db->query($phql, array(
                    'id_paciente'   => $id_paciente
                ));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                $flag_exist = false;
                while ($data = $result->fetch()) {
                    $flag_exist = true;
                    if ($data['estatus'] != 1){
                        throw new Exception("El paciente se encuentra dado de baja");
                    }
                }

                if (!$flag_exist){
                    throw new Exception('No existe registro del paciente seleccionado');
                }
            } else {
                //  SE REALIZA EL REGISTRO DEL PACIENTE
                if (empty($primer_apellido)){
                    throw new Exception('Apellido paterno vacio');
                }

                if (empty($nombre)){
                    throw new Exception('Nombre vacio');
                }

                if (empty($celular) || strlen($celular) != 10){
                    throw new Exception('Formato de celular erroneo o el dato esta vacio');
                }

                //  SE CREA EL REGISTRO
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
                                        id_locacion_registro
                                    ) 
                        VALUES (
                                    (select fn_crear_clave_paciente()), 
                                    :primer_apellido,
                                    :segundo_apellido,
                                    :nombre,
                                    :celular,
                                    :id_locacion_registro
                                ) RETURNING id";
        
                $values = [
                    'primer_apellido'       => $primer_apellido,
                    'segundo_apellido'      => $segundo_apellido,
                    'nombre'                => $nombre,
                    'celular'               => $celular,
                    'id_locacion_registro'  => $id_locacion,
                ];
        
                $result = $conexion->query($phql, $values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        
                if ($result) {
                    while ($data = $result->fetch()) {
                        $id_paciente    = $data['id'];
                    }
                }
        
                if (!is_numeric($id_paciente)) {
                    throw new Exception('Error al crear el registro del paciente');
                }
            }

            //  SE VERIFICA QUE EL DIA A CREAR NO SEA MENOR AL DIA DE HOY
            $flag_exist = false;
            $phql   = " SELECT 
                            CASE WHEN TO_DATE(:fecha_cita, 'YYYY-MM-DD') < current_date THEN 0 ELSE 1 END as fecha_permitida, 
                            CASE WHEN TO_DATE(:fecha_cita, 'YYYY-MM-DD') > fecha_limite THEN 0 ELSE 1 END as fecha_limite_apertura,
                            current_date AS hoy,
                            fecha_limite
                        FROM tbapertura_agenda 
                        WHERE id_locacion = :id_locacion 
                        ORDER BY fecha_limite DESC LIMIT 1;";

            $result = $conexion->query($phql, array(
                'id_locacion'   => $id_locacion,
                'fecha_cita'    => $fecha_cita
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            if ($result) {
                while ($data = $result->fetch()) {
                    $flag_exist = true;
                    if ($data['fecha_permitida'] != 1 ){
                        throw new Exception('La fecha ingresada es menor al dia de hoy: '.$data['hoy']);
                    }

                    if ($data['fecha_limite_apertura'] != 1 ){
                        throw new Exception('La fecha ingresada es mayor a la fecha limite de apertura de agenda: '.$data['fecha_limite_apertura']);
                    }
                }
            }

            if (!$flag_exist){
                throw new Execption('No existe registro de apertura de agenda para la locaci&oacute;n');
            }

            try{
                //  SE VERIFICA QUE EL DOCENTE O EL PACIENTE NO TENGAN UNA CITA
                //  QUE SE EMPALME CON LA HORA SOLICITADA
                $phql   = "SELECT * FROM fn_validar_citas_diarias(:id_profesional , :id_paciente, :fecha_cita, :hora_inicio, :hora_termino)";
                $result = $db->query($phql, array(
                    'id_profesional'    => $id_profesional,
                    'id_paciente'       => $id_paciente,
                    'fecha_cita'        => $fecha_cita,
                    'hora_inicio'       => $hora_inicio,
                    'hora_termino'      => $hora_termino,
                ));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                $flag_validar   = false;
                if ($result){
                    while($data = $result->fetch()){
                        $flag_create    = true;
                    }
                }

            } catch(\Exception $err){
                throw new \Exception(FuncionesGlobales::raiseExceptionMessage($err->getMessage()));
            }

            //  SE BUSCAN LOS COSTOS DE LOS SERVICIOS
            $phql   = "SELECT * FROM ctlocaciones_servicios WHERE id_locacion = :id_locacion";
            $result = $db->query($phql, array(
                'id_locacion'   => $id_locacion,
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_servicios  = array();
            if ($result){
                while($data = $result->fetch()){
                    $arr_servicios[$data['id_servicio']]    = $data;
                }
            }

            //  SE CREA EL REGISTRO DE LA CITA
            $phql   = "INSERT INTO tbagenda_citas (id_locacion,id_paciente,fecha_cita,dia,hora_inicio,hora_termino,id_profesional,id_usuario_agenda,total,id_cita_reagendada,id_cita_programada) 
                        VALUES(:id_locacion,:id_paciente,:fecha_cita,:dia,:hora_inicio,:hora_termino,:id_profesional,:id_usuario_agenda,0,:id_cita_reagendada,:id_cita_programada) RETURNING *;";

            $values = array(
                'id_locacion'       => $id_locacion,
                'id_paciente'       => $id_paciente,
                'fecha_cita'        => $fecha_cita,
                'dia'               => $dia,
                'hora_inicio'       => $hora_inicio,
                'hora_termino'      => $hora_termino,
                'id_profesional'    => $id_profesional,
                'id_usuario_agenda' => $id_usuario_solicitud,
                'id_cita_reagendada'    => is_numeric($id_agenda_cita_anterior) ? $id_agenda_cita_anterior : null, 
                'id_cita_programada'    => $id_cita_programada
            );

            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id_agenda_cita = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id_agenda_cita = $data['id'];
                }
            }

            if ($id_agenda_cita == null){
                throw new Exception('No se pudo realizar el registrode la cita');
            }

            $calcula_total  = 0;
            foreach($servicios as $servicio){
                $calcula_total  = $calcula_total + $servicio['costo'];
                //  SE OBTIENEN LOS COSTOS REGISTRADOS POR SERVICIO
                $phql   = "INSERT INTO tbagenda_citas_servicios (id_agenda_cita,id_servicio,duracion,costo)
                            VALUES (:id_agenda_cita,:id_servicio,:duracion,:costo)";
                $result = $conexion->execute($phql, array(
                    'id_agenda_cita'    => $id_agenda_cita,
                    'id_servicio'       => $servicio['id_servicio'],
                    'duracion'          => $servicio['duracion'] * 60,
                    'costo'             => $arr_servicios[$servicio['id_servicio']]['costo'],
                ));
            }

            //  SE UPDATEA EL COSTO TOTAL
            $phql   = "UPDATE tbagenda_citas SET total = :calcula_total WHERE id = :id_agenda_cita";
            $conexion->execute($phql,array(
                'calcula_total'     => $calcula_total,
                'id_agenda_cita'    => $id_agenda_cita
            ));

            //  CAMBIO PARA DEV 

            $conexion->commit();

            return json_encode(array('MSG' => 'OK'));


        }catch (\Exception $e) { 
            $conexion->rollback();
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });

    $app->get('/tbagenda_citas/get_today', function () use ($app,$db,$request) {
        try{
            $today  = '';
            $phql   = "SELECT current_date as today";

            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
            if ($result){
                while($data = $result->fetch()){
                    $today  = $data['today'];
                }
            }

            return json_encode(array('today' => $today));

        }catch (\Exception $e) { 
            $conexion->rollback();
            $response = new Response();
            $response->setJsonContent($e->getMessage());
            $response->setStatusCode(400, 'not found');
            return $response;
        }
    });
};
