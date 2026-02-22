<?php

namespace Services;

use Exception;

class BlacklistService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Verificar si un token está en lista negra
     */
    public function isBlacklisted($jti)
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM tbjwt_lista_negra WHERE token_jti = :jti";
            $result = $this->db->query($sql, ['jti' => $jti]);
            $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
            $row = $result->fetch();
            
            return $row['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Agregar token a lista negra
     */
    public function addToBlacklist($jti, $userId, $tokenType, $expiresAt, $reason = null, $ipAddress = null)
    {
        try {
            $sql = "INSERT INTO tbjwt_lista_negra 
                    (token_jti, id_usuario, tipo_token, fecha_expiracion, motivo, direccion_ip) 
                    VALUES (:jti, :id_usuario, :tipo_token, :fecha_expiracion, :motivo, :direccion_ip)";
            
            $this->db->execute($sql, [
                'jti' => $jti,
                'id_usuario' => $userId,
                'tipo_token' => $tokenType,
                'fecha_expiracion' => date('Y-m-d H:i:s', $expiresAt),
                'motivo' => $reason,
                'direccion_ip' => $ipAddress
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Error al revocar token');
        }
    }

    /**
     * Revocar todos los tokens de un usuario
     */
    /* public function revokeAllUserTokens($userId, $reason = 'logout_all')
    {
        try {
            error_log("Revocados todos los tokens del usuario: $userId - Motivo: $reason");
            return true;
        } catch (Exception $e) {
            error_log('Error revocando tokens del usuario: ' . $e->getMessage());
            return false;
        }
    } */

    /**
     * Limpiar tokens expirados de la lista negra
     */
    /* public function cleanExpired()
    {
        try {
            $sql = "DELETE FROM tbjwt_lista_negra WHERE fecha_expiracion < NOW()";
            $this->db->execute($sql);
            return true;
        } catch (Exception $e) {
            error_log('Error limpiando tokens expirados: ' . $e->getMessage());
            return false;
        }
    } */
}