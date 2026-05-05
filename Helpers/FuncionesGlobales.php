<?php
namespace Helpers;

class FuncionesGlobales {

    public static function ToLower($string){
        return mb_strtolower($string, 'UTF-8');
    }

    public static function formatearFecha($fecha,$formato_retorno = null) {
        if(empty($fecha)) return '';
        $formato_retorno    = $formato_retorno != null ? $formato_retorno : "d/m/Y";
        return date($formato_retorno, strtotime($fecha));
    }

    public static function formatearFechaHumana($fecha) {
        if(empty($fecha)) return '';
        
        $meses = [
            1  => 'Enero',    2  => 'Febrero',  3  => 'Marzo',
            4  => 'Abril',    5  => 'Mayo',     6  => 'Junio',
            7  => 'Julio',    8  => 'Agosto',   9  => 'Septiembre',
            10 => 'Octubre',  11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $timestamp = strtotime($fecha);
        $dia       = date('j', $timestamp);
        $mes       = $meses[(int) date('n', $timestamp)];
        $anio      = date('Y', $timestamp);

        return "{$dia} de {$mes} del {$anio}";
    }

    public static function validarCorreo($correo) {
        return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $correo);
    }
    
    public static function validarTelefono($telefono) {
        return preg_match('/^\d{10}$/', $telefono);
    }

    public static function generarToken($longitud = 32) {
        return bin2hex(random_bytes($longitud / 2));
    }

    public static function validarCantidadMonetaria($cantidad) {
        // Expresión regular para validar números enteros o con hasta dos decimales
        $regex = '/^\d+(\.\d{1,2})?$/';
    
        // Si la cantidad es un número entero sin decimales, la convertimos a string
        if (is_int($cantidad)) {
            $cantidad = strval($cantidad);
        }
    
        // Verificar que la cantidad sea un string y que coincida con la expresión regular
        if (is_string($cantidad) && preg_match($regex, $cantidad)) {
            $numero = floatval($cantidad);
            // Verificar que no sea negativo
            if ($numero >= 0) {
                return true;
            }
        } elseif (is_float($cantidad) || is_int($cantidad)) {
            // Verificar que no sea negativo y que sea un número
            if ($cantidad >= 0) {
                return true;
            }
        }
    
        // Si no cumple con las condiciones, retornar false
        return false;
    }

    public static function formatearDecimal($valor) {
        // Convertir el valor a float
        $valor = floatval($valor);
        
        // Formatear el valor con 2 decimales
        $valorFormateado = number_format($valor, 2, '.', '');
        
        return $valorFormateado;
    }

    public static function raiseExceptionMessage($mensaje){
        // Usamos una expresión regular para extraer el texto deseado
        preg_match('/ERROR: (.*?)\.\.\./', $mensaje, $matches);

        // Verificamos si se encontró una coincidencia
        if (isset($matches[1])) {
            $textoExtraido = $matches[1];
            return  $textoExtraido;
        } else {
            return $mensaje;
        }
    }

    public static function clear_text_html($html)
    {
        // 1. Remover etiquetas <script> y su contenido completo
        // ✅ CORRECTO (flags de PHP)
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $html);
        
        // 2. Remover etiquetas <?php y su contenido
        $html = preg_replace('/<\?php.*?\?>/is', '', $html);
        $html = preg_replace('/<\?.*?\?>/is', '', $html);
        
        // 3. Remover todos los eventos JavaScript (onclick, onmouseover, etc.)
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s*on\w+\s*=\s*[^>\s]*/i', '', $html);
        
        // 4. Remover javascript: y vbscript: en URLs
        $html = preg_replace('/javascript\s*:/i', '', $html);
        $html = preg_replace('/vbscript\s*:/i', '', $html);
        
        // 5. Remover etiquetas peligrosas específicas
        $dangerousTags = [
            'script', 'php', 'iframe', 'object', 'embed', 'form', 'input', 
            'button', 'textarea', 'select', 'style', 'link', 'meta'
        ];
        
        foreach ($dangerousTags as $tag) {
            $html = preg_replace('/<\/?' . preg_quote($tag) . '(\s[^>]*)?>/i', '', $html);
        }
        
        // // 6. Solo permitir etiquetas HTML seguras (basado en tu configuración de Summernote)
        // $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><span><div><font>';
        // $html = strip_tags($html, $allowedTags);
        
        // 7. Limpiar atributos style peligrosos pero mantener seguros
        // $html = preg_replace_callback(
        //     '/style\s*=\s*["\']([^"\']*)["\']/i',
        //     function($matches) {
        //         $styleContent = $matches[1];
                
        //         // Remover CSS peligroso
        //         if (preg_match('/expression|javascript|behavior|binding/i', $styleContent)) {
        //             return '';
        //         }
                
        //         // Solo permitir propiedades CSS seguras
        //         $allowedProps = ['color', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'background-color', 'text-align'];
        //         $cleanStyles = [];
                
        //         foreach (explode(';', $styleContent) as $property) {
        //             $property = trim($property);
        //             if (empty($property)) continue;
                    
        //             $parts = explode(':', $property, 2);
        //             if (count($parts) == 2) {
        //                 $prop = trim(strtolower($parts[0]));
        //                 $value = trim($parts[1]);
                        
        //                 if (in_array($prop, $allowedProps) && !preg_match('/javascript|expression/i', $value)) {
        //                     $cleanStyles[] = $prop . ': ' . $value;
        //                 }
        //             }
        //         }
                
        //         return !empty($cleanStyles) ? 'style="' . implode('; ', $cleanStyles) . '"' : '';
        //     },
        //     $html
        // );
        
        // 8. Limpiar espacios extra y retornar
        return trim(preg_replace('/\s+/', ' ', $html));
    }

    /**
     * Calcula el Índice de Masa Corporal (IMC).
     *
     * @param float $peso   Peso en kilogramos (kg).
     * @param float $altura Altura en centímetros (cm).
     * @return float        IMC redondeado a 2 decimales.
     */
    public static function calcularIMC($peso, $altura) {
        // Validar que sean valores positivos
        if ($peso <= 0 || $altura <= 0 || empty($peso) || empty($altura)) {
            return null;
        }

        // Convertir altura de centímetros a metros
        $alturaMetros = $altura / 100;

        // Calcular IMC
        $imc = $peso / pow($alturaMetros, 2);

        // Retornar con 2 decimales
        return round($imc, 2);
    }

    /**
     * Function para aplicar saldo a favor en los conceptos deudores
     * 
     * @param transaction   $conexion               Conexion de la transaccion
     * @param Numeric       $id_paciente            Paciente a aplicar
     * @param Numeric       $id_usuario_solicitud   
     */
    public static function AplicarSaldoFavor($conexion,$id_paciente,$id_usuario_solicitud){
        try{

            //  SE BUSCAN SI EL PACIENTE TIENE SALDO A FAVOR
            $saldo_favor    = 0;
            $phql   = "SELECT * FROM fn_saldo_favor_paciente(:id_paciente);";
            $result = $conexion->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    $saldo_favor    = $data['fn_saldo_favor_paciente'];
                }
            }

            if ($saldo_favor == 0){
                return array(
                    'msg'   => "El paciente no cuenta con saldo a favor",
                    'error' => false
                );
            }

            //  SE OBTIENEN TODOS LOS ABONOS QUE HA REALIZO EL PACIENTE QUE TENGAN SALDO A FAVOR
            $phql   = " SELECT 
                            a.id as id_abono,
                            a.monto - COALESCE(b.monto_usado, 0) as cantidad_disponible,
                            a.ticket_folio
                        FROM tbabonos a
                        LEFT JOIN LATERAL (
                            SELECT SUM(t1.monto) AS monto_usado 
                            FROM tbabonos_movimientos t1
                            WHERE a.id = t1.id_abono 
                            AND (t1.estatus = 1 OR (t1.estatus = 0 AND t1.tipo_cancelacion = 2))
                        ) b ON TRUE
                        WHERE a.id_paciente = :id_paciente
                        AND a.estatus = 1 AND a.monto - COALESCE(b.monto_usado, 0) > 0
                        ORDER BY a.fecha_hora_pago;";

            $arr_abonos = array();
            $result = $conexion->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            if ($result){
                while($data = $result->fetch()){
                    $arr_abonos[]   = $data;
                }
            }

            //  SE BUSCAN TODAS LAS CITAS QUE NO ESTEN PAGADAS
            $phql   = " SELECT a.*,fn_saldo_cita(a.id) as saldo_cita FROM tbagenda_citas a
                        WHERE a.id_paciente = :id_paciente AND activa <> 0 AND pagada = 0
                        ORDER BY fecha_cita ASC;";

            $result = $conexion->query($phql,array(
                'id_paciente'   => $id_paciente
            ));
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);

            $arr_citas_pagar    = array();
            if ($result){
                while($data = $result->fetch()){
                    $arr_citas_pagar[]  = $data;
                }
            }

            if (count($arr_citas_pagar) == 0){
                return array(
                    'msg'   => "El paciente no cuenta con citas deudoras",
                    'error' => false
                );
            }

            //  SE HACE EL RECORRIDO DE ABONOS PARA CREAR LOS MOVTOS
            foreach($arr_abonos as $index_abono => $abono){
                // Formatear a 2 decimales para asegurar formato monetario
                $monto      = $abono['cantidad_disponible'];
                $monto      = round($monto, 2);
                $id_abono   = $abono['id_abono'];
                $ticket_folio   = $abono['ticket_folio'];

                //  SE RECORREN TODAS LAS CITAS A PAGAR
                foreach($arr_citas_pagar as $index => $cita_pagar){
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
                        $arr_citas_pagar[$index]['saldo_cita']  = (($cita_pagar['saldo_cita'] * 100) - ($monto * 100)) / 100;
                        $monto_movto                            = $monto;
                        $monto                                  = 0;
                    }

                    $phql   = "INSERT INTO tbabonos_movimientos (
                                            id_abono,
                                            id_agenda_cita,
                                            monto,
                                            id_usuario_captura,
                                            ticket_folio
                                            )
                                        VALUES (
                                            :id_abono,
                                            :id_agenda_cita,
                                            :monto,
                                            :id_usuario_captura,
                                            :ticket_folio
                                        )";
                    
                    $values = array(
                        'id_abono'              => $id_abono,
                        'id_agenda_cita'        => $cita_pagar['id'],
                        'monto'                 => $monto_movto,
                        'id_usuario_captura'    => $id_usuario_solicitud,
                        'ticket_folio'          => $ticket_folio
                    );

                    $result = $conexion->execute($phql,$values);

                    //  SI SE LIQUIDO LA CITA ESTA SE SACA DEL ARRAY Y SE MARCA COMO PAGADA
                    if ($liquidar_cargo){
                        $phql   = "UPDATE tbagenda_citas SET pagada = 1, fecha_pago = NOW() WHERE id = :id_agenda_cita";
                        $result = $conexion->execute($phql,array('id_agenda_cita' => $cita_pagar['id']));

                        unset($arr_citas_pagar[$index]);
                    }

                    //  SI EL MONTO LLEGA A 0 SE TENIENE EL RECORRIDO
                    if ($monto == 0){
                        break;
                    }
                }

                //  ARRAY DE ID ABONOS GENERADOS Y CANTIDAD RESTANTE POSTERIOR A SER APLICADO
                //  ESTO SOLO SI EL MONTO TIENE DINERO, SIRVE PARA SABER SI QUEDO ANTICIPADO
                if ($monto == 0){
                    unset($arr_abonos[$index_abono]);
                } else {
                    $arr_abonos[$index_abono]['cantidad_disponible']    = $monto;
                }
            }

            return array(
                'msg'   => 'Abonos aplicados correctamente',
                'error' => false
            );

        } catch(\Exception $e){
            return array(
                'msg'   => $e->getMessage(),
                'error' => true
            );
        }
    }
}