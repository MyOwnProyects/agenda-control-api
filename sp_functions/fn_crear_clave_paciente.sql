CREATE OR REPLACE FUNCTION fn_crear_clave_paciente() RETURNS TEXT AS $$
DECLARE
    clave_generada  TEXT;
    num_pacientes   TEXT;
    inicio_clave    TEXT;
BEGIN

    --  FECHA DE CAPTURA
    SELECT to_char(NOW(), 'YYMM')::TEXT INTO inicio_clave;

    SELECT 
        RIGHT(LPAD(CAST((GREATEST(COUNT(*), 1) + 1) AS TEXT), 4, '0'), 4) INTO num_pacientes
    FROM ctpacientes
    WHERE clave ILIKE inicio_clave || '%';

    -- Aquí puedes generar la clave como desees
    clave_generada  := inicio_clave || num_pacientes;

    RETURN clave_generada;
END;
$$ LANGUAGE plpgsql;