<?php

declare(strict_types=1);

namespace Middleware;

use Phalcon\Mvc\Micro;
use Phalcon\Di\DiInterface;
use Phalcon\Http\Request;

class JsonMiddleware
{
    public function __invoke(Micro $app)
    {
        // Obtener el contenedor de dependencias correctamente
        $di = $app->getDI();
        $request = $di->getShared('request');

        $parsedBody = [];
        
        // Obtener Content-Type desde el header y servidor
        $contentType = $request->getHeader("CONTENT_TYPE") ?: $request->getServer("CONTENT_TYPE");


        // Verificar si la solicitud tiene JSON
        if (strpos($request->getHeader("CONTENT_TYPE"), "application/json") !== false) {
            $rawBody = $request->getRawBody();
            $parsedBody = json_decode($rawBody, true);

            // Si el JSON es inválido, establecerlo como un array vacío
            if (json_last_error() !== JSON_ERROR_NONE) {
                $parsedBody = [];
            }
        }

        $_POST  = $parsedBody;

        return true;
    }
}
