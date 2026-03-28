CREATE OR REPLACE FUNCTION fn_saldo_favor_paciente(p_id_paciente INT)
RETURNS NUMERIC AS $$
DECLARE
    v_saldo_favor   NUMERIC;
BEGIN

    --  SE CALCULA EL SALDO A FAVOR DEL PACIENTE
    --  OBTENIENDO TODOS LOS ABONOS DE ESTE Y A CADA UNO RESTANDOLE
    --  LOS MOVIMIENTOS QUE TENGA, AL FINAL SE HACE UNA SUMA DE CADA ABONO
    SELECT COALESCE(SUM(a.monto - COALESCE(b.monto_usado, 0)), 0) INTO v_saldo_favor
    FROM tbabonos a
    LEFT JOIN LATERAL (
        SELECT SUM(t1.monto) AS monto_usado 
        FROM tbabonos_movimientos t1
        WHERE a.id = t1.id_abono 
        AND t1.estatus = 1
    ) b ON TRUE
    WHERE a.id_paciente = p_id_paciente
    AND a.estatus = 1;

    -- Retornar saldo pendiente, mínimo 0
    RETURN v_saldo_favor;
END;
$$ LANGUAGE plpgsql;