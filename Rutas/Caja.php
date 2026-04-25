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

            //  SE BUSCA EL SALDO A FAVOR DEL PACIENTE
            $phql   = "SELECT * FROM fn_saldo_favor_paciente(:id_paciente);";
            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    $arr_return['saldo_favor']  = $data['fn_saldo_favor_paciente'];
                }
            }

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
                
                $row['edad_actual']     = empty($row['edad_actual']) ? 'S/E' : $row['edad_actual'];
                $row['nombre_completo'] = $row['nombre_completo'].' ('.$row['edad_actual'].')';

                //  SE BUSCA SI LA CITA TIENE MOVIMIENTOS
                $phql   = " SELECT 
                                a.*,
                                b.clave as clave_usuario_captura,
                                COALESCE(c.clave,'') as clave_usuario_cancelacion 
                            FROM tbabonos_movimientos a
                            LEFT JOIN ctusuarios b ON a.id_usuario_captura = b.id
                            LEFT JOIN ctusuarios c ON a.id_usuario_cancelacion = c.id
                            WHERE a.id_agenda_cita = :id_agenda_cita 
                            ORDER BY a.fecha_captura";

                $result_movtos  = $db->query($phql,array('id_agenda_cita' => $row['id_agenda_cita']));
                $result_movtos->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                $arr_movtos = array();
                if ($result_movtos){
                    while($data_movtos = $result_movtos->fetch()){
                        $data_movtos['fecha_captura']       = FuncionesGlobales::formatearFecha($data_movtos['fecha_captura'],'d/m/Y H:i');
                        $data_movtos['fecha_cancelacion']   = FuncionesGlobales::formatearFecha($data_movtos['fecha_cancelacion'],'d/m/Y H:i');
                        $data_movtos['label_estatus']       = $data_movtos['estatus'] == 1 ? 'Activo' : 'Cancelado';
                        $arr_movtos[]                       = $data_movtos;
                    }
                }

                $row['movimientos'] = $arr_movtos;
                
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

    $app->post('/caja/save_pago', function () use ($app,$db,$request) {
        $conexion   = $this->db;
        try{
            $conexion->begin();

            $obj_citas_saldos    = $request->getPost('obj_citas_saldos');
            $obj_info_pago       = $request->getPost('obj_info_pago');
            $info_ticket         = $request->getPost('info_ticket');
            $id_paciente         = $request->getPost('id_paciente');
            $usuario_solicitud      = $request->getPost('usuario_solicitud');

            //  ORDEN EN QUE SE GENERARAN LOS ABONOS SEGUN EL METODO DE PAGO
            $arr_orden_metodo_pago  = array(
                [
                    'label_table'   => 'EFECTIVO',
                    'index'         => 'pago_efectivo'
                ],
                [
                    'label_table'   => 'TRANSFERENCIA',
                    'index'         => 'pago_transferencia'
                ],
                [
                    'label_table'   => 'TARJETA',
                    'index'         => 'pago_tarjeta'
                ]
            );

            if (empty($obj_citas_saldos) || count($obj_citas_saldos) == 0){
                throw new Exception('Información de citas a pagar vacias');
            }

            if (empty($obj_info_pago) || count($obj_info_pago) == 0){
                throw new Exception('Información de pagos vacias');
            }

            if (empty($info_ticket)){
                throw new Exception('Información de Ticket vacios');
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

            //  SE VERIFICA SI EL SALDO DE LAS CITAS A PAGAR ES EL MISMO QUE VISUALIZABA EL USUARIO
            foreach($obj_citas_saldos as $saldo){
                $phql   = " SELECT fn_saldo_cita(a.id) as saldo_cita 
                            FROM tbagenda_citas a WHERE a.id = :id_agenda_cita AND activa <> 0";
                $flag_exist = false;

                $result = $db->query($phql, array(
                    'id_agenda_cita'    => $saldo['id_agenda_cita']
                ));
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result){
                    while($data = $result->fetch()){
                        $flag_exist = true;
                        if ($data['saldo_cita'] != $saldo['saldo_cita']){
                            throw new Exception('Una de la citas cuenta con un saldo diferente al mostrado en pantalla, refresca la vista para actualizar la información');
                        }
                    }
                }

                if (!$flag_exist){
                    throw new Exception('Una de las citas ya no se encuentra disponible para pagar, refresca la vista para actualizar la información');
                }
            }

            //  SE BUSCA EL SALDO A FAVOR DEL PACIENTE
            $saldo_favor_calculado  = 0;
            $phql   = "SELECT * FROM fn_saldo_favor_paciente(:id_paciente);";
            $result = $db->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    $saldo_favor_calculado  = $data['fn_saldo_favor_paciente'];
                }
            }

            //  @TODO FUNCION SALDO A FAVOR SE VALIDA QUE EL SALDO A FAVOR DEL USUARIO SEA EL MISMO
            if ($saldo_favor_calculado != $obj_info_pago['saldo_favor']){
                throw new Exception("El saldo a favor del paciente a cambiado, refresca la vista para actualizar la información");
            }

            //  SE OBTIENE EL FOLIO DEL TICKET
            $phql   = "SELECT * FROM fn_folio_ticket();";
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while($data = $result->fetch()){
                $folio_generado = $data['fn_folio_ticket'];
            }

            //  SE GENERA EL TICKET
            $phql   = " INSERT INTO tbtickets_pagos (folio,id_paciente,detalle,id_usuario_captura)
                        VALUES (:folio,:id_paciente,:detalle,:id_usuario_captura)";

            $values = array(
                'folio'         => $folio_generado,
                'id_paciente'   => $id_paciente,
                'detalle'       => json_encode($info_ticket),
                'id_usuario_captura'    => $id_usuario_solicitud
            );

            $result = $conexion->query($phql,$values);

            //  APLICAR SALDO A FAVOR PRIMERO
            if ($saldo_favor_calculado > 0){
                $phql   = "SELECT a.id AS id_abono, a.monto - COALESCE(b.monto_usado, 0) AS monto_disponible
                            FROM tbabonos a
                            LEFT JOIN LATERAL (
                                SELECT SUM(t1.monto) AS monto_usado 
                                FROM tbabonos_movimientos t1
                                WHERE a.id = t1.id_abono 
                                AND t1.estatus = 1
                            ) b ON TRUE
                            WHERE a.id_paciente = :id_paciente
                            AND a.estatus = 1
                            AND (a.monto - COALESCE(b.monto_usado, 0)) > 0
                            ;";

                $result_saldo_favor = $db->query($phql,array(
                    'id_paciente'   => $id_paciente
                ));
                $result_saldo_favor->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result_saldo_favor){
                    while($data = $result_saldo_favor->fetch()){
                        //  SE RECORREN TODAS LAS CITAS A PAGAR
                        $id_abono   = $data['id_abono'];
                        $monto      = $data['monto_disponible'];
                        foreach($obj_citas_saldos as $index => $cita_pagar){
                            //  CONVERTIMOS EL SALDO A NUMERICO
                            $cita_pagar['saldo_cita']   = $cita_pagar['saldo_cita'] * 1;

                            //  SE VERIFICA SI CON EL ABONO LA CITA QUEDA LIQUIDADA
                            $liquidar_cargo = false;
                            $monto_movto    = 0;
                            if ($monto >= $cita_pagar['saldo_cita']){
                                $liquidar_cargo = true;

                                $monto          = (($monto * 100) - ($cita_pagar['saldo_cita'] * 100)) / 100;
                                $monto_movto    = $cita_pagar['saldo_cita'];
                            } else {
                                //  COMO NO ALCANZA A LIQUIDAR SE CREARA EL MOVTO CON LA CANTIDAD
                                //  DEL ABONO Y SE REALIZA LA RESTA DEL SALDO DE LA CITA
                                $obj_citas_saldos[$index]['saldo_cita'] = (($cita_pagar['saldo_cita'] * 100) - ($monto * 100)) / 100;
                                $monto_movto                            = $monto;
                                $monto                                  = 0;
                            }

                            $phql   = "INSERT INTO tbabonos_movimientos (
                                                    id_abono,
                                                    id_agenda_cita,
                                                    monto,
                                                    id_usuario_captura
                                                    )
                                                VALUES (
                                                    :id_abono,
                                                    :id_agenda_cita,
                                                    :monto,
                                                    :id_usuario_captura
                                                )";
                            
                            $values = array(
                                'id_abono'              => $id_abono,
                                'id_agenda_cita'        => $cita_pagar['id_agenda_cita'],
                                'monto'                 => $monto_movto,
                                'id_usuario_captura'    => $id_usuario_solicitud
                            );

                            $result = $conexion->execute($phql,$values);

                            //  SI SE LIQUIDO LA CITA ESTA SE SACA DEL ARRAY Y SE MARCA COMO PAGADA
                            if ($liquidar_cargo){
                                $phql   = "UPDATE tbagenda_citas SET pagada = 1, fecha_pago = NOW() WHERE id = :id_agenda_cita";
                                $result = $conexion->execute($phql,array('id_agenda_cita' => $cita_pagar['id_agenda_cita']));

                                unset($obj_citas_saldos[$index]);
                            }

                            //  SI EL MONTO LLEGA A 0 SE TENIENE EL RECORRIDO
                            if ($monto == 0){
                                break;
                            }
                        }
                    }
                }
            }
            
            $id_abonos_generados    = array();
            foreach($arr_orden_metodo_pago as $index_metodo_pago => $metodo_pago){

                //  EN CASO DE QUE EL METODO DE PAGO NO VENGA EN EL ARRAY
                if (!isset($obj_info_pago[$metodo_pago['index']])){
                    continue;
                }

                $id_abono   = null;
                $monto = $obj_info_pago[$metodo_pago['index']];

                // Validar que sea un monto monetario válido y mayor a 0
                $monto = floatval($monto);

                if (!is_numeric($monto) || round($monto, 2) < 0) {
                    // Maneja el error según tu lógica
                    throw new Exception('El monto debe ser mayor a 0.');
                }

                // Formatear a 2 decimales para asegurar formato monetario
                $monto = round($monto, 2);

                //  SI EL MONTO ES 0 NO SE CREA EL ABONO
                if ($monto == 0){
                    continue;
                }

                //  CONVERTIR EL MONTO A NUMERICO
                $monto  = $monto * 1;

                //  SE CREA EL ABONO
                $phql   = " INSERT INTO tbabonos (id_paciente,monto,tipo_abono,metodo_pago,id_usuario_captura,ticket_folio)
                        VALUES (:id_paciente,:monto,:tipo_abono,:metodo_pago,:id_usuario_captura,:ticket_folio) RETURNING *";
                
                $values = array(
                    'id_paciente'   => $id_paciente,
                    'monto'         => $monto,
                    'tipo_abono'    => 1,
                    'metodo_pago'   => $metodo_pago['label_table'],
                    'id_usuario_captura'    => $id_usuario_solicitud,
                    'ticket_folio'          => $folio_generado
                );

                $result = $conexion->query($phql, $values);
                $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                if ($result){
                    while($data = $result->fetch()){
                        $id_abono   = $data['id'];
                    }
                }

                $id_abonos_generados[$id_abono] = $monto;

                //  SE RECORREN TODAS LAS CITAS A PAGAR
                foreach($obj_citas_saldos as $index => $cita_pagar){
                    //  CONVERTIMOS EL SALDO A NUMERICO
                    $cita_pagar['saldo_cita']   = $cita_pagar['saldo_cita'] * 1;

                    //  SE VERIFICA SI CON EL ABONO LA CITA QUEDA LIQUIDADA
                    $liquidar_cargo = false;
                    $monto_movto    = 0;
                    if ($monto >= $cita_pagar['saldo_cita']){
                        $liquidar_cargo = true;

                        $monto          = (($monto * 100) - ($cita_pagar['saldo_cita'] * 100)) / 100;
                        $monto_movto    = $cita_pagar['saldo_cita'];
                    } else {
                        //  COMO NO ALCANZA A LIQUIDAR SE CREARA EL MOVTO CON LA CANTIDAD
                        //  DEL ABONO Y SE REALIZA LA RESTA DEL SALDO DE LA CITA
                        $obj_citas_saldos[$index]['saldo_cita'] = (($cita_pagar['saldo_cita'] * 100) - ($monto * 100)) / 100;
                        $monto_movto                            = $monto;
                        $monto                                  = 0;
                    }

                    $phql   = "INSERT INTO tbabonos_movimientos (
                                            id_abono,
                                            id_agenda_cita,
                                            monto,
                                            id_usuario_captura
                                            )
                                        VALUES (
                                            :id_abono,
                                            :id_agenda_cita,
                                            :monto,
                                            :id_usuario_captura
                                        )";
                    
                    $values = array(
                        'id_abono'              => $id_abono,
                        'id_agenda_cita'        => $cita_pagar['id_agenda_cita'],
                        'monto'                 => $monto_movto,
                        'id_usuario_captura'    => $id_usuario_solicitud
                    );

                    $result = $conexion->execute($phql,$values);

                    //  SI SE LIQUIDO LA CITA ESTA SE SACA DEL ARRAY Y SE MARCA COMO PAGADA
                    if ($liquidar_cargo){
                        $phql   = "UPDATE tbagenda_citas SET pagada = 1, fecha_pago = NOW() WHERE id = :id_agenda_cita";
                        $result = $conexion->execute($phql,array('id_agenda_cita' => $cita_pagar['id_agenda_cita']));

                        unset($obj_citas_saldos[$index]);
                    }

                    //  SI EL MONTO LLEGA A 0 SE TENIENE EL RECORRIDO
                    if ($monto == 0){
                        break;
                    }
                }

                //  ARRAY DE ID ABONOS GENERADOS Y CANTIDAD RESTANTE POSTERIOR A SER APLICADO
                //  ESTO SOLO SI EL MONTO TIENE DINERO, SIRVE PARA SABER SI QUEDO ANTICIPADO
                if ($monto == 0){
                    unset($id_abonos_generados[$id_abono]);
                } else {
                    $id_abonos_generados[$id_abono] = $monto;
                }
                
            }

            //  SI EL ARRAY DE ABONOS GENERADOS TIENE DATOS QUIERE DECIR QUE HAY SALDOS A FAVOR
            $suma_monto_favor   = 0;
            if (count($id_abonos_generados) > 0){
                foreach($id_abonos_generados as $id_abono => $monto){
                    $phql   = "INSERT INTO tbsaldo_favor (id_paciente,monto) VALUES (:id_paciente,:monto) RETURNING *";

                    $result = $conexion->query($phql, array(
                        'id_paciente'   => $id_paciente,
                        'monto'         => $monto
                    ));
                    $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

                    $id_saldo_favor = null;
                    if ($result){
                        while($data = $result->fetch()){
                            $id_saldo_favor = $data['id'];
                        }
                    }

                    //  SE INGRESA EL SALDO A FAVOR AL ABONO
                    $phql   = "UPDATE tbabonos SET id_saldo_favor = :id_saldo_favor WHERE id = :id_abono";
                    $result = $conexion->execute($phql,array(
                        'id_saldo_favor'    => $id_saldo_favor,
                        'id_abono'          => $id_abono
                    ));

                    $suma_monto_favor   = (($suma_monto_favor * 100) + ($monto * 100)) / 100;
                }
            }

            //  SI EL SALDO A FAVOR ES DIFERENTE AL EXCEDENTE DEL OBJETO
            //  SIGNIFICA QUE DURANTE LA CAPTURA DE PAGOS EL SALDO A FAVOR O UNA CANTIDAD
            //  CAMBIO, POR CUAL ARROJARA ERROR
            if ($suma_monto_favor != $obj_info_pago['excedente']){
                throw new Exception("El saldo a favor del paciente a cambiar, refresca la vista para actualizar la información");
            }

            $conexion->commit();
            //$conexion->rollback();
    
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
            $response->setStatusCode(404, 'Not found');
            return $response;
        }
        
    });

    $app->get('/caja/tickets_count', function () use ($app,$db,$request) {
        try{

            $id             = $request->getQuery('id');
            $folio          = $request->getQuery('folio');
            $id_paciente    = $request->getQuery('id_paciente');
        
            // Definir el query SQL
            $phql   = "SELECT 
                            COUNT(1) as num_registros
                        FROM tbtickets_pagos a 
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($folio)) {
                $phql           .= " AND lower(a.folio) ILIKE :folio";
                $values['folio'] = "%".FuncionesGlobales::ToLower($folio)."%";
            }

            if (!empty($id_paciente)) {
                $phql           .= " AND a.id_paciente = :id_paciente";
                $values['id_paciente'] = $id_paciente;
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
    $app->get('/caja/tickets_show', function () use ($app,$db,$request) {
        try{

            $id             = $request->getQuery('id');
            $folio          = $request->getQuery('folio');
            $id_paciente    = $request->getQuery('id_paciente');
        
            // Definir el query SQL
            $phql   = " SELECT  
                            a.*,
                            (b.primer_apellido|| ' ' ||COALESCE(b.segundo_apellido,'')||' '||b.nombre) as nombre_completo,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_usuario
                        FROM tbtickets_pagos a 
                        LEFT JOIN ctpacientes b ON a.id_paciente = b.id
                        LEFT JOIN ctusuarios c ON a.id_usuario_captura = c.id
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($folio)) {
                $phql           .= " AND lower(a.folio) ILIKE :folio";
                $values['folio'] = "%".FuncionesGlobales::ToLower($folio)."%";
            }

            if (!empty($id_paciente)) {
                $phql                   .= " AND a.id_paciente = :id_paciente";
                $values['id_paciente']  = $id_paciente;
            }
            
            $phql   .= ' ORDER BY a.fecha_captura DESC ';

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $detalles               = json_decode($row['detalle'],true);
                $row['monto_recibido']  = '$'.FuncionesGlobales::formatearDecimal($detalles['monto_recibido']);
                $row['label_fecha']     = FuncionesGlobales::formatearFecha($row['fecha_captura'],'d/m/Y H:i');
                $data[]                 = $row;
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

    // Ruta principal para obtener todos los registros
    $app->get('/caja/abonos_show', function () use ($app,$db,$request) {
        try{

            $id_ticket  = $request->getQuery('id_ticket');
            $folio      = $request->getQuery('folio');
        
            // Definir el query SQL
            $phql   = " SELECT  
                            a.*,
                            COALESCE(b.clave,'') as clave_usuario_captura,
                            COALESCE(c.clave,'') as clave_usuario_cancelacion
                        FROM tbabonos a 
                        LEFT JOIN ctusuarios b ON a.id_usuario_captura = b.id
                        LEFT JOIN ctusuarios c ON a.id_usuario_cancelacion = c.id
                        LEFT JOIN tbtickets_pagos d ON a.ticket_folio = d.folio
                        WHERE 1 = 1 ";
            $values = array();

            if (!empty($folio)) {
                $phql           .= " AND lower(a.ticket_folio) ILIKE :folio";
                $values['folio'] = "%".FuncionesGlobales::ToLower($folio)."%";
            }

            if (!empty($id_ticket)) {
                $phql                   .= " AND d.id = :id_ticket";
                $values['id_ticket']    = $id_ticket;
            }
            
            $phql   .= ' ORDER BY a.fecha_captura ASC ';
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['monto']       = '$'.FuncionesGlobales::formatearDecimal($row['monto']);
                $row['label_fecha_captura']     = FuncionesGlobales::formatearFecha($row['fecha_captura'],'d/m/Y H:i');
                $row['label_fecha_cancelacion'] = FuncionesGlobales::formatearFecha($row['fecha_cancelacion'],'d/m/Y H:i');
                $row['observaciones_cancelacion']   = $row['observaciones_cancelacion'] == null ? '' : $row['observaciones_cancelacion'];
                $data[]                 = $row;
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

    // Ruta principal para obtener todos los registros
    $app->put('/caja/save_cancelacion_devolucion', function () use ($app,$db,$request) {
        try{

            $arr_id_abono               = $request->getPost('arr_id_abono');
            $observaciones_cancelacion  = $request->getPost('observaciones_cancelacion');
            $usuario_solicitud          = $request->getPost('usuario_solicitud');

            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave_usuario";
            $result = $db->query($phql,array('clave_usuario' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $id_usuario_solicitud   = null;
            if ($result){
                while($data = $result->fetch()){
                    $id_usuario_solicitud   = $data['id'];
                }
            }
        
            //  SE RECORRE EL ARRAY DE ABONOS A CANCELAR
            foreach($arr_id_abono as $id_abono){
                //  SE VERIFICA SI EL ABONO SE REALIZO HACE N CANTIDAD DE DIAS 
                //  ASI COMO EL ESTATUS DEL ABONO
                $phql   = " SELECT 
                                (CASE WHEN (a.fecha_captura + (b.valor)::integer * INTERVAL '1 day') <= now() 
                                THEN 1 ELSE 0 END) AS  fecha_caducada,
                                b.valor,
                                a.
                            FROM tbabonos a
                            LEFT JOIN ctvariables_sistema b ON b.clave = 'dias_movimientos_citas_vencidas'
                            WHERE a.id = 1;";
                
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
};