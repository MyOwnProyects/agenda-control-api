<?php

namespace Middleware;

use Phalcon\Http\Response;
use Services\JwtService;
use Services\BlacklistService;
use Exception;

class AuthMiddleware
{
    public function __invoke($app)
    {
        $request = $app->getDI()->get('request');
        $db = $app->getDI()->get('db');
        
        // Obtener token del header Authorization
        $authHeader = $request->getHeader('Authorization');
        
        if (!$authHeader) {
            return $this->unauthorized($app, 'Token no proporcionado');
        }
        
        // Verificar formato: "Bearer {token}"
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->unauthorized($app, 'Formato de token inválido. Use: Bearer {token}');
        }
        
        $token = $matches[1];
        
        try {
            // Validar token
            $jwtService = new JwtService();
            $payload = $jwtService->validateToken($token);
            
            // Verificar que sea access token
            if (!isset($payload['type']) || $payload['type'] !== 'access') {
                return $this->unauthorized($app, 'Tipo de token inválido. Use access token.');
            }
            
            // Verificar que NO esté en blacklist
            $blacklistService = new BlacklistService($db);
            if ($blacklistService->isBlacklisted($payload['jti'])) {
                return $this->unauthorized($app, 'Token revocado');
            }
            
            // Guardar usuario autenticado en DI
            $app->getDI()->setShared('authenticatedUser', function() use ($payload) {
                return (object)[
                    'id'        => $payload['sub'],
                    'username'  => $payload['username'] ?? null,
                    'nombre'    => $payload['nombre_completo'] ?? null,
                ];
            });
            
            return true;
            
        } catch (Exception $e) {
            return $this->unauthorized($app, $e->getMessage());
        }
    }
    
    private function unauthorized($app, $message)
    {
        $response = new Response();
        $response->setStatusCode(401, "Unauthorized");
        $response->setJsonContent([
            'status'    => 'error',
            'message'   => $message,
            'validado'  => false,
        ]);
        $response->send();
        exit;
    }
}