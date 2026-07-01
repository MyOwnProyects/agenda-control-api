CREATE OR REPLACE FUNCTION fn_saldo_cita(p_id_agenda_cita INT)
RETURNS NUMERIC AS $$
DECLARE
    v_costo         NUMERIC;
    v_total_abonado NUMERIC;
BEGIN
    -- Obtener costo de la cita
    SELECT total INTO v_costo
    FROM tbagenda_citas
    WHERE id = p_id_agenda_cita;

    -- Obtener total abonado
    SELECT COALESCE(SUM(monto), 0) INTO v_total_abonado
    FROM tbabonos_movimientos
    WHERE id_agenda_cita = p_id_agenda_cita
    AND estatus = 1;

    -- Retornar saldo pendiente, mínimo 0
    RETURN GREATEST(v_costo - v_total_abonado, 0);
END;
$$ LANGUAGE plpgsql;