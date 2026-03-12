-- Agregar modalidad de trabajo al asesor
-- presencial: solo puede trabajar en horario presencial de la campaña
-- teletrabajo: puede trabajar en cualquier horario (tiene acceso remoto)
-- mixto: puede hacer presencial y extender con teletrabajo antes/después

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'modalidad_trabajo') THEN
        CREATE TYPE modalidad_trabajo AS ENUM ('presencial', 'teletrabajo', 'mixto');
    END IF;
END
$$;

ALTER TABLE advisor_constraints
    ADD COLUMN IF NOT EXISTS modalidad_trabajo modalidad_trabajo NOT NULL DEFAULT 'mixto';

-- Actualizar asesores existentes según su configuración actual:
-- Sin VPN = presencial, Con VPN + velada = teletrabajo, Con VPN sin velada = mixto
UPDATE advisor_constraints
SET modalidad_trabajo = CASE
    WHEN tiene_vpn = false THEN 'presencial'::modalidad_trabajo
    WHEN tiene_vpn = true AND disponible_velada = true THEN 'teletrabajo'::modalidad_trabajo
    ELSE 'mixto'::modalidad_trabajo
END;
