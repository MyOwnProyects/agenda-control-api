CREATE OR REPLACE FUNCTION fn_cancelar_citas()
RETURNS TRIGGER AS $$
BEGIN

    IF (NEW.activa <> 0) THEN 
        RETURN NULL;
    END IF;

    --  ESTA FUNCION REALIZA LA CANCELACION DE MOVTOS
    --  DESPUES DE HABER CANCELADO UNA CITA
    UPDATE tbabonos_movimientos SET 
        estatus = 0,
        id_usuario_cancelacion = NEW.id_usuario_cancelacion,
        fecha_cancelacion = NEW.fecha_cancelacion
    WHERE id_agenda_cita = NEW.id AND estatus <> 0;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

/* CREATE TRIGGER trg_fn_cancelar_citas
AFTER UPDATE ON tbagenda_citas
FOR EACH ROW
WHEN (NEW.activa = 0)  -- Solo cuando se cancela
EXECUTE FUNCTION fn_cancelar_citas(); */