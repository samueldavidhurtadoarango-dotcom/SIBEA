-- =====================================================================
-- BenefitRack v2 - Script de base de datos
-- Proyecto formativo SENA (ADSO) - Gestión de beneficios para aprendices
-- =====================================================================
-- Compatible con MariaDB 10.4 (XAMPP) y MariaDB 11.x.
-- Fuente de verdad: specs/001-correccion-entregables.md (decisiones D1-D12).
--
-- Variables de sesión que usa la base de datos (las fija la aplicación
-- al abrir cada conexión):
--   SET @app_id_usuario = <id del usuario que inició sesión>;   -- D9
--   SET @app_ip         = '<ip del cliente>';                    -- opcional
-- Si no están definidas, los triggers guardan NULL.
-- =====================================================================

-- !!! ATENCIÓN !!!
-- La siguiente línea BORRA por completo la base de datos benefitrack_v2
-- (tablas y datos) y la vuelve a crear vacía. No ejecute este script
-- sobre una base que tenga datos reales que quiera conservar.
DROP DATABASE IF EXISTS benefitrack_v2;

CREATE DATABASE benefitrack_v2
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE benefitrack_v2;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Modo estricto solo para esta ejecución: los triggers guardan este modo.
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- SECCIÓN 1. CATÁLOGOS
-- =====================================================================

-- Instituciones que ofrecen beneficios (D10). Hoy solo el SENA - CTGI;
-- deja la base preparada para más instituciones sin construir su gestión.
CREATE TABLE instituciones (
  id_institucion  INT          NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(150) NOT NULL,
  nit             VARCHAR(20)  NULL,
  ciudad          VARCHAR(100) NULL,
  estado          ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_institucion),
  UNIQUE KEY uk_institucion_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles del sistema (D7): Administrador y Aprendiz.
CREATE TABLE roles (
  id_rol       INT          NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(50)  NOT NULL,
  descripcion  VARCHAR(255) NULL,
  PRIMARY KEY (id_rol),
  UNIQUE KEY uk_rol_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de cuenta bancaria (Ahorros, Corriente).
CREATE TABLE tipos_cuenta (
  id_tipo_cuenta  INT         NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_tipo_cuenta),
  UNIQUE KEY uk_tipo_cuenta_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de documento que el aprendiz adjunta a su postulación.
CREATE TABLE tipos_documento (
  id_tipo_documento  INT          NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(100) NOT NULL,
  descripcion        VARCHAR(255) NULL,
  estado             ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (id_tipo_documento),
  UNIQUE KEY uk_tipo_documento_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bancos y billeteras donde el aprendiz recibe los pagos.
CREATE TABLE entidades_bancarias (
  id_banco  INT          NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(100) NOT NULL,
  estado    ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (id_banco),
  UNIQUE KEY uk_banco_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EPS (entidad de salud) a la que está afiliado el aprendiz.
CREATE TABLE eps (
  id_eps  INT          NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(150) NOT NULL,
  estado  ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (id_eps),
  UNIQUE KEY uk_eps_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programas de formación, cada uno de una institución (D10).
CREATE TABLE programas (
  id_programa     INT          NOT NULL AUTO_INCREMENT,
  id_institucion  INT          NOT NULL,
  nombre          VARCHAR(150) NOT NULL,
  estado          ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (id_programa),
  UNIQUE KEY uk_programa_institucion_nombre (id_institucion, nombre),
  CONSTRAINT fk_programa_institucion FOREIGN KEY (id_institucion)
    REFERENCES instituciones (id_institucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECCIÓN 2. USUARIOS Y ACCESO
-- =====================================================================

-- Usuarios del sistema: administradores y aprendices (D7).
-- La edad ya no se guarda: se calcula desde fecha_nacimiento (D11).
CREATE TABLE usuarios (
  id_usuario         INT          NOT NULL AUTO_INCREMENT,
  documento          VARCHAR(30)  NOT NULL,
  nombre             VARCHAR(100) NOT NULL,
  apellido           VARCHAR(100) NOT NULL,
  fecha_nacimiento   DATE         NULL,
  telefono           VARCHAR(30)  NULL,
  direccion          VARCHAR(200) NULL,
  correo             VARCHAR(150) NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  id_rol             INT          NOT NULL,
  id_programa        INT          NULL,
  id_eps             INT          NULL,
  ficha              VARCHAR(30)  NULL,
  estado             ENUM('Activo','Inactivo','Bloqueado') NOT NULL DEFAULT 'Activo',
  intentos_fallidos  INT          NOT NULL DEFAULT 0,
  fecha_bloqueo      DATETIME     NULL,
  fecha_registro     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uk_usuario_documento (documento),
  UNIQUE KEY uk_usuario_correo (correo),
  KEY idx_usuario_nombre (nombre, apellido),
  CONSTRAINT fk_usuario_rol      FOREIGN KEY (id_rol)      REFERENCES roles (id_rol),
  CONSTRAINT fk_usuario_programa FOREIGN KEY (id_programa) REFERENCES programas (id_programa),
  CONSTRAINT fk_usuario_eps      FOREIGN KEY (id_eps)      REFERENCES eps (id_eps),
  CONSTRAINT chk_usuario_intentos CHECK (intentos_fallidos >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones abiertas de los usuarios.
CREATE TABLE sesiones (
  id_sesion      BIGINT       NOT NULL AUTO_INCREMENT,
  id_usuario     INT          NOT NULL,
  token_sesion   VARCHAR(255) NOT NULL,
  fecha_inicio   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_cierre   DATETIME     NULL,
  ip             VARCHAR(45)  NULL,
  activa         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id_sesion),
  UNIQUE KEY uk_sesion_token (token_sesion),
  KEY idx_sesion_usuario (id_usuario),
  CONSTRAINT fk_sesion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de recuperación de contraseña (D11, RF-2.2).
-- Se guarda solo el hash del token (nunca el token en claro).
CREATE TABLE recuperacion_contrasena (
  id_recuperacion_contrasena  BIGINT      NOT NULL AUTO_INCREMENT,
  id_usuario                  INT         NOT NULL,
  token_hash                  CHAR(64)    NOT NULL COMMENT 'SHA-256 en hexadecimal del token enviado por correo',
  fecha_creacion              DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_expiracion            DATETIME    NOT NULL,
  usado                       TINYINT(1)  NOT NULL DEFAULT 0,
  fecha_uso                   DATETIME    NULL,
  ip_solicitud                VARCHAR(45) NULL,
  PRIMARY KEY (id_recuperacion_contrasena),
  UNIQUE KEY uk_recuperacion_token (token_hash),
  KEY idx_recuperacion_usuario (id_usuario),
  CONSTRAINT fk_recuperacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_recuperacion_expiracion CHECK (fecha_expiracion > fecha_creacion),
  CONSTRAINT chk_recuperacion_usado CHECK (usado IN (0, 1) AND (usado = 0 OR fecha_uso IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos bancarios del aprendiz (uno por usuario).
CREATE TABLE informacion_bancaria (
  id_informacion_bancaria  INT          NOT NULL AUTO_INCREMENT,
  id_usuario               INT          NOT NULL,
  id_banco                 INT          NOT NULL,
  id_tipo_cuenta           INT          NOT NULL,
  numero_cuenta            VARCHAR(100) NOT NULL,
  titular                  VARCHAR(200) NOT NULL,
  fecha_registro           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_informacion_bancaria),
  UNIQUE KEY uk_bancaria_usuario (id_usuario),
  KEY idx_bancaria_banco (id_banco),
  KEY idx_bancaria_tipo_cuenta (id_tipo_cuenta),
  CONSTRAINT fk_bancaria_usuario     FOREIGN KEY (id_usuario)     REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_bancaria_banco       FOREIGN KEY (id_banco)       REFERENCES entidades_bancarias (id_banco),
  CONSTRAINT fk_bancaria_tipo_cuenta FOREIGN KEY (id_tipo_cuenta) REFERENCES tipos_cuenta (id_tipo_cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECCIÓN 3. BENEFICIOS Y CONVOCATORIAS
-- =====================================================================

-- Beneficios que ofrece una institución (alimentación, plan de datos, cívica...).
CREATE TABLE beneficios (
  id_beneficio         INT          NOT NULL AUTO_INCREMENT,
  id_institucion       INT          NOT NULL,
  nombre               VARCHAR(150) NOT NULL,
  descripcion          TEXT         NOT NULL,
  categoria            VARCHAR(100) NOT NULL,
  estado               ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  fecha_creacion       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_beneficio),
  UNIQUE KEY uk_beneficio_institucion_nombre (id_institucion, nombre),
  CONSTRAINT fk_beneficio_institucion FOREIGN KEY (id_institucion)
    REFERENCES instituciones (id_institucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convocatorias de un beneficio. Definen los cupos (D2) y los valores por
-- defecto que se copian a cada asignación (D3): valor por pago, número de
-- pagos y duración en meses.
CREATE TABLE convocatorias (
  id_convocatoria    INT           NOT NULL AUTO_INCREMENT,
  id_beneficio       INT           NOT NULL,
  nombre             VARCHAR(150)  NOT NULL,
  fecha_inicio       DATE          NOT NULL,
  fecha_cierre       DATE          NOT NULL,
  cupos              INT           NOT NULL,
  requisitos         TEXT          NOT NULL,
  valor_pago         DECIMAL(12,2) NOT NULL COMMENT 'Valor por defecto de cada pago',
  numero_pagos       INT           NOT NULL DEFAULT 1 COMMENT 'Número de pagos por defecto',
  duracion_meses     INT           NOT NULL DEFAULT 1 COMMENT 'Duración por defecto del beneficio',
  estado             ENUM('Activa','Inactiva','Cerrada','Cierre anticipado') NOT NULL DEFAULT 'Inactiva',
  motivo_cierre      TEXT          NULL,
  fecha_cierre_real  DATETIME      NULL,
  usuario_cierre     INT           NULL,
  fecha_creacion     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_convocatoria),
  KEY idx_convocatoria_beneficio (id_beneficio),
  KEY idx_convocatoria_usuario_cierre (usuario_cierre),
  KEY idx_convocatoria_estado (estado),
  CONSTRAINT fk_convocatoria_beneficio FOREIGN KEY (id_beneficio)   REFERENCES beneficios (id_beneficio),
  CONSTRAINT fk_convocatoria_usuario   FOREIGN KEY (usuario_cierre) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_convocatoria_fechas   CHECK (fecha_cierre > fecha_inicio),
  CONSTRAINT chk_convocatoria_cupos    CHECK (cupos > 0),
  CONSTRAINT chk_convocatoria_valor    CHECK (valor_pago > 0),
  CONSTRAINT chk_convocatoria_pagos    CHECK (numero_pagos > 0),
  CONSTRAINT chk_convocatoria_duracion CHECK (duracion_meses > 0),
  CONSTRAINT chk_convocatoria_cierre_anticipado
    CHECK (estado <> 'Cierre anticipado' OR (motivo_cierre IS NOT NULL AND TRIM(motivo_cierre) <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECCIÓN 4. POSTULACIONES
-- =====================================================================

-- Postulación de un aprendiz a una convocatoria.
-- Estados (D1): Pendiente -> Validada -> Aprobada, y Rechazada desde
-- Pendiente o Validada. El estado inicial es Pendiente.
CREATE TABLE postulaciones (
  id_postulacion         INT       NOT NULL AUTO_INCREMENT,
  id_usuario             INT       NOT NULL COMMENT 'Aprendiz dueño de la postulación',
  id_convocatoria        INT       NOT NULL,
  datos_formulario       LONGTEXT  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  estado                 ENUM('Pendiente','Validada','Aprobada','Rechazada') NOT NULL DEFAULT 'Pendiente',
  justificacion_rechazo  TEXT      NULL COMMENT 'Obligatoria cuando el estado es Rechazada',
  observaciones          TEXT      NULL,
  fecha_creacion         DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de envío',
  fecha_validacion       DATETIME  NULL,
  id_usuario_validacion  INT       NULL,
  fecha_aprobacion       DATETIME  NULL,
  id_usuario_aprobacion  INT       NULL,
  fecha_actualizacion    DATETIME  NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_postulacion),
  UNIQUE KEY uk_postulacion_usuario_convocatoria (id_usuario, id_convocatoria),
  KEY idx_postulacion_convocatoria_estado (id_convocatoria, estado),
  KEY idx_postulacion_fecha (fecha_creacion),
  KEY idx_postulacion_validador (id_usuario_validacion),
  KEY idx_postulacion_aprobador (id_usuario_aprobacion),
  CONSTRAINT fk_postulacion_usuario     FOREIGN KEY (id_usuario)            REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_postulacion_convocatoria FOREIGN KEY (id_convocatoria)      REFERENCES convocatorias (id_convocatoria),
  CONSTRAINT fk_postulacion_validador   FOREIGN KEY (id_usuario_validacion) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_postulacion_aprobador   FOREIGN KEY (id_usuario_aprobacion) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_postulacion_formulario CHECK (datos_formulario IS NULL OR JSON_VALID(datos_formulario)),
  CONSTRAINT chk_postulacion_justificacion
    CHECK (estado <> 'Rechazada' OR (justificacion_rechazo IS NOT NULL AND TRIM(justificacion_rechazo) <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos cargados por el aprendiz. La validación es manual (D6): el
-- administrador compara los datos digitados con el documento. Sin OCR.
CREATE TABLE documentos_postulacion (
  id_documento           INT           NOT NULL AUTO_INCREMENT,
  id_postulacion         INT           NOT NULL,
  id_tipo_documento      INT           NOT NULL,
  nombre_archivo         VARCHAR(255)  NOT NULL,
  ruta_archivo           VARCHAR(500)  NOT NULL,
  extension              VARCHAR(10)   NOT NULL,
  tamano_mb              DECIMAL(6,2)  NOT NULL,
  estado_validacion      ENUM('Pendiente','Validado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  observaciones          TEXT          NULL,
  fecha_carga            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_validacion       DATETIME      NULL,
  id_usuario_validacion  INT           NULL,
  PRIMARY KEY (id_documento),
  KEY idx_documento_postulacion (id_postulacion),
  KEY idx_documento_tipo (id_tipo_documento),
  KEY idx_documento_validador (id_usuario_validacion),
  CONSTRAINT fk_documento_postulacion FOREIGN KEY (id_postulacion)        REFERENCES postulaciones (id_postulacion),
  CONSTRAINT fk_documento_tipo        FOREIGN KEY (id_tipo_documento)     REFERENCES tipos_documento (id_tipo_documento),
  CONSTRAINT fk_documento_validador   FOREIGN KEY (id_usuario_validacion) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_documento_tamano CHECK (tamano_mb > 0 AND tamano_mb <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos adicionales que el administrador pide al aprendiz.
-- Mientras tanto la postulación sigue en Pendiente (D1).
CREATE TABLE solicitudes_documentacion (
  id_solicitud      INT       NOT NULL AUTO_INCREMENT,
  id_postulacion    INT       NOT NULL,
  id_usuario        INT       NOT NULL COMMENT 'Administrador que hace la solicitud',
  observacion       TEXT      NOT NULL,
  estado            ENUM('Pendiente','Atendida','Cerrada') NOT NULL DEFAULT 'Pendiente',
  fecha_solicitud   DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_atencion    DATETIME  NULL,
  PRIMARY KEY (id_solicitud),
  KEY idx_solicitud_postulacion (id_postulacion),
  KEY idx_solicitud_usuario (id_usuario),
  CONSTRAINT fk_solicitud_postulacion FOREIGN KEY (id_postulacion) REFERENCES postulaciones (id_postulacion),
  CONSTRAINT fk_solicitud_usuario     FOREIGN KEY (id_usuario)     REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de cambios de estado de cada postulación (D9).
-- id_usuario es quien hizo el cambio (@app_id_usuario), no el aprendiz.
CREATE TABLE historial_estados (
  id_historial     INT       NOT NULL AUTO_INCREMENT,
  id_postulacion   INT       NOT NULL,
  estado_anterior  ENUM('Pendiente','Validada','Aprobada','Rechazada') NULL,
  estado_nuevo     ENUM('Pendiente','Validada','Aprobada','Rechazada') NOT NULL,
  justificacion    TEXT      NULL,
  id_usuario       INT       NULL COMMENT 'Usuario de la aplicación que hizo el cambio',
  fecha            DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_historial_postulacion (id_postulacion, fecha),
  KEY idx_historial_usuario (id_usuario),
  CONSTRAINT fk_historial_postulacion FOREIGN KEY (id_postulacion) REFERENCES postulaciones (id_postulacion),
  CONSTRAINT fk_historial_usuario     FOREIGN KEY (id_usuario)     REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECCIÓN 5. BENEFICIARIOS, PAGOS Y NOVEDADES
-- =====================================================================

-- Asignación del beneficio (D3): se crea sola al aprobar una postulación y
-- copia los valores por defecto de la convocatoria.
-- Estados: En curso -> Finalizado (automático con el último pago vigente);
-- Suspendido y Cancelado son manuales y exigen motivo.
CREATE TABLE asignaciones_beneficio (
  id_asignacion        INT           NOT NULL AUTO_INCREMENT,
  id_postulacion       INT           NOT NULL,
  valor_pago           DECIMAL(12,2) NOT NULL,
  numero_pagos         INT           NOT NULL,
  duracion_meses       INT           NOT NULL,
  fecha_inicio         DATE          NOT NULL,
  fecha_fin            DATE          NULL COMMENT 'Se llena al finalizar o cancelar',
  estado               ENUM('En curso','Finalizado','Suspendido','Cancelado') NOT NULL DEFAULT 'En curso',
  motivo_estado        TEXT          NULL COMMENT 'Motivo del último cambio manual de estado',
  id_usuario_registro  INT           NULL COMMENT 'Usuario que aprobó y generó la asignación',
  fecha_creacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_asignacion),
  UNIQUE KEY uk_asignacion_postulacion (id_postulacion),
  KEY idx_asignacion_estado (estado),
  KEY idx_asignacion_usuario (id_usuario_registro),
  CONSTRAINT fk_asignacion_postulacion FOREIGN KEY (id_postulacion)      REFERENCES postulaciones (id_postulacion),
  CONSTRAINT fk_asignacion_usuario     FOREIGN KEY (id_usuario_registro) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_asignacion_valor    CHECK (valor_pago > 0),
  CONSTRAINT chk_asignacion_pagos    CHECK (numero_pagos > 0),
  CONSTRAINT chk_asignacion_duracion CHECK (duracion_meses > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagos registrados a mano por el administrador (D4). El sistema no hace
-- transacciones. Los pagos no se editan ni se borran: un error se corrige
-- anulando el pago (con motivo) y registrando otro con el mismo número.
-- La columna generada "vigente" vale 1 solo si el pago está Registrado
-- (NULL si está Anulado); como en un índice UNIQUE los NULL no chocan,
-- uk_pago_vigente permite muchos anulados pero un solo Registrado por número.
CREATE TABLE pagos (
  id_pago               INT           NOT NULL AUTO_INCREMENT,
  id_asignacion         INT           NOT NULL,
  numero_pago           INT           NOT NULL,
  monto                 DECIMAL(12,2) NOT NULL,
  fecha_programada      DATE          NULL,
  fecha_pago            DATE          NOT NULL,
  hubo_retraso          TINYINT(1)    NOT NULL DEFAULT 0,
  motivo_retraso        TEXT          NULL COMMENT 'Obligatorio si hubo_retraso = 1',
  observacion           TEXT          NULL,
  estado                ENUM('Registrado','Anulado') NOT NULL DEFAULT 'Registrado',
  motivo_anulacion      TEXT          NULL,
  fecha_anulacion       DATETIME      NULL,
  id_usuario_anulacion  INT           NULL,
  id_usuario_registro   INT           NULL COMMENT 'Administrador que registró el pago',
  fecha_registro        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vigente               TINYINT(1)    AS (IF(estado = 'Registrado', 1, NULL)) PERSISTENT,
  PRIMARY KEY (id_pago),
  UNIQUE KEY uk_pago_vigente (id_asignacion, numero_pago, vigente),
  KEY idx_pago_fecha (fecha_pago),
  KEY idx_pago_usuario_registro (id_usuario_registro),
  KEY idx_pago_usuario_anulacion (id_usuario_anulacion),
  CONSTRAINT fk_pago_asignacion        FOREIGN KEY (id_asignacion)        REFERENCES asignaciones_beneficio (id_asignacion),
  CONSTRAINT fk_pago_usuario_registro  FOREIGN KEY (id_usuario_registro)  REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_pago_usuario_anulacion FOREIGN KEY (id_usuario_anulacion) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_pago_numero   CHECK (numero_pago > 0),
  CONSTRAINT chk_pago_monto    CHECK (monto > 0),
  CONSTRAINT chk_pago_retraso  CHECK (hubo_retraso IN (0, 1)
                                      AND (hubo_retraso = 0 OR (motivo_retraso IS NOT NULL AND TRIM(motivo_retraso) <> ''))),
  CONSTRAINT chk_pago_anulacion CHECK (estado = 'Registrado'
                                       OR (motivo_anulacion IS NOT NULL AND TRIM(motivo_anulacion) <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Novedades de una asignación, visibles para el aprendiz en su portal (D5).
CREATE TABLE novedades (
  id_novedad     INT       NOT NULL AUTO_INCREMENT,
  id_asignacion  INT       NOT NULL,
  tipo           ENUM('Retraso de pago','Suspensión','Reactivación','Cancelación','Otra') NOT NULL,
  descripcion    TEXT      NOT NULL,
  fecha          DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_usuario     INT       NULL COMMENT 'Usuario que registró la novedad',
  PRIMARY KEY (id_novedad),
  KEY idx_novedad_asignacion (id_asignacion, fecha),
  KEY idx_novedad_usuario (id_usuario),
  CONSTRAINT fk_novedad_asignacion FOREIGN KEY (id_asignacion) REFERENCES asignaciones_beneficio (id_asignacion),
  CONSTRAINT fk_novedad_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_novedad_descripcion CHECK (TRIM(descripcion) <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECCIÓN 6. NOTIFICACIONES, AUDITORÍA Y MIGRACIONES
-- =====================================================================

-- Notificaciones al aprendiz (D8). Cada fila sirve para el aviso dentro del
-- sistema (leida) y para el correo (estado_correo, que actualiza la
-- aplicación al enviarlo).
CREATE TABLE notificaciones (
  id_notificacion  BIGINT       NOT NULL AUTO_INCREMENT,
  id_usuario       INT          NOT NULL COMMENT 'Aprendiz que recibe la notificación',
  destinatario     VARCHAR(150) NOT NULL COMMENT 'Correo de destino',
  evento           ENUM('Validada','Aprobada','Rechazada','Pago','Novedad') NOT NULL,
  asunto           VARCHAR(255) NOT NULL,
  cuerpo           TEXT         NOT NULL,
  id_postulacion   INT          NULL,
  leida            TINYINT(1)   NOT NULL DEFAULT 0,
  fecha_lectura    DATETIME     NULL,
  estado_correo    ENUM('Pendiente','Enviada','Error') NOT NULL DEFAULT 'Pendiente',
  fecha_creacion   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_envio      DATETIME     NULL,
  PRIMARY KEY (id_notificacion),
  KEY idx_notificacion_usuario (id_usuario, leida),
  KEY idx_notificacion_correo (estado_correo),
  KEY idx_notificacion_postulacion (id_postulacion),
  CONSTRAINT fk_notificacion_usuario     FOREIGN KEY (id_usuario)     REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_notificacion_postulacion FOREIGN KEY (id_postulacion) REFERENCES postulaciones (id_postulacion),
  CONSTRAINT chk_notificacion_leida CHECK (leida IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoría de acciones (D9). id_usuario es quien hizo la acción
-- (@app_id_usuario); detalle guarda un JSON con los datos del cambio.
CREATE TABLE auditoria (
  id_auditoria  BIGINT        NOT NULL AUTO_INCREMENT,
  id_usuario    INT           NULL COMMENT 'Usuario de la aplicación que hizo la acción',
  fecha         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accion        VARCHAR(100)  NOT NULL,
  entidad       VARCHAR(100)  NOT NULL COMMENT 'Tabla afectada',
  id_entidad    INT           NULL COMMENT 'Id del registro afectado',
  detalle       LONGTEXT      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  ip            VARCHAR(45)   NULL,
  PRIMARY KEY (id_auditoria),
  KEY idx_auditoria_usuario (id_usuario),
  KEY idx_auditoria_fecha (fecha),
  KEY idx_auditoria_entidad (entidad, id_entidad),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_auditoria_detalle CHECK (detalle IS NULL OR JSON_VALID(detalle))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migraciones de datos desde Excel (D11, RF-4.3): un registro por archivo
-- cargado, con el conteo de filas exitosas y fallidas y el detalle de errores.
CREATE TABLE migraciones (
  id_migracion        INT          NOT NULL AUTO_INCREMENT,
  id_usuario          INT          NULL COMMENT 'Administrador que hizo la carga',
  nombre_archivo      VARCHAR(255) NOT NULL,
  entidad_destino     VARCHAR(100) NOT NULL COMMENT 'Tabla a la que se cargaron los datos',
  total_registros     INT          NOT NULL DEFAULT 0,
  registros_exitosos  INT          NOT NULL DEFAULT 0,
  registros_fallidos  INT          NOT NULL DEFAULT 0,
  estado              ENUM('En proceso','Completada','Completada con errores','Fallida') NOT NULL DEFAULT 'En proceso',
  detalle_errores     LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  fecha_inicio        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_fin           DATETIME     NULL,
  PRIMARY KEY (id_migracion),
  KEY idx_migracion_usuario (id_usuario),
  KEY idx_migracion_fecha (fecha_inicio),
  CONSTRAINT fk_migracion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_migracion_conteos CHECK (total_registros >= 0 AND registros_exitosos >= 0
                                          AND registros_fallidos >= 0
                                          AND registros_exitosos + registros_fallidos <= total_registros),
  CONSTRAINT chk_migracion_detalle CHECK (detalle_errores IS NULL OR JSON_VALID(detalle_errores))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECCIÓN 7. TRIGGERS (reglas de negocio)
-- =====================================================================
-- Todos los errores de negocio usan SQLSTATE '45000' con un mensaje en
-- español que la aplicación puede mostrar tal cual al usuario.

DELIMITER //

-- ---------------------------------------------------------------------
-- Documentos de la postulación
-- ---------------------------------------------------------------------

-- Al cambiar el estado de validación de un documento guarda cuándo y
-- quién lo validó (D6, D9). Esos dos datos solo los escribe este trigger:
-- no se pueden modificar a mano (I3).
CREATE TRIGGER tr_validacion_documento
BEFORE UPDATE ON documentos_postulacion
FOR EACH ROW
BEGIN
  IF NOT (OLD.fecha_validacion <=> NEW.fecha_validacion)
     OR NOT (OLD.id_usuario_validacion <=> NEW.id_usuario_validacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La fecha y el usuario de validación los registra la base de datos; no se pueden modificar';
  END IF;

  IF NOT (OLD.estado_validacion <=> NEW.estado_validacion) THEN
    SET NEW.fecha_validacion = NOW();
    SET NEW.id_usuario_validacion = @app_id_usuario;
  END IF;
END//

-- ---------------------------------------------------------------------
-- Convocatorias
-- ---------------------------------------------------------------------

-- Los cupos no pueden quedar por debajo de las postulaciones ya aprobadas.
CREATE TRIGGER tr_convocatoria_validar
BEFORE UPDATE ON convocatorias
FOR EACH ROW
BEGIN
  DECLARE v_aprobadas INT;
  DECLARE v_mensaje   VARCHAR(128);

  IF NEW.cupos < OLD.cupos THEN
    SELECT COUNT(*) INTO v_aprobadas
      FROM postulaciones
     WHERE id_convocatoria = NEW.id_convocatoria
       AND estado = 'Aprobada'
      LOCK IN SHARE MODE;

    IF NEW.cupos < v_aprobadas THEN
      SET v_mensaje = CONCAT('Los cupos no pueden ser menores que las postulaciones ya aprobadas (',
                             v_aprobadas, ')');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
    END IF;
  END IF;
END//

-- ---------------------------------------------------------------------
-- Postulaciones
-- ---------------------------------------------------------------------

-- Toda postulación nueva empieza en Pendiente (D1), la crea un usuario con
-- rol Aprendiz y solo en una convocatoria Activa y dentro de sus fechas.
-- Los datos de validación y aprobación no se pueden traer ya llenos (I3).
CREATE TRIGGER tr_postulacion_inicial
BEFORE INSERT ON postulaciones
FOR EACH ROW
BEGIN
  DECLARE v_rol       VARCHAR(50);
  DECLARE v_estado    VARCHAR(20);
  DECLARE v_abierta   INT;

  IF NOT (NEW.estado <=> 'Pendiente') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una postulación nueva debe iniciar en estado Pendiente';
  END IF;

  SELECT r.nombre INTO v_rol
    FROM usuarios u
    JOIN roles r ON r.id_rol = u.id_rol
   WHERE u.id_usuario = NEW.id_usuario;

  IF v_rol IS NOT NULL AND v_rol <> 'Aprendiz' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Solo un usuario con rol Aprendiz puede crear una postulación';
  END IF;

  SELECT estado, CURDATE() BETWEEN fecha_inicio AND fecha_cierre INTO v_estado, v_abierta
    FROM convocatorias
   WHERE id_convocatoria = NEW.id_convocatoria;

  IF v_estado IS NOT NULL AND (v_estado <> 'Activa' OR v_abierta = 0) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La convocatoria no está abierta: debe estar Activa y dentro de sus fechas';
  END IF;

  IF NEW.justificacion_rechazo IS NOT NULL
     OR NEW.fecha_validacion IS NOT NULL OR NEW.id_usuario_validacion IS NOT NULL
     OR NEW.fecha_aprobacion IS NOT NULL OR NEW.id_usuario_aprobacion IS NOT NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Los datos de validación, aprobación y rechazo los registra la base de datos';
  END IF;
END//

-- Deja en el historial y en la auditoría la creación de la postulación (D9).
CREATE TRIGGER tr_postulacion_creada
AFTER INSERT ON postulaciones
FOR EACH ROW
BEGIN
  INSERT INTO historial_estados (id_postulacion, estado_anterior, estado_nuevo, id_usuario)
  VALUES (NEW.id_postulacion, NULL, NEW.estado, @app_id_usuario);

  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario, 'CREAR', 'postulaciones', NEW.id_postulacion,
          JSON_OBJECT('id_usuario', NEW.id_usuario,
                      'id_convocatoria', NEW.id_convocatoria,
                      'estado_nuevo', NEW.estado),
          @app_ip);
END//

-- Valida cada cambio de estado antes de guardarlo:
--   * solo transiciones permitidas (D1);
--   * justificación obligatoria al rechazar (D1);
--   * no aprobar más postulaciones que los cupos de la convocatoria (D2);
--   * guarda quién validó / aprobó y cuándo (D2, D9).
CREATE TRIGGER tr_postulacion_validar
BEFORE UPDATE ON postulaciones
FOR EACH ROW
BEGIN
  DECLARE v_cupos     INT;
  DECLARE v_aprobadas INT;
  DECLARE v_mensaje   VARCHAR(128);

  IF NOT (OLD.id_usuario <=> NEW.id_usuario) OR NOT (OLD.id_convocatoria <=> NEW.id_convocatoria) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede cambiar el aprendiz ni la convocatoria de una postulación';
  END IF;

  -- I3: quién validó / aprobó y cuándo lo escribe solo este trigger.
  IF NOT (OLD.fecha_validacion      <=> NEW.fecha_validacion)
     OR NOT (OLD.id_usuario_validacion <=> NEW.id_usuario_validacion)
     OR NOT (OLD.fecha_aprobacion      <=> NEW.fecha_aprobacion)
     OR NOT (OLD.id_usuario_aprobacion <=> NEW.id_usuario_aprobacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Los datos de validación y aprobación los registra la base de datos; no se pueden modificar';
  END IF;

  -- I3: la justificación solo se escribe en el mismo cambio que rechaza.
  IF NOT (OLD.justificacion_rechazo <=> NEW.justificacion_rechazo)
     AND NOT (OLD.estado <> 'Rechazada' AND NEW.estado <=> 'Rechazada') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La justificación del rechazo solo se registra al rechazar y no se puede cambiar';
  END IF;

  -- I3: el formulario solo se corrige mientras nadie lo ha revisado.
  IF NOT (OLD.datos_formulario <=> NEW.datos_formulario) AND OLD.estado <> 'Pendiente' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El formulario solo se puede modificar mientras la postulación está Pendiente';
  END IF;

  IF NOT (OLD.estado <=> NEW.estado) THEN

    IF NOT (   (OLD.estado = 'Pendiente' AND NEW.estado IN ('Validada', 'Rechazada'))
            OR (OLD.estado = 'Validada'  AND NEW.estado IN ('Aprobada', 'Rechazada'))) THEN
      SET v_mensaje = CONCAT('Transición de estado no permitida: ', OLD.estado, ' -> ',
                             IFNULL(NULLIF(NEW.estado, ''), '(vacío)'));
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
    END IF;

    IF NEW.estado = 'Rechazada'
       AND (NEW.justificacion_rechazo IS NULL OR TRIM(NEW.justificacion_rechazo) = '') THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La justificación es obligatoria al rechazar una postulación';
    END IF;

    IF NEW.estado = 'Validada' THEN
      SET NEW.fecha_validacion      = NOW();
      SET NEW.id_usuario_validacion = @app_id_usuario;
    END IF;

    IF NEW.estado = 'Aprobada' THEN
      -- FOR UPDATE bloquea la convocatoria: las aprobaciones de la misma
      -- convocatoria se hacen de una en una. LOCK IN SHARE MODE en el conteo
      -- obliga a leer los datos ya confirmados por la otra sesión, y no la
      -- foto vieja de REPEATABLE READ (corrección B1).
      SELECT cupos INTO v_cupos
        FROM convocatorias
       WHERE id_convocatoria = NEW.id_convocatoria
         FOR UPDATE;

      SELECT COUNT(*) INTO v_aprobadas
        FROM postulaciones
       WHERE id_convocatoria = NEW.id_convocatoria
         AND estado = 'Aprobada'
         LOCK IN SHARE MODE;

      IF v_aprobadas >= v_cupos THEN
        SET v_mensaje = CONCAT('No hay cupos disponibles en la convocatoria: ',
                               v_aprobadas, ' aprobadas de ', v_cupos, ' cupos');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
      END IF;

      SET NEW.fecha_aprobacion      = NOW();
      SET NEW.id_usuario_aprobacion = @app_id_usuario;
    END IF;

  END IF;
END//

-- Historial de estados: una fila por cada cambio, con el usuario de la
-- aplicación que lo hizo (D9). Corrige el original, que guardaba siempre 1.
CREATE TRIGGER tr_postulacion_estado
AFTER UPDATE ON postulaciones
FOR EACH ROW
BEGIN
  IF NOT (OLD.estado <=> NEW.estado) THEN
    INSERT INTO historial_estados (id_postulacion, estado_anterior, estado_nuevo, justificacion, id_usuario)
    VALUES (NEW.id_postulacion, OLD.estado, NEW.estado,
            IF(NEW.estado = 'Rechazada', NEW.justificacion_rechazo, NULL),
            @app_id_usuario);
  END IF;
END//

-- Auditoría de toda modificación de una postulación (D9). Corrige el
-- original, que guardaba al aprendiz dueño en vez de quien hizo el cambio.
CREATE TRIGGER tr_auditoria_postulacion
AFTER UPDATE ON postulaciones
FOR EACH ROW
FOLLOWS tr_postulacion_estado
BEGIN
  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario,
          IF(OLD.estado <=> NEW.estado, 'ACTUALIZAR', 'CAMBIO_ESTADO'),
          'postulaciones', NEW.id_postulacion,
          JSON_OBJECT('estado_anterior', OLD.estado,
                      'estado_nuevo', NEW.estado,
                      'justificacion', NEW.justificacion_rechazo),
          @app_ip);
END//

-- Notificación al aprendiz al pasar a Validada, Aprobada o Rechazada (D8).
-- La de rechazo incluye la justificación.
CREATE TRIGGER tr_postulacion_notificar
AFTER UPDATE ON postulaciones
FOR EACH ROW
FOLLOWS tr_auditoria_postulacion
BEGIN
  IF NOT (OLD.estado <=> NEW.estado) AND NEW.estado IN ('Validada', 'Aprobada', 'Rechazada') THEN
    INSERT INTO notificaciones (id_usuario, destinatario, evento, asunto, cuerpo, id_postulacion)
    SELECT u.id_usuario,
           u.correo,
           NEW.estado,
           CONCAT('Postulación ', LOWER(NEW.estado)),
           CASE NEW.estado
             WHEN 'Validada' THEN
               CONCAT('Su postulación a la convocatoria "', c.nombre,
                      '" fue validada. Ahora espera la selección de beneficiarios.')
             WHEN 'Aprobada' THEN
               CONCAT('Su postulación a la convocatoria "', c.nombre,
                      '" fue aprobada. Consulte en el portal el valor y los pagos de su beneficio.')
             ELSE
               CONCAT('Su postulación a la convocatoria "', c.nombre,
                      '" fue rechazada. Justificación: ', NEW.justificacion_rechazo)
           END,
           NEW.id_postulacion
      FROM usuarios u
      JOIN convocatorias c ON c.id_convocatoria = NEW.id_convocatoria
     WHERE u.id_usuario = NEW.id_usuario;
  END IF;
END//

-- Al aprobar se crea la asignación del beneficio copiando los valores por
-- defecto de la convocatoria (D3).
CREATE TRIGGER tr_postulacion_crear_asignacion
AFTER UPDATE ON postulaciones
FOR EACH ROW
FOLLOWS tr_postulacion_notificar
BEGIN
  IF NOT (OLD.estado <=> NEW.estado) AND NEW.estado = 'Aprobada' THEN
    INSERT INTO asignaciones_beneficio
           (id_postulacion, valor_pago, numero_pagos, duracion_meses, fecha_inicio, id_usuario_registro)
    SELECT NEW.id_postulacion, c.valor_pago, c.numero_pagos, c.duracion_meses, CURDATE(), @app_id_usuario
      FROM convocatorias c
     WHERE c.id_convocatoria = NEW.id_convocatoria;
  END IF;
END//

-- Solo se pide documentación adicional a postulaciones Pendiente; la
-- postulación no cambia de estado mientras tanto (D1).
CREATE TRIGGER tr_solicitud_documentacion_validar
BEFORE INSERT ON solicitudes_documentacion
FOR EACH ROW
BEGIN
  DECLARE v_estado VARCHAR(20);

  SELECT estado INTO v_estado
    FROM postulaciones
   WHERE id_postulacion = NEW.id_postulacion;

  IF v_estado <> 'Pendiente' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Solo se puede solicitar documentación de una postulación Pendiente';
  END IF;
END//

-- ---------------------------------------------------------------------
-- Asignaciones del beneficio
-- ---------------------------------------------------------------------

-- Una asignación solo existe para una postulación Aprobada, una sola vez,
-- y siempre empieza En curso (D3).
CREATE TRIGGER tr_asignacion_validar_insercion
BEFORE INSERT ON asignaciones_beneficio
FOR EACH ROW
BEGIN
  DECLARE v_estado VARCHAR(20);

  SELECT estado INTO v_estado
    FROM postulaciones
   WHERE id_postulacion = NEW.id_postulacion;

  IF v_estado IS NULL OR v_estado <> 'Aprobada' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Solo se puede crear una asignación para una postulación Aprobada';
  END IF;

  IF EXISTS (SELECT 1 FROM asignaciones_beneficio WHERE id_postulacion = NEW.id_postulacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La postulación ya tiene una asignación de beneficio';
  END IF;

  IF NOT (NEW.estado <=> 'En curso') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una asignación nueva debe iniciar en estado En curso';
  END IF;

  SET NEW.id_usuario_registro = COALESCE(NEW.id_usuario_registro, @app_id_usuario);
END//

-- Valida los cambios de una asignación (D3, D4):
--   * transiciones: En curso -> Finalizado | Suspendido | Cancelado,
--     Suspendido -> En curso (reactivación) | Cancelado,
--     Finalizado -> En curso solo desde tr_pago_anulado (I2);
--   * Finalizado solo con todos los pagos vigentes registrados;
--   * motivo nuevo obligatorio al suspender, reactivar o cancelar;
--   * valor, número de pagos, duración y fecha de inicio solo se cambian
--     si la asignación está En curso y aún no tiene pagos (B2).
CREATE TRIGGER tr_asignacion_validar_estado
BEFORE UPDATE ON asignaciones_beneficio
FOR EACH ROW
BEGIN
  DECLARE v_vigentes INT;
  DECLARE v_pagos    INT;
  DECLARE v_mensaje  VARCHAR(128);
  DECLARE v_reapertura INT;

  -- La autorización de reapertura se consume al empezar: vale para un solo
  -- cambio aunque luego este trigger lance un error.
  SET v_reapertura = (@_br_reapertura <=> NEW.id_asignacion);
  SET @_br_reapertura = NULL;

  -- LOCK IN SHARE MODE: contar los pagos ya confirmados por otras sesiones
  -- y no la foto vieja de REPEATABLE READ (corrección I1).
  SELECT COUNT(*), IFNULL(SUM(estado = 'Registrado'), 0) INTO v_pagos, v_vigentes
    FROM pagos
   WHERE id_asignacion = NEW.id_asignacion
    LOCK IN SHARE MODE;

  IF NOT (OLD.id_postulacion <=> NEW.id_postulacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede cambiar la postulación de una asignación';
  END IF;

  -- I2: la reapertura solo la pide tr_pago_anulado, que fija la variable
  -- interna @_br_reapertura con el id de esta asignación.
  IF OLD.estado = 'Finalizado' AND NEW.estado <=> 'En curso'
     AND v_reapertura = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una asignación finalizada solo vuelve a En curso al anular uno de sus pagos';
  END IF;

  IF NEW.numero_pagos < v_vigentes THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El número total de pagos no puede ser menor que los pagos ya registrados';
  END IF;

  -- B2: valores congelados en cuanto hay pagos (vigentes o anulados) o la
  -- asignación deja de estar En curso.
  IF (   NOT (OLD.valor_pago     <=> NEW.valor_pago)
      OR NOT (OLD.numero_pagos   <=> NEW.numero_pagos)
      OR NOT (OLD.duracion_meses <=> NEW.duracion_meses)
      OR NOT (OLD.fecha_inicio   <=> NEW.fecha_inicio))
     AND (v_pagos > 0 OR OLD.estado <> 'En curso') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se pueden cambiar valor, pagos, duración ni inicio: la asignación ya tiene pagos o no está En curso';
  END IF;

  IF NEW.estado = 'Finalizado' AND v_vigentes < NEW.numero_pagos THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La asignación solo puede finalizar cuando todos sus pagos están registrados';
  END IF;

  IF NOT (OLD.estado <=> NEW.estado) THEN

    IF NOT (   (OLD.estado = 'En curso'   AND NEW.estado IN ('Finalizado', 'Suspendido', 'Cancelado'))
            OR (OLD.estado = 'Suspendido' AND NEW.estado IN ('En curso', 'Cancelado'))
            OR (OLD.estado = 'Finalizado' AND NEW.estado = 'En curso')) THEN
      SET v_mensaje = CONCAT('Transición de estado de asignación no permitida: ', OLD.estado, ' -> ',
                             IFNULL(NULLIF(NEW.estado, ''), '(vacío)'));
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
    END IF;

    IF OLD.estado = 'Finalizado' AND v_vigentes >= NEW.numero_pagos THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Una asignación finalizada solo vuelve a En curso al anular uno de sus pagos';
    END IF;

    IF (NEW.estado IN ('Suspendido', 'Cancelado') OR OLD.estado = 'Suspendido')
       AND (NEW.motivo_estado IS NULL OR TRIM(NEW.motivo_estado) = ''
            OR NEW.motivo_estado <=> OLD.motivo_estado) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El motivo es obligatorio para suspender, reactivar o cancelar una asignación';
    END IF;

    IF NEW.estado IN ('Finalizado', 'Cancelado') THEN
      SET NEW.fecha_fin = CURDATE();
    ELSE
      SET NEW.fecha_fin = NULL;
    END IF;

  END IF;

END//

-- Auditoría de los cambios de la asignación y novedad automática al
-- suspender, reactivar o cancelar (D5), para que el aprendiz la vea en su
-- portal (y reciba la notificación por tr_novedad_registrada).
CREATE TRIGGER tr_asignacion_cambio_estado
AFTER UPDATE ON asignaciones_beneficio
FOR EACH ROW
BEGIN
  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario,
          IF(OLD.estado <=> NEW.estado, 'ACTUALIZAR', 'CAMBIO_ESTADO'),
          'asignaciones_beneficio', NEW.id_asignacion,
          JSON_OBJECT('estado_anterior', OLD.estado,
                      'estado_nuevo', NEW.estado,
                      'motivo', NEW.motivo_estado,
                      'numero_pagos', NEW.numero_pagos,
                      'valor_pago', NEW.valor_pago),
          @app_ip);

  IF NOT (OLD.estado <=> NEW.estado) THEN
    IF NEW.estado = 'Suspendido' THEN
      INSERT INTO novedades (id_asignacion, tipo, descripcion)
      VALUES (NEW.id_asignacion, 'Suspensión', NEW.motivo_estado);
    ELSEIF NEW.estado = 'Cancelado' THEN
      INSERT INTO novedades (id_asignacion, tipo, descripcion)
      VALUES (NEW.id_asignacion, 'Cancelación', NEW.motivo_estado);
    ELSEIF OLD.estado = 'Suspendido' AND NEW.estado = 'En curso' THEN
      INSERT INTO novedades (id_asignacion, tipo, descripcion)
      VALUES (NEW.id_asignacion, 'Reactivación', NEW.motivo_estado);
    END IF;
  END IF;
END//

-- Las asignaciones no se borran: se cancelan con motivo (I4).
CREATE TRIGGER tr_asignacion_no_eliminar
BEFORE DELETE ON asignaciones_beneficio
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Las asignaciones no se pueden eliminar: cancélelas indicando el motivo';
END//

-- ---------------------------------------------------------------------
-- Pagos
-- ---------------------------------------------------------------------

-- Reglas al registrar un pago (D4):
--   * solo en asignaciones En curso;
--   * número entre 1 y el total de pagos de la asignación;
--   * un solo pago Registrado por número (respaldado por uk_pago_vigente);
--   * motivo obligatorio si hubo retraso;
--   * fecha de pago entre el inicio de la asignación y hoy; fecha
--     programada no anterior al inicio (I5);
--   * si el monto es distinto del valor del pago, observación obligatoria (I5);
--   * guarda quién lo registró (@app_id_usuario).
CREATE TRIGGER tr_pago_validar
BEFORE INSERT ON pagos
FOR EACH ROW
BEGIN
  DECLARE v_estado  VARCHAR(20);
  DECLARE v_total   INT;
  DECLARE v_inicio  DATE;
  DECLARE v_valor   DECIMAL(12,2);
  DECLARE v_mensaje VARCHAR(128);

  -- FOR UPDATE bloquea la asignación: evita dos registros simultáneos del
  -- mismo número o del último pago.
  SELECT estado, numero_pagos, fecha_inicio, valor_pago INTO v_estado, v_total, v_inicio, v_valor
    FROM asignaciones_beneficio
   WHERE id_asignacion = NEW.id_asignacion
     FOR UPDATE;

  IF NOT (NEW.estado <=> 'Registrado') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Un pago nuevo debe registrarse en estado Registrado';
  END IF;

  IF v_estado <> 'En curso' THEN
    SET v_mensaje = CONCAT('Solo se pueden registrar pagos en asignaciones En curso (estado actual: ',
                           v_estado, ')');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;

  IF NEW.numero_pago <= 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El número de pago debe ser mayor que cero';
  END IF;

  IF NEW.numero_pago > v_total THEN
    SET v_mensaje = CONCAT('El número de pago (', NEW.numero_pago,
                           ') supera el total de pagos de la asignación (', v_total, ')');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;

  IF EXISTS (SELECT 1 FROM pagos
              WHERE id_asignacion = NEW.id_asignacion
                AND numero_pago = NEW.numero_pago
                AND estado = 'Registrado'
               LOCK IN SHARE MODE) THEN
    SET v_mensaje = CONCAT('Ya existe un pago registrado con el número ', NEW.numero_pago,
                           ' en esta asignación; anúlelo antes de registrar otro');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;

  IF NEW.hubo_retraso = 1 AND (NEW.motivo_retraso IS NULL OR TRIM(NEW.motivo_retraso) = '') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El motivo del retraso es obligatorio cuando hubo retraso';
  END IF;

  IF NEW.fecha_pago < v_inicio OR NEW.fecha_pago > CURDATE() THEN
    SET v_mensaje = CONCAT('La fecha de pago debe estar entre el inicio de la asignación (',
                           DATE_FORMAT(v_inicio, '%d/%m/%Y'), ') y hoy');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;

  IF NEW.fecha_programada IS NOT NULL AND NEW.fecha_programada < v_inicio THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La fecha programada no puede ser anterior al inicio de la asignación';
  END IF;

  -- (Un monto <= 0 lo rechaza la regla chk_pago_monto.)
  IF NEW.monto > 0 AND NEW.monto <> v_valor
     AND (NEW.observacion IS NULL OR TRIM(NEW.observacion) = '') THEN
    SET v_mensaje = CONCAT('El monto es distinto del valor del pago (', FORMAT(v_valor, 2, 'es_CO'),
                           '): la observación es obligatoria');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;

  SET NEW.id_usuario_registro = COALESCE(NEW.id_usuario_registro, @app_id_usuario);
END//

-- Tras registrar un pago: notifica al aprendiz (D8), audita (D9) y, si ya
-- están todos los pagos vigentes, pasa la asignación a Finalizado (D3).
CREATE TRIGGER tr_pago_registrado
AFTER INSERT ON pagos
FOR EACH ROW
BEGIN
  DECLARE v_vigentes INT;
  DECLARE v_total    INT;

  INSERT INTO notificaciones (id_usuario, destinatario, evento, asunto, cuerpo, id_postulacion)
  SELECT u.id_usuario,
         u.correo,
         'Pago',
         CONCAT('Pago ', NEW.numero_pago, ' de ', a.numero_pagos, ' registrado'),
         CONCAT('Se registró el pago ', NEW.numero_pago, ' de ', a.numero_pagos,
                ' por $', FORMAT(NEW.monto, 2, 'es_CO'),
                ' con fecha ', DATE_FORMAT(NEW.fecha_pago, '%d/%m/%Y'), '.',
                IF(NEW.hubo_retraso = 1, CONCAT(' El pago tuvo retraso: ', NEW.motivo_retraso, '.'), '')),
         p.id_postulacion
    FROM asignaciones_beneficio a
    JOIN postulaciones p ON p.id_postulacion = a.id_postulacion
    JOIN usuarios u      ON u.id_usuario = p.id_usuario
   WHERE a.id_asignacion = NEW.id_asignacion;

  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario, 'REGISTRAR_PAGO', 'pagos', NEW.id_pago,
          JSON_OBJECT('id_asignacion', NEW.id_asignacion,
                      'numero_pago', NEW.numero_pago,
                      'monto', NEW.monto,
                      'hubo_retraso', NEW.hubo_retraso),
          @app_ip);

  -- LOCK IN SHARE MODE: contar también los pagos que otra sesión acaba de
  -- confirmar, no la foto vieja de REPEATABLE READ (corrección I1).
  SELECT COUNT(*) INTO v_vigentes
    FROM pagos
   WHERE id_asignacion = NEW.id_asignacion
     AND estado = 'Registrado'
    LOCK IN SHARE MODE;

  SELECT numero_pagos INTO v_total
    FROM asignaciones_beneficio
   WHERE id_asignacion = NEW.id_asignacion;

  IF v_vigentes >= v_total THEN
    UPDATE asignaciones_beneficio
       SET estado = 'Finalizado',
           motivo_estado = 'Se registró el último pago'
     WHERE id_asignacion = NEW.id_asignacion
       AND estado = 'En curso';
  END IF;
END//

-- Los pagos no se editan (D4). Lo único permitido es pasar de Registrado a
-- Anulado indicando el motivo; se guarda quién anuló y cuándo.
CREATE TRIGGER tr_pago_inmutable
BEFORE UPDATE ON pagos
FOR EACH ROW
BEGIN
  IF NOT (    OLD.estado = 'Registrado' AND NEW.estado <=> 'Anulado'
          AND OLD.id_pago             <=> NEW.id_pago
          AND OLD.id_asignacion       <=> NEW.id_asignacion
          AND OLD.numero_pago         <=> NEW.numero_pago
          AND OLD.monto               <=> NEW.monto
          AND OLD.fecha_programada    <=> NEW.fecha_programada
          AND OLD.fecha_pago          <=> NEW.fecha_pago
          AND OLD.hubo_retraso        <=> NEW.hubo_retraso
          AND OLD.motivo_retraso      <=> NEW.motivo_retraso
          AND OLD.observacion         <=> NEW.observacion
          AND OLD.id_usuario_registro <=> NEW.id_usuario_registro
          AND OLD.fecha_registro      <=> NEW.fecha_registro) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Los pagos no se pueden modificar: solo se permite anular un pago Registrado indicando el motivo';
  END IF;

  IF NEW.motivo_anulacion IS NULL OR TRIM(NEW.motivo_anulacion) = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El motivo de anulación es obligatorio';
  END IF;

  SET NEW.fecha_anulacion      = NOW();
  SET NEW.id_usuario_anulacion = @app_id_usuario;
END//

-- Tras anular un pago: audita (D9) y, si la asignación estaba Finalizado,
-- la devuelve a En curso para poder registrar el pago corregido (D3, D4).
CREATE TRIGGER tr_pago_anulado
AFTER UPDATE ON pagos
FOR EACH ROW
BEGIN
  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario, 'ANULAR_PAGO', 'pagos', NEW.id_pago,
          JSON_OBJECT('id_asignacion', NEW.id_asignacion,
                      'numero_pago', NEW.numero_pago,
                      'motivo_anulacion', NEW.motivo_anulacion),
          @app_ip);

  -- @_br_reapertura autoriza (una sola vez) la reapertura Finalizado ->
  -- En curso en tr_asignacion_validar_estado (I2).
  SET @_br_reapertura = NEW.id_asignacion;
  UPDATE asignaciones_beneficio
     SET estado = 'En curso',
         motivo_estado = LEFT(CONCAT('Se anuló el pago ', NEW.numero_pago, ': ', NEW.motivo_anulacion), 1000)
   WHERE id_asignacion = NEW.id_asignacion
     AND estado = 'Finalizado';
  SET @_br_reapertura = NULL;
END//

-- Los pagos no se borran (D4).
CREATE TRIGGER tr_pago_no_eliminar
BEFORE DELETE ON pagos
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Los pagos no se pueden eliminar: anúlelos indicando el motivo';
END//

-- ---------------------------------------------------------------------
-- Novedades
-- ---------------------------------------------------------------------

-- Guarda quién registró la novedad (D5, D9).
CREATE TRIGGER tr_novedad_preparar
BEFORE INSERT ON novedades
FOR EACH ROW
BEGIN
  IF NEW.descripcion IS NULL OR TRIM(NEW.descripcion) = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La descripción de la novedad es obligatoria';
  END IF;

  SET NEW.id_usuario = COALESCE(NEW.id_usuario, @app_id_usuario);
END//

-- Notifica al aprendiz cada novedad (D8) y la audita (D9).
CREATE TRIGGER tr_novedad_registrada
AFTER INSERT ON novedades
FOR EACH ROW
BEGIN
  INSERT INTO notificaciones (id_usuario, destinatario, evento, asunto, cuerpo, id_postulacion)
  SELECT u.id_usuario,
         u.correo,
         'Novedad',
         CONCAT('Novedad en su beneficio: ', NEW.tipo),
         CONCAT('Novedad registrada en su beneficio de la convocatoria "', c.nombre, '": ', NEW.descripcion),
         p.id_postulacion
    FROM asignaciones_beneficio a
    JOIN postulaciones p ON p.id_postulacion = a.id_postulacion
    JOIN convocatorias c ON c.id_convocatoria = p.id_convocatoria
    JOIN usuarios u      ON u.id_usuario = p.id_usuario
   WHERE a.id_asignacion = NEW.id_asignacion;

  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario, 'REGISTRAR_NOVEDAD', 'novedades', NEW.id_novedad,
          JSON_OBJECT('id_asignacion', NEW.id_asignacion,
                      'tipo', NEW.tipo,
                      'descripcion', NEW.descripcion),
          @app_ip);
END//

-- ---------------------------------------------------------------------
-- Protección contra borrados y cambios directos (I4)
-- ---------------------------------------------------------------------

-- Las postulaciones no se borran: se rechazan con justificación.
CREATE TRIGGER tr_postulacion_no_eliminar
BEFORE DELETE ON postulaciones
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Las postulaciones no se pueden eliminar: recházelas con una justificación';
END//

-- El historial de estados solo admite filas nuevas.
CREATE TRIGGER tr_historial_no_modificar
BEFORE UPDATE ON historial_estados
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'El historial de estados no se puede modificar ni eliminar';
END//

CREATE TRIGGER tr_historial_no_eliminar
BEFORE DELETE ON historial_estados
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'El historial de estados no se puede modificar ni eliminar';
END//

-- La auditoría solo admite filas nuevas.
CREATE TRIGGER tr_auditoria_no_modificar
BEFORE UPDATE ON auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'La auditoría no se puede modificar ni eliminar';
END//

CREATE TRIGGER tr_auditoria_no_eliminar
BEFORE DELETE ON auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'La auditoría no se puede modificar ni eliminar';
END//

DELIMITER ;

-- =====================================================================
-- SECCIÓN 8. DATOS SEMILLA MÍNIMOS
-- =====================================================================

INSERT INTO instituciones (id_institucion, nombre, ciudad) VALUES
  (1, 'SENA - Centro Textil y de Gestión Industrial', NULL);

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
  (1, 'Administrador', 'Gestiona convocatorias, valida y aprueba postulaciones y registra pagos'),
  (2, 'Aprendiz',      'Se postula a convocatorias y consulta su estado, pagos y novedades');

INSERT INTO tipos_cuenta (id_tipo_cuenta, nombre) VALUES
  (1, 'Ahorros'),
  (2, 'Corriente');

INSERT INTO tipos_documento (id_tipo_documento, nombre, descripcion) VALUES
  (1, 'Documento de identidad', 'Documento de identificación del aprendiz'),
  (2, 'Certificado bancario',   'Certificación de la cuenta bancaria del aprendiz'),
  (3, 'Documento adicional',    'Documento solicitado adicionalmente por el administrador');

INSERT INTO eps (nombre) VALUES
  ('Coosalud'),
  ('Famisanar'),
  ('Nueva EPS'),
  ('Salud Total'),
  ('Sanitas'),
  ('Savia Salud'),
  ('SURA');
