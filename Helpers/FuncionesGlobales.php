<?php
namespace Helpers;

class FuncionesGlobales {

    public static function ToLower($string){
        return mb_strtolower($string, 'UTF-8');
    }

    public static function formatearFecha($fecha) {
        return date("d-m-Y", strtotime($fecha));
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
}