CREATE OR REPLACE FUNCTION fn_folio_ticket(p_tipo_ticket VARCHAR)
RETURNS VARCHAR AS $$
DECLARE
    v_folio 		VARCHAR;    --  FOLIO GENERADO
    v_num_registros	INT;        --  COUNT DE NUMERO DE FOLIO CAPTURADOS
    v_anio_actual   INT;        --  ANIO ACTUAL
    v_letra         VARCHAR;    --  LETRA CON LA QUE INICIARA EL TICKET
BEGIN

    --  PARAMETROS QUE PUEDE RECIBIR LA FUNCION
    --  'beca'
	--  'pago'

    --  SE OBTIENE EL AÑO ACTUAL, ESTO PARA GENERAR FOLIO POR AÑO
    SELECT EXTRACT(YEAR FROM CURRENT_DATE) INTO v_anio_actual;

    --  NUMERO DE TICKETS CREADOS EN EL AÑO EN CURSO
    v_letra := '';
    IF p_tipo_ticket = 'pago' THEN
        SELECT COUNT(*) + 1 FROM tbtickets_pagos 
        WHERE EXTRACT(YEAR FROM fecha_captura) = v_anio_actual AND
        folio ILIKE 'T-%'
        INTO v_num_registros;

        v_letra := 'T';
    END IF;

    IF p_tipo_ticket = 'beca' THEN
        SELECT COUNT(*) + 1 FROM tbtickets_pagos 
        WHERE EXTRACT(YEAR FROM fecha_captura) = v_anio_actual AND
        folio ILIKE 'B-%'
        INTO v_num_registros;

        v_letra := 'B';
    END IF;
	

    SELECT v_letra||'-'|| v_anio_actual || LPAD(v_num_registros::TEXT, 4, '0')
    INTO v_folio;
    
    RETURN v_folio;
END;
$$ LANGUAGE plpgsql;