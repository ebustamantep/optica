CREATE TABLE IF NOT EXISTS llx_optica_abono (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    fk_consulta INTEGER NOT NULL,
    fecha_pago DATETIME NOT NULL,
    monto DOUBLE(24,8) NOT NULL DEFAULT 0,
    currency_code VARCHAR(8) NULL DEFAULT 'USD',
    tasa DOUBLE(24,8) NOT NULL DEFAULT 1,
    monto_moneda DOUBLE(24,8) NOT NULL DEFAULT 0,
    metodo_pago VARCHAR(64) NULL DEFAULT 'Efectivo',
    fk_bank_account INTEGER NULL,
    fk_bank INTEGER NULL,
    nota VARCHAR(255) NULL,
    fk_user INTEGER NULL,
    datec DATETIME NULL,
    KEY idx_optica_abono_consulta (fk_consulta)
) ENGINE=innodb;
