-- Copyright (C) 2026 Optica Module
-- Tablas para el modulo Optica

CREATE TABLE IF NOT EXISTS llx_optica_montura (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    ref VARCHAR(64) NOT NULL,
    label VARCHAR(255) NOT NULL,
    clase VARCHAR(1) NOT NULL DEFAULT 'A',
    marca VARCHAR(128) NULL,
    color VARCHAR(64) NULL,
    precio DOUBLE(24,8) NOT NULL DEFAULT 60.00000000,
    stock INTEGER NOT NULL DEFAULT 0,
    fk_product INTEGER NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    datec DATETIME NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_optica_montura_ref (entity, ref)
) ENGINE=innodb;
