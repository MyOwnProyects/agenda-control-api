CREATE OR REPLACE FUNCTION fn_folio_ticket()
RETURNS VARCHAR AS $$
DECLARE
    v_folio 		VARCHAR;
    v_num_registros	INT;
BEGIN
	
	SELECT COUNT(*) + 1 FROM tbtickets_pagos INTO v_num_registros;

    SELECT 'T-' || LPAD(v_num_registros::TEXT, 5, '0')
    INTO v_folio;
    
    RETURN v_folio;
END;
$$ LANGUAGE plpgsql;