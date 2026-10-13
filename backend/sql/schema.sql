-- Esquema de la app de deportistas Polideportivos (Malvinas Argentinas / Palmeras)
-- Idempotente: borra y recrea todas las tablas.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS asistencias;
DROP TABLE IF EXISTS inscripciones;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS actividades;
DROP TABLE IF EXISTS polideportivos;
DROP TABLE IF EXISTS calles;
DROP TABLE IF EXISTS sms_log;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS sesiones;
DROP TABLE IF EXISTS codigos_verificacion;
DROP TABLE IF EXISTS registros_pendientes;
DROP TABLE IF EXISTS deportistas;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE deportistas (
    dni                  VARCHAR(8)      NOT NULL,
    nombre               VARCHAR(80)     NOT NULL,
    apellido             VARCHAR(80)     NOT NULL,
    fecha_nacimiento     DATE            NULL,
    email                VARCHAR(120)    NULL,
    calle                VARCHAR(120)    NOT NULL,
    numero               VARCHAR(6)      NOT NULL,
    departamento         VARCHAR(20)     NULL,
    localidad            VARCHAR(80)     NOT NULL,
    celular              VARCHAR(15)     NOT NULL,
    celular_verificado   TINYINT(1)      NOT NULL DEFAULT 0,
    celular_verificado_at DATETIME       NULL,
    celular_nuevo        VARCHAR(15)     NULL,
    password_hash        VARCHAR(255)    NULL,
    foto                 VARCHAR(80)     NULL,
    origen               ENUM('csv','app') NOT NULL DEFAULT 'csv',
    estado               TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dni),
    INDEX idx_deportistas_celular (celular),
    INDEX idx_deportistas_origen (origen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registros_pendientes (
    dni               VARCHAR(8)   NOT NULL,
    nombre            VARCHAR(80)  NOT NULL,
    apellido          VARCHAR(80)  NOT NULL,
    fecha_nacimiento  DATE         NULL,
    email             VARCHAR(120) NULL,
    calle             VARCHAR(120) NOT NULL,
    numero            VARCHAR(6)   NOT NULL,
    departamento      VARCHAR(20)  NULL,
    localidad         VARCHAR(80)  NOT NULL,
    celular           VARCHAR(15)  NOT NULL,
    celular_verificado TINYINT(1)  NOT NULL DEFAULT 0,
    creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en         DATETIME     NOT NULL,
    PRIMARY KEY (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE codigos_verificacion (
    id         BIGINT       NOT NULL AUTO_INCREMENT,
    dni        VARCHAR(8)   NOT NULL,
    proposito  ENUM('registro','cambio_celular','recuperar_clave') NOT NULL,
    destino    VARCHAR(15)  NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_en  DATETIME     NOT NULL,
    intentos   INT          NOT NULL DEFAULT 0,
    usado_en   DATETIME     NULL,
    creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_codigos_dni_prop (dni, proposito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesiones (
    token_hash  CHAR(64)     NOT NULL,
    dni         VARCHAR(8)   NOT NULL,
    creada_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en   DATETIME     NOT NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    revocada_en DATETIME     NULL,
    PRIMARY KEY (token_hash),
    INDEX idx_sesiones_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polideportivos (
    id     INT         NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actividades (
    id_actividad INT         NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    cupos        INT         NOT NULL DEFAULT 0,
    estado       TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (id_actividad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
    id              INT          NOT NULL AUTO_INCREMENT,
    id_categoria    INT          NOT NULL,
    categoria       VARCHAR(80)  NOT NULL,
    dia             VARCHAR(20)  NOT NULL,
    hora_i          VARCHAR(8)   NOT NULL,
    hora_f          VARCHAR(8)   NOT NULL,
    profesor        VARCHAR(120) NULL,
    actividad_id    INT          NOT NULL,
    polideportivo_id INT         NOT NULL DEFAULT 5,
    estado          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    INDEX idx_categorias_id_categoria (id_categoria),
    INDEX idx_categorias_actividad (actividad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inscripciones (
    id_act_ins  INT        NOT NULL,
    dni         VARCHAR(8) NOT NULL,
    actividad_id INT       NOT NULL,
    categoria_id INT       NOT NULL,
    estado      TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta  DATETIME   NULL,
    PRIMARY KEY (id_act_ins),
    INDEX idx_inscripciones_dni (dni),
    INDEX idx_inscripciones_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencias (
    id          BIGINT      NOT NULL AUTO_INCREMENT,
    dni         VARCHAR(8)  NOT NULL,
    categoria_id INT        NOT NULL,
    fecha       DATE        NOT NULL,
    presente    TINYINT(1)  NOT NULL DEFAULT 0,
    tomada      TINYINT(1)  NOT NULL DEFAULT 1,
    observacion VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asistencia (dni, categoria_id, fecha),
    INDEX idx_asistencias_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calles (
    id    INT         NOT NULL AUTO_INCREMENT,
    calle VARCHAR(120) NOT NULL,
    codigo VARCHAR(20) NULL,
    PRIMARY KEY (id),
    INDEX idx_calles_nombre (calle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_log (
    id       BIGINT       NOT NULL AUTO_INCREMENT,
    destino  VARCHAR(15)  NOT NULL,
    proposito VARCHAR(30) NOT NULL,
    dni      VARCHAR(8)   NULL,
    mensaje  VARCHAR(255) NOT NULL,
    creado_en DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sms_log_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    llave    VARCHAR(120) NOT NULL,
    contador INT          NOT NULL DEFAULT 0,
    inicio   DATETIME     NOT NULL,
    PRIMARY KEY (llave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
