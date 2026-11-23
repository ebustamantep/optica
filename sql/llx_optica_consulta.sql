CREATE TABLE IF NOT EXISTS llx_optica_consulta (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    ref VARCHAR(64) NOT NULL,
    fk_soc INTEGER NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    -- Ojo Derecho (OD)
    od_esf VARCHAR(16) NULL,
    od_cil VARCHAR(16) NULL,
    od_eje VARCHAR(16) NULL,
    od_add VARCHAR(16) NULL,
    od_av VARCHAR(32) NULL,
    od_dnp VARCHAR(16) NULL,
    -- Ojo Izquierdo (OI)
    oi_esf VARCHAR(16) NULL,
    oi_cil VARCHAR(16) NULL,
    oi_eje VARCHAR(16) NULL,
    oi_add VARCHAR(16) NULL,
    oi_av VARCHAR(32) NULL,
    oi_dnp VARCHAR(16) NULL,
    -- Generales opticos
    dp VARCHAR(16) NULL,
    altura VARCHAR(16) NULL,
    observaciones TEXT NULL,
    -- Montura (con stock)
    fk_montura INTEGER NULL,
    precio_montura DOUBLE(24,8) NOT NULL DEFAULT 0,
    -- Cristales (servicio en Dolibarr sin stock, fabricacion bajo pedido)
    fk_cristal_product INTEGER NULL,
    tipo_cristal VARCHAR(128) NULL,
    descripcion_cristal TEXT NULL,
    precio_cristales DOUBLE(24,8) NOT NULL DEFAULT 0,
    -- Totales y estado
    total DOUBLE(24,8) NOT NULL DEFAULT 0,
    abono DOUBLE(24,8) NOT NULL DEFAULT 0,
    saldo DOUBLE(24,8) NOT NULL DEFAULT 0,
    estado TINYINT(1) NOT NULL DEFAULT 0,
    entregado TINYINT(1) NOT NULL DEFAULT 0,
    stock_descontado TINYINT(1) NOT NULL DEFAULT 0,
    fk_facture INTEGER NULL,
    fecha_entrega DATETIME NULL,
    fk_user_author INTEGER NULL,
    datec DATETIME NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_optica_consulta_ref (entity, ref),
    KEY idx_optica_consulta_soc (fk_soc),
    KEY idx_optica_consulta_montura (fk_montura)
) ENGINE=innodb;
