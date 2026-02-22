<?php

use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Services\JwtService;
use Services\BlacklistService;

return function (Micro $app, $di) {
    
    $request = $app->getDI()->get('request');
    $db = $di->get('db');

    // ============================================
    // POST /api/auth/login - Autenticar usuario
    // ============================================
    $app->post('/autenticacion/login', function () use ($app, $db, $request) {
        try {
            // Soportar tanto JSON como form-urlencoded
            $contentType = $request->getHeader('Content-Type');
            
            if (strpos($contentType, 'application/json') !== false) {
                // Datos en JSON
                $rawBody = $request->getRawBody();
                $data = json_decode($rawBody, true);
                $username = $data['username'] ?? null;
                $password = $data['password'] ?? null;
            } else {
                // Datos en form-urlencoded (POST normal)
                $username = $request->getPost('username');
                $password = $request->getPost('password');
            }

            // Validar que vengan los datos
            if (empty($username) || empty($password)) {
                $response = new Response();
                $response->setStatusCode(400, "Bad Request");
                $response->setJsonContent([
                    'status' => 'error',
                    'validado' => false,
                    'message' => 'Username y password son requeridos'
                ]);
                return $response;
            }

            // Buscar usuario en base de datos
            $sql = "SELECT 
                        a.*,
                        (a.primer_apellido|| ' ' ||COALESCE(a.segundo_apellido,'')||' '||a.nombre) as nombre_completo,
                        b.clave as clave_tipo_usuario, 
                        b.nombre as nombre_tipo_usuario,
                        a.id_profesional
                    FROM ctusuarios a 
                    LEFT JOIN cttipo_usuarios b ON a.id_tipo_usuario = b.id 
                    WHERE a.clave = :username AND estatus = 1";
            
            $result = $db->query($sql, ['username' => $username]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
            $user = $result->fetch();

            if (!$user) {
                $response = new Response();
                $response->setStatusCode(401, "Unauthorized");
                $response->setJsonContent([
                    'status' => 'error',
                    'validado' => false,
                    'message' => 'Credenciales inválidas'
                ]);
                return $response;
            }

            // Validar contraseña (SHA256)
            $hash = hash('sha256', $password);
            if (!hash_equals($user['contrasena'], $hash)) {
                $response = new Response();
                $response->setStatusCode(401, "Unauthorized");
                $response->setJsonContent([
                    'status' => 'error',
                    'validado' => false,
                    'message' => 'Credenciales inválidas'
                ]);
                return $response;
            }

            // Generar tokens JWT
            $jwtService     = new JwtService();
            $accessToken    = $jwtService->generateAccessToken($user);
            $refreshToken   = $jwtService->generateRefreshToken($user);

            // Respuesta exitosa
            $response = new Response();
            $response->setStatusCode(200, "OK");
            $response->setJsonContent([
                'status'        => 'success',
                'validado'      => true,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
                'expires_in'    => 1800, // 30 minutos
                'user'          => $user
            ]);
            return $response;

        } catch (\Exception $e) {
            $response = new Response();
            $response->setStatusCode(500, "Internal Server Error");
            $response->setJsonContent([
                'status' => 'error',
                'validado' => false,
                'message' => 'Error en el servidor'
            ]);
            return $response;
        }
    });

    // ============================================
    // POST /api/auth/refresh - Renovar access token
    // ============================================
    $app->post('/autenticacion/refresh', function () use ($app, $db, $request) {
        try {
            $refreshToken = $request->getPost('refresh_token');

            if (empty($refreshToken)) {
                $response = new Response();
                $response->setStatusCode(400, "Bad Request");
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Refresh token requerido'
                ]);
                return $response;
            }

            // Validar refresh token
            $jwtService = new JwtService();
            $payload = $jwtService->validateToken($refreshToken);

            // Verificar que sea refresh token
            if ($payload['type'] !== 'refresh') {
                $response = new Response();
                $response->setStatusCode(400, "Bad Request");
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Token inválido. Use refresh token.'
                ]);
                return $response;
            }

            // Verificar que NO esté en blacklist
            $blacklistService = new BlacklistService($db);
            if ($blacklistService->isBlacklisted($payload['jti'])) {
                $response = new Response();
                $response->setStatusCode(401, "Unauthorized");
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Refresh token revocado'
                ]);
                return $response;
            }

            // Buscar usuario
            $sql = "SELECT id, clave, nombre, primer_apellido, segundo_apellido, id_tipo_usuario 
                    FROM ctusuarios 
                    WHERE id = :user_id AND estatus = 1";
            
            $result = $db->query($sql, ['user_id' => $payload['sub']]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
            $user = $result->fetch();

            if (!$user) {
                $response = new Response();
                $response->setStatusCode(401, "Unauthorized");
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ]);
                return $response;
            }

            // Generar NUEVO access token
            $newAccessToken = $jwtService->generateAccessToken($user);

            $response = new Response();
            $response->setStatusCode(200, "OK");
            $response->setJsonContent([
                'status' => 'success',
                'access_token' => $newAccessToken,
                'token_type' => 'Bearer',
                'expires_in' => 1800
            ]);
            return $response;

        } catch (\Exception $e) {
            $response = new Response();
            $response->setStatusCode(401, "Unauthorized");
            $response->setJsonContent([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            return $response;
        }
    });

    // ============================================
    // POST /api/auth/logout - Revocar tokens
    // ============================================
    $app->post('/autenticacion/logout', function () use ($app, $db, $request) {
        try {
            $refreshToken = $request->getPost('refresh_token');
            $accessToken = $request->getHeader('Authorization');

            if (empty($refreshToken)) {
                $response = new Response();
                $response->setStatusCode(400, "Bad Request");
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Refresh token requerido'
                ]);
                return $response;
            }

            $jwtService = new JwtService();
            $blacklistService = new BlacklistService($db);

            // Validar y agregar refresh token a blacklist
            $payload = $jwtService->validateToken($refreshToken);
            $blacklistService->addToBlacklist(
                $payload['jti'],
                $payload['sub'],
                'refresh',
                $payload['exp'],
                'logout',
                $request->getClientAddress()
            );

            // Opcionalmente agregar access token a blacklist
            if ($accessToken && preg_match('/Bearer\s(\S+)/', $accessToken, $matches)) {
                $token = $matches[1];
                try {
                    $accessPayload = $jwtService->validateToken($token);
                    $blacklistService->addToBlacklist(
                        $accessPayload['jti'],
                        $accessPayload['sub'],
                        'access',
                        $accessPayload['exp'],
                        'logout',
                        $request->getClientAddress()
                    );
                } catch (\Exception $e) {
                    // Access token inválido/expirado, no importa
                }
            }

            $response = new Response();
            $response->setStatusCode(200, "OK");
            $response->setJsonContent([
                'status' => 'success',
                'message' => 'Sesión cerrada correctamente'
            ]);
            return $response;

        } catch (\Exception $e) {
            $response = new Response();
            $response->setStatusCode(500, "Internal Server Error");
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'Error al cerrar sesión'
            ]);
            return $response;
        }
    });
};