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
    $app->get('/caja/get_citas_adeudos', function () use ($app,$db,$request) {
        try{

            $id_paciente    = $request->getQuery('id_paciente');

            if (empty($id_paciente) || !is_numeric($id_paciente)){
                throw new Exception('Parametro de paciente invalido');
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

            $arr_return = array(
                'citas'         => [],
                'saldo_favor'   => 0
            );

            // Definir el query SQL
            $phql   = " SELECT  
                            a.id as id_agenda_cita,
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
                                    EXTRACT(YEAR FROM AGE(CURRENT_DATE, b.fecha_nacimiento))::text || '.' ||
                                    LPAD(EXTRACT(MONTH FROM AGE(CURRENT_DATE, b.fecha_nacimiento))::text, 2, '0')
                                ELSE NULL
                            END AS edad_actual,
                            a.id_cita_simultanea,
                            a.id_motivo_cita_fuera_horario,
                            i.nombre as nombre_motivo_cita_fuera_horario,
                            a.observaciones_motivo_cita_fuera_horario,
                            COALESCE(j.num_citas_simultaneas,0) as num_citas_simultaneas,
                            fn_saldo_cita(a.id) as saldo_cita
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
                        WHERE a.id_paciente = :id_paciente AND a.pagada = 0 AND a.activa <> 0
                        ORDER BY a.fecha_cita,a.hora_inicio,a.hora_termino";
            
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['servicios']   = array();
                $row['estatus']     = $arr_estatus_cita[$row['activa']];
                $row['fecha_completa']  = $dias_semana[$row['day'] - 1].' '.FuncionesGlobales::formatearFecha($row['fecha_cita']) . ' de '. $row['start']. ' a '.$row['end'];
                $row['label_pagada']    = $row['pagada'] == 1 ? 'SI' : 'NO';
                $row['label_dia']       = $dias_semana[$row['day'] - 1];
                $row['label_asistencia']        = $arr_estatus_asistencia[$row['asistencia']];
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

                $row['hora_cita']  = $row['start'] . ' - ' . $row['end'];
                $row['num_servicios'] = count($row['servicios']);
                if (count($row['servicios']) == 1){
                    $row['num_servicios_costo'] = $row['servicios'][0]['clave'].' / $'.$row['total'];
                } else {
                    $row['num_servicios_costo'] = count($row['servicios']).' / $'.$row['total'];
                }
                
                $row['edad_actual'] = empty($row['edad_actual']) ? 'S/E' : $row['edad_actual'];

                if (!$agenda_movil){
                    $row['nombre_completo'] = $row['nombre_completo'].' ('.$row['edad_actual'].')';
                } else {
                    $row['nombre_completo'] = $row['primer_apellido'].' '.$row['nombre'].' ('.$row['edad_actual'].')';
                }
                
                $data[] = $row;
            }

            $arr_return['citas']    = $data;
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent($arr_return);
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
    $app->get('/caja/get_fecha_hora', function () use ($app,$db,$request) {
        try{
            $controlador            = $request->getQuery('controlador');
        
            // Definir el query SQL
            $phql = "SELECT CURRENT_DATE as fecha, TO_CHAR(NOW(), 'HH24:MI:SS') AS hora_actual";
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            // Recorrer los resultados
            $arr_return = array(
                'fecha_hora'    => ''
            );
            while ($row = $result->fetch()) {
                $row['fecha']               = FuncionesGlobales::formatearFecha($row['fecha']);
                $arr_return['fecha_hora']   = $row['fecha'].' '.$row['hora_actual'];
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
            $response->setStatusCode(404, 'Not found');
            return $response;
        }
        
    });
};