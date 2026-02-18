<?php

namespace Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtService
{
    private $config;

    public function __construct()
    {
        $this->config = include(__DIR__ . '/../config.php');
    }

    /**
     * Generar Access Token
     */
    public function generateAccessToken($user)
    {
        $now = time();
        $jwtConfig = $this->config['jwt'];
        
        $payload = [
            'jti' => bin2hex(random_bytes(16)),     // ID único del token
            'iat' => $now,                           // Issued at
            'exp' => $now + $jwtConfig['access_token_expire'],
            'sub' => $user['id'],                    // User ID
            'username' => $user['clave'],            // Username
            'nombre_completo' => trim($user['nombre'] . ' ' . $user['primer_apellido']),
            'type' => 'access'
        ];

        return JWT::encode($payload, $jwtConfig['secret_key'], $jwtConfig['algorithm']);
    }

    /**
     * Generar Refresh Token
     */
    public function generateRefreshToken($user)
    {
        $now = time();
        $jwtConfig = $this->config['jwt'];
        
        $payload = [
            'jti' => bin2hex(random_bytes(16)),     // ID único del token
            'iat' => $now,
            'exp' => $now + $jwtConfig['refresh_token_expire'],
            'sub' => $user['id'],
            'type' => 'refresh'
        ];

        return JWT::encode($payload, $jwtConfig['secret_key'], $jwtConfig['algorithm']);
    }

    /**
     * Validar y decodificar token
     */
    public function validateToken($token)
    {
        try {
            $jwtConfig = $this->config['jwt'];
            $decoded = JWT::decode($token, new Key($jwtConfig['secret_key'], $jwtConfig['algorithm']));
            return (array)$decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new Exception('Token expirado', 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new Exception('Token inválido - firma incorrecta', 401);
        } catch (Exception $e) {
            throw new Exception('Token inválido: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Obtener JTI de un token sin validar (útil para blacklist)
     */
    public function getTokenJti($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload['jti'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}