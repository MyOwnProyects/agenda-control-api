<?php 

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Helpers\FuncionesGlobales;

return function (Micro $app,$di) {
    // Declarar el objeto request global
    $request = $app->getDI()->get('request');
    // Obtener el adaptador de base de datos desde el contenedor DI
    $db = $di->get('db');

    // Ruta principal para obtener todos los registros
    $app->get('/dashboard_menu/show', function () use ($app,$db,$request) {
        try{
            $id_locacion    = $request->getQuery('id_locacion');
            $clave_usuario  = $request->getQuery('usuario_solicitud') ?? null;

            $fecha_bd   = null;
            $hora_bd    = null;

            //  SE BUSCA EL TIPO USUARIO, SI ES ADMIN O RECEPCIONISTA DEJA VER TODAS LAS CITAS
            //  DE LO CONTRARIO MOSTRARA SOLO LAS CITAS DEL PROFESIONAL DEL MISMO USUARIO
            //  SE BUSCA EL ID DEL USUARIO
            $phql   = " SELECT a.*,b.clave as clave_tipo_usuario 
                        FROM ctusuarios a 
                        LEFT JOIN cttipo_usuarios b ON a.id_tipo_usuario = b.id 
                        WHERE a.clave = :clave_usuario";

            $result = $db->query($phql,array('clave_usuario' => $clave_usuario));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $id_usuario_solicitud   = null;
            $clave_tipo_usuario     = null;
            $id_profesional_usuario = null;
            if ($result){
                while($data = $result->fetch()){
                    $id_usuario_solicitud   = $data['id'];
                    $clave_tipo_usuario     = $data['clave_tipo_usuario'];
                    $id_profesional_usuario = $data['id_profesional'];
                }
            }

            //  QUERY PARA DOMINGO
            // $phql = "SELECT (CURRENT_DATE + INTERVAL '1 day')::DATE as CURRENT_DATE, TO_CHAR(NOW(), 'HH24:MI:SS') AS hora_actual";
            $phql = "SELECT CURRENT_DATE, TO_CHAR(NOW(), 'HH24:MI:SS') AS hora_actual";
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $fecha_bd   = $row['current_date'];
                // $hora_bd    = '15:44:55';//$row['hora_actual'];
                $hora_bd    = $row['hora_actual'];
            }

            $datetime = new DateTime($fecha_bd);

            // Clonamos para no alterar el original
            $fecha_inicio_semana  = clone $datetime;
            $fecha_termino_semana = clone $datetime;

            // Ajustamos al inicio (lunes) y fin (domingo)
            $fecha_inicio_semana->modify('monday this week');
            $fecha_termino_semana->modify('sunday this week');

            $fecha_inicio_param     = $fecha_inicio_semana->format('Y-m-d');
            $fecha_termino_param    = $fecha_termino_semana->format('Y-m-d');

            // Formatos

            // Etiqueta manual (sin intl)
            $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
            $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

            $dia_semana = $dias[$datetime->format('w')];
            $dia = $datetime->format('j');
            $mes = $meses[$datetime->format('n') - 1];

            $fecha_actual_label = ucfirst("$dia_semana, $dia de $mes");

            // Retorno final
            $arr_return = array(
                'fecha_actual'          => $fecha_bd,
                'hora_bd'               => $hora_bd,
                'fecha_actual_label'    => $fecha_actual_label,
                'fecha_inicio_semana'   => $fecha_inicio_semana->format('d/m/Y'),
                'fecha_termino_semana'  => $fecha_termino_semana->format('d/m/Y'),
                'citas'                 => [],
                'dia_semana'            => $dia_semana
            );

            $phql   = " SELECT 
                            a.id as id_agenda_cita,
                            a.id_locacion,
                            a.id_profesional,
                            a.dia,
                            a.fecha_cita,
                            a.id_paciente,
                            a.activa,
                            TO_CHAR(a.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(a.hora_termino, 'HH24:MI') AS hora_termino,
                            (b.primer_apellido|| ' ' ||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_completo,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_profesional
                        FROM tbagenda_citas a 
                        LEFT JOIN ctpacientes b ON a.id_paciente = b.id
                        LEFT JOIN ctprofesionales c ON a.id_profesional = c.id
                        LEFT JOIN ctmotivos_cancelacion_cita d ON a.id_motivo_cancelacion = d.id
                        WHERE a.fecha_cita BETWEEN :fecha_inicio AND :fecha_termino 
                        AND a.activa <> 2 AND (d.visible IS NULL OR d.visible = 1) ";

            $values = array(
                'fecha_inicio'  => $fecha_inicio_param,
                'fecha_termino' => $fecha_termino_param
            );

            if (!empty($id_locacion)){
                $phql   .= ' AND a.id_locacion = :id_locacion';
                $values['id_locacion']  = $id_locacion;
            }

            if ($clave_tipo_usuario != 'user_admin' && $clave_tipo_usuario != 'RECEP'){
                $phql   .= ' AND a.id_profesional = :id_profesional';
                $values['id_profesional']   = $id_profesional_usuario;
            }

            $phql   .= ' ORDER BY a.fecha_cita ASC, a.hora_inicio ASC,  b.primer_apellido, b.segundo_apellido, b.nombre';

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            // Recorrer los resultados
            while ($row = $result->fetch()) {
                $phql   = " SELECT 
                                a.id_agenda_cita,
                                b.clave,
                                b.nombre as nombre_servicio,
                                b.codigo_color
                            FROM tbagenda_citas_servicios a 
                            LEFT JOIN ctservicios b ON a.id_servicio = b.id
                            WHERE a.id_agenda_cita = :id_agenda_cita 
                            ORDER BY b.costo DESC";
                $result_servicios = $db->query($phql,array('id_agenda_cita' => $row['id_agenda_cita']));
                $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result_servicios){
                    while($data_servicios = $result_servicios->fetch()){
                        $row['servicios'][] = $data_servicios;
                    }
                }

                $arr_return['citas'][]  = $row;
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

    $app->get('/reportes/general_citas', function () use ($app,$db,$request) {
        try{
            //  REPORTE GENERAL DE CITAS EN EL RANGO DE FECHAS
            //  INCLUYE INFORMACION GENERAL DEL PACIENTES Y DE LA CITA
            $id_locacion    = $request->getQuery('id_locacion');
            $activa         = $request->getQuery('activa') ?? null;
            $id_profesional = $request->getQuery('id_profesional') ?? null;
            $id_paciente    = $request->getQuery('id_paciente') ?? null;
            $rango_fechas   = $request->getQuery('rango_fechas') ?? null;

            if (empty($rango_fechas)){
                throw new Exception('Rango de fechas vacio');
            }

            $dias_semana        = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
            $arr_estatus_asistencia = [
                0   => 'FALTA',
                1   => 'ASISTENCIA',
                2   => 'RETARDO',
                3   => 'ACTIVIDAD EN CASA',   
                null    => 'Sin asignar' 
            ];

            $arr_estatus_cita   = array(
                0   => 'CANCELADA',
                1   => 'ACTIVA',
                2   => 'PENDIENTE DE AGENDAR',
            );

            
            $phql   = " SELECT  
                            a.id as id_agenda_cita,
                            a.id_cita_programada,
                            a.asistencia,
                            a.dia as day,
                            a.fecha_cita,
                            TO_CHAR(a.hora_inicio, 'HH24:MI') AS hora_inicio,
                            TO_CHAR(a.hora_termino, 'HH24:MI') AS hora_termino,
                            CEIL(EXTRACT(EPOCH FROM (a.hora_termino - a.hora_inicio)) / 60) AS duracion,
                            (b.primer_apellido|| ' ' ||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_completo,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_profesional,
                            a.id_profesional,
                            b.celular,
                            b.primer_apellido,
                            COALESCE(b.segundo_apellido,'') as segundo_apellido,
                            b.nombre,
                            b.fecha_nacimiento,
                            a.total,
                            a.id_cita_reagendada,
                            a.id_paciente,
                            a.activa,
                            d.nombre as nombre_locacion,
                            a.fecha_captura,
                            a.fecha_cancelacion,
                            e.nombre as motivo_cancelacion,
                            (f.primer_apellido|| ' ' ||COALESCE(f.segundo_apellido,'')||' '||f.nombre) as usuario_cancelacion,
                            (g.primer_apellido|| ' ' ||COALESCE(g.segundo_apellido,'')||' '||g.nombre) as usuario_captura,
                            a.observaciones_cancelacion,
                            (CASE WHEN (a.fecha_cita + (h.valor)::integer * INTERVAL '1 day') < NOW()::DATE THEN 1 ELSE 0 END) as vencida,
                            a.id_locacion,
                            a.pagada,
                            a.fecha_pago,
                            a.forma_pago,
                            CASE 
                                WHEN b.fecha_nacimiento IS NOT NULL THEN
                                    EXTRACT(YEAR FROM AGE(a.fecha_cita, b.fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(a.fecha_cita, b.fecha_nacimiento))::text, 2, '0')
                                ELSE NULL
                            END AS edad_actual,
                            a.id_cita_simultanea,
                            a.id_motivo_cita_fuera_horario,
                            i.nombre as nombre_motivo_cita_fuera_horario,
                            a.observaciones_motivo_cita_fuera_horario,
                            COALESCE(j.num_citas_simultaneas,0) as num_citas_simultaneas
                        FROM tbagenda_citas a 
                        LEFT JOIN ctpacientes b ON a.id_paciente = b.id
                        LEFT JOIN ctprofesionales c ON a.id_profesional = c.id
                        LEFT JOIN ctlocaciones d ON a.id_locacion = d.id
                        LEFT JOIN ctmotivos_cancelacion_cita e ON a.id_motivo_cancelacion = e.id
                        LEFT JOIN ctusuarios f ON a.id_usuario_cancelacion = f.id
                        LEFT JOIN ctusuarios g ON a.id_usuario_agenda = g.id
                        LEFT JOIN ctvariables_sistema h ON h.clave = 'dias_movimientos_citas_vencidas'
                        LEFT JOIN ctmotivos_citas_fuera_horario i ON a.id_motivo_cita_fuera_horario = i.id
                        LEFT JOIN LATERAL ( 
                            SELECT t1.id_cita_simultanea,COUNT(*) AS num_citas_simultaneas  
                            FROM  tbagenda_citas t1
                            WHERE t1.id_cita_simultanea IS NOT NULL AND a.id = t1.id_cita_simultanea
                            AND t1.activa = 1
                            GROUP BY t1.id_cita_simultanea
                        ) j ON j.id_cita_simultanea = a.id
                        WHERE 1 = 1 ";
            $values = array();

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

            if (is_numeric($id_paciente)){
                $phql           .= " AND a.id_paciente = :id_paciente";
                $values['id_paciente']  = $id_paciente;
            }

            if (!empty($tipo_busqueda)){

                if ($tipo_busqueda == 'activas'){
                    $phql   .= " AND a.activa = 1 ";
                }

                if ($tipo_busqueda == 'pendientes'){
                    $phql   .= " AND a.activa = 2 ";
                }

                if ($tipo_busqueda == 'canceladas'){
                    $phql   .= " AND a.activa = 0 ";
                }
            }

            $phql   .= ' ORDER BY a.fecha_cita ASC,a.hora_inicio ASC,a.hora_termino ASC,b.nombre,b.primer_apellido';
    
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['servicios']   = array();
                $row['fecha_nacimiento']    = FuncionesGlobales::formatearFecha($row['fecha_nacimiento']);
                $row['fecha_captura']       = FuncionesGlobales::formatearFecha($row['fecha_captura']);
                $row['fecha_cita']          = FuncionesGlobales::formatearFecha($row['fecha_cita']);
                $row['estatus']             = $arr_estatus_cita[$row['activa']];
                $row['fecha_completa']      = $dias_semana[$row['day'] - 1].' '.FuncionesGlobales::formatearFecha($row['fecha_cita']) . ' de '. $row['hora_inicio']. ' a '.$row['hora_termino'];
                $row['label_pagada']        = $row['pagada'] == 1 ? 'SI' : 'NO';
                $row['label_dia']           = $dias_semana[$row['day'] - 1];
                $row['label_asistencia']        = $arr_estatus_asistencia[$row['asistencia']];
                $row['pagada']                  = $row['pagada'] == 1 ? 'SI' : 'NO';
                $row['total']                   = FuncionesGlobales::formatearDecimal($row['total']);
                $row['info_citas_simultaneas']  = array();
                $phql   = " SELECT 
                                a.*,
                                b.clave,
                                b.nombre as nombre_servicio,
                                b.codigo_color
                            FROM tbagenda_citas_servicios a 
                            LEFT JOIN ctservicios b ON a.id_servicio = b.id
                            WHERE a.id_agenda_cita = :id_agenda_cita 
                            ORDER BY b.costo DESC";
                $result_servicios = $db->query($phql,array('id_agenda_cita' => $row['id_agenda_cita']));
                $result_servicios->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result_servicios){
                    while($data_servicios = $result_servicios->fetch()){
                        $data_servicios['duracion'] = $data_servicios['duracion'] / 60;
                        $row['servicios'][]         = $data_servicios;
                    }
                }

                $row['codigo_color']    = $row['servicios'][0]['codigo_color'];

                $row['hora_cita']  = $row['hora_inicio'] . ' - ' . $row['hora_termino'];
                $row['num_servicios'] = count($row['servicios']);
                if (count($row['servicios']) == 1){
                    $row['num_servicios_costo'] = $row['servicios'][0]['clave'].' / $'.$row['total'];
                } else {
                    $row['num_servicios_costo'] = count($row['servicios']).' / $'.$row['total'];
                }

                $row['edad_actual'] = empty($row['edad_actual']) ? 'S/E' : $row['edad_actual'];
                $row['nombre_completo'] = $row['nombre_completo'].' ('.$row['edad_actual'].')';
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

    $app->get('/reportes/general_ingresos', function () use ($app,$db,$request) {
        try{
            //  REPORTE GENERAL DE CITAS EN EL RANGO DE FECHAS
            //  INCLUYE INFORMACION GENERAL DEL PACIENTES Y DE LA CITA
            $id_locacion    = $request->getQuery('id_locacion');
            $rango_fechas   = $request->getQuery('rango_fechas') ?? null;

            if (empty($rango_fechas)){
                throw new Exception('Rango de fechas vacio');
            }

            $dias_semana        = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
            $arr_estatus_asistencia = [
                0   => 'FALTA',
                1   => 'ASISTENCIA',
                2   => 'RETARDO',
                3   => 'ACTIVIDAD EN CASA',   
                null    => 'Sin asignar' 
            ];

            $arr_estatus_cita   = array(
                0   => 'CANCELADA',
                1   => 'ACTIVA',
                2   => 'PENDIENTE DE AGENDAR',
            );

            $values = [
                'fecha_inicio'  => $rango_fechas['fecha_inicio'],
                'fecha_termino' => $rango_fechas['fecha_termino'],
            ];

            $filtro = '';
            if (!empty($id_locacion)) {
                $filtro .= " AND a.id_locacion = :id_locacion ";
                $values['id_locacion']  = $id_locacion;
            }
            
            $phql   = " SELECT 
                            fecha_pago::DATE,
                            SUM(CASE WHEN a.forma_pago = 'TRANSFERENCIA' THEN a.total ELSE 0 END) AS total_transferencia,
                            SUM(CASE WHEN a.forma_pago = 'EFECTIVO' THEN a.total ELSE 0 END) AS total_efectivo,
                            SUM (total) as total_pagos 
                        FROM tbagenda_citas a WHERE a.pagada = 1 ".$filtro."
                            AND a.fecha_pago BETWEEN :fecha_inicio AND :fecha_termino AND NOT EXISTS (
                                    SELECT 1 FROM tbagenda_citas t1 
                                    WHERE a.id = t1.id_cita_reagendada 
                                ) 
                            --  EXCLUYE CITAS PAGADAS QUE POR NUEVA GENERACIO NDE CITAS SE HAYAN CANCELADO
                            AND NOT EXISTS(
                                SELECT 1 FROM tbagenda_citas t2 
                                LEFT JOIN ctmotivos_cancelacion_cita t4 ON t2.id_motivo_cancelacion = t4.id
                                WHERE t2.activa = 0 AND t2.pagada = 1 
                                AND t2.id_cita_programada IS NOT NULL AND t4.clave = 'NGC' AND NOT EXISTS (
                                    SELECT 1 FROM tbagenda_citas t3 WHERE t2.id = t3.id_cita_reagendada 
                                ) AND t2.id = a.id
                            )
                            GROUP BY fecha_pago::DATE ORDER BY fecha_pago";
    
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $arr_return = array(
                'hoja_1'    => [
                    'total_pagos'           => 0,
                    'total_efectivo'        => 0,
                    'total_transferencia'   => 0
                ],
                'hoja_2'    => []
            );
            while ($row = $result->fetch()) {
                $row['fecha_pago']  = FuncionesGlobales::formatearFecha($row['fecha_pago']);

                $arr_return['hoja_2'][] = $row;

                //  CALCULO DE TOTALES
                $arr_return['hoja_1']['total_pagos']    = (($arr_return['hoja_1']['total_pagos'] * 100) + ($row['total_pagos'] * 100) ) / 100;
                $arr_return['hoja_1']['total_efectivo']         = (($arr_return['hoja_1']['total_efectivo'] * 100) + ($row['total_efectivo'] * 100) ) / 100;
                $arr_return['hoja_1']['total_transferencia']    = (($arr_return['hoja_1']['total_transferencia'] * 100) + ($row['total_transferencia'] * 100) ) / 100;
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

    $app->get('/reportes/mensajes_enviados', function () use ($app,$db,$request) {
        try{
            //  REPORTE GENERAL DE CITAS EN EL RANGO DE FECHAS
            //  INCLUYE INFORMACION GENERAL DEL PACIENTES Y DE LA CITA
            $id_locacion    = $request->getQuery('id_locacion');
            $id_paciente    = $request->getQuery('id_paciente') ?? null;
            $rango_fechas   = $request->getQuery('rango_fechas') ?? null;

            if (empty($rango_fechas)){
                throw new Exception('Rango de fechas vacio');
            }

            $values = [
                'fecha_inicio'  => $rango_fechas['fecha_inicio'],
                'fecha_termino' => $rango_fechas['fecha_termino'],
            ];
            
            $phql   = " SELECT 
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_paciente,
                            b.nombre AS nombre_plantilla,
                            a.fecha_envio,
                            a.mensaje_generado,
                            (e.primer_apellido|| ' ' ||COALESCE(e.segundo_apellido,'')||' '||e.nombre) as nombre_usuario
                        FROM tbmensajes_enviados a
                        LEFT JOIN ctplantillas_mensajes b ON a.id_plantilla_mensaje = b.id
                        LEFT JOIN ctpacientes c ON a.id_paciente = c.id
                        LEFT JOIN tbagenda_citas d ON a.id_agenda_cita = d.id
                        LEFT JOIN ctusuarios e  ON a.id_usuario_solicitud = e.id
                        WHERE fecha_envio BETWEEN :fecha_inicio AND :fecha_termino ";
    
            if (!empty($id_locacion)) {
                $phql           .= " AND d.id_locacion = :id_locacion ";
                $values['id_locacion']  = $id_locacion;
            }

            if (is_numeric($id_paciente)){
                $phql           .= " AND a.id_paciente = :id_paciente ";
                $values['id_paciente']  = $id_paciente;
            }

            $phql   .= ' ORDER BY a.fecha_envio DESC ';

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_return = array();
            while ($row = $result->fetch()) {
                $row['fecha_envio']  = FuncionesGlobales::formatearFecha($row['fecha_envio'],'d/m/Y H:i');

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
};