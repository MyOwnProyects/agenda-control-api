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

    $app->get('/plantillas_mensajes/generar_mensaje', function () use ($app,$db,$request) {
        try{

            $id_agenda_cita = $request->getQuery('id_agenda_cita') ?? null;

            $dias_semana    = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];

            if (empty($id_agenda_cita) || !is_numeric($id_agenda_cita)){
                throw new Exception('Parametro de cita invalido');
            }

            //  SE GENERA LA INFORMACION DE LA CITA
            $phql   = " SELECT  
                            (e.nombre||' '||e.primer_apellido) as nombre_completo,
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
                            b.dia as dia_anterior
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
            
            //  BUSQUEDA DE PLANTILLA
            $phql = "SELECT * FROM ctplantillas_mensajes WHERE 1 = 1 ";

            $flag_plantilla = false;

            //  AVISO DE CITA CANCELADA
            if ($arr_info_cita['activa'] == 0){
                $phql           .= " AND tipo_mensaje = 0 ";
                $flag_plantilla = true;
            }

            //  AVISO DE CITA MARCADA COMO PENDIENTE POR REAGENDAR
            if ($arr_info_cita['activa'] == 2){
                $phql           .= " AND tipo_mensaje = 3 ";
                $flag_plantilla = true;
            }

            //  AVISO DE CITA ORDINARIA CREADA
            if ($arr_info_cita['activa'] == 1 && $arr_info_cita['id_cita_programada'] == null && !is_numeric($arr_info_cita['id_cita_reagendada'])){
                $phql           .= " AND tipo_mensaje = 1 ";
                $flag_plantilla = true;
            }

            //  AVISO DE CITA REAGENDADA
            if ($arr_info_cita['activa'] == 1 && is_numeric($arr_info_cita['id_cita_reagendada'])){
                $phql           .= " AND tipo_mensaje = 2 ";
                $flag_plantilla = true;
            }

            if (!$flag_plantilla){
                $response = new Response();
                $response->setJsonContent(array(
                    'mensaje'   => array(),
                    'celular'   => $arr_info_cita['celular'],
                    'link'      => ''
                ));
                $response->setStatusCode(200, 'OK');
                return $response;
            }
    
            $result = $db->query($phql);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
    
            $arr_return = array();
            while ($row = $result->fetch()) {
                $reemplazos = [
                    '{{PACIENTE}}'        => $arr_info_cita['nombre_completo'],
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

                $arr_info_cita['celular']   = 6624767555;
                $arr_return['mensaje'] = str_replace(
                    array_keys($reemplazos),
                    array_values($reemplazos),
                    $row['mensaje']
                );

                // Normalizar saltos de línea desde BD
                $mensaje    = $arr_return['mensaje'];
                $mensaje    = str_replace('%0A', "\n", $mensaje);

                // Limpieza extra (por si acaso)
                $mensaje = html_entity_decode($mensaje, ENT_QUOTES, 'UTF-8');

                // Sanitizar teléfono (solo números)
                $telefono = preg_replace('/\D/', '', $arr_info_cita['celular']);

                $arr_return['celular'] = $telefono;

                // 🔹 Generar link FINAL correcto
                $arr_return['link'] =
                    'https://web.whatsapp.com/send?phone=52' .
                    $telefono .
                    '&text=' .
                    urlencode($mensaje);
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
    
};