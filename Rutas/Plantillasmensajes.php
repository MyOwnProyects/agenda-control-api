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
    $app->get('/plantillas_mensajes/show', function () use ($app,$db,$request) {
        try{
            
            $phql = "SELECT * FROM ctplantillas_mensajes ORDER BY tipo_mensaje";
    
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $arr_return = array();
            while ($row = $result->fetch()) {
                $arr_return[]   = $row;
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

    $app->get('/plantillas_mensajes/plantilla_por_cita', function () use ($app,$db,$request) {
        try{

            $id_agenda_cita = $request->getQuery('id_agenda_cita') ?? null;

            $dias_semana    = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];

            if (empty($id_agenda_cita) || !is_numeric($id_agenda_cita)){
                throw new Exception('Parametro de cita invalido');
            }

            //  SE GENERA LA INFORMACION DE LA CITA
            $phql   = " SELECT  
                            (e.nombre||' '||e.primer_apellido) as nombre_completo,
                            e.nombre,
                            (c.primer_apellido|| ' ' ||COALESCE(c.segundo_apellido,'')||' '||c.nombre) as nombre_profesional,
                            a.fecha_cita as fecha_nueva,
                            TO_CHAR(a.hora_inicio, 'HH24:MI') AS hora,
                            b.fecha_cita as fecha_anterior,
                            d.nombre as locacion,
                            d.latitud,
                            d.longitud,
                            a.activa,
                            a.id_cita_reagendada,
                            a.id_cita_programada,
                            e.celular,
                            a.dia as dia_cita,
                            b.dia as dia_anterior,
                            a.id_paciente
                        FROM tbagenda_citas a 
                        LEFT JOIN tbagenda_citas b ON a.id_cita_reagendada = b.id
                        LEFT JOIN ctprofesionales c ON a.id_profesional = c.id
                        LEFT JOIN ctlocaciones d ON a.id_locacion = d.id
                        LEFT JOIN ctpacientes e ON a.id_paciente = e.id
                        WHERE a.id = :id_agenda_cita";

            $result = $db->query($phql,array(
                'id_agenda_cita'    => $id_agenda_cita
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $arr_info_cita  = array();
            while ($row = $result->fetch()) {
                //  $mapsUrl = 'https://maps.google.com/?q=' . $latitud . ',' . $longitud;
                $arr_info_cita  = $row;
            }

            if (count($arr_info_cita) == 0){
                throw new Exception('Cita inexistente en el catalogo');
            }

            //  TELEFONO DEL CLIENTE
            $telefono   = '52'.preg_replace('/\D/', '', $arr_info_cita['celular']);
            //  BORRAR ANTES DE COMMIT A PRODUCCION
            $telefono   = '526624767555';
            
            $arr_return = array(
                'celular'       => $arr_info_cita['celular'],
                'paciente'      => $arr_info_cita['nombre_completo'],
                'id_paciente'   => $arr_info_cita['id_paciente'],
                'plantillas'    => array(),
                'fecha_cita'        => FuncionesGlobales::formatearFecha($arr_info_cita['fecha_nueva']),
                'hora_cita'         => $arr_info_cita['hora'],
                'dia_cita'          => $dias_semana[$arr_info_cita['dia_cita'] - 1],
                'nombre_completo'   => $arr_info_cita['nombre_completo'],
                'id_agenda_cita'    => $id_agenda_cita
            );
            
            //  BUSQUEDA DE PLANTILLA
            $phql = "SELECT * FROM ctplantillas_mensajes WHERE tipo_mensaje IS NULL ";

            $flag_plantilla = false;

            //  AVISO DE CITA CANCELADA
            if ($arr_info_cita['activa'] == 0){
                $phql           .= " OR tipo_mensaje = 0 ";
                $flag_plantilla = true;
            }

            //  AVISO DE CITA MARCADA COMO PENDIENTE POR REAGENDAR
            if ($arr_info_cita['activa'] == 2){
                $phql           .= " OR tipo_mensaje = 3 ";
                $flag_plantilla = true;
            }

            //  AVISO DE CITA ORDINARIA CREADA
            if ($arr_info_cita['activa'] == 1 && $arr_info_cita['id_cita_programada'] == null && !is_numeric($arr_info_cita['id_cita_reagendada'])){
                $phql           .= " OR tipo_mensaje = 1 ";
                $flag_plantilla = true;
            }

            //  AVISO DE CITA REAGENDADA
            if ($arr_info_cita['activa'] == 1 && is_numeric($arr_info_cita['id_cita_reagendada'])){
                $phql           .= " OR tipo_mensaje = 2 ";
                $flag_plantilla = true;
            }
    
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            while ($row = $result->fetch()) {
                $plantilla  = array(
                    'mensaje'   => '',
                    'link'      => '',
                    'nombre_plantilla'      => $row['nombre'],
                    'id_plantilla_mensaje'  => $row['id']
                );

                $reemplazos = [
                    '{{PACIENTE}}'        => $arr_info_cita['nombre'],
                    '{{PROFESIONAL}}'     => $arr_info_cita['nombre_profesional'],
                    '{{FECHA_CITA}}'      => FuncionesGlobales::formatearFecha($arr_info_cita['fecha_nueva']),
                    '{{HORA}}'            => $arr_info_cita['hora'],
                    '{{FECHA_ANTERIOR}}'  => $arr_info_cita['fecha_anterior']
                        ? FuncionesGlobales::formatearFecha($arr_info_cita['fecha_anterior'])
                        : '',
                    '{{LOCACION}}'        => $arr_info_cita['locacion'],
                    '{{MAPS_URL}}'        => (
                        $arr_info_cita['latitud'] && $arr_info_cita['longitud']
                            ? "https://maps.google.com/?q={$arr_info_cita['latitud']},{$arr_info_cita['longitud']}"
                            : ''
                    ),
                    '{{DIA_CITA}}'        => $dias_semana[$arr_info_cita['dia_cita'] - 1],
                    '{{DIA_ANTERIOR}}'    => $dias_semana[$arr_info_cita['dia_anterior'] - 1],
                ];

                $plantilla['mensaje']   = str_replace(
                    array_keys($reemplazos),
                    array_values($reemplazos),
                    $row['mensaje']
                );

                // Normalizar saltos de línea desde BD
                $mensaje    = $plantilla['mensaje'];
                $mensaje    = str_replace('%0A', "\n", $mensaje);

                // Limpieza extra (por si acaso)
                $mensaje = html_entity_decode($mensaje, ENT_QUOTES, 'UTF-8');

                // Generar link FINAL correcto
                $plantilla['link']  =
                    'https://web.whatsapp.com/send?phone=' .
                    $telefono .
                    '&text=' .
                    urlencode($mensaje);

                $arr_return['plantillas'][] = $plantilla;
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

    $app->post('/plantillas_mensajes/plantilla_enviada', function () use ($app, $db, $request) {
        try {

            //  PARAMETROS
            $id_agenda_cita         = $request->getPost('id_agenda_cita') ?? null;
            $id_plantilla_mensaje   = $request->getPost('id_plantilla_mensaje');
            $id_paciente            = $request->getPost('id_paciente') ?? null;
            $id_profesional         = $request->getPost('id_profesional') ?? null;
            $mensaje_generado       = $request->getPost('mensaje_generado');
            $usuario_solicitud      = $request->getPost('usuario_solicitud');

            if (empty($id_plantilla_mensaje) || !is_numeric($id_plantilla_mensaje)){
                throw new Exception("Identificador de plantilla vacio");
            }

            //  SE BUSCA EL ID_PROFESIONAL DEL USUARIO
            $phql   = "SELECT * FROM ctusuarios WHERE clave = :clave";
            $result = $db->query($phql,array('clave' => $usuario_solicitud));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            // Recorrer los resultados
            $id_usuario_solicitud   = null;
            while ($row = $result->fetch()) {
                $id_usuario_solicitud   = $row['id'];
            }

            if (empty($mensaje_generado)){
                throw new Exception("Mensaje vacio");
            }

            //  SE CREA EL REGISTRO
            $phql   = "INSERT INTO tbmensajes_enviados (
                                    id_plantilla_mensaje,
                                    id_usuario_solicitud,
                                    id_agenda_cita,
                                    id_paciente,
                                    id_profesional,
                                    mensaje_generado)
                        VALUES (:id_plantilla_mensaje,
                                :id_usuario_solicitud,
                                :id_agenda_cita,
                                :id_paciente,
                                :id_profesional,
                                :mensaje_generado)";

            $values = array(
                'id_plantilla_mensaje'  => $id_plantilla_mensaje,
                'id_usuario_solicitud'  => $id_usuario_solicitud,
                'id_agenda_cita'        => empty($id_agenda_cita) ? null : $id_agenda_cita,
                'id_paciente'           => empty($id_paciente) ? null : $id_paciente,
                'id_profesional'        => empty($id_profesional) ? null : $id_profesional,
                'mensaje_generado'      => $mensaje_generado
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