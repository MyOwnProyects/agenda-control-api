CREATE OR REPLACE FUNCTION fn_programar_citas(p_id_paciente INT, p_id_locacion INT, p_fecha_inicio DATE, p_fecha_termino DATE, p_clave_usuario_agenda VARCHAR)
RETURNS TEXT AS $$
DECLARE
    validar_fecha_inicio    BOOLEAN;
    fecha_actual            DATE;
    fecha_validacion        DATE;
    dia_semana              TEXT;
    dia_semana_num          INT;
    arr_info_cita           RECORD;
    v_id_agenda_cita        INT;
    i                       INT; -- CONTADOR DE DIAS
    arr_id_paciente         INT[];
    count_pacientes         INT;
    count_citas             INT;
    v_dias                  INT;
    v_id_usuario_agenda     INT;
    v_id_motivo_cancelacion INT;
BEGIN

    --  VALIDACION DE FECHA DE INICIO
    SELECT current_date <= p_fecha_inicio::DATE INTO validar_fecha_inicio;
    RAISE NOTICE 'p_fecha_inicio = %, current_date = %', p_fecha_inicio, current_date;
    IF validar_fecha_inicio = FALSE THEN 
        RAISE EXCEPTION 'No se permite programar citas (%) menos al d&iacute;a de hoy: (%)...',p_fecha_inicio::DATE,current_date;
    END IF;

    --  SE CALCULA LA DIFERENCIA DE DIAS
    SELECT p_fecha_termino::DATE - p_fecha_inicio::DATE AS diferencia_dias INTO v_dias;

    IF v_dias < 0 THEN 
        RAISE EXCEPTION 'Fecha de inicio es menor a la fecha final...';
    END IF;

    --  SE BUSCA EL ID DEL USUARIO
    SELECT id INTO v_id_usuario_agenda FROM ctusuarios WHERE clave = p_clave_usuario_agenda;

    --  VERIFICA QUE NO SE GENEREN CITAS PASADAS
    IF p_fecha_inicio::DATE < NOW()::DATE THEN
        RAISE EXCEPTION 'La fecha ingresada (%) es menor al día de hoy (%)...',p_fecha_inicio::DATE,NOW()::DATE;
    END IF;

    -- SI ID_PACIENTE ES NULL ES PARA GENERAR A TODOS LOS PACIENTES 
    --  QUE NO ESTEN DADOS DE BAJA Y QUE TENGAN CITA EN LA LOCACION INDICADA
    IF p_id_paciente IS NULL THEN 

        --  SE CREA REGISTRO DE APERTURA DE AGENDA
        INSERT INTO tbapertura_agenda (id_usuario,id_locacion,fecha_apertura,fecha_limite) 
        VALUES (v_id_usuario_agenda,p_id_locacion,p_fecha_inicio,p_fecha_termino);

        SELECT array_agg(b.id) INTO arr_id_paciente
        FROM tbcitas_programadas a 
        LEFT JOIN ctpacientes b  ON a.id_paciente = b.id 
        WHERE b.estatus = 1 AND a.id_locacion = p_id_locacion;
    ELSE 
        --  SE VERIFICA QUE LAS FECHAS ESTEN EN EL RANGO DE LA APERTURA DE AGENDA
        SELECT fecha_limite INTO fecha_validacion FROM tbapertura_agenda WHERE id_locacion = p_id_locacion ORDER BY fecha_limite DESC LIMIT 1;

        IF fecha_validacion <> null AND p_fecha_termino > fecha_validacion THEN 
            RAISE EXCEPTION 'El rango de fechas ingresado sobrepasa a la fecha de apertura de agenda: (%)...',fecha_validacion;
        END IF;

        arr_id_paciente := array_append(arr_id_paciente, p_id_paciente);
    END IF;

    RAISE NOTICE 'PACIENTES (%)', arr_id_paciente;

    --  SE CANCELAN TODOS LOS REGISTROS ACTIVOS YA EXISTENTES EN LA AGENDA QUE SEAN DE CITAS PROGRAMADAS
    --  DE LOS PACIENTES QUE ESTEN EN EL RANGO DE FECHAS DE FECHA_INICO Y FECHA_INICIO + V_DIAS
    --DELETE FROM tbagenda_citas WHERE id_paciente = ANY (arr_id_paciente) AND fecha_cita::DATE between p_fecha_inicio and p_fecha_inicio::date + (v_dias || ' days')::interval;
    SELECT id INTO v_id_motivo_cancelacion FROM ctmotivos_cancelacion_cita WHERE clave = 'APA';

    UPDATE tbagenda_citas SET 
        activa = 0, 
        id_motivo_cancelacion = v_id_motivo_cancelacion, 
        id_usuario_cancelacion = v_id_usuario_agenda, 
        fecha_cancelacion = NOW(),
        observaciones_cancelacion = 'SE REALIZO UNA NUEVA APERTURA DE AGENDA EN LAS FECHAS DE ESTA CITA'
    WHERE id_paciente = ANY (arr_id_paciente) and activa = 1 AND fecha_cita::DATE between p_fecha_inicio and p_fecha_inicio::date + (v_dias || ' days')::interval;

    count_pacientes := COUNT(arr_id_paciente);
    count_citas     := 0;

    FOR i IN 1..7 LOOP
        FOR arr_info_cita IN
            SELECT 
                c.id_paciente,
                a.dia,
                a.hora_inicio,
                a.hora_termino,
                b.id_cita_programada,
                b.id_servicio,
                b.id_profesional,
                e.costo,
                e.duracion
            FROM tbcitas_programadas_servicios_horarios a 
            LEFT JOIN tbcitas_programadas_servicios b ON a.id_cita_programada_servicio = b.id
            LEFT JOIN tbcitas_programadas c ON b.id_cita_programada = c.id 
            LEFT JOIN ctpacientes d ON c.id_paciente = d.id
            LEFT JOIN ctlocaciones_servicios e ON b.id_servicio = e.id_servicio AND e.id_locacion = p_id_locacion
            WHERE d.estatus = 1 AND a.dia = i AND e.id IS NOT NULL AND c.id_paciente = ANY (arr_id_paciente)
            ORDER BY c.id_paciente
            --  e.id is not null para asegurarnos que el servicio se sigue dando en la locacion
            --  de lo contrario ay no se generara la cita
        LOOP 

            -- Recorremos cada fecha generada por generate_series
            FOR fecha_actual IN
                SELECT generate_series(p_fecha_inicio, p_fecha_inicio + (v_dias || ' days')::INTERVAL, '1 day')::DATE
            LOOP
                -- Obtenemos el día de la semana en inglés
                dia_semana := to_char(fecha_actual, 'Day');

                -- Mapeamos el día de la semana a español
                CASE trim(dia_semana)
                    WHEN 'Monday'       THEN dia_semana_num := 1;
                    WHEN 'Tuesday'      THEN dia_semana_num := 2;
                    WHEN 'Wednesday'    THEN dia_semana_num := 3;
                    WHEN 'Thursday'     THEN dia_semana_num := 4;
                    WHEN 'Friday'       THEN dia_semana_num := 5;
                    WHEN 'Saturday'     THEN dia_semana_num := 6;
                    WHEN 'Sunday'       THEN dia_semana_num := 7;
                END CASE;
                
                --  PARA SABER QUE LA FECHA GENERADA ES DEL DIA DE LA CITA
                IF dia_semana_num <> i THEN 
                    CONTINUE;
                END IF;

                v_id_agenda_cita    := null;

                INSERT INTO tbagenda_citas (id_paciente,id_locacion,fecha_cita,dia,hora_inicio,hora_termino,id_usuario_agenda,id_cita_programada,id_profesional,total)
                VALUES (arr_info_cita.id_paciente,p_id_locacion,fecha_actual,i,arr_info_cita.hora_inicio,arr_info_cita.hora_termino,v_id_usuario_agenda,arr_info_cita.id_cita_programada,arr_info_cita.id_profesional,arr_info_cita.costo) 
                RETURNING id INTO v_id_agenda_cita;

                --  SE CREA REGISTRO DEL SERVICIO
                INSERT INTO tbagenda_citas_servicios (id_agenda_cita,id_servicio,costo,duracion)
                VALUES (v_id_agenda_cita,arr_info_cita.id_servicio,arr_info_cita.costo,arr_info_cita.duracion);

                count_citas := count_citas + 1;

            END LOOP;

        END LOOP;

    END LOOP;
    
    RETURN 'Se generaron '|| count_citas || ' citas para '|| count_pacientes || ' paciente(s)';
END;
$$ LANGUAGE plpgsql;
