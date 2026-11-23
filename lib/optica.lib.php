<?php
/* Copyright (C) 2026 Optica Module */

/**
 * Clases de monturas y sus precios fijos por defecto (IVA 16% incluido)
 */
function optica_get_clases_montura()
{
	return array(
		'A' => array('label' => 'Clase A ($60)', 'precio' => 60.00),
		'B' => array('label' => 'Clase B ($50)', 'precio' => 50.00),
		'C' => array('label' => 'Clase C ($40)', 'precio' => 40.00),
		'D' => array('label' => 'Clase D ($30)', 'precio' => 30.00),
	);
}

/**
 * Estados de una consulta óptica
 */
function optica_get_estados()
{
	return array(
		0 => 'En Consulta / Pendiente',
		1 => 'En Laboratorio (Cristales)',
		2 => 'Listo para Entrega',
		3 => 'Entregado',
	);
}

/**
 * Asegura columnas nuevas si las tablas ya existían de una versión previa
 */
function optica_ensure_schema($db)
{
	static $done = false;
	if ($done) return;
	$done = true;

	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_montura ADD COLUMN fk_product INTEGER NULL AFTER stock");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_consulta ADD COLUMN entregado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_consulta ADD COLUMN stock_descontado TINYINT(1) NOT NULL DEFAULT 0 AFTER entregado");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_abono ADD COLUMN currency_code VARCHAR(8) NULL DEFAULT 'USD' AFTER monto");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_abono ADD COLUMN tasa DOUBLE(24,8) NOT NULL DEFAULT 1 AFTER currency_code");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_abono ADD COLUMN monto_moneda DOUBLE(24,8) NOT NULL DEFAULT 0 AFTER tasa");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_abono ADD COLUMN fk_bank_account INTEGER NULL AFTER metodo_pago");
	$db->query("ALTER TABLE ".MAIN_DB_PREFIX."optica_abono ADD COLUMN fk_bank INTEGER NULL AFTER fk_bank_account");
}

/**
 * Genera la siguiente referencia de consulta (ej. OPT-2026-0001)
 */
function optica_next_ref($db, $entity = 1)
{
	$year = date('Y');
	$prefix = 'OPT-'.$year.'-';
	$sql = "SELECT MAX(ref) as maxref FROM ".MAIN_DB_PREFIX."optica_consulta WHERE entity = ".((int) $entity)." AND ref LIKE '".$db->escape($prefix)."%'";
	$res = $db->query($sql);
	$num = 1;
	if ($res && ($obj = $db->fetch_object($res)) && !empty($obj->maxref)) {
		$parts = explode('-', $obj->maxref);
		$last = (int) end($parts);
		$num = $last + 1;
	}
	return $prefix.str_pad((string) $num, 4, '0', STR_PAD_LEFT);
}

/**
 * Obtiene las monedas activas y sus tasas desde llx_multicurrency
 */
function optica_get_active_currencies($db)
{
	global $conf;
	$baseCur = $conf->currency ?: 'USD';
	$monedas = array($baseCur => 1.0);
	$sqlMc = "SELECT m.code, (SELECT r.rate FROM ".MAIN_DB_PREFIX."multicurrency_rate r WHERE r.fk_multicurrency = m.rowid ORDER BY r.date_sync DESC, r.rowid DESC LIMIT 1) as rate";
	$sqlMc .= " FROM ".MAIN_DB_PREFIX."multicurrency m WHERE m.entity = ".((int) $conf->entity);
	$resMc = $db->query($sqlMc);
	if ($resMc) {
		while ($mc = $db->fetch_object($resMc)) {
			$monedas[$mc->code] = ((float) $mc->rate > 0) ? (float) $mc->rate : 1.0;
		}
	}
	return $monedas;
}

/**
 * Obtiene la última tasa de cambio de una moneda en Dolibarr
 */
function optica_get_currency_rate($db, $currency_code, $fallback = 1.0)
{
	global $conf;
	$baseCur = $conf->currency ?: 'USD';
	if ($currency_code === $baseCur) return 1.0;

	$sqlRate = "SELECT r.rate FROM ".MAIN_DB_PREFIX."multicurrency m INNER JOIN ".MAIN_DB_PREFIX."multicurrency_rate r ON r.fk_multicurrency = m.rowid WHERE m.code = '".$db->escape($currency_code)."' AND m.entity = ".((int) $conf->entity)." ORDER BY r.date_sync DESC, r.rowid DESC LIMIT 1";
	$resRate = $db->query($sqlRate);
	if ($resRate && ($objR = $db->fetch_object($resRate)) && (float) $objR->rate > 0) {
		return (float) $objR->rate;
	}
	return ($fallback > 0) ? (float) $fallback : 1.0;
}

/**
 * Convierte un pago hacia la moneda original de una cuenta bancaria
 */
function optica_convert_to_account_currency($db, $monto_usd, $currency_code, $tasa_pago, $monto_moneda, $acct_currency)
{
	global $conf;
	$baseCur = $conf->currency ?: 'USD';
	$acctCur = $acct_currency ?: $baseCur;

	if ($acctCur === $currency_code) {
		return round((float) $monto_moneda, 2);
	}
	if ($acctCur === $baseCur) {
		return round((float) $monto_usd, 2);
	}
	$tasaCuenta = optica_get_currency_rate($db, $acctCur, ($tasa_pago > 1 ? $tasa_pago : 1.0));
	return round((float) $monto_usd * $tasaCuenta, 2);
}

/**
 * Obtiene o crea el único almacén por defecto para monturas
 */
function optica_get_default_warehouse($db, $user)
{
	global $conf;
	if (!file_exists(DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php')) return 0;
	require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

	$resEnt = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE entity = ".((int) $conf->entity)." AND statut = 1 ORDER BY rowid ASC LIMIT 1");
	if ($resEnt && ($objE = $db->fetch_object($resEnt))) {
		return (int) $objE->rowid;
	}
	$ent = new Entrepot($db);
	$ent->libelle = 'Almacén Óptica';
	$ent->statut = 1;
	return (int) $ent->create($user);
}

/**
 * Ajusta el stock real de un producto en el almacén único de Dolibarr
 */
function optica_adjust_product_stock($db, $user, $fk_product, $delta, $label = 'Movimiento desde módulo Óptica')
{
	if ($fk_product <= 0 || $delta == 0) return false;
	if (!file_exists(DOL_DOCUMENT_ROOT.'/product/class/product.class.php')) return false;
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

	$entrepot_id = optica_get_default_warehouse($db, $user);
	if ($entrepot_id <= 0) return false;

	$prod = new Product($db);
	if ($prod->fetch($fk_product) <= 0) return false;

	$movement = ($delta > 0) ? 0 : 1; // 0 = añadir, 1 = descontar
	$qty = abs((int) $delta);
	$res = $prod->correct_stock($user, $entrepot_id, $qty, $movement, $label);
	return ($res >= 0);
}

/**
 * Sincroniza una montura de óptica con un Producto nativo de Dolibarr (IVA 16% incluido)
 */
function optica_sync_product($db, $user, $ref, $label, $clase, $precio_ttc, $stock = 0)
{
	global $conf;

	if (!file_exists(DOL_DOCUMENT_ROOT.'/product/class/product.class.php')) {
		return 0;
	}
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

	$prod = new Product($db);
	if ($prod->fetch(0, $ref) > 0) {
		return (int) $prod->id;
	}

	$prod->ref = $ref;
	$prod->label = $label.' (Montura Clase '.$clase.')';
	$prod->description = 'Montura óptica Clase '.$clase;
	$prod->type = Product::TYPE_PRODUCT;
	$prod->tva_tx = 16;
	$prod->price_base_type = 'TTC';
	$prod->price_ttc = (float) $precio_ttc;
	$prod->price = round((float) $precio_ttc / 1.16, 8);
	$prod->status = 1;
	$prod->status_buy = 1;

	$pid = $prod->create($user);
	if ($pid > 0) {
		if ((int) $stock > 0) {
			optica_adjust_product_stock($db, $user, (int) $pid, (int) $stock, 'Stock inicial desde módulo Óptica');
		}
		return (int) $pid;
	}
	return 0;
}

/**
 * Crea o vincula un pago en Financiera de Dolibarr (llx_paiement) sin duplicar movimientos en llx_bank
 */
function optica_registrar_pago_factura($db, $user, $fk_facture, $monto_usd, $metodo_pago = 'Efectivo', $fk_bank_account = 0, $fecha = 0, $nota = '', $existing_fk_bank = 0, $monto_banco = 0)
{
	if ($fk_facture <= 0 || $monto_usd <= 0) return array('pid' => 0, 'fk_bank' => (int) $existing_fk_bank);
	if (!file_exists(DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php')) return array('pid' => 0, 'fk_bank' => (int) $existing_fk_bank);

	require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

	$fac = new Facture($db);
	if ($fac->fetch($fk_facture) <= 0) return array('pid' => 0, 'fk_bank' => (int) $existing_fk_bank);
	$fac->fetch_thirdparty();

	$payCode = ($metodo_pago === 'Tarjeta') ? 'CB' : (($metodo_pago === 'Transferencia') ? 'VIR' : 'LIQ');
	$paiement = new Paiement($db);
	$paiement->datepaye = $fecha > 0 ? $fecha : dol_now();
	$paiement->amounts = array($fk_facture => (float) $monto_usd);
	$paiement->paiementid = dol_getIdFromCode($db, $payCode, 'c_paiement', 'code', 'id', 1);
	$paiement->paiementcode = $payCode;
	$paiement->note_private = $nota ?: 'Abono desde módulo Óptica';

	$pid = $paiement->create($user, 1);
	$fk_bank = (int) $existing_fk_bank;

	if ($pid > 0) {
		if ($fk_bank_account > 0) {
			$acct = new Account($db);
			if ($acct->fetch($fk_bank_account) > 0) {
				// Si aún no existía línea bancaria (pago hecho después de facturar), crearla en la moneda original de la cuenta
				if ($fk_bank <= 0) {
					$amtBank = ($monto_banco > 0) ? (float) $monto_banco : (float) $monto_usd;
					$fk_bank = (int) $acct->addline($paiement->datepaye, $payCode, '(CustomerInvoicePayment) '.$fac->ref.($nota ? ' - '.$nota : ''), $amtBank, '', 0, $user, '', '', '', 0, '', (float) $monto_usd);
				}
				// Vincular el Paiement con la línea bancaria en llx_paiement y llx_bank_url sin duplicar llx_bank
				if ($fk_bank > 0) {
					$paiement->update_fk_bank($fk_bank);
					$acct->add_url_line($fk_bank, $pid, DOL_URL_ROOT.'/compta/paiement/card.php?id=', '(paiement)', 'payment');
					if (!empty($fac->thirdparty->id)) {
						$acct->add_url_line($fk_bank, $fac->thirdparty->id, DOL_URL_ROOT.'/comm/card.php?socid=', (string) $fac->thirdparty->name, 'company');
					}
				}
			}
		}

		// Marcar la factura como Pagada si la suma de pagos cubre el total TTC
		$resSum = $db->query("SELECT SUM(amount) as paid FROM ".MAIN_DB_PREFIX."paiement_facture WHERE fk_facture = ".((int) $fk_facture));
		if ($resSum && ($objS = $db->fetch_object($resSum)) && (float) $objS->paid >= ((float) $fac->total_ttc - 0.01)) {
			$fac->setPaid($user);
		}
	}

	return array('pid' => (int) $pid, 'fk_bank' => $fk_bank);
}

/**
 * Genera y valida la factura en Dolibarr (en USD, con IVA 16% incluido) y vincula los abonos previos
 */
function optica_facturar_consulta($db, $user, $consulta_id)
{
	optica_ensure_schema($db);

	$sql = "SELECT c.*, m.ref as montura_ref, m.label as montura_label, m.clase as montura_clase, m.fk_product as montura_fk_product";
	$sql .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."optica_montura m ON m.rowid = c.fk_montura";
	$sql .= " WHERE c.rowid = ".((int) $consulta_id);

	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) == 0) {
		return array('ok' => false, 'fk_facture' => 0, 'error' => 'Consulta no encontrada.');
	}
	$c = $db->fetch_object($res);

	if ((int) $c->fk_facture > 0) {
		return array('ok' => true, 'fk_facture' => (int) $c->fk_facture, 'error' => '');
	}

	$fk_facture = 0;
	if (file_exists(DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php')) {
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$fac = new Facture($db);
		$fac->socid = (int) $c->fk_soc;
		$fac->date = dol_now();
		$fac->type = Facture::TYPE_STANDARD;
		$fac->note_public = 'Orden Óptica: '.$c->ref;
		$fac->note_private = 'Abonos previos registrados en óptica: $'.number_format((float) $c->abono, 2);

		$facid = $fac->create($user);
		if ($facid <= 0) {
			return array('ok' => false, 'fk_facture' => 0, 'error' => 'Error al crear factura: '.$fac->error);
		}

		// Montura con IVA 16% incluido (TTC) y vinculada a fk_product
		if (!empty($c->fk_montura) && (float) $c->precio_montura > 0) {
			$descMontura = 'Montura '.$c->montura_ref.' - '.$c->montura_label.' (Clase '.$c->montura_clase.')';
			$fac->addline($descMontura, 0, 1, 16, 0, 0, (int) $c->montura_fk_product, 0, '', '', 0, 0, '', 'TTC', (float) $c->precio_montura, 0);
		}
		// Cristales con IVA 16% incluido (TTC)
		if ((float) $c->precio_cristales > 0 || !empty($c->tipo_cristal)) {
			$descCristal = 'Cristales bajo pedido: '.($c->tipo_cristal ?: 'Ópticos');
			if (!empty($c->descripcion_cristal)) {
				$descCristal .= ' - '.$c->descripcion_cristal;
			}
			$fac->addline($descCristal, 0, 1, 16, 0, 0, 0, 0, '', '', 0, 0, '', 'TTC', (float) $c->precio_cristales, 1);
		}

		$fac->validate($user);
		$fk_facture = (int) $facid;
	}

	if ($fk_facture > 0) {
		$db->query("UPDATE ".MAIN_DB_PREFIX."optica_consulta SET fk_facture = ".((int) $fk_facture)." WHERE rowid = ".((int) $consulta_id));

		// Vincular en Financiera todos los abonos previos sin duplicar líneas en llx_bank
		$resAb = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."optica_abono WHERE fk_consulta = ".((int) $consulta_id)." ORDER BY rowid ASC");
		if ($resAb) {
			while ($ab = $db->fetch_object($resAb)) {
				$monto_banco = ($ab->monto_moneda > 0) ? (float) $ab->monto_moneda : (float) $ab->monto;
				optica_registrar_pago_factura($db, $user, $fk_facture, (float) $ab->monto, $ab->metodo_pago, (int) $ab->fk_bank_account, $db->jdate($ab->fecha_pago), $ab->nota, (int) $ab->fk_bank, $monto_banco);
			}
		}
	}

	return array('ok' => true, 'fk_facture' => $fk_facture, 'error' => '');
}

/**
 * Entrega físicamente la montura (descuenta stock de tabla y almacén único) y factura si aún no tiene factura
 */
function optica_entregar_y_facturar($db, $user, $consulta_id)
{
	optica_ensure_schema($db);

	$sql = "SELECT c.*, m.ref as montura_ref, m.clase as montura_clase, m.stock as montura_stock, m.fk_product as montura_fk_product";
	$sql .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."optica_montura m ON m.rowid = c.fk_montura";
	$sql .= " WHERE c.rowid = ".((int) $consulta_id);

	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) == 0) {
		return array('ok' => false, 'fk_facture' => 0, 'error' => 'Consulta no encontrada.');
	}
	$c = $db->fetch_object($res);

	// Descontar stock una sola vez al entregar físicamente
	if (!empty($c->fk_montura) && (int) $c->stock_descontado === 0) {
		if ((int) $c->montura_stock <= 0) {
			return array('ok' => false, 'fk_facture' => (int) $c->fk_facture, 'error' => 'No hay stock disponible para la montura '.$c->montura_ref.' (Clase '.$c->montura_clase.').');
		}
		$sqlStock = "UPDATE ".MAIN_DB_PREFIX."optica_montura SET stock = stock - 1 WHERE rowid = ".((int) $c->fk_montura)." AND stock > 0";
		if (!$db->query($sqlStock)) {
			return array('ok' => false, 'fk_facture' => (int) $c->fk_facture, 'error' => 'Error al descontar stock de montura: '.$db->lasterror());
		}
		if ((int) $c->montura_fk_product > 0) {
			optica_adjust_product_stock($db, $user, (int) $c->montura_fk_product, -1, 'Entrega Orden Óptica '.$c->ref);
		}
		$db->query("UPDATE ".MAIN_DB_PREFIX."optica_consulta SET stock_descontado = 1 WHERE rowid = ".((int) $consulta_id));
	}

	// Generar factura si aún no existe
	$fk_facture = (int) $c->fk_facture;
	if ($fk_facture <= 0) {
		$resFac = optica_facturar_consulta($db, $user, $consulta_id);
		if (!$resFac['ok']) {
			return $resFac;
		}
		$fk_facture = (int) $resFac['fk_facture'];
	}

	$sqlUpd = "UPDATE ".MAIN_DB_PREFIX."optica_consulta SET estado = 3, entregado = 1, fecha_entrega = '".$db->idate(dol_now())."' WHERE rowid = ".((int) $consulta_id);
	if (!$db->query($sqlUpd)) {
		return array('ok' => false, 'fk_facture' => $fk_facture, 'error' => 'Error al marcar entrega: '.$db->lasterror());
	}

	return array('ok' => true, 'fk_facture' => $fk_facture, 'error' => '');
}

/**
 * Registra un abono (inicial o adicional) con multimoneda, banco inmediato y facturación automática al completar el 100%
 */
function optica_registrar_abono($db, $user, $consulta_id, $monto_moneda, $currency_code, $tasa, $metodo, $fk_bank_account, $nota = '')
{
	global $conf;
	optica_ensure_schema($db);

	$baseCur = $conf->currency ?: 'USD';
	$currency_code = $currency_code ?: $baseCur;
	$tasa = ($currency_code === $baseCur) ? 1.0 : optica_get_currency_rate($db, $currency_code, $tasa);
	if ($tasa <= 0) $tasa = 1.0;

	$monto_moneda = (float) $monto_moneda;
	if ($monto_moneda <= 0) return array('ok' => false, 'abono_id' => 0, 'fk_facture' => 0, 'error' => 'Monto inválido.');

	$monto_usd = ($currency_code === $baseCur) ? $monto_moneda : round($monto_moneda / $tasa, 2);

	$resC = $db->query("SELECT rowid, ref, fk_soc, fk_facture, total, abono, saldo FROM ".MAIN_DB_PREFIX."optica_consulta WHERE rowid = ".((int) $consulta_id));
	if (!$resC || $db->num_rows($resC) == 0) {
		return array('ok' => false, 'abono_id' => 0, 'fk_facture' => 0, 'error' => 'Consulta no encontrada.');
	}
	$c = $db->fetch_object($resC);

	$fk_bank = 0;
	$monto_banco = $monto_moneda;

	if ($fk_bank_account > 0 && file_exists(DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php')) {
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$acct = new Account($db);
		if ($acct->fetch($fk_bank_account) > 0) {
			$monto_banco = optica_convert_to_account_currency($db, $monto_usd, $currency_code, $tasa, $monto_moneda, $acct->currency_code);

			if ((int) $c->fk_facture > 0) {
				// Ya existe factura: optica_registrar_pago_factura crea el Paiement + 1 sola línea bancaria
				$resPay = optica_registrar_pago_factura($db, $user, (int) $c->fk_facture, $monto_usd, $metodo, $fk_bank_account, dol_now(), $nota, 0, $monto_banco);
				$fk_bank = (int) $resPay['fk_bank'];
			} else {
				// Anticipo previo a factura: entra de inmediato al banco en la moneda de la cuenta
				$payCode = ($metodo === 'Tarjeta') ? 'CB' : (($metodo === 'Transferencia') ? 'VIR' : 'LIQ');
				$fk_bank = (int) $acct->addline(dol_now(), $payCode, 'Anticipo Óptica '.$c->ref.' ('.$currency_code.' '.number_format($monto_moneda, 2).')'.($nota ? ' - '.$nota : ''), $monto_banco, '', 0, $user, '', '', '', 0, '', (float) $monto_usd);
				if ($fk_bank > 0 && !empty($c->fk_soc)) {
					$resS = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".((int) $c->fk_soc));
					if ($resS && ($objS = $db->fetch_object($resS))) {
						$acct->add_url_line($fk_bank, (int) $c->fk_soc, DOL_URL_ROOT.'/comm/card.php?socid=', (string) $objS->nom, 'company');
					}
				}
			}
		}
	} elseif ((int) $c->fk_facture > 0) {
		optica_registrar_pago_factura($db, $user, (int) $c->fk_facture, $monto_usd, $metodo, 0, dol_now(), $nota);
	}

	$sqlAb = "INSERT INTO ".MAIN_DB_PREFIX."optica_abono (entity, fk_consulta, fecha_pago, monto, currency_code, tasa, monto_moneda, metodo_pago, fk_bank_account, fk_bank, nota, fk_user, datec) VALUES (";
	$sqlAb .= ((int) $conf->entity).", ".((int) $consulta_id).", '".$db->idate(dol_now())."', ".((float) $monto_usd).", '".$db->escape($currency_code)."', ".((float) $tasa).", ".((float) $monto_moneda).", '".$db->escape($metodo)."', ".($fk_bank_account > 0 ? (int) $fk_bank_account : "NULL").", ".($fk_bank > 0 ? (int) $fk_bank : "NULL").", '".$db->escape($nota)."', ".((int) $user->id).", '".$db->idate(dol_now())."')";
	$db->query($sqlAb);
	$new_abono_id = (int) $db->last_insert_id(MAIN_DB_PREFIX."optica_abono");

	$nuevo_abono = (float) $c->abono + $monto_usd;
	$nuevo_saldo = max(0, (float) $c->total - $nuevo_abono);
	$db->query("UPDATE ".MAIN_DB_PREFIX."optica_consulta SET abono = ".((float) $nuevo_abono).", saldo = ".((float) $nuevo_saldo)." WHERE rowid = ".((int) $consulta_id));

	$fk_facture = (int) $c->fk_facture;
	// Opción 1B: si completó el 100% del pago y aún no tiene factura, generar factura (sin marcar entregado hasta entrega física)
	if ($nuevo_saldo <= 0.01 && $fk_facture <= 0) {
		$resFac = optica_facturar_consulta($db, $user, $consulta_id);
		if ($resFac['ok']) {
			$fk_facture = (int) $resFac['fk_facture'];
		}
	}

	return array('ok' => true, 'abono_id' => $new_abono_id, 'fk_facture' => $fk_facture, 'saldo' => $nuevo_saldo, 'error' => '');
}
