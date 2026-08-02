CREATE OR REPLACE FUNCTION fn_folio_ticket()
RETURNS VARCHAR AS $$
DECLARE
    v_folio 		VARCHAR;
    v_num_registros	INT;
    v_anio_actual   INT;
BEGIN
	
    --  SE OBTIENE EL AÑO ACTUAL, ESTO PARA GENERAR FOLIO POR AÑO
    SELECT EXTRACT(YEAR FROM CURRENT_DATE) INTO v_anio_actual;

    --  NUMERO DE TICKETS CREADOS EN EL AÑO EN CURSO
	SELECT COUNT(*) + 1 FROM tbtickets_pagos WHERE EXTRACT(YEAR FROM fecha_captura) = v_anio_actual INTO v_num_registros;

    SELECT 'T-'|| v_anio_actual || LPAD(v_num_registros::TEXT, 4, '0')
    INTO v_folio;
    
    RETURN v_folio;
END;
$$ LANGUAGE plpgsql;