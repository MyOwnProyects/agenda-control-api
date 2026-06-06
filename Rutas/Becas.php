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
    $app->get('/becas/count', function () use ($app,$db,$request) {
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
                        FROM ctbecas a 
                        WHERE 1 = 1 AND a.visible = 1 ";
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
    $app->get('/becas/show', function () use ($app,$db,$request) {
        try{
            $id     = $request->getQuery('id');
            $clave  = $request->getQuery('clave');
            $nombre = $request->getQuery('nombre');
            
            if ($id != null && !is_numeric($id)){
                throw new Exception("Parametro de id invalido");
            }
        
            // Definir el query SQL
            $phql   = "SELECT * FROM ctbecas a WHERE 1 = 1 AND a.visible = 1";
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
            
            $phql   .= ' ORDER BY a.clave,a.nombre ';

            if ($request->hasQuery('offset')){
                $phql   .= " LIMIT ".$request->getQuery('length').' OFFSET '.$request->getQuery('offset');
            }
    
            // Ejecutar el query y obtener el resultado
            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $data = [];
            while ($row = $result->fetch()) {
                $row['label_estatus']   = $row['estatus'] == 1 ? 'ACTIVA' : 'INACTIVA';
                $row['label_tipo_beca'] = $row['tipo_beca'] == 1 ? 'IMPORTE' : 'PORCENTAJE';
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

    $app->post('/becas/save', function () use ($app, $db, $request) {
        $conexion = $db; 
        try {
            $conexion->begin();
    
            // OBTENER DATOS JSON
            $id_beca        = $request->getPost('id') ?? null;
            $clave          = $request->getPost('clave') ?? null;
            $nombre         = $request->getPost('nombre') ?? null;
            $descripcion    = $request->getPost('descripcion') ?? null;
            $tipo_beca      = $request->getPost('tipo_beca') ?? null;
            $indefinida     = $request->getPost('indefinida');
            $usuario_solicitud      = $request->getPost('usuario_solicitud');
    
            // VERIFICAR QUE CLAVE Y NOMBRE NO ESTEN VACÍOS
            if (empty($clave)) {
                throw new Exception('Parámetro "Clave" vacío');
            }
    
            if (empty($nombre)) {
                throw new Exception('Parámetro "Nombre" vacío');
            }

            if (!is_numeric($tipo_beca)) {
                throw new Exception('Parámetro "tipo beca" No valido');
            }

            if (!is_numeric($indefinida)) {
                throw new Exception('Parámetro "Indefinida" vacío');
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

            //  SE DESACTIVA LA BECA EN CASO DE SER EDICION
            if (is_numeric($id_beca)){
                $phql   = "UPDATE ctbecas SET estatus = 0 , visible = 0 WHERE id = :id_beca";
                $result = $conexion->query($phql,array('id_beca' => $id_beca));
            }
            
            // VERIFICAR QUE LA CLAVE NO ESTÉ REPETIDA
            $phql = "SELECT * FROM ctbecas WHERE clave = :clave AND visible = 1 ";
    
            $result = $db->query($phql, array('clave' => $clave));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                throw new Exception('La clave: ' . $clave . ' ya se encuentra registrada');
            }
    
            // INSERTAR NUEVO servicio
            $phql = "INSERT INTO ctbecas (
                                    clave,
                                    nombre,
                                    descripcion,
                                    id_usuario_captura,
                                    tipo_beca,
                                    indefinida
                                ) 
                     VALUES (
                                :clave, 
                                :nombre, 
                                :descripcion,
                                :id_usuario_captura,
                                :tipo_beca,
                                :indefinida
                            ) RETURNING id";
    
            $values = [
                'clave'         => $clave,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion,
                'id_usuario_captura'    => $id_usuario_solicitud,
                'tipo_beca'             => $tipo_beca,
                'indefinida'            => $indefinida
            ];
    
            $result = $conexion->query($phql, $values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $id = null;
            if ($result) {
                while ($data = $result->fetch()) {
                    $id = $data['id'];
                }
            }
    
            if (!$id) {
                throw new Exception('Error al crear el servicio');
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

    $app->put('/becas/change_status', function () use ($app, $db,$request) {
        try{

            $id             = $request->getPost('id');
            $status_actual  = $request->getPost('status_actual');
            $nuevo_status   = $request->getPost('nuevo_status');

            //  ESTATUS ACTUAl
            $phql   = "SELECT * FROM ctbecas WHERE id = :id";
            $result = $db->query($phql, array('id' => $id));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            while ($row = $result->fetch()) {
                if ($row['estatus'] != $status_actual){
                    throw new Exception('El estatus del servicio ya no es el indicado, favor de refrescar la vista.');
                }
            }

            $phql   = "UPDATE ctbecas SET estatus = :nuevo_status WHERE id = :id";
            $result = $db->execute($phql, array('id' => $id,'nuevo_status' => $nuevo_status));

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

     $app->delete('/becas/delete', function () use ($app, $db,$request) {
        try{
            $id     = $request->getPost('id');

            $phql   = "UPDATE ctbecas SET estatus = 0, visible = 0 WHERE id = :id";
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

    //  ASIGNACION DE BECAS
    // Ruta principal para obtener todos los usuarios
    $app->get('/paciente_becas/count', function () use ($app,$db,$request) {
        try{

            $id             = $request->getQuery('id');
            $id_paciente    = $request->getQuery('clave');
        
            // Definir el query SQL
            $phql   = "SELECT 
                            COUNT(1) as num_registros
                        FROM tbpaciente_becas a 
                        WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (is_numeric($id_paciente)){
                $phql                   .= " AND a.id_paciente = :id_paciente";
                $values['id_paciente']  = $id_paciente;
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
    $app->get('/paciente_becas/show', function () use ($app,$db,$request) {
        try{

            $id             = $request->getQuery('id');
            $id_paciente    = $request->getQuery('clave');
        
            // Definir el query SQL
            $phql   = " SELECT 
                            a.*,
                            (b.clave|| ' - ' || b.nombre) as beca,
                            COALESCE(c.monto_usado,0) as monto_usado
                        FROM tbpaciente_becas a 
                        LEFT JOIN ctbecas b ON a.id_beca = b.id
                        LEFT JOIN LATERAL (
                            SELECT SUM(t1.monto) AS monto_usado 
                            FROM tbabonos_movimientos t1
                            LEFT JOIN tbabonos t2 ON t1.id_abono = t2.id
                            WHERE a.id = t2.id_paciente_beca 
                            AND (t1.estatus = 1 OR (t1.estatus = 0 AND t1.tipo_cancelacion = 2))
                        ) c ON TRUE
                        WHERE 1 = 1 ";
            $values = array();
    
            if (is_numeric($id)){
                $phql           .= " AND a.id = :id";
                $values['id']   = $id;
            }

            if (is_numeric($id_paciente)){
                $phql                   .= "AND a.id_paciente = :id_paciente";
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
                $row['label_estatus']           = $row['estatus'] == 1 ? 'ACTIVA' : 'INACTIVA';
                $row['label_tipo_beca']         = $row['tipo_beca'] == 1 ? 'IMPORTE' : 'PORCENTAJE';
                $row_['label_fecha_captura']    = FuncionesGlobales::formatearFecha($row['label_fecha_captura'],'d/m/Y H:i');
                $data[]                         = $row;
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

    $app->post('/becas/save_pago', function () use ($app,$db,$request) {
        $conexion   = $this->db;
        try{
            $conexion->begin();

            $obj_citas_saldos    = $request->getPost('obj_citas_saldos');
            $obj_info_pago       = $request->getPost('obj_info_pago');
            $info_ticket         = $request->getPost('info_ticket');
            $id_paciente         = $request->getPost('id_paciente');
            $usuario_solicitud      = $request->getPost('usuario_solicitud');

            $obj_info_pago['citas_excluidas']   = $obj_info_pago['citas_excluidas'] ?? array();

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

            //  SE BUSCAN LAS CITAS DEUDORAS DEL PACIENTE, ORDENADAS DE LA MAS ANTIGUA
            //  A LA MAS RECIENTE, SE VERIFICARA QUE SI EL PACIENTE PAGARA POR EJEMPLO 
            //  5 CITAS LOS ID'S DEL QUERY DEBEN DE SER IGUALES A LOS DEL ARRAY
            //  INCLUIDOS EL ORDEN DE PAGO

            $tmp_id_citas   = array();
            foreach($obj_citas_saldos as $info_cita){
                $tmp_id_citas[] = $info_cita['id_agenda_cita'];
            }

            //  CITAS EXCLUIDAS
            $excluir_id         = '';
            $id_citas_excluidas = $obj_info_pago['citas_excluidas']; 
            if (count($obj_info_pago['citas_excluidas']) > 0 ){
                $excluir_id = implode(',', array_map('intval', $obj_info_pago['citas_excluidas']));
                $excluir_id = " AND id NOT IN ($excluir_id) ";
            }
            

            $phql   = "SELECT id,fecha_cita FROM tbagenda_citas 
                        WHERE id_paciente = :id_paciente AND activa <> 0 AND pagada = 0 
                        $excluir_id
                        ORDER BY fecha_cita,hora_inicio,hora_termino LIMIT :limite_citas";
            $values = array(
                'id_paciente'   => $id_paciente,
                'limite_citas'  => count($obj_citas_saldos)
            );

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            //  SE OBTIENE LA PRIMERA CITA A PAGAR
            $fecha_primera_cita = null;
            if ($result){
                $num_row    = 0;
                while($data = $result->fetch()){
                    if ($tmp_id_citas[$num_row] != $data['id']){
                        throw new Exception('Verifica la selección de citas. Debes seleccionar las citas pendientes en orden, comenzando por la más antigua.');
                    } else {

                        if ($fecha_primera_cita == ''){
                            $fecha_primera_cita = $data['fecha_cita'];
                        }

                        unset($tmp_id_citas[$num_row]);
                        $num_row    ++;
                    }
                }
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

            //  SE CREA REGUSTRO DE ID_PACIENTE_BECA
            $phql   = "INSERT INTO tbpaciente_becas (id_paciente,id_beca,monto_asignado,fecha_inicio,id_usuario_captura)
                        VALUES(:id_paciente,:id_beca,:monto_asignado,:fecha_inicio,:id_usuario_captura) RETURNING *";

            $values = array(
                'id_paciente'           => $id_paciente,
                'id_beca'               => $obj_info_pago['id_beca'],
                'monto_asignado'        => 0,
                'fecha_inicio'          => $fecha_primera_cita,
                'id_usuario_captura'    => $id_usuario_solicitud
            );

            $result = $db->query($phql,$values);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $id_paciente_beca   = null;
            while($data = $result->fetch()){
                $id_paciente_beca   = $data['id'];
            }

            //  SE GUARDAN LAS CITAS EXCLUIDAS
            if (count($id_citas_excluidas) > 0){
                $phql   = "INSERT INTO tbpaciente_becas_citas_excluidas (id_paciente_beca,id_agenda_cita)
                            VALUES (:id_paciente_beca,:id_agenda_cita)";
                foreach($id_citas_excluidas as $id_cita){
                    $values = array(
                        'id_paciente_beca'  => $id_paciente_beca,
                        'id_agenda_cita'    => $id_cita
                    );

                    $result = $conexion->query($phql,$values);
                }
            }

            //  SE OBTIENE EL FOLIO DEL TICKET
            $phql   = "SELECT * FROM fn_folio_ticket('beca');";
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
            
            $id_abonos_generados    = array();
            $total_monto            = 0;
            foreach($arr_orden_metodo_pago as $index_metodo_pago => $metodo_pago){

                //  EN CASO DE QUE EL METODO DE PAGO NO VENGA EN EL ARRAY
                if (!isset($obj_info_pago[$metodo_pago['index']])){
                    continue;
                }

                $id_abono   = null;
                $monto      = $obj_info_pago[$metodo_pago['index']];
                $referenca_transferencia    = null;
                $fecha_hora_transferencia   = null;

                if ($metodo_pago['index'] != 'pago_efectivo'){
                    $referenca_transferencia    = $obj_info_pago['referencia_transferencia'];
                    $referenca_transferencia    = trim($referenca_transferencia);
                    $fecha_transferencia        = $obj_info_pago['fecha_transferencia'];
                    $hora_transferencia         = $obj_info_pago['hora_transferencia'];

                    $hora_transferencia = $obj_info_pago['hora_transferencia'];

                    // Si no viene hora, usar la hora actual del servidor
                    if (empty($hora_transferencia)) {
                        $hora_transferencia = date('H:i:s');
                    }

                    $fecha_hora_transferencia   = $fecha_transferencia.' '.$hora_transferencia;
                }

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
                $monto          = $monto * 1;
                $total_monto    = (($total_monto * 100) + ($monto * 100)) / 100;

                //  SE CREA EL ABONO
                $phql   = " INSERT INTO tbabonos (id_paciente,monto,tipo_abono,metodo_pago,id_usuario_captura,ticket_folio,fecha_hora_pago,referencia)
                        VALUES (:id_paciente,:monto,:tipo_abono,:metodo_pago,:id_usuario_captura,:ticket_folio,:fecha_hora_pago,:referencia) RETURNING *";
                
                $values = array(
                    'id_paciente'   => $id_paciente,
                    'monto'         => $monto,
                    'tipo_abono'    => 2,
                    'metodo_pago'   => $metodo_pago['label_table'],
                    'id_usuario_captura'    => $id_usuario_solicitud,
                    'ticket_folio'          => $folio_generado,
                    'fecha_hora_pago'       => $fecha_hora_transferencia == '' || $fecha_hora_transferencia == null ? 'now()' : $fecha_hora_transferencia,
                    'referencia'            => $referenca_transferencia
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
                                            id_usuario_captura,
                                            ticket_folio,
                                            fecha_hora_pago
                                            )
                                        VALUES (
                                            :id_abono,
                                            :id_agenda_cita,
                                            :monto,
                                            :id_usuario_captura,
                                            :ticket_folio,
                                            :fecha_hora_pago
                                        )";
                    
                    $values = array(
                        'id_abono'              => $id_abono,
                        'id_agenda_cita'        => $cita_pagar['id_agenda_cita'],
                        'monto'                 => $monto_movto,
                        'id_usuario_captura'    => $id_usuario_solicitud,
                        'ticket_folio'          => $folio_generado,
                        'fecha_hora_pago'       => $fecha_hora_transferencia == '' || $fecha_hora_transferencia == null ? 'now()' : $fecha_hora_transferencia,
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

            //  SE ACTUALIZA EL MONTO TOTAL PAGADO
            $phql   = "UPDATE tbpaciente_becas SET monto_asignado = :total_monto WHERE id = :id_paciente_beca";
            $result = $conexion->execute($phql,array(
                'total_monto'       => $total_monto,
                'id_paciente_beca'  => $id_paciente_beca
            ));

            $conexion->commit();
            //$conexion->rollback();
    
            // Devolver los datos en formato JSON
            $response = new Response();
            $response->setJsonContent(array('MSG' => 'OK'));
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
};
