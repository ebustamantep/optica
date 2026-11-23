<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "bills", "banks"));
if (!$user->hasRight('optica', 'read')) accessforbidden();
optica_ensure_schema($db);

$date_start = GETPOST('date_start', 'alphanohtml') ?: date('Y-m-d');
$date_end = GETPOST('date_end', 'alphanohtml') ?: date('Y-m-d');
$fk_bank_account = GETPOSTINT('fk_bank_account');

$arrayofjs = array('/takepos/js/jquery.colorbox-min.js', '/optica/js/optica.js');
$arrayofcss = array('/takepos/css/colorbox.css');
llxHeader('', 'Óptica - Reporte de Caja / Corte Z', '', '', 0, 0, $arrayofjs, $arrayofcss);
print load_fiche_titre('Reporte de Caja y Corte Z (Óptica)', '', 'fa-cash-register');

$cuentas = array();
$resB = $db->query("SELECT rowid, label, currency_code FROM ".MAIN_DB_PREFIX."bank_account WHERE entity = ".((int) $conf->entity)." AND clos = 0 ORDER BY label ASC");
if ($resB) {
	while ($b = $db->fetch_object($resB)) $cuentas[] = $b;
}

$corteZUrl = dol_buildpath('/optica/corte_z.php?date_start='.urlencode($date_start).'&date_end='.urlencode($date_end).'&fk_bank_account='.((int) $fk_bank_account), 1);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="no-print" style="background:#f9f9f9; padding:10px; border:1px solid #ddd; border-radius:5px; margin-bottom:14px;">';
print 'Desde: <input type="date" name="date_start" value="'.dol_escape_htmltag($date_start).'"> ';
print 'Hasta: <input type="date" name="date_end" value="'.dol_escape_htmltag($date_end).'"> ';
print 'Cuenta Bancaria: <select name="fk_bank_account"><option value="0">-- Todas --</option>';
foreach ($cuentas as $b) {
	$sel = ($fk_bank_account === (int) $b->rowid) ? ' selected' : '';
	print '<option value="'.$b->rowid.'"'.$sel.'>'.dol_escape_htmltag($b->label.' ('.$b->currency_code.')').'</option>';
}
print '</select> ';
print '<input type="submit" class="button" value="Consultar"> ';
print '<a class="butAction optica-popup-link" title="Corte Z - Cierre de Caja" href="'.$corteZUrl.'" onclick="return opticaPrintPopup(this.href, \'Corte Z - Cierre de Caja\');">Imprimir Corte Z (Popup)</a>';
print '</form>';

$sql = "SELECT a.*, c.ref as consulta_ref, s.nom as cliente, ba.label as bank_label, ba.currency_code as bank_cur, b.amount as bank_amount";
$sql .= " FROM ".MAIN_DB_PREFIX."optica_abono a";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."optica_consulta c ON c.rowid = a.fk_consulta";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account ba ON ba.rowid = a.fk_bank_account";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank b ON b.rowid = a.fk_bank";
$sql .= " WHERE a.entity = ".((int) $conf->entity);
$sql .= " AND DATE(a.fecha_pago) >= '".$db->escape($date_start)."' AND DATE(a.fecha_pago) <= '".$db->escape($date_end)."'";
if ($fk_bank_account > 0) {
	$sql .= " AND a.fk_bank_account = ".((int) $fk_bank_account);
}
$sql .= " ORDER BY a.fecha_pago DESC, a.rowid DESC";

$res = $db->query($sql);

$totalesMoneda = array();
$totalesMetodo = array();
$totalesBanco = array();
$totalUsd = 0.0;
$totalBancosUsd = 0.0;
$totalSinBancoUsd = 0.0;
$rows = array();

if ($res) {
	while ($obj = $db->fetch_object($res)) {
		$rows[] = $obj;
		$baseCur = $conf->currency ?: 'USD';
		$cur = $obj->currency_code ?: $baseCur;
		$amtCur = ($obj->monto_moneda > 0) ? (float) $obj->monto_moneda : (float) $obj->monto;
		$totalesMoneda[$cur] = ($totalesMoneda[$cur] ?? 0) + $amtCur;
		$met = $obj->metodo_pago ?: 'Efectivo';
		$totalesMetodo[$met] = ($totalesMetodo[$met] ?? 0) + (float) $obj->monto;

		if (!empty($obj->fk_bank_account)) {
			$acctCur = $obj->bank_cur ?: $baseCur;
			$bKey = $obj->bank_label.' ('.$acctCur.')';
			if (!isset($totalesBanco[$bKey])) {
				$totalesBanco[$bKey] = array('cur' => $acctCur, 'native' => 0.0, 'usd' => 0.0);
			}
			$nativeAmt = ($obj->bank_amount !== null) ? (float) $obj->bank_amount : (float) $obj->monto;
			$totalesBanco[$bKey]['native'] += $nativeAmt;
			$totalesBanco[$bKey]['usd'] += (float) $obj->monto;
			$totalBancosUsd += (float) $obj->monto;
		} else {
			$totalSinBancoUsd += (float) $obj->monto;
		}
		$totalUsd += (float) $obj->monto;
	}
}

// Resumen de ventas del período para vista Corte Z
$sqlV = "SELECT COUNT(c.rowid) as num_ordenes, SUM(c.total) as total_ordenes, SUM(c.saldo) as saldo_ordenes,";
$sqlV .= " SUM(CASE WHEN c.fk_facture > 0 THEN 1 ELSE 0 END) as num_facturas,";
$sqlV .= " SUM(CASE WHEN c.fk_facture > 0 THEN c.total ELSE 0 END) as total_facturado";
$sqlV .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
$sqlV .= " WHERE c.entity = ".((int) $conf->entity);
$sqlV .= " AND DATE(c.fecha_consulta) >= '".$db->escape($date_start)."' AND DATE(c.fecha_consulta) <= '".$db->escape($date_end)."'";
$resV = $db->query($sqlV);
$v = ($resV) ? $db->fetch_object($resV) : null;
$totalFacturado = $v ? (float) $v->total_facturado : 0.0;
$baseFact = round($totalFacturado / 1.16, 2);
$ivaFact = round($totalFacturado - $baseFact, 2);

// Vista en pantalla tipo Corte Z
print '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px; margin-bottom:18px;">';

print '<div class="optica-card" style="font-family:monospace; background:#fcfcfc;">';
print '<h4 style="margin-top:0; border-bottom:1px dashed #999; padding-bottom:6px;">CORTE Z - RESUMEN DE VENTAS</h4>';
print '<div>Órdenes del período: <strong style="float:right;">'.(int) ($v ? $v->num_ordenes : 0).'</strong></div>';
print '<div>Total órdenes: <strong style="float:right;">$'.number_format((float) ($v ? $v->total_ordenes : 0), 2).'</strong></div>';
print '<div>Facturas emitidas: <strong style="float:right;">'.(int) ($v ? $v->num_facturas : 0).'</strong></div>';
print '<div>Base imponible facturada: <strong style="float:right;">$'.number_format($baseFact, 2).'</strong></div>';
print '<div>IVA (16% incluido): <strong style="float:right;">$'.number_format($ivaFact, 2).'</strong></div>';
print '<div style="border-top:1px dashed #999; margin-top:6px; padding-top:6px;"><strong>TOTAL FACTURADO:</strong> <strong style="float:right;">$'.number_format($totalFacturado, 2).'</strong></div>';
print '<div>Saldo pendiente período: <strong style="float:right;">$'.number_format((float) ($v ? $v->saldo_ordenes : 0), 2).'</strong></div>';
print '</div>';

print '<div class="optica-card" style="font-family:monospace; background:#fcfcfc;">';
print '<h4 style="margin-top:0; border-bottom:1px dashed #999; padding-bottom:6px;">CORTE Z - COBROS EN CAJA ('.count($rows).' ops.)</h4>';
foreach ($totalesMetodo as $met => $sumMet) {
	print '<div>Método '.dol_escape_htmltag($met).': <strong style="float:right;">$'.number_format($sumMet, 2).'</strong></div>';
}
print '<div style="border-top:1px dashed #ccc; margin:6px 0;"></div>';
foreach ($totalesMoneda as $cur => $sumCur) {
	print '<div>Moneda recibida '.dol_escape_htmltag($cur).': <strong style="float:right;">'.number_format($sumCur, 2).' '.dol_escape_htmltag($cur).'</strong></div>';
}
print '<div style="border-top:1px dashed #ccc; margin:6px 0;"></div>';
foreach ($totalesBanco as $bKey => $bData) {
	print '<div>Banco '.dol_escape_htmltag($bKey).': <strong style="float:right;">'.number_format($bData['native'], 2).' '.dol_escape_htmltag($bData['cur']).' ($'.number_format($bData['usd'], 2).')</strong></div>';
}
if ($totalSinBancoUsd > 0) {
	print '<div>Sin cuenta bancaria: <strong style="float:right;">$'.number_format($totalSinBancoUsd, 2).'</strong></div>';
}
print '<div style="border-top:1px dashed #999; margin-top:6px; padding-top:6px;">Total en Bancos (USD): <strong style="float:right;">$'.number_format($totalBancosUsd, 2).'</strong></div>';
print '<div style="font-size:14px;"><strong>TOTAL CAJA (EQUIV. USD):</strong> <strong style="float:right;">$'.number_format($totalUsd, 2).'</strong></div>';
print '</div>';

print '</div>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Fecha</th><th>Ticket</th><th>Orden</th><th>Cliente</th><th>Método</th><th>Cuenta Bancaria</th><th>Moneda Pago</th><th class="right">Depósito Banco</th><th class="right">Equiv. USD</th></tr>';
if (count($rows) > 0) {
	foreach ($rows as $r) {
		$cur = $r->currency_code ?: 'USD';
		$amtCur = ($r->monto_moneda > 0) ? (float) $r->monto_moneda : (float) $r->monto;
		$bCur = $r->bank_cur ?: ($conf->currency ?: 'USD');
		$depTxt = ($r->bank_amount !== null) ? number_format((float) $r->bank_amount, 2).' '.dol_escape_htmltag($bCur) : '-';
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($r->fecha_pago), 'dayhour').'</td>';
		print '<td><a class="optica-popup-link" title="Ticket de Abono" href="'.dol_buildpath('/optica/abono_ticket.php?id='.$r->rowid, 1).'" onclick="return opticaPrintPopup(this.href, \'Ticket de Abono\');">#'.str_pad((string) $r->rowid, 6, '0', STR_PAD_LEFT).'</a></td>';
		print '<td><a href="'.dol_buildpath('/optica/consulta_card.php?id='.$r->fk_consulta, 1).'"><strong>'.dol_escape_htmltag($r->consulta_ref).'</strong></a></td>';
		print '<td>'.dol_escape_htmltag($r->cliente ?: 'N/A').'</td>';
		print '<td>'.dol_escape_htmltag($r->metodo_pago).'</td>';
		print '<td>'.dol_escape_htmltag($r->bank_label ? $r->bank_label.' ('.$bCur.')' : 'Sin cuenta').'</td>';
		print '<td>'.number_format($amtCur, 2).' '.dol_escape_htmltag($cur).'</td>';
		print '<td class="right">'.$depTxt.'</td>';
		print '<td class="right"><strong>$'.number_format((float) $r->monto, 2).'</strong></td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="9" class="opacitymedium center">No hay movimientos de caja en el rango seleccionado.</td></tr>';
}
print '</table>';

llxFooter();
$db->close();
