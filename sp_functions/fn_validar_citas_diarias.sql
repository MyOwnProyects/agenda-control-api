CREATE OR REPLACE FUNCTION fn_validar_citas_diarias(p_id_profesional INT, p_id_paciente INT, p_fecha_cita DATE, p_hora_inicio VARCHAR, p_hora_termino VARCHAR)
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
            a.*,
            (e.primer_apellido|| ' ' || COALESCE(e.segundo_apellido,'') || ' '|| e.nombre) as nombre_paciente
        FROM tbagenda_citas a 
        LEFT JOIN ctpacientes e ON a.id_paciente = e.id
        WHERE (a.id_paciente = p_id_paciente OR a.id_profesional = p_id_profesional) 
                AND a.fecha_cita = p_fecha_cita
                AND a.activa = 1
    LOOP 
        --  SE VERIFICA QUE LA HORA INICIO NO SE EMPALME
        IF v_hora_inicio_time >= v_citas.hora_inicio::TIME AND v_hora_inicio_time < v_citas.hora_termino::TIME THEN
            RAISE EXCEPTION 'El paciente (%) cuenta con un horario asignado que genera conflictos:  Día (%) de (%) a (%)...',v_citas.nombre_paciente,p_fecha_cita,p_hora_inicio,p_hora_termino;
        END IF; 

        --  SE VERIFICA QUE LA HORA DE TERMINO NO SE EMPALME
        IF v_hora_termino_time > v_citas.hora_inicio::TIME AND v_hora_termino_time <= v_citas.hora_termino::TIME THEN
            RAISE EXCEPTION 'El paciente (%) cuenta con un horario asignado que genera conflictos:  D&iacute;a (%) de (%) a (%)...',v_citas.nombre_paciente,p_fecha_cita,p_hora_inicio,p_hora_termino;
        END IF;

        --  SE VERIFICA QUE LA HORA DE INICIO Y TERMINO ENGLOBEN A LA HORA INDICADA
        IF v_hora_inicio_time < v_citas.hora_inicio::TIME AND v_hora_termino_time > v_citas.hora_termino::TIME THEN
            RAISE EXCEPTION 'El paciente (%) cuenta con un horario asignado que genera conflictos:  D&iacute;a (%) de (%) a (%)...',v_citas.nombre_paciente,p_fecha_cita,p_hora_inicio,p_hora_termino;
        END IF;

    END LOOP;

    RETURN v_flag_insert;
END;
$$ LANGUAGE plpgsql;