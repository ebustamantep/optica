<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "companies", "bills"));
if (!$user->hasRight('optica', 'read')) accessforbidden();

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
optica_ensure_schema($db);

// Guardar nueva consulta
if ($action === 'add' && $user->hasRight('optica', 'write')) {
	$fk_soc = GETPOSTINT('fk_soc');
	$fk_montura = GETPOSTINT('fk_montura');
	$precio_montura = (float) GETPOST('precio_montura', 'alphanohtml');
	$tipo_cristal = GETPOST('tipo_cristal', 'alphanohtml');
	$descripcion_cristal = GETPOST('descripcion_cristal', 'alphanohtml');
	$precio_cristales = (float) GETPOST('precio_cristales', 'alphanohtml');
	$abono_inicial = (float) GETPOST('abono_inicial', 'alphanohtml');
	$abono_currency = GETPOST('abono_currency', 'aZ09') ?: ($conf->currency ?: 'USD');
	$abono_tasa = (float) GETPOST('abono_tasa', 'alphanohtml');
	$abono_metodo = GETPOST('abono_metodo', 'alphanohtml') ?: 'Efectivo';
	$abono_bank_account = GETPOSTINT('abono_bank_account');

	$total = $precio_montura + $precio_cristales;
	$ref = optica_next_ref($db, $conf->entity);

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."optica_consulta (entity, ref, fk_soc, fecha_consulta,";
	$sql .= " od_esf, od_cil, od_eje, od_add, od_av, od_dnp,";
	$sql .= " oi_esf, oi_cil, oi_eje, oi_add, oi_av, oi_dnp,";
	$sql .= " dp, altura, observaciones, fk_montura, precio_montura,";
	$sql .= " tipo_cristal, descripcion_cristal, precio_cristales, total, abono, saldo, estado, entregado, stock_descontado, fk_user_author, datec) VALUES (";
	$sql .= ((int) $conf->entity).", '".$db->escape($ref)."', ".((int) $fk_soc).", '".$db->idate(dol_now())."',";
	$sql .= " '".$db->escape(GETPOST('od_esf', 'alphanohtml'))."', '".$db->escape(GETPOST('od_cil', 'alphanohtml'))."', '".$db->escape(GETPOST('od_eje', 'alphanohtml'))."', '".$db->escape(GETPOST('od_add', 'alphanohtml'))."', '".$db->escape(GETPOST('od_av', 'alphanohtml'))."', '".$db->escape(GETPOST('od_dnp', 'alphanohtml'))."',";
	$sql .= " '".$db->escape(GETPOST('oi_esf', 'alphanohtml'))."', '".$db->escape(GETPOST('oi_cil', 'alphanohtml'))."', '".$db->escape(GETPOST('oi_eje', 'alphanohtml'))."', '".$db->escape(GETPOST('oi_add', 'alphanohtml'))."', '".$db->escape(GETPOST('oi_av', 'alphanohtml'))."', '".$db->escape(GETPOST('oi_dnp', 'alphanohtml'))."',";
	$sql .= " '".$db->escape(GETPOST('dp', 'alphanohtml'))."', '".$db->escape(GETPOST('altura', 'alphanohtml'))."', '".$db->escape(GETPOST('observaciones', 'restricthtml'))."',";
	$sql .= " ".($fk_montura > 0 ? (int) $fk_montura : "NULL").", ".((float) $precio_montura).",";
	$sql .= " '".$db->escape($tipo_cristal)."', '".$db->escape($descripcion_cristal)."', ".((float) $precio_cristales).",";
	$sql .= " ".((float) $total).", 0, ".((float) $total).", 0, 0, 0, ".((int) $user->id).", '".$db->idate(dol_now())."')";

	if ($db->query($sql)) {
		$newid = $db->last_insert_id(MAIN_DB_PREFIX."optica_consulta");
		if ($abono_inicial > 0) {
			$resAb = optica_registrar_abono($db, $user, $newid, $abono_inicial, $abono_currency, $abono_tasa, $abono_metodo, $abono_bank_account, 'Abono inicial en consulta');
			if ($resAb['ok'] && $resAb['abono_id'] > 0) {
				setEventMessages('Consulta creada y abono registrado. <a href="'.dol_buildpath('/optica/abono_ticket.php?id='.$resAb['abono_id'], 1).'" onclick="return opticaPrintPopup(this.href, \'Ticket de Abono\');"><strong>Imprimir Ticket Abono</strong></a>', null, 'mesgs');
			}
		}
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$newid);
		exit;
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
}

// Actualizar montura y cristales en consulta existente
if ($action === 'update_montura' && $id > 0 && $user->hasRight('optica', 'write')) {
	$fk_montura = GETPOSTINT('fk_montura');
	$precio_montura = (float) GETPOST('precio_montura', 'alphanohtml');
	$tipo_cristal = GETPOST('tipo_cristal', 'alphanohtml');
	$descripcion_cristal = GETPOST('descripcion_cristal', 'alphanohtml');
	$precio_cristales = (float) GETPOST('precio_cristales', 'alphanohtml');

	$total = $precio_montura + $precio_cristales;
	$sqlUpdM = "UPDATE ".MAIN_DB_PREFIX."optica_consulta SET ";
	$sqlUpdM .= "fk_montura = ".($fk_montura > 0 ? (int) $fk_montura : "NULL").", ";
	$sqlUpdM .= "precio_montura = ".((float) $precio_montura).", ";
	$sqlUpdM .= "tipo_cristal = '".$db->escape($tipo_cristal)."', ";
	$sqlUpdM .= "descripcion_cristal = '".$db->escape($descripcion_cristal)."', ";
	$sqlUpdM .= "precio_cristales = ".((float) $precio_cristales).", ";
	$sqlUpdM .= "total = ".((float) $total).", ";
	$sqlUpdM .= "saldo = GREATEST(0, ".((float) $total)." - abono) ";
	$sqlUpdM .= "WHERE rowid = ".((int) $id)." AND estado < 3";
	if ($db->query($sqlUpdM)) {
		setEventMessages('Montura y cristales actualizados.', null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
	exit;
}

// Actualizar datos ópticos (RX)
if ($action === 'update_rx' && $id > 0 && $user->hasRight('optica', 'write')) {
	$sqlRx = "UPDATE ".MAIN_DB_PREFIX."optica_consulta SET ";
	$sqlRx .= "od_esf = '".$db->escape(GETPOST('od_esf', 'alphanohtml'))."', ";
	$sqlRx .= "od_cil = '".$db->escape(GETPOST('od_cil', 'alphanohtml'))."', ";
	$sqlRx .= "od_eje = '".$db->escape(GETPOST('od_eje', 'alphanohtml'))."', ";
	$sqlRx .= "od_add = '".$db->escape(GETPOST('od_add', 'alphanohtml'))."', ";
	$sqlRx .= "od_av = '".$db->escape(GETPOST('od_av', 'alphanohtml'))."', ";
	$sqlRx .= "od_dnp = '".$db->escape(GETPOST('od_dnp', 'alphanohtml'))."', ";
	$sqlRx .= "oi_esf = '".$db->escape(GETPOST('oi_esf', 'alphanohtml'))."', ";
	$sqlRx .= "oi_cil = '".$db->escape(GETPOST('oi_cil', 'alphanohtml'))."', ";
	$sqlRx .= "oi_eje = '".$db->escape(GETPOST('oi_eje', 'alphanohtml'))."', ";
	$sqlRx .= "oi_add = '".$db->escape(GETPOST('oi_add', 'alphanohtml'))."', ";
	$sqlRx .= "oi_av = '".$db->escape(GETPOST('oi_av', 'alphanohtml'))."', ";
	$sqlRx .= "oi_dnp = '".$db->escape(GETPOST('oi_dnp', 'alphanohtml'))."', ";
	$sqlRx .= "dp = '".$db->escape(GETPOST('dp', 'alphanohtml'))."', ";
	$sqlRx .= "altura = '".$db->escape(GETPOST('altura', 'alphanohtml'))."', ";
	$sqlRx .= "observaciones = '".$db->escape(GETPOST('observaciones', 'restricthtml'))."' ";
	$sqlRx .= "WHERE rowid = ".((int) $id);
	if ($db->query($sqlRx)) {
		setEventMessages('Datos ópticos actualizados correctamente.', null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
	exit;
}

// Registrar abono adicional (usando helper unificado sin duplicar banco)
if ($action === 'add_abono' && $id > 0 && $user->hasRight('optica', 'write')) {
	$monto_moneda = (float) GETPOST('monto_abono', 'alphanohtml');
	$currency_code = GETPOST('currency_code', 'aZ09') ?: ($conf->currency ?: 'USD');
	$tasa = (float) GETPOST('tasa', 'alphanohtml');
	$metodo = GETPOST('metodo_pago', 'alphanohtml') ?: 'Efectivo';
	$fk_bank_account = GETPOSTINT('fk_bank_account');
	$nota = GETPOST('nota_abono', 'alphanohtml');

	$resAb = optica_registrar_abono($db, $user, $id, $monto_moneda, $currency_code, $tasa, $metodo, $fk_bank_account, $nota);
	if ($resAb['ok']) {
		$msg = 'Abono registrado. <a href="'.dol_buildpath('/optica/abono_ticket.php?id='.$resAb['abono_id'], 1).'" onclick="return opticaPrintPopup(this.href, \'Ticket de Abono\');"><strong>Imprimir Ticket Abono</strong></a>';
		if ((float) $resAb['saldo'] <= 0.01 && (int) $resAb['fk_facture'] > 0) {
			$msg .= ' | Pago completo: Factura #'.$resAb['fk_facture'].' generada y pagada. <a href="'.dol_buildpath('/optica/factura_ticket.php?id='.$id, 1).'" onclick="return opticaPrintPopup(this.href, \'Factura Ticket\');"><strong>Imprimir Factura Ticket</strong></a>';
		}
		setEventMessages($msg, null, 'mesgs');
	} else {
		setEventMessages($resAb['error'], null, 'errors');
	}
	header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
	exit;
}

// Cambiar estado (Laboratorio / Listo)
if ($action === 'set_estado' && $id > 0 && $user->hasRight('optica', 'write')) {
	$nuevo_estado = GETPOSTINT('estado');
	if (in_array($nuevo_estado, array(0, 1, 2), true)) {
		$db->query("UPDATE ".MAIN_DB_PREFIX."optica_consulta SET estado = ".((int) $nuevo_estado)." WHERE rowid = ".((int) $id));
		setEventMessages('Estado actualizado.', null, 'mesgs');
	}
	header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
	exit;
}

// Entregar montura y facturar
if ($action === 'entregar_facturar' && $id > 0 && $user->hasRight('optica', 'write')) {
	$resFac = optica_entregar_y_facturar($db, $user, $id);
	if ($resFac['ok']) {
		$msg = 'Montura entregada (stock descontado)';
		if ($resFac['fk_facture'] > 0) {
			$msg .= ' y Factura #'.$resFac['fk_facture'].' generada.';
		}
		setEventMessages($msg, null, 'mesgs');
	} else {
		setEventMessages($resFac['error'], null, 'errors');
	}
	header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
	exit;
}

$arrayofjs = array('/takepos/js/jquery.colorbox-min.js', '/optica/js/optica.js');
$arrayofcss = array('/takepos/css/colorbox.css');
llxHeader('', 'Óptica - Ficha de Consulta', '', '', 0, 0, $arrayofjs, $arrayofcss);

if ($action === 'create') {
	print load_fiche_titre('Nueva Consulta Óptica', '', 'fa-eye');
	$fk_soc_pre = GETPOSTINT('fk_soc');

	// Cargar clientes
	$clientes = array();
	$resSoc = $db->query("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (".getEntity('societe').") ORDER BY nom ASC LIMIT 200");
	if ($resSoc) {
		while ($s = $db->fetch_object($resSoc)) $clientes[$s->rowid] = $s->nom;
	}

	// Cargar monturas con stock > 0
	$monturas = array();
	$resMon = $db->query("SELECT rowid, ref, label, clase, precio, stock FROM ".MAIN_DB_PREFIX."optica_montura WHERE entity = ".((int) $conf->entity)." AND active = 1 ORDER BY clase ASC, ref ASC");
	if ($resMon) {
		while ($m = $db->fetch_object($resMon)) $monturas[] = $m;
	}

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">Cliente</td><td><select name="fk_soc" required class="minwidth300">';
	print '<option value="">-- Seleccione Cliente --</option>';
	foreach ($clientes as $sid => $snom) {
		$sel = ($fk_soc_pre === (int) $sid) ? ' selected' : '';
		print '<option value="'.$sid.'"'.$sel.'>'.dol_escape_htmltag($snom).'</option>';
	}
	print '</select></td></tr>';
	print '</table><br>';

	print '<h3>1. Datos Ópticos (Fórmula)</h3>';
	print '<table class="noborder centpercent optica-rx-table">';
	print '<tr class="liste_titre"><th>Ojo</th><th>Esfera (ESF)</th><th>Cilindro (CIL)</th><th>Eje</th><th>Adición (ADD)</th><th>Agudeza Visual (AV)</th><th>DNP</th></tr>';
	print '<tr class="oddeven"><td><strong>OD (Derecho)</strong></td>';
	print '<td><input type="text" name="od_esf" placeholder="-1.25"></td>';
	print '<td><input type="text" name="od_cil" placeholder="-0.50"></td>';
	print '<td><input type="text" name="od_eje" placeholder="90°"></td>';
	print '<td><input type="text" name="od_add" placeholder="+1.50"></td>';
	print '<td><input type="text" name="od_av" placeholder="20/20"></td>';
	print '<td><input type="text" name="od_dnp" placeholder="31"></td></tr>';
	print '<tr class="oddeven"><td><strong>OI (Izquierdo)</strong></td>';
	print '<td><input type="text" name="oi_esf" placeholder="-1.00"></td>';
	print '<td><input type="text" name="oi_cil" placeholder="-0.25"></td>';
	print '<td><input type="text" name="oi_eje" placeholder="85°"></td>';
	print '<td><input type="text" name="oi_add" placeholder="+1.50"></td>';
	print '<td><input type="text" name="oi_av" placeholder="20/20"></td>';
	print '<td><input type="text" name="oi_dnp" placeholder="31"></td></tr>';
	print '</table>';

	print '<table class="border centpercent" style="margin-top:8px;">';
	print '<tr><td class="titlefieldcreate">Distancia Pupilar (DP)</td><td><input type="text" name="dp" placeholder="62 mm"></td></tr>';
	print '<tr><td>Altura Pupilar</td><td><input type="text" name="altura" placeholder="18 mm"></td></tr>';
	print '<tr><td>Observaciones Optométricas</td><td><textarea name="observaciones" rows="2" class="centpercent"></textarea></td></tr>';
	print '</table><br>';

	print '<h3>2. Montura (Con Stock) y Cristales (Bajo Pedido)</h3>';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate">Montura</td><td><select name="fk_montura" id="fk_montura" onchange="var p=this.options[this.selectedIndex].getAttribute(\'data-precio\'); if(p) document.getElementById(\'precio_montura\').value=p;">';
	print '<option value="0" data-precio="0">-- Sin montura / Propia del cliente --</option>';
	foreach ($monturas as $m) {
		$disabled = ((int) $m->stock <= 0) ? ' disabled' : '';
		print '<option value="'.$m->rowid.'" data-precio="'.number_format((float) $m->precio, 2, '.', '').'"'.$disabled.'>'.dol_escape_htmltag($m->ref.' - '.$m->label).' | Clase '.$m->clase.' ($'.number_format((float) $m->precio, 2).') | Stock: '.$m->stock.'</option>';
	}
	print '</select></td></tr>';
	// Cargar cuentas bancarias y monedas activas
	$cuentas = array();
	$resB = $db->query("SELECT rowid, ref, label, currency_code FROM ".MAIN_DB_PREFIX."bank_account WHERE entity = ".((int) $conf->entity)." AND clos = 0 ORDER BY label ASC");
	if ($resB) {
		while ($b = $db->fetch_object($resB)) $cuentas[] = $b;
	}
	$monedas = optica_get_active_currencies($db);
	$baseCur = $conf->currency ?: 'USD';

	print '<tr><td>Precio Montura ($ IVA 16% incl.)</td><td><input type="number" step="0.01" name="precio_montura" id="precio_montura" value="0.00"></td></tr>';
	print '<tr><td>Tipo de Cristal (Sin Stock)</td><td><input type="text" name="tipo_cristal" class="minwidth300" placeholder="Ej. Monofocal Antirreflejo Blue Block"></td></tr>';
	print '<tr><td>Detalle / Laboratorio Cristal</td><td><input type="text" name="descripcion_cristal" class="minwidth400" placeholder="Ej. Policarbonato 1.59, filtro UV"></td></tr>';
	print '<tr><td>Precio Cristales ($ IVA 16% incl.)</td><td><input type="number" step="0.01" name="precio_cristales" value="0.00"></td></tr>';
	print '<tr><td><strong>Abono Inicial en Consulta</strong></td><td>';
	print 'Monto: <input type="number" step="0.01" name="abono_inicial" value="0.00" style="width:90px;"> ';
	print 'Moneda: <select name="abono_currency" onchange="var r=this.options[this.selectedIndex].getAttribute(\'data-rate\'); if(r) document.getElementById(\'create_tasa\').value=r;">';
	foreach ($monedas as $mcode => $mrate) {
		$sel = ($mcode === $baseCur) ? ' selected' : '';
		print '<option value="'.dol_escape_htmltag($mcode).'" data-rate="'.number_format((float) $mrate, 4, '.', '').'"'.$sel.'>'.dol_escape_htmltag($mcode).'</option>';
	}
	print '</select> ';
	print 'Tasa: <input type="number" step="0.0001" name="abono_tasa" id="create_tasa" value="1" style="width:80px;"> ';
	print 'Método: <select name="abono_metodo"><option value="Efectivo">Efectivo</option><option value="Tarjeta">Tarjeta</option><option value="Transferencia">Transferencia</option></select> ';
	print 'Cuenta: <select name="abono_bank_account"><option value="0">-- Sin cuenta --</option>';
	foreach ($cuentas as $b) {
		print '<option value="'.$b->rowid.'">'.dol_escape_htmltag($b->label.' ('.$b->currency_code.')').'</option>';
	}
	print '</select>';
	print '</td></tr>';
	print '</table>';

	print '<div class="center" style="margin-top:15px;">';
	print '<input type="submit" class="button" value="Guardar Consulta">';
	print ' <a class="button button-cancel" href="'.dol_buildpath('/optica/consulta_list.php', 1).'">Cancelar</a>';
	print '</div>';
	print '</form>';
} elseif ($id > 0) {
	$sql = "SELECT c.*, s.nom as cliente, m.ref as montura_ref, m.label as montura_label, m.clase as montura_clase, m.stock as montura_stock";
	$sql .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."optica_montura m ON m.rowid = c.fk_montura";
	$sql .= " WHERE c.rowid = ".((int) $id);
	$res = $db->query($sql);
	if (!$res) {
		print '<div class="error">Error SQL: '.dol_escape_htmltag($db->lasterror()).'</div>';
		llxFooter();
		exit;
	}
	if ($db->num_rows($res) == 0) {
		print '<div class="error">Consulta no encontrada (ID: '.((int) $id).').</div>';
		llxFooter();
		exit;
	}
	$c = $db->fetch_object($res);
	$estados = optica_get_estados();

	print load_fiche_titre('Consulta Óptica: '.$c->ref, '', 'fa-eye');

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">Cliente</td><td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$c->fk_soc.'"><strong>'.dol_escape_htmltag($c->cliente).'</strong></a></td></tr>';
	print '<tr><td>Fecha Consulta</td><td>'.dol_print_date($db->jdate($c->fecha_consulta), 'dayhour').'</td></tr>';
	print '<tr><td>Estado</td><td><span class="optica-badge optica-badge-'.((int) $c->estado).'">'.($estados[(int) $c->estado] ?? '').'</span></td></tr>';
	if ($c->fk_facture) {
		print '<tr><td>Factura Generada</td><td><a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$c->fk_facture.'">Ver Factura #'.$c->fk_facture.'</a> <a class="butAction" href="'.dol_buildpath('/optica/factura_ticket.php?id='.$id, 1).'" onclick="return opticaPrintPopup(this.href, \'Factura Ticket\');">Imprimir Factura Ticket</a></td></tr>';
	}
	print '</table><br>';

	print '<h3>Datos Ópticos ';
	print '<a class="button small" href="'.dol_buildpath('/optica/rx_ticket.php?id='.$id, 1).'" onclick="return opticaPrintPopup(this.href, \'Ticket Datos Ópticos\');">Imprimir Ticket Datos Ópticos</a> ';
	if ($user->hasRight('optica', 'write') && $action !== 'edit_rx') {
		print '<a class="button small" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit_rx&token='.newToken().'">Editar Datos Ópticos</a>';
	}
	print '</h3>';

	if ($action === 'edit_rx' && $user->hasRight('optica', 'write')) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_rx">';
		print '<table class="noborder centpercent optica-rx-table">';
		print '<tr class="liste_titre"><th>Ojo</th><th>ESF</th><th>CIL</th><th>EJE</th><th>ADD</th><th>AV</th><th>DNP</th></tr>';
		print '<tr class="oddeven"><td><strong>OD</strong></td>';
		print '<td><input type="text" name="od_esf" value="'.dol_escape_htmltag($c->od_esf).'"></td>';
		print '<td><input type="text" name="od_cil" value="'.dol_escape_htmltag($c->od_cil).'"></td>';
		print '<td><input type="text" name="od_eje" value="'.dol_escape_htmltag($c->od_eje).'"></td>';
		print '<td><input type="text" name="od_add" value="'.dol_escape_htmltag($c->od_add).'"></td>';
		print '<td><input type="text" name="od_av" value="'.dol_escape_htmltag($c->od_av).'"></td>';
		print '<td><input type="text" name="od_dnp" value="'.dol_escape_htmltag($c->od_dnp).'"></td></tr>';
		print '<tr class="oddeven"><td><strong>OI</strong></td>';
		print '<td><input type="text" name="oi_esf" value="'.dol_escape_htmltag($c->oi_esf).'"></td>';
		print '<td><input type="text" name="oi_cil" value="'.dol_escape_htmltag($c->oi_cil).'"></td>';
		print '<td><input type="text" name="oi_eje" value="'.dol_escape_htmltag($c->oi_eje).'"></td>';
		print '<td><input type="text" name="oi_add" value="'.dol_escape_htmltag($c->oi_add).'"></td>';
		print '<td><input type="text" name="oi_av" value="'.dol_escape_htmltag($c->oi_av).'"></td>';
		print '<td><input type="text" name="oi_dnp" value="'.dol_escape_htmltag($c->oi_dnp).'"></td></tr>';
		print '</table>';
		print '<p>DP: <input type="text" name="dp" value="'.dol_escape_htmltag($c->dp).'" style="width:80px;"> ';
		print 'Altura: <input type="text" name="altura" value="'.dol_escape_htmltag($c->altura).'" style="width:80px;"> ';
		print 'Obs: <input type="text" name="observaciones" value="'.dol_escape_htmltag($c->observaciones).'" class="minwidth300"> ';
		print '<input type="submit" class="button" value="Guardar Cambios"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">Cancelar</a></p>';
		print '</form><br>';
	} else {
		print '<table class="noborder centpercent optica-rx-table">';
		print '<tr class="liste_titre"><th>Ojo</th><th>ESF</th><th>CIL</th><th>EJE</th><th>ADD</th><th>AV</th><th>DNP</th></tr>';
		print '<tr class="oddeven"><td><strong>OD</strong></td><td>'.$c->od_esf.'</td><td>'.$c->od_cil.'</td><td>'.$c->od_eje.'</td><td>'.$c->od_add.'</td><td>'.$c->od_av.'</td><td>'.$c->od_dnp.'</td></tr>';
		print '<tr class="oddeven"><td><strong>OI</strong></td><td>'.$c->oi_esf.'</td><td>'.$c->oi_cil.'</td><td>'.$c->oi_eje.'</td><td>'.$c->oi_add.'</td><td>'.$c->oi_av.'</td><td>'.$c->oi_dnp.'</td></tr>';
		print '</table>';
		print '<p><strong>DP:</strong> '.dol_escape_htmltag($c->dp ?: '-').' | <strong>Altura:</strong> '.dol_escape_htmltag($c->altura ?: '-').' | <strong>Obs:</strong> '.dol_escape_htmltag($c->observaciones ?: '-').'</p><br>';
	}

	print '<h3>Montura, Cristales y Totales ';
	if ((int) $c->entregado === 0 && (int) $c->fk_facture === 0 && $user->hasRight('optica', 'write') && $action !== 'edit_montura') {
		print '<a class="button small" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit_montura&token='.newToken().'">Editar Montura / Cristales</a>';
	}
	print '</h3>';

	if ($action === 'edit_montura' && (int) $c->estado < 3 && $user->hasRight('optica', 'write')) {
		$monturas = array();
		$resMon = $db->query("SELECT rowid, ref, label, clase, precio, stock FROM ".MAIN_DB_PREFIX."optica_montura WHERE entity = ".((int) $conf->entity)." AND active = 1 ORDER BY clase ASC, ref ASC");
		if ($resMon) {
			while ($m = $db->fetch_object($resMon)) $monturas[] = $m;
		}
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_montura">';
		print '<table class="border centpercent">';
		print '<tr><td class="titlefield">Montura</td><td><select name="fk_montura" onchange="var p=this.options[this.selectedIndex].getAttribute(\'data-precio\'); if(p!==null) document.getElementById(\'edit_precio_montura\').value=p;">';
		print '<option value="0" data-precio="0.00">-- Sin montura / Propia del cliente --</option>';
		foreach ($monturas as $m) {
			$sel = ((int) $c->fk_montura === (int) $m->rowid) ? ' selected' : '';
			$disabled = ((int) $m->stock <= 0 && (int) $c->fk_montura !== (int) $m->rowid) ? ' disabled' : '';
			print '<option value="'.$m->rowid.'" data-precio="'.number_format((float) $m->precio, 2, '.', '').'"'.$sel.$disabled.'>'.dol_escape_htmltag($m->ref.' - '.$m->label).' | Clase '.$m->clase.' ($'.number_format((float) $m->precio, 2).') | Stock: '.$m->stock.'</option>';
		}
		print '</select></td></tr>';
		print '<tr><td>Precio Montura ($)</td><td><input type="number" step="0.01" name="precio_montura" id="edit_precio_montura" value="'.number_format((float) $c->precio_montura, 2, '.', '').'"></td></tr>';
		print '<tr><td>Tipo de Cristal (Sin Stock)</td><td><input type="text" name="tipo_cristal" class="minwidth300" value="'.dol_escape_htmltag($c->tipo_cristal).'"></td></tr>';
		print '<tr><td>Detalle Cristal</td><td><input type="text" name="descripcion_cristal" class="minwidth400" value="'.dol_escape_htmltag($c->descripcion_cristal).'"></td></tr>';
		print '<tr><td>Precio Cristales ($)</td><td><input type="number" step="0.01" name="precio_cristales" value="'.number_format((float) $c->precio_cristales, 2, '.', '').'"></td></tr>';
		print '</table>';
		print '<p><input type="submit" class="button" value="Guardar Montura y Cristales"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">Cancelar</a></p>';
		print '</form><br>';
	} else {
		print '<table class="border centpercent">';
		print '<tr><td class="titlefield">Montura</td><td>'.($c->montura_ref ? dol_escape_htmltag($c->montura_ref.' - '.$c->montura_label.' (Clase '.$c->montura_clase.') | Stock actual: '.$c->montura_stock) : 'Sin montura').' ($'.number_format((float) $c->precio_montura, 2).')</td></tr>';
		print '<tr><td>Cristales (Sin Stock)</td><td>'.dol_escape_htmltag($c->tipo_cristal ?: '-').' '.dol_escape_htmltag($c->descripcion_cristal ? '('.$c->descripcion_cristal.')' : '').' ($'.number_format((float) $c->precio_cristales, 2).')</td></tr>';
		print '<tr><td><strong>Total</strong></td><td><strong>$'.number_format((float) $c->total, 2).'</strong></td></tr>';
		print '<tr><td>Abonado</td><td>$'.number_format((float) $c->abono, 2).'</td></tr>';
		print '<tr><td><strong>Saldo Pendiente</strong></td><td><strong>$'.number_format((float) $c->saldo, 2).'</strong></td></tr>';
		print '</table>';
	}

	// Botones de flujo
	if (((int) $c->estado < 3 || (int) $c->entregado === 0) && $user->hasRight('optica', 'write')) {
		print '<div class="tabsAction">';
		if ((int) $c->estado < 1) print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=set_estado&estado=1&token='.newToken().'">Enviar a Laboratorio</a>';
		if ((int) $c->estado < 2) print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=set_estado&estado=2&token='.newToken().'">Marcar Listo para Entrega</a>';
		$lblEntrega = ((int) $c->fk_facture > 0) ? 'Entregar Montura (Descontar Stock)' : 'Entregar Montura y Facturar';
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=entregar_facturar&token='.newToken().'" onclick="return confirm(\'¿Confirmar entrega de montura (descuenta stock)?\');">'.$lblEntrega.'</a>';
		print '</div>';
	}

	// Formulario rápido de abono mientras exista saldo pendiente (incluso si ya está facturada)
	if ((float) $c->saldo > 0.01 && $user->hasRight('optica', 'write')) {
		$cuentas = array();
		$resB = $db->query("SELECT rowid, ref, label, currency_code FROM ".MAIN_DB_PREFIX."bank_account WHERE entity = ".((int) $conf->entity)." AND clos = 0 ORDER BY label ASC");
		if ($resB) {
			while ($b = $db->fetch_object($resB)) $cuentas[] = $b;
		}
		$monedas = optica_get_active_currencies($db);
		$baseCur = $conf->currency ?: 'USD';
		print '<h3>Registrar Nuevo Abono</h3>';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="hidden" name="action" value="add_abono">';
		print 'Monto: <input type="number" step="0.01" name="monto_abono" required style="width:90px;"> ';
		print 'Moneda: <select name="currency_code" onchange="var r=this.options[this.selectedIndex].getAttribute(\'data-rate\'); if(r) document.getElementById(\'optica_tasa\').value=r;">';
		foreach ($monedas as $mcode => $mrate) {
			$sel = ($mcode === $baseCur) ? ' selected' : '';
			print '<option value="'.dol_escape_htmltag($mcode).'" data-rate="'.number_format((float) $mrate, 4, '.', '').'"'.$sel.'>'.dol_escape_htmltag($mcode).'</option>';
		}
		print '</select> ';
		print 'Tasa: <input type="number" step="0.0001" name="tasa" id="optica_tasa" value="1" style="width:85px;"> ';
		print 'Método: <select name="metodo_pago"><option value="Efectivo">Efectivo</option><option value="Tarjeta">Tarjeta</option><option value="Transferencia">Transferencia</option></select> ';
		print 'Cuenta Bancaria: <select name="fk_bank_account"><option value="0">-- Sin cuenta --</option>';
		foreach ($cuentas as $b) {
			print '<option value="'.$b->rowid.'">'.dol_escape_htmltag($b->label.' ('.$b->currency_code.')').'</option>';
		}
		print '</select> ';
		print 'Nota: <input type="text" name="nota_abono" placeholder="Abono parcial"> ';
		print '<input type="submit" class="button" value="Registrar Abono">';
		print '</form><br>';
	}

	// Historial de abonos
	print '<h3>Historial de Abonos</h3>';
	$resAb = $db->query("SELECT a.*, ba.label as bank_label FROM ".MAIN_DB_PREFIX."optica_abono a LEFT JOIN ".MAIN_DB_PREFIX."bank_account ba ON ba.rowid = a.fk_bank_account WHERE a.fk_consulta = ".((int) $id)." ORDER BY a.rowid ASC");
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>Fecha</th><th>Método / Cuenta</th><th>Moneda</th><th>Nota</th><th class="right">Monto (USD)</th><th class="center">Ticket</th></tr>';
	if ($resAb && $db->num_rows($resAb) > 0) {
		while ($ab = $db->fetch_object($resAb)) {
			$monedaTxt = !empty($ab->currency_code) ? $ab->currency_code.' ('.number_format((float) $ab->monto_moneda, 2).')' : 'USD';
			$cuentaTxt = dol_escape_htmltag($ab->metodo_pago).(!empty($ab->bank_label) ? ' / '.dol_escape_htmltag($ab->bank_label) : '');
			print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($ab->fecha_pago), 'dayhour').'</td><td>'.$cuentaTxt.'</td><td>'.dol_escape_htmltag($monedaTxt).'</td><td>'.dol_escape_htmltag($ab->nota).'</td><td class="right">$'.number_format((float) $ab->monto, 2).'</td>';
			print '<td class="center"><a class="button small" href="'.dol_buildpath('/optica/abono_ticket.php?id='.$ab->rowid, 1).'" onclick="return opticaPrintPopup(this.href, \'Ticket de Abono\');">Imprimir Ticket</a></td></tr>';
		}
	} else {
		print '<tr><td colspan="6" class="opacitymedium center">Sin abonos registrados.</td></tr>';
	}
	print '</table>';
}

llxFooter();
$db->close();
