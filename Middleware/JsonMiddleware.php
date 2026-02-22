<?php
declare(strict_types=1);

namespace Middleware;

use Phalcon\Mvc\Micro;

class JsonMiddleware
{
    public function __invoke(Micro $app)
    {
        $di = $app->getDI();
        $request = $di->getShared('request');
        
        // Obtener Content-Type
        $contentType = $request->getHeader("CONTENT_TYPE") ?: '';
        
        // SOLO procesar JSON si viene como application/json
        if (strpos($contentType, "application/json") !== false) {
            $rawBody = $request->getRawBody();
            
            // Validar que no esté vacío
            if (empty($rawBody)) {
                return true;
            }
            
            $parsedBody = json_decode($rawBody, true);
            
            // Validar JSON válido
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsedBody)) {
                // Sanitizar: solo permitir arrays simples
                $_POST = $this->sanitizeArray($parsedBody);
            } else {
                // JSON inválido - rechazar
                $response = new \Phalcon\Http\Response();
                $response->setStatusCode(400, "Bad Request");
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'JSON inválido'
                ]);
                $response->send();
                exit;
            }
        }
        // Si es form-urlencoded, PHP ya pobló $_POST de forma segura
        
        return true;
    }
    
    /**
     * Sanitizar array recursivamente
     * Previene objetos anidados maliciosos
     */
    private function sanitizeArray($data, $depth = 0)
    {
        // Limitar profundidad para prevenir DoS
        if ($depth > 10) {
            return [];
        }
        
        if (!is_array($data)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            // Solo permitir strings y números como keys
            if (!is_string($key) && !is_numeric($key)) {
                continue;
            }
            
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $depth + 1);
            } else {
                // Convertir a string de forma segura
                $sanitized[$key] = is_scalar($value) ? $value : null;
            }
        }
        
        return $sanitized;
    }
}