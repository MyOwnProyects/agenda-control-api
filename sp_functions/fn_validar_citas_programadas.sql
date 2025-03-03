CREATE OR REPLACE FUNCTION fn_validar_citas_programadas(p_id_profesional INT, p_id_paciente INT, p_dia INT,p_label_dia VARCHAR, p_hora_inicio VARCHAR, p_hora_termino VARCHAR)
RETURNS BOOLEAN AS $$
DECLARE
    -- Declaración de variables locales (si es necesario)
    v_flag_insert       BOOLEAN;
    v_citas             RECORD;
    v_hora_inicio_time  TIME;
    v_hora_termino_time TIME;
BEGIN

    v_flag_insert   := TRUE;
    --  INICIACION DE VARIABLES PARA CONVERTIRLAS EN TIME '09:30:00'
    v_hora_inicio_time  := p_hora_inicio::TIME;
    v_hora_termino_time := p_hora_termino::TIME;
    
    --  SE BUSCAN TODAS LAS CITAS DEL PROFESIONAL, INDEPENDIENTEMENTE DE LA INSTITUCION
    FOR v_citas IN 
        SELECT 
            c.*,
            (e.primer_apellido|| ' ' || COALESCE(e.segundo_apellido,'') || ' '|| e.nombre) as nombre_paciente
        FROM tbcitas_programadas a 
        LEFT JOIN tbcitas_programadas_servicios b ON a.id = b.id_cita_programada
        LEFT JOIN tbcitas_programadas_servicios_horarios c ON b.id = c.id_cita_programada_servicio
        LEFT JOIN ctpacientes e ON a.id_paciente = e.id
        WHERE (a.id_paciente = p_id_paciente OR b.id_profesional = p_id_profesional) AND c.dia = p_dia
    LOOP 
        --  SE VERIFICA QUE LA HORA INICIO NO SE EMPALME
        IF v_hora_inicio_time >= v_citas.hora_inicio::TIME AND v_hora_inicio_time < v_citas.hora_termino::TIME THEN
            RAISE EXCEPTION 'El paciente (%) cuenta con un horario asignado que genera conflictos:  Día (%) de (%) a (%)...',v_citas.nombre_paciente,p_label_dia,p_hora_inicio,p_hora_termino;
        END IF; 

        --  SE VERIFICA QUE LA HORA DE TERMINO NO SE EMPALME
        IF v_hora_termino_time > v_citas.hora_inicio::TIME AND v_hora_termino_time <= v_citas.hora_termino::TIME THEN
            RAISE EXCEPTION 'El paciente (%) cuenta con un horario asignado que genera conflictos:  Día (%) de (%) a (%)...',v_citas.nombre_paciente,p_label_dia,p_hora_inicio,p_hora_termino;
        END IF;

    END LOOP;

    RETURN v_flag_insert;
END;
$$ LANGUAGE plpgsql;