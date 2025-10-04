<?php
namespace Helpers;

class FuncionesGlobales {

    public static function ToLower($string){
        return mb_strtolower($string, 'UTF-8');
    }

    public static function formatearFecha($fecha) {
        if(empty($fecha)) return '';
        return date("d/m/Y", strtotime($fecha));
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
}