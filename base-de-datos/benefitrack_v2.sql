-- =====================================================================
-- BenefitRack v2 - Script de base de datos
-- Proyecto formativo SENA (ADSO) - Gestión de beneficios para aprendices
-- =====================================================================
-- Compatible con MariaDB 10.4 (XAMPP) y MariaDB 11.x.
-- Fuente de verdad: specs/001-correccion-entregables.md (decisiones D1-D19).
--
-- Variables de sesión que usa la base de datos (las fija la aplicación
-- al abrir cada conexión):
--   SET @app_id_usuario = <id del usuario que inició sesión>;   -- D9
--   SET @app_ip         = '<ip del cliente>';                    -- opcional
--   SET @app_justificacion = '<motivo>';  -- antes de cambiar el rol o el
--                                         -- estado de un usuario (D15)
-- El flujo de inicio de sesión (intentos fallidos, bloqueo y desbloqueo
-- automáticos) se ejecuta con @app_id_usuario = NULL: todavía no hay
-- usuario de sesión (D13).
-- @app_justificacion es obligatoria en todo cambio de rol o estado de un
-- usuario que no sea automático, también con root; se guarda en auditoria.
-- Vía recomendada: pr_cambiar_estado_usuario / pr_cambiar_rol_usuario, que
-- la fijan solo para ese cambio y la limpian.
-- Si @app_id_usuario no está definida, los triggers guardan NULL. Pero,
-- salvo con el usuario de conexión root (mantenimiento, instalación y
-- pruebas, D17), las acciones de gestión con @app_id_usuario NULL se
-- RECHAZAN: la aplicación debe fijar siempre la variable. En la auditoría
-- de cuentas, id_usuario NULL significa «el sistema» solo cuando el origen
-- es Sistema; con origen Mantenimiento significa «root sin sesión».
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
-- Rutinas auxiliares de autorización
-- ---------------------------------------------------------------------

-- Devuelve 1 si el usuario existe, tiene rol Administrador y está Activo.
CREATE FUNCTION fn_es_administrador(p_id_usuario INT)
RETURNS TINYINT(1)
NOT DETERMINISTIC
READS SQL DATA
BEGIN
  RETURN EXISTS (SELECT 1
                   FROM usuarios u
                   JOIN roles r ON r.id_rol = u.id_rol
                  WHERE u.id_usuario = p_id_usuario
                    AND r.nombre = 'Administrador'
                    AND u.estado = 'Activo');
END//

-- Lanza un error si la acción no la hace un administrador activo:
--   * @app_id_usuario definido y que no es administrador activo, o
--   * @app_id_usuario NULL con cualquier usuario de conexión que no sea
--     root (lista blanca): la aplicación (benefitrack_app u otro usuario)
--     no puede "olvidar" la variable para saltarse el control.
-- Solo con root (mantenimiento, scripts, pruebas) el NULL sigue permitido
-- (D9). Se compara solo el nombre, sin el host.
-- USER() es el usuario que se conectó, aunque el trigger corra como DEFINER.
CREATE PROCEDURE pr_exigir_administrador(IN p_accion VARCHAR(80))
READS SQL DATA
BEGIN
  DECLARE v_mensaje VARCHAR(128);

  IF (@app_id_usuario IS NULL AND NOT (SUBSTRING_INDEX(USER(), '@', 1) <=> 'root'))
     OR (@app_id_usuario IS NOT NULL AND NOT fn_es_administrador(@app_id_usuario)) THEN
    SET v_mensaje = CONCAT('Solo un administrador activo puede ', p_accion);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;
END//

-- Igual que pr_exigir_administrador, pero también acepta al aprendiz dueño
-- de la postulación indicada (ronda 10): es quien sube de nuevo un archivo
-- o atiende una solicitud de documentación. Con @app_id_usuario NULL solo
-- vale root, como siempre.
CREATE PROCEDURE pr_exigir_dueno_o_administrador(IN p_id_postulacion INT, IN p_accion VARCHAR(50))
READS SQL DATA
BEGIN
  DECLARE v_mensaje VARCHAR(128);

  IF NOT ((@app_id_usuario IS NULL AND SUBSTRING_INDEX(USER(), '@', 1) <=> 'root')
          OR (@app_id_usuario IS NOT NULL
              AND (fn_es_administrador(@app_id_usuario)
                   OR EXISTS (SELECT 1 FROM postulaciones
                               WHERE id_postulacion = p_id_postulacion
                                 AND id_usuario = @app_id_usuario)))) THEN
    SET v_mensaje = CONCAT('Solo el aprendiz dueño de la postulación o un administrador activo puede ', p_accion);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;
END//

-- Igual que pr_exigir_administrador, pero también acepta al titular del dato
-- (el usuario p_id_titular): su cuenta bancaria, su postulación, sus
-- notificaciones (ronda 11, D19). Con @app_id_usuario NULL solo vale root.
CREATE PROCEDURE pr_exigir_titular_o_administrador(IN p_id_titular INT, IN p_accion VARCHAR(50))
READS SQL DATA
BEGIN
  DECLARE v_mensaje VARCHAR(128);

  IF NOT ((@app_id_usuario IS NULL AND SUBSTRING_INDEX(USER(), '@', 1) <=> 'root')
          OR (@app_id_usuario IS NOT NULL
              AND (@app_id_usuario <=> p_id_titular OR fn_es_administrador(@app_id_usuario)))) THEN
    SET v_mensaje = CONCAT('Solo el titular o un administrador activo puede ', p_accion);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;
END//

-- ---------------------------------------------------------------------
-- Usuarios
-- ---------------------------------------------------------------------

-- Devuelve 1 si el cambio de estado es uno de los automáticos del sistema.
-- Los hace SOLO el inicio de sesión, que todavía no tiene usuario de sesión
-- (@app_id_usuario NULL):
--   * Activo -> Bloqueado con intentos_fallidos >= 3;
--   * Bloqueado -> Activo cuando ya pasaron 15 minutos desde fecha_bloqueo
--     (hora real, SYSDATE()).
-- Con un usuario de sesión (aprendiz o administrador) nunca es automático:
-- es un cambio de esa persona. Solo depende de la variable y de los valores
-- recibidos, no de la tabla, así que da lo mismo en el BEFORE y en el AFTER
-- aunque la sentencia cambie varias filas.
CREATE FUNCTION fn_cambio_estado_sistema(p_estado_anterior VARCHAR(20),
                                         p_estado_nuevo    VARCHAR(20),
                                         p_fecha_bloqueo   DATETIME,
                                         p_intentos        INT)
RETURNS TINYINT(1)
NOT DETERMINISTIC
NO SQL
BEGIN
  RETURN @app_id_usuario IS NULL
         AND ((p_estado_anterior = 'Activo' AND p_estado_nuevo = 'Bloqueado'
               AND IFNULL(p_intentos, 0) >= 3)
              OR (p_estado_anterior = 'Bloqueado' AND p_estado_nuevo = 'Activo'
                  AND p_fecha_bloqueo IS NOT NULL
                  AND p_fecha_bloqueo + INTERVAL 15 MINUTE <= SYSDATE()));
END//

-- Crear un usuario Administrador exige un administrador activo. El
-- registro de aprendices (sin sesión) sigue permitido.
CREATE TRIGGER tr_usuario_validar_insercion
BEFORE INSERT ON usuarios
FOR EACH ROW
BEGIN
  IF NEW.id_rol = (SELECT id_rol FROM roles WHERE nombre = 'Administrador') THEN
    CALL pr_exigir_administrador('crear un usuario Administrador');
  END IF;

  -- Un usuario nuevo nace Activo; otro estado lo decide un administrador.
  IF NOT (NEW.estado <=> 'Activo') THEN
    CALL pr_exigir_administrador('crear un usuario que no esté Activo');
  END IF;

  -- La fecha de bloqueo la pone la base, nunca la aplicación.
  IF NEW.estado = 'Bloqueado' THEN
    SET NEW.fecha_bloqueo = SYSDATE();  -- hora real, ver tr_usuario_validar
  ELSE
    SET NEW.fecha_bloqueo = NULL;
  END IF;
END//

-- Cambiar el rol o el estado de un usuario exige un administrador activo y
-- una justificación (@app_justificacion, RF-1.3), para que nadie se dé a sí
-- mismo el rol de Administrador y cada cambio quede explicado. La única
-- excepción son los cambios automáticos del inicio de sesión
-- (fn_cambio_estado_sistema): sin usuario de sesión, Activo -> Bloqueado
-- con 3 intentos fallidos y Bloqueado -> Activo pasados 15 minutos.
-- Con root y @app_id_usuario NULL (mantenimiento) no hace falta
-- administrador, pero sí justificación.
-- El control de administrador se decide en el BEFORE de cada fila: si el
-- administrador se cambia a sí mismo, su fila todavía tiene los valores
-- anteriores. Límite: en un UPDATE de varias filas, si el administrador se
-- bloquea o inactiva en una fila, en las filas siguientes ya no cuenta como
-- administrador activo y la sentencia entera se rechaza.
-- Al pasar a Bloqueado la base guarda fecha_bloqueo = SYSDATE() e ignora la
-- fecha recibida. Al salir de Bloqueado (automático o por un administrador)
-- intentos_fallidos vuelve a 0 y fecha_bloqueo a NULL. Mientras la cuenta
-- sigue Bloqueada, cambiar fecha_bloqueo exige administrador.
-- Se usa SYSDATE() (hora real del servidor) y no NOW(): NOW() se puede
-- cambiar en la sesión con SET timestamp, y eso permitiría bloquear con una
-- fecha vieja o desbloquear "en el futuro". (Si el servidor se arranca con
-- --sysdate-is-now, SYSDATE() vuelve a ser NOW(); no lo use.)
--
-- Datos de la cuenta (ronda 11, D19): contraseña, correo, documento,
-- nombre, contacto, programa, EPS, ficha, etc.
--   * cada quien cambia los suyos (@app_id_usuario = la fila);
--   * los de otro usuario (también sus intentos_fallidos) solo un
--     administrador activo, y nunca la contraseña ni el correo de otro
--     administrador: ese administrador cambia los suyos o usa la
--     recuperación de contraseña (con el correo se tomaría su cuenta);
--   * sin usuario de sesión (inicio de sesión) solo se tocan intentos,
--     estado y bloqueo; los datos de la cuenta, solo root (mantenimiento).
--     La recuperación de contraseña se hace con @app_id_usuario = dueño
--     del token, después de que la aplicación lo comprueba.
CREATE TRIGGER tr_usuario_validar
BEFORE UPDATE ON usuarios
FOR EACH ROW
BEGIN
  DECLARE v_cambia_rol    TINYINT(1) DEFAULT 0;
  DECLARE v_cambia_estado TINYINT(1) DEFAULT 0;
  DECLARE v_automatico    TINYINT(1) DEFAULT 0;
  DECLARE v_cambia_clave  TINYINT(1) DEFAULT 0;
  DECLARE v_cambia_cuenta TINYINT(1) DEFAULT 0;

  SET v_cambia_clave  = NOT (OLD.password_hash <=> NEW.password_hash)
                        OR NOT (OLD.correo <=> NEW.correo);
  SET v_cambia_cuenta = v_cambia_clave
                        OR NOT (OLD.id_usuario       <=> NEW.id_usuario)
                        OR NOT (OLD.documento        <=> NEW.documento)
                        OR NOT (OLD.nombre           <=> NEW.nombre)
                        OR NOT (OLD.apellido         <=> NEW.apellido)
                        OR NOT (OLD.fecha_nacimiento <=> NEW.fecha_nacimiento)
                        OR NOT (OLD.telefono         <=> NEW.telefono)
                        OR NOT (OLD.direccion        <=> NEW.direccion)
                        OR NOT (OLD.id_programa      <=> NEW.id_programa)
                        OR NOT (OLD.id_eps           <=> NEW.id_eps)
                        OR NOT (OLD.ficha            <=> NEW.ficha)
                        OR NOT (OLD.fecha_registro   <=> NEW.fecha_registro);

  IF @app_id_usuario IS NULL THEN
    IF v_cambia_cuenta THEN
      CALL pr_exigir_administrador('modificar los datos de una cuenta sin usuario de sesión');
    END IF;
  ELSEIF NOT (@app_id_usuario <=> OLD.id_usuario)
         AND (v_cambia_cuenta OR NOT (OLD.intentos_fallidos <=> NEW.intentos_fallidos)) THEN
    CALL pr_exigir_administrador('modificar los datos de la cuenta de otro usuario');
    IF v_cambia_clave AND OLD.id_rol = (SELECT id_rol FROM roles WHERE nombre = 'Administrador') THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un administrador no puede cambiar la contraseña ni el correo de otro administrador';
    END IF;
  END IF;

  SET v_cambia_rol    = NOT (OLD.id_rol <=> NEW.id_rol);
  SET v_cambia_estado = NOT (OLD.estado <=> NEW.estado);
  SET v_automatico    = v_cambia_estado AND NOT v_cambia_rol
                        AND fn_cambio_estado_sistema(OLD.estado, NEW.estado,
                                                     OLD.fecha_bloqueo, NEW.intentos_fallidos);

  IF (v_cambia_rol OR v_cambia_estado) AND NOT v_automatico THEN
    CALL pr_exigir_administrador('cambiar el rol o el estado de un usuario');
    IF TRIM(IFNULL(@app_justificacion, '')) = '' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El cambio de rol o de estado de un usuario exige una justificación (@app_justificacion)';
    END IF;
  END IF;

  IF v_cambia_estado THEN
    IF NEW.estado = 'Bloqueado' THEN
      SET NEW.fecha_bloqueo = SYSDATE();
    END IF;
    IF OLD.estado = 'Bloqueado' THEN
      SET NEW.intentos_fallidos = 0;
      SET NEW.fecha_bloqueo = NULL;
    END IF;
  ELSEIF NEW.estado = 'Bloqueado' AND NOT (OLD.fecha_bloqueo <=> NEW.fecha_bloqueo) THEN
    CALL pr_exigir_administrador('cambiar la fecha de bloqueo de un usuario');
  END IF;
END//

-- Deja en la auditoría cada cambio de rol o de estado de un usuario: quién
-- (@app_id_usuario), el valor anterior y el nuevo, el origen y la
-- justificación. Orígenes:
--   * Sistema: cambio automático del inicio de sesión; id_usuario NULL y la
--     justificación la escribe la base;
--   * Mantenimiento: root sin usuario de sesión; id_usuario NULL y la
--     justificación es la que dio root;
--   * Usuario: una persona con sesión (normalmente un administrador).
-- Sumar intentos_fallidos solo no se audita, para no llenar la tabla.
CREATE TRIGGER tr_usuario_auditar
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
  DECLARE v_cambia_rol    TINYINT(1) DEFAULT 0;
  DECLARE v_cambia_estado TINYINT(1) DEFAULT 0;
  DECLARE v_sistema       TINYINT(1) DEFAULT 0;

  SET v_cambia_rol    = NOT (OLD.id_rol <=> NEW.id_rol);
  SET v_cambia_estado = NOT (OLD.estado <=> NEW.estado);

  IF v_cambia_rol OR v_cambia_estado THEN
    SET v_sistema = v_cambia_estado AND NOT v_cambia_rol
                    AND fn_cambio_estado_sistema(OLD.estado, NEW.estado,
                                                 OLD.fecha_bloqueo, NEW.intentos_fallidos);

    INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
    VALUES (@app_id_usuario,
            CASE WHEN v_cambia_rol AND v_cambia_estado THEN 'CAMBIO_ROL_ESTADO'
                 WHEN v_cambia_estado THEN 'CAMBIO_ESTADO'
                 ELSE 'CAMBIO_ROL' END,
            'usuarios', NEW.id_usuario,
            JSON_OBJECT('rol_anterior', OLD.id_rol,
                        'rol_nuevo', NEW.id_rol,
                        'estado_anterior', OLD.estado,
                        'estado_nuevo', NEW.estado,
                        'origen', IF(v_sistema, 'Sistema',
                                     IF(@app_id_usuario IS NULL, 'Mantenimiento', 'Usuario')),
                        'justificacion',
                        IF(v_sistema,
                           IF(NEW.estado = 'Bloqueado', 'Bloqueo automático por intentos fallidos',
                                                        'Desbloqueo automático pasados 15 minutos'),
                           NULLIF(TRIM(@app_justificacion), ''))),
            @app_ip);
  END IF;
END//

-- Vía recomendada para cambiar el estado de un usuario: fija la
-- justificación solo para este cambio, hace el UPDATE y deja
-- @app_justificacion en NULL, también si el cambio falla. Así un motivo no
-- se arrastra al siguiente cambio de la sesión. Los controles (administrador
-- activo, justificación, auditoría) los siguen haciendo los triggers.
-- Errores claros antes de tocar la tabla: estado no válido, usuario que no
-- existe y usuario que ya tiene ese estado. La justificación es TEXT y se
-- guarda completa en auditoria.detalle (LONGTEXT). El manejador atrapa
-- también SQLWARNING porque, en modo estricto, algunos errores (1265,
-- «Data truncated») llegan con un SQLSTATE de advertencia.
CREATE PROCEDURE pr_cambiar_estado_usuario(IN p_id_usuario    INT,
                                           IN p_estado        VARCHAR(255),
                                           IN p_justificacion TEXT)
MODIFIES SQL DATA
BEGIN
  -- Ante cualquier error o advertencia: salir (EXIT), borrar la
  -- justificación para que no se arrastre al siguiente cambio y devolver el
  -- mismo error a quien llamó (RESIGNAL). SQLWARNING porque en modo
  -- estricto el 1265 «Data truncated» llega con SQLSTATE 01000.
  DECLARE EXIT HANDLER FOR SQLEXCEPTION, SQLWARNING
  BEGIN
    SET @app_justificacion = NULL;
    RESIGNAL;
  END;

  SET @app_justificacion = p_justificacion;
  IF p_estado IS NULL OR p_estado NOT IN ('Activo', 'Inactivo', 'Bloqueado') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Estado no válido: debe ser Activo, Inactivo o Bloqueado';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_usuario) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe el usuario indicado';
  END IF;
  IF EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_usuario AND estado = p_estado) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario ya tiene ese estado';
  END IF;

  UPDATE usuarios SET estado = p_estado WHERE id_usuario = p_id_usuario;
  -- Respaldo: si por cualquier motivo no cambió ninguna fila, se avisa.
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se cambió el estado del usuario';
  END IF;
  SET @app_justificacion = NULL;
END//

-- Igual que pr_cambiar_estado_usuario, para el rol (por nombre:
-- 'Administrador' o 'Aprendiz').
CREATE PROCEDURE pr_cambiar_rol_usuario(IN p_id_usuario    INT,
                                        IN p_rol           VARCHAR(255),
                                        IN p_justificacion TEXT)
MODIFIES SQL DATA
BEGIN
  DECLARE v_id_rol INT DEFAULT NULL;
  -- Mismo manejador que pr_cambiar_estado_usuario: salir, borrar la
  -- justificación y devolver el error original (RESIGNAL).
  DECLARE EXIT HANDLER FOR SQLEXCEPTION, SQLWARNING
  BEGIN
    SET @app_justificacion = NULL;
    RESIGNAL;
  END;

  SET @app_justificacion = p_justificacion;
  SET v_id_rol = (SELECT id_rol FROM roles WHERE nombre = p_rol);
  IF v_id_rol IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe el rol indicado';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_usuario) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe el usuario indicado';
  END IF;
  IF EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = p_id_usuario AND id_rol = v_id_rol) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario ya tiene ese rol';
  END IF;

  UPDATE usuarios SET id_rol = v_id_rol WHERE id_usuario = p_id_usuario;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se cambió el rol del usuario';
  END IF;
  SET @app_justificacion = NULL;
END//

-- ---------------------------------------------------------------------
-- Información bancaria (ronda 11, D19)
-- ---------------------------------------------------------------------
-- La cuenta donde se pagan los beneficios la registra y la cambia su
-- titular o un administrador activo; nadie más. Una cuenta no se pasa a
-- otro usuario.

CREATE TRIGGER tr_bancaria_crear BEFORE INSERT ON informacion_bancaria FOR EACH ROW
  CALL pr_exigir_titular_o_administrador(NEW.id_usuario, 'registrar esta cuenta bancaria')//

CREATE TRIGGER tr_bancaria_modificar
BEFORE UPDATE ON informacion_bancaria
FOR EACH ROW
BEGIN
  IF NOT (OLD.id_usuario <=> NEW.id_usuario) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La cuenta bancaria no se puede pasar a otro usuario';
  END IF;
  CALL pr_exigir_titular_o_administrador(OLD.id_usuario, 'modificar esta cuenta bancaria');
END//

-- ---------------------------------------------------------------------
-- Catálogos y beneficios (ronda 10)
-- ---------------------------------------------------------------------
-- Crear o modificar instituciones, tipos de cuenta, tipos de documento,
-- entidades bancarias, EPS, programas y beneficios es gestión: solo un
-- administrador activo (o root sin sesión, como la semilla de este script).

CREATE TRIGGER tr_institucion_crear BEFORE INSERT ON instituciones FOR EACH ROW
  CALL pr_exigir_administrador('crear una institución')//
CREATE TRIGGER tr_institucion_modificar BEFORE UPDATE ON instituciones FOR EACH ROW
  CALL pr_exigir_administrador('modificar una institución')//
CREATE TRIGGER tr_tipo_cuenta_crear BEFORE INSERT ON tipos_cuenta FOR EACH ROW
  CALL pr_exigir_administrador('crear un tipo de cuenta')//
CREATE TRIGGER tr_tipo_cuenta_modificar BEFORE UPDATE ON tipos_cuenta FOR EACH ROW
  CALL pr_exigir_administrador('modificar un tipo de cuenta')//
CREATE TRIGGER tr_tipo_documento_crear BEFORE INSERT ON tipos_documento FOR EACH ROW
  CALL pr_exigir_administrador('crear un tipo de documento')//
CREATE TRIGGER tr_tipo_documento_modificar BEFORE UPDATE ON tipos_documento FOR EACH ROW
  CALL pr_exigir_administrador('modificar un tipo de documento')//
CREATE TRIGGER tr_banco_crear BEFORE INSERT ON entidades_bancarias FOR EACH ROW
  CALL pr_exigir_administrador('crear una entidad bancaria')//
CREATE TRIGGER tr_banco_modificar BEFORE UPDATE ON entidades_bancarias FOR EACH ROW
  CALL pr_exigir_administrador('modificar una entidad bancaria')//
CREATE TRIGGER tr_eps_crear BEFORE INSERT ON eps FOR EACH ROW
  CALL pr_exigir_administrador('crear una EPS')//
CREATE TRIGGER tr_eps_modificar BEFORE UPDATE ON eps FOR EACH ROW
  CALL pr_exigir_administrador('modificar una EPS')//
CREATE TRIGGER tr_programa_crear BEFORE INSERT ON programas FOR EACH ROW
  CALL pr_exigir_administrador('crear un programa')//
CREATE TRIGGER tr_programa_modificar BEFORE UPDATE ON programas FOR EACH ROW
  CALL pr_exigir_administrador('modificar un programa')//
CREATE TRIGGER tr_beneficio_crear BEFORE INSERT ON beneficios FOR EACH ROW
  CALL pr_exigir_administrador('crear un beneficio')//
CREATE TRIGGER tr_beneficio_modificar BEFORE UPDATE ON beneficios FOR EACH ROW
  CALL pr_exigir_administrador('modificar un beneficio')//

-- ---------------------------------------------------------------------
-- Documentos de la postulación
-- ---------------------------------------------------------------------

-- Un documento nuevo (lo sube el aprendiz) nace Pendiente: si llega con otro
-- estado se rechaza, y los datos de la validación (quién, cuándo y la
-- observación del administrador) se descartan, porque solo los escribe
-- tr_validacion_documento al validar. La fecha de carga es la hora real.
-- Solo sube documentos el aprendiz dueño de la postulación o un
-- administrador activo (ronda 11, D19).
CREATE TRIGGER tr_documento_validar_insercion
BEFORE INSERT ON documentos_postulacion
FOR EACH ROW
BEGIN
  CALL pr_exigir_dueno_o_administrador(NEW.id_postulacion, 'subir un documento a esta postulación');
  IF NOT (NEW.estado_validacion <=> 'Pendiente') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Un documento nuevo debe empezar en estado Pendiente';
  END IF;
  SET NEW.id_usuario_validacion = NULL;
  SET NEW.fecha_validacion      = NULL;
  SET NEW.observaciones         = NULL;
  SET NEW.fecha_carga           = SYSDATE();
END//

-- Cambios de un documento (D6, D9, ronda 10):
--   * la fecha y el usuario de validación y la fecha de carga solo los
--     escribe la base; un documento no se pasa a otra postulación;
--   * la observación es del administrador: solo él la cambia;
--   * reemplazar el archivo (tipo, nombre, ruta, extensión o tamaño) solo
--     se permite si el documento está Pendiente o Rechazado, lo hace el
--     aprendiz dueño o un administrador, y el documento vuelve a Pendiente
--     sin datos de validación y con fecha de carga nueva. Un documento
--     Validado no se reemplaza (quedaría «validado» un archivo que nadie vio);
--   * validar o rechazar exige administrador y guarda quién y cuándo.
CREATE TRIGGER tr_validacion_documento
BEFORE UPDATE ON documentos_postulacion
FOR EACH ROW
BEGIN
  IF NOT (OLD.fecha_validacion <=> NEW.fecha_validacion)
     OR NOT (OLD.id_usuario_validacion <=> NEW.id_usuario_validacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La fecha y el usuario de validación los registra la base de datos; no se pueden modificar';
  END IF;
  IF NOT (OLD.fecha_carga <=> NEW.fecha_carga) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La fecha de carga la registra la base de datos; no se puede modificar';
  END IF;
  IF NOT (OLD.id_postulacion <=> NEW.id_postulacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Un documento no se puede pasar a otra postulación';
  END IF;
  IF NOT (OLD.observaciones <=> NEW.observaciones) THEN
    CALL pr_exigir_administrador('escribir la observación de un documento');
  END IF;

  IF NOT (OLD.id_tipo_documento <=> NEW.id_tipo_documento)
     OR NOT (OLD.nombre_archivo <=> NEW.nombre_archivo)
     OR NOT (OLD.ruta_archivo   <=> NEW.ruta_archivo)
     OR NOT (OLD.extension      <=> NEW.extension)
     OR NOT (OLD.tamano_mb      <=> NEW.tamano_mb) THEN
    IF OLD.estado_validacion = 'Validado' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un documento validado no se puede reemplazar';
    END IF;
    CALL pr_exigir_dueno_o_administrador(OLD.id_postulacion, 'reemplazar el archivo de un documento');
    IF NOT (OLD.estado_validacion <=> NEW.estado_validacion) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Al reemplazar el archivo, el documento vuelve a Pendiente; se valida después';
    END IF;
    SET NEW.estado_validacion     = 'Pendiente';
    SET NEW.fecha_validacion      = NULL;
    SET NEW.id_usuario_validacion = NULL;
    SET NEW.observaciones         = NULL;
    SET NEW.fecha_carga           = SYSDATE();
  ELSEIF NOT (OLD.estado_validacion <=> NEW.estado_validacion) THEN
    CALL pr_exigir_administrador('validar o rechazar un documento');
    SET NEW.fecha_validacion = SYSDATE();
    SET NEW.id_usuario_validacion = @app_id_usuario;
  END IF;
END//

-- ---------------------------------------------------------------------
-- Convocatorias
-- ---------------------------------------------------------------------

-- Crear una convocatoria es una acción de gestión: solo un administrador
-- activo. Nace Activa o Inactiva, y los datos del cierre (quién y cuándo)
-- los deja vacíos la base.
CREATE TRIGGER tr_convocatoria_validar_insercion
BEFORE INSERT ON convocatorias
FOR EACH ROW
BEGIN
  CALL pr_exigir_administrador('crear una convocatoria');
  IF NEW.estado NOT IN ('Activa', 'Inactiva') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una convocatoria nueva debe estar Activa o Inactiva';
  END IF;
  SET NEW.usuario_cierre    = NULL;
  SET NEW.fecha_cierre_real = NULL;
END//

-- Todo cambio de una convocatoria lo hace un administrador activo (sus
-- valores se copian a las asignaciones al aprobar). Los cupos no pueden
-- quedar por debajo de las postulaciones ya aprobadas. Quién la cerró y
-- cuándo los anota la base al pasar a Cerrada o Cierre anticipado (usuario
-- de sesión y hora real; sin sesión, solo root, se respeta el autor
-- recibido) y los vacía al reabrir; fuera de eso no se escriben a mano.
CREATE TRIGGER tr_convocatoria_validar
BEFORE UPDATE ON convocatorias
FOR EACH ROW
BEGIN
  DECLARE v_aprobadas INT;
  DECLARE v_mensaje   VARCHAR(128);
  DECLARE v_cierra    TINYINT(1);
  DECLARE v_reabre    TINYINT(1);

  CALL pr_exigir_administrador('modificar una convocatoria');

  SET v_cierra = NEW.estado IN ('Cerrada', 'Cierre anticipado')
                 AND OLD.estado NOT IN ('Cerrada', 'Cierre anticipado');
  SET v_reabre = OLD.estado IN ('Cerrada', 'Cierre anticipado')
                 AND NEW.estado NOT IN ('Cerrada', 'Cierre anticipado');

  IF NOT v_cierra AND NOT v_reabre
     AND (NOT (OLD.usuario_cierre <=> NEW.usuario_cierre)
          OR NOT (OLD.fecha_cierre_real <=> NEW.fecha_cierre_real)) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Los datos del cierre de la convocatoria los registra la base de datos; no se pueden modificar';
  END IF;

  IF v_cierra THEN
    IF @app_id_usuario IS NOT NULL THEN
      SET NEW.usuario_cierre = @app_id_usuario;
    END IF;
    SET NEW.fecha_cierre_real = SYSDATE();
  ELSEIF v_reabre THEN
    SET NEW.usuario_cierre    = NULL;
    SET NEW.fecha_cierre_real = NULL;
  END IF;

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
-- La crea el propio aprendiz a su nombre o un administrador activo (ronda
-- 11, D19): un aprendiz no postula a otro.
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

  CALL pr_exigir_titular_o_administrador(NEW.id_usuario, 'crear esta postulación');

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
--   * guarda quién validó / aprobó y cuándo (D2, D9);
--   * un cambio que no es de estado (formulario, observaciones) lo hace
--     el aprendiz dueño o un administrador activo (ronda 11, D19); el de
--     estado exige administrador más abajo.
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

  IF OLD.estado <=> NEW.estado THEN
    CALL pr_exigir_titular_o_administrador(OLD.id_usuario, 'modificar esta postulación');
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

    CALL pr_exigir_administrador(CASE NEW.estado
                                   WHEN 'Validada'  THEN 'validar una postulación'
                                   WHEN 'Aprobada'  THEN 'aprobar una postulación'
                                   WHEN 'Rechazada' THEN 'rechazar una postulación'
                                   ELSE 'cambiar el estado de una postulación'
                                 END);

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
    SELECT NEW.id_postulacion, c.valor_pago, c.numero_pagos, c.duracion_meses,
           GREATEST(CURDATE(), c.fecha_inicio), @app_id_usuario
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

  CALL pr_exigir_administrador('pedir documentación adicional');

  SELECT estado INTO v_estado
    FROM postulaciones
   WHERE id_postulacion = NEW.id_postulacion;

  IF v_estado <> 'Pendiente' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Solo se puede solicitar documentación de una postulación Pendiente';
  END IF;

  -- Una solicitud nace Pendiente, sin fecha de atención, a nombre del
  -- usuario de sesión (sin sesión, solo root, se respeta el recibido).
  IF NOT (NEW.estado <=> 'Pendiente') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una solicitud nueva debe empezar en estado Pendiente';
  END IF;
  SET NEW.fecha_atencion  = NULL;
  SET NEW.fecha_solicitud = SYSDATE();
  IF @app_id_usuario IS NOT NULL THEN
    SET NEW.id_usuario = @app_id_usuario;
  END IF;
END//

-- Cambios de una solicitud de documentación (ronda 10):
--   * lo que se pidió, a quién, quién lo pidió y cuándo no cambia;
--   * solo Pendiente -> Atendida o Pendiente -> Cerrada;
--   * Atendida: la marca el aprendiz dueño de la postulación (al subir lo
--     pedido) o un administrador; la fecha de atención es la hora real;
--   * Cerrada (ya no hace falta): solo un administrador; sin fecha de
--     atención, porque nadie la atendió.
CREATE TRIGGER tr_solicitud_documentacion_actualizar
BEFORE UPDATE ON solicitudes_documentacion
FOR EACH ROW
BEGIN
  IF NOT (OLD.id_solicitud    <=> NEW.id_solicitud)
     OR NOT (OLD.id_postulacion  <=> NEW.id_postulacion)
     OR NOT (OLD.id_usuario      <=> NEW.id_usuario)
     OR NOT (OLD.fecha_solicitud <=> NEW.fecha_solicitud)
     OR NOT (OLD.observacion     <=> NEW.observacion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Los datos de la solicitud no se pueden modificar';
  END IF;

  IF OLD.estado <=> NEW.estado THEN
    IF NOT (OLD.fecha_atencion <=> NEW.fecha_atencion) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha de atención la registra la base de datos; no se puede modificar';
    END IF;
  ELSE
    IF NOT (OLD.estado = 'Pendiente' AND NEW.estado IN ('Atendida', 'Cerrada')) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Una solicitud solo puede pasar de Pendiente a Atendida o a Cerrada';
    END IF;
    IF NEW.estado = 'Atendida' THEN
      CALL pr_exigir_dueno_o_administrador(OLD.id_postulacion, 'atender una solicitud de documentación');
      SET NEW.fecha_atencion = SYSDATE();
    ELSE
      CALL pr_exigir_administrador('cerrar una solicitud de documentación');
      SET NEW.fecha_atencion = NULL;
    END IF;
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
  DECLARE v_estado      VARCHAR(20);
  DECLARE v_inicio_conv DATE;
  DECLARE v_mensaje     VARCHAR(128);

  CALL pr_exigir_administrador('crear una asignación');

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

  -- fecha_inicio razonable: desde el inicio de la convocatoria hasta un año
  -- después de hoy.
  SELECT c.fecha_inicio INTO v_inicio_conv
    FROM postulaciones p
    JOIN convocatorias c ON c.id_convocatoria = p.id_convocatoria
   WHERE p.id_postulacion = NEW.id_postulacion;

  IF NEW.fecha_inicio < v_inicio_conv OR NEW.fecha_inicio > CURDATE() + INTERVAL 1 YEAR THEN
    SET v_mensaje = CONCAT('La fecha de inicio de la asignación debe estar entre el inicio de la convocatoria (',
                           DATE_FORMAT(v_inicio_conv, '%d/%m/%Y'), ') y un año después de hoy');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
  END IF;

  IF NOT (NEW.estado <=> 'En curso') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una asignación nueva debe iniciar en estado En curso';
  END IF;

  -- Con usuario de sesión, el registro es suyo; sin sesión (solo root) se
  -- respeta el valor recibido.
  IF @app_id_usuario IS NOT NULL THEN
    SET NEW.id_usuario_registro = @app_id_usuario;
  END IF;
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
  DECLARE v_inicio_conv DATE;

  -- La autorización de reapertura se consume al empezar: vale para un solo
  -- cambio aunque luego este trigger lance un error.
  SET v_reapertura = (@_br_reapertura <=> NEW.id_asignacion);
  SET @_br_reapertura = NULL;

  CALL pr_exigir_administrador(CASE
                                 WHEN OLD.estado <=> NEW.estado     THEN 'modificar una asignación'
                                 WHEN NEW.estado = 'Suspendido'     THEN 'suspender una asignación'
                                 WHEN NEW.estado = 'Cancelado'      THEN 'cancelar una asignación'
                                 WHEN OLD.estado = 'Suspendido'     THEN 'reactivar una asignación'
                                 ELSE 'cambiar el estado de una asignación'
                               END);

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
  IF NOT (OLD.id_usuario_registro <=> NEW.id_usuario_registro) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El usuario que registró la asignación no se puede modificar';
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

  -- fecha_inicio razonable: desde el inicio de la convocatoria hasta un año
  -- después de hoy (evita, por ejemplo, poner 1900 y pagar en 1900).
  IF NOT (OLD.fecha_inicio <=> NEW.fecha_inicio) THEN
    SELECT c.fecha_inicio INTO v_inicio_conv
      FROM postulaciones p
      JOIN convocatorias c ON c.id_convocatoria = p.id_convocatoria
     WHERE p.id_postulacion = NEW.id_postulacion;

    IF NEW.fecha_inicio < v_inicio_conv OR NEW.fecha_inicio > CURDATE() + INTERVAL 1 YEAR THEN
      SET v_mensaje = CONCAT('La fecha de inicio de la asignación debe estar entre el inicio de la convocatoria (',
                             DATE_FORMAT(v_inicio_conv, '%d/%m/%Y'), ') y un año después de hoy');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
    END IF;
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

  CALL pr_exigir_administrador('registrar un pago');

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

  -- Quién y cuándo los pone la base (ronda 9): con usuario de sesión,
  -- registra ese usuario (se ignora el valor recibido); sin sesión (solo
  -- root, mantenimiento) se respeta el recibido. Un pago nuevo no trae
  -- datos de anulación.
  IF @app_id_usuario IS NOT NULL THEN
    SET NEW.id_usuario_registro = @app_id_usuario;
  END IF;
  SET NEW.fecha_registro       = SYSDATE();
  SET NEW.motivo_anulacion     = NULL;
  SET NEW.fecha_anulacion      = NULL;
  SET NEW.id_usuario_anulacion = NULL;
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
  CALL pr_exigir_administrador('anular un pago');

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
  CALL pr_exigir_administrador('registrar una novedad');

  IF NEW.descripcion IS NULL OR TRIM(NEW.descripcion) = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La descripción de la novedad es obligatoria';
  END IF;

  -- Con usuario de sesión, la novedad es suya; sin sesión (solo root) se
  -- respeta el valor recibido. La fecha es la hora real.
  IF @app_id_usuario IS NOT NULL THEN
    SET NEW.id_usuario = @app_id_usuario;
  END IF;
  SET NEW.fecha = SYSDATE();
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
-- Notificaciones (ronda 11, D19)
-- ---------------------------------------------------------------------

-- Las notificaciones las crea el sistema (los triggers de postulaciones,
-- pagos y novedades, que ya corren con un administrador o con root sin
-- sesión) o un administrador activo. Un aprendiz no se inventa avisos.
CREATE TRIGGER tr_notificacion_crear BEFORE INSERT ON notificaciones FOR EACH ROW
  CALL pr_exigir_administrador('crear una notificación')//

-- Cambios de una notificación:
--   * marcarla como leída (leida, fecha_lectura): su titular o un
--     administrador activo; nunca otro aprendiz;
--   * el contenido (a quién, correo, evento, asunto, cuerpo, postulación,
--     fecha) y el envío del correo (estado_correo, fecha_envio): solo un
--     administrador activo (o root sin sesión).
CREATE TRIGGER tr_notificacion_modificar
BEFORE UPDATE ON notificaciones
FOR EACH ROW
BEGIN
  CALL pr_exigir_titular_o_administrador(OLD.id_usuario, 'modificar esta notificación');

  IF NOT (OLD.id_notificacion <=> NEW.id_notificacion)
     OR NOT (OLD.id_usuario     <=> NEW.id_usuario)
     OR NOT (OLD.destinatario   <=> NEW.destinatario)
     OR NOT (OLD.evento         <=> NEW.evento)
     OR NOT (OLD.asunto         <=> NEW.asunto)
     OR NOT (OLD.cuerpo         <=> NEW.cuerpo)
     OR NOT (OLD.id_postulacion <=> NEW.id_postulacion)
     OR NOT (OLD.fecha_creacion <=> NEW.fecha_creacion)
     OR NOT (OLD.estado_correo  <=> NEW.estado_correo)
     OR NOT (OLD.fecha_envio    <=> NEW.fecha_envio) THEN
    CALL pr_exigir_administrador('cambiar el contenido o el envío de una notificación');
  END IF;
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

-- Las fechas de una migración las pone la base con la hora real
-- (SYSDATE(), no se altera con SET timestamp): fecha_inicio al crearla y
-- fecha_fin si ya nace terminada.
-- Solo un administrador activo (o root sin sesión) registra una migración,
-- y con usuario de sesión la carga queda a su nombre (ronda 11, D19).
CREATE TRIGGER tr_migracion_preparar
BEFORE INSERT ON migraciones
FOR EACH ROW
BEGIN
  CALL pr_exigir_administrador('registrar una migración');
  IF @app_id_usuario IS NOT NULL THEN
    SET NEW.id_usuario = @app_id_usuario;
  END IF;
  SET NEW.fecha_inicio = SYSDATE();
  SET NEW.fecha_fin = IF(NEW.estado = 'En proceso', NULL, SYSDATE());
END//

-- Una migración terminada (Completada, Completada con errores o Fallida)
-- ya no se puede modificar: sus totales son el resultado oficial de la
-- carga. Mientras está En proceso se pueden actualizar los contadores; al
-- terminar, la base pone fecha_fin con la hora real. fecha_inicio no cambia.
-- Solo la modifica un administrador activo (o root sin sesión), y quién
-- hizo la carga no cambia (ronda 11, D19).
CREATE TRIGGER tr_migracion_validar
BEFORE UPDATE ON migraciones
FOR EACH ROW
BEGIN
  CALL pr_exigir_administrador('modificar una migración');
  IF NOT (OLD.id_usuario <=> NEW.id_usuario) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El usuario que hizo la migración no se puede modificar';
  END IF;
  IF NOT (OLD.estado <=> 'En proceso') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una migración terminada no se puede modificar';
  END IF;
  SET NEW.fecha_inicio = OLD.fecha_inicio;
  SET NEW.fecha_fin = IF(NEW.estado = 'En proceso', NULL, SYSDATE());
END//

-- Migraciones desde Excel (RI-4.3): registrar o iniciar una carga deja su
-- huella en la auditoría.
CREATE TRIGGER tr_migracion_creada
AFTER INSERT ON migraciones
FOR EACH ROW
BEGIN
  INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
  VALUES (@app_id_usuario,
          IF(NEW.estado = 'En proceso', 'INICIAR_MIGRACION', 'REGISTRAR_MIGRACION'),
          'migraciones', NEW.id_migracion,
          JSON_OBJECT('id_usuario', NEW.id_usuario,
                      'nombre_archivo', NEW.nombre_archivo,
                      'entidad_destino', NEW.entidad_destino,
                      'estado', NEW.estado,
                      'total_registros', NEW.total_registros,
                      'registros_exitosos', NEW.registros_exitosos,
                      'registros_fallidos', NEW.registros_fallidos),
          @app_ip);
END//

-- Cada cambio de estado de una migración (por ejemplo, al terminar) queda
-- en la auditoría con los totales. Actualizar solo los contadores mientras
-- sigue En proceso no se audita, para no llenar la tabla fila a fila.
CREATE TRIGGER tr_migracion_actualizada
AFTER UPDATE ON migraciones
FOR EACH ROW
BEGIN
  IF NOT (OLD.estado <=> NEW.estado) THEN
    INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad, detalle, ip)
    VALUES (@app_id_usuario,
            IF(NEW.estado = 'En proceso', 'ACTUALIZAR_MIGRACION', 'FINALIZAR_MIGRACION'),
            'migraciones', NEW.id_migracion,
            JSON_OBJECT('estado_anterior', OLD.estado,
                        'estado_nuevo', NEW.estado,
                        'total_registros', NEW.total_registros,
                        'registros_exitosos', NEW.registros_exitosos,
                        'registros_fallidos', NEW.registros_fallidos,
                        'fecha_fin', NEW.fecha_fin),
            @app_ip);
  END IF;
END//

-- La fecha de cada fila de auditoría es la hora real del servidor
-- (SYSDATE()): el DEFAULT CURRENT_TIMESTAMP se puede falsear con
-- SET timestamp en la sesión.
CREATE TRIGGER tr_auditoria_fecha
BEFORE INSERT ON auditoria
FOR EACH ROW
BEGIN
  SET NEW.fecha = SYSDATE();
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
-- La semilla la carga root sin usuario de sesión (los catálogos exigen
-- administrador o root con la variable en NULL).
SET @app_id_usuario = NULL;

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
  (3, 'Documento adicional',    'Documento solicitado adicionalmente por el administrador'),
  (4, 'Certificado de EPS',     'Certificado de afiliación a la EPS del aprendiz');

INSERT INTO eps (nombre) VALUES
  ('Coosalud'),
  ('Famisanar'),
  ('Nueva EPS'),
  ('Salud Total'),
  ('Sanitas'),
  ('Savia Salud'),
  ('SURA');
