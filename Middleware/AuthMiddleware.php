<?php

namespace Middleware;

use Phalcon\Http\Response;

class AuthMiddleware
{
    public function __invoke($app)
    {
        // Obtener el servicio request desde el contenedor DI
        $request = $app->getDI()->get('request');

        // Obtener el token de autorización de los encabezados
        $token = $request->getHeader('Authorization');

        // Verificar si el token es válido
        if (!$token || $token !== 'Bearer your-secret-token') {
            // Si no está autorizado, devolver una respuesta 401
            $response = new Response();
            $response->setStatusCode(401, "Unauthorized");
            $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Acceso no autorizado',
            ]);
            $response->send();

            // Detener la ejecución del script
            exit;
        }

        // Si está autorizado, permitir continuar
        return true;
    }
}
