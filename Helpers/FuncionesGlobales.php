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
}