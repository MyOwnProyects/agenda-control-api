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
};