<?php
/* Copyright (C) 2026 Optica Module */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

if (!$user->hasRight('optica', 'read')) accessforbidden();
optica_ensure_schema($db);

$date_start = GETPOST('date_start', 'alphanohtml') ?: date('Y-m-d');
$date_end = GETPOST('date_end', 'alphanohtml') ?: date('Y-m-d');
$fk_bank_account = GETPOSTINT('fk_bank_account');

// 1. Resumen de ingresos / abonos en caja en el período (incluyendo monto real en banco)
$sqlAb = "SELECT a.*, c.ref as consulta_ref, ba.label as bank_label, ba.currency_code as bank_cur, b.amount as bank_amount";
$sqlAb .= " FROM ".MAIN_DB_PREFIX."optica_abono a";
$sqlAb .= " INNER JOIN ".MAIN_DB_PREFIX."optica_consulta c ON c.rowid = a.fk_consulta";
$sqlAb .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account ba ON ba.rowid = a.fk_bank_account";
$sqlAb .= " LEFT JOIN ".MAIN_DB_PREFIX."bank b ON b.rowid = a.fk_bank";
$sqlAb .= " WHERE a.entity = ".((int) $conf->entity);
$sqlAb .= " AND DATE(a.fecha_pago) >= '".$db->escape($date_start)."' AND DATE(a.fecha_pago) <= '".$db->escape($date_end)."'";
if ($fk_bank_account > 0) {
	$sqlAb .= " AND a.fk_bank_account = ".((int) $fk_bank_account);
}
$sqlAb .= " ORDER BY a.rowid ASC";

$resAb = $db->query($sqlAb);
$numAbonos = 0;
$totalCajaUsd = 0.0;
$totalBancosUsd = 0.0;
$totalSinBancoUsd = 0.0;
$porMoneda = array();
$porMetodo = array();
$porBanco = array();

if ($resAb) {
	while ($ab = $db->fetch_object($resAb)) {
		$numAbonos++;
		$baseCur = $conf->currency ?: 'USD';
		$cur = $ab->currency_code ?: $baseCur;
		$amtCur = ($ab->monto_moneda > 0) ? (float) $ab->monto_moneda : (float) $ab->monto;
		$porMoneda[$cur] = ($porMoneda[$cur] ?? 0) + $amtCur;

		$met = $ab->metodo_pago ?: 'Efectivo';
		$porMetodo[$met] = ($porMetodo[$met] ?? 0) + (float) $ab->monto;

		if (!empty($ab->fk_bank_account)) {
			$acctCur = $ab->bank_cur ?: $baseCur;
			$bKey = $ab->bank_label.' ('.$acctCur.')';
			if (!isset($porBanco[$bKey])) {
				$porBanco[$bKey] = array('cur' => $acctCur, 'native' => 0.0, 'usd' => 0.0);
			}
			$nativeAmt = ($ab->bank_amount !== null) ? (float) $ab->bank_amount : (float) $ab->monto;
			$porBanco[$bKey]['native'] += $nativeAmt;
			$porBanco[$bKey]['usd'] += (float) $ab->monto;
			$totalBancosUsd += (float) $ab->monto;
		} else {
			$totalSinBancoUsd += (float) $ab->monto;
		}

		$totalCajaUsd += (float) $ab->monto;
	}
}

// 2. Resumen de ventas (consultas y facturas del período)
$sqlV = "SELECT COUNT(c.rowid) as num_ordenes, SUM(c.total) as total_ordenes, SUM(c.saldo) as saldo_ordenes,";
$sqlV .= " SUM(CASE WHEN c.fk_facture > 0 THEN 1 ELSE 0 END) as num_facturas,";
$sqlV .= " SUM(CASE WHEN c.fk_facture > 0 THEN c.total ELSE 0 END) as total_facturado,";
$sqlV .= " MIN(c.ref) as primera_ref, MAX(c.ref) as ultima_ref";
$sqlV .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
$sqlV .= " WHERE c.entity = ".((int) $conf->entity);
$sqlV .= " AND DATE(c.fecha_consulta) >= '".$db->escape($date_start)."' AND DATE(c.fecha_consulta) <= '".$db->escape($date_end)."'";
$resV = $db->query($sqlV);
$v = ($resV) ? $db->fetch_object($resV) : null;

$totalFacturado = $v ? (float) $v->total_facturado : 0.0;
$baseFact = round($totalFacturado / 1.16, 2);
$ivaFact = round($totalFacturado - $baseFact, 2);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Corte Z - <?php echo dol_escape_htmltag($date_start); ?></title>
<style>
	@page { margin: 4mm; size: 80mm auto; }
	body { font-family: 'Courier New', monospace; font-size: 11px; width: 100%; max-width: 74mm; margin: 0 auto; padding: 6px; color: #000; background: #fff; box-sizing: border-box; }
	.center { text-align: center; }
	.row { display: flex; justify-content: space-between; align-items: flex-start; gap: 6px; margin: 2px 0; }
	.row span:last-child { text-align: right; white-space: nowrap; flex-shrink: 0; }
	.line { border-top: 1px dashed #000; margin: 6px 0; }
	.dline { border-top: 2px solid #000; margin: 6px 0; }
	.bold { font-weight: bold; }
	.no-print { margin-top: 12px; text-align: center; }
	@media print { .no-print { display: none; } }
</style>
</head>
<body onload="window.print()">
	<div class="center bold"><?php echo dol_escape_htmltag($mysoc->name ?: 'ÓPTICA'); ?></div>
	<?php if (!empty($mysoc->idprof1)) echo '<div class="center">RIF/NIT: '.dol_escape_htmltag($mysoc->idprof1).'</div>'; ?>
	<?php if (!empty($mysoc->address)) echo '<div class="center">'.dol_escape_htmltag($mysoc->address).'</div>'; ?>
	<div class="dline"></div>
	<div class="center bold">REPORTE DE CIERRE / CORTE Z</div>
	<div>Emitido: <?php echo dol_print_date(dol_now(), 'dayhour'); ?></div>
	<div>Usuario: <?php echo dol_escape_htmltag($user->getFullName($langs)); ?></div>
	<div>Período: <?php echo dol_escape_htmltag($date_start.' al '.$date_end); ?></div>
	<div class="line"></div>

	<div class="bold">1. RESUMEN DE VENTAS</div>
	<div class="row"><span>Órdenes registradas:</span><span><?php echo (int) ($v ? $v->num_ordenes : 0); ?></span></div>
	<?php if ($v && !empty($v->primera_ref)) : ?>
	<div class="row"><span>Rango:</span><span><?php echo dol_escape_htmltag($v->primera_ref.' / '.$v->ultima_ref); ?></span></div>
	<?php endif; ?>
	<div class="row"><span>Total Órdenes:</span><span>$<?php echo number_format((float) ($v ? $v->total_ordenes : 0), 2); ?></span></div>
	<div class="row"><span>Facturas emitidas:</span><span><?php echo (int) ($v ? $v->num_facturas : 0); ?></span></div>
	<div class="row"><span>Base Imponible Fact.:</span><span>$<?php echo number_format($baseFact, 2); ?></span></div>
	<div class="row"><span>IVA (16% incluido):</span><span>$<?php echo number_format($ivaFact, 2); ?></span></div>
	<div class="row bold"><span>TOTAL FACTURADO:</span><span>$<?php echo number_format($totalFacturado, 2); ?></span></div>
	<div class="row"><span>Saldo Pendiente:</span><span>$<?php echo number_format((float) ($v ? $v->saldo_ordenes : 0), 2); ?></span></div>
	<div class="line"></div>

	<div class="bold">2. COBROS EN CAJA (<?php echo $numAbonos; ?> ops.)</div>
	<?php foreach ($porMetodo as $met => $sumMet) : ?>
		<div class="row"><span><?php echo dol_escape_htmltag($met); ?>:</span><span>$<?php echo number_format($sumMet, 2); ?></span></div>
	<?php endforeach; ?>
	<div class="line"></div>

	<div class="bold">3. DESGLOSE POR MONEDA</div>
	<?php if (count($porMoneda) > 0) : foreach ($porMoneda as $cur => $sumCur) : ?>
		<div class="row"><span>Total <?php echo dol_escape_htmltag($cur); ?>:</span><span><?php echo number_format($sumCur, 2).' '.dol_escape_htmltag($cur); ?></span></div>
	<?php endforeach; else : ?>
		<div>Sin movimientos en caja</div>
	<?php endif; ?>
	<div class="line"></div>

	<div class="bold">4. DESGLOSE POR CUENTA BANCARIA</div>
	<?php foreach ($porBanco as $bName => $bData) : ?>
		<div class="row"><span><?php echo dol_escape_htmltag($bName); ?>:</span><span><?php echo number_format($bData['native'], 2).' '.dol_escape_htmltag($bData['cur']); ?><br><small>($<?php echo number_format($bData['usd'], 2); ?>)</small></span></div>
	<?php endforeach; ?>
	<?php if ($totalSinBancoUsd > 0) : ?>
		<div class="row"><span>Sin cuenta bancaria:</span><span>$<?php echo number_format($totalSinBancoUsd, 2); ?></span></div>
	<?php endif; ?>
	<div class="dline"></div>

	<div class="row"><span>Total en Bancos (USD):</span><span>$<?php echo number_format($totalBancosUsd, 2); ?></span></div>
	<div class="row bold" style="font-size:12px;"><span>TOTAL CAJA (USD):</span><span>$<?php echo number_format($totalCajaUsd, 2); ?></span></div>
	<div class="line"></div>
	<div class="center">*** FIN DEL CORTE Z ***</div>
	<div class="no-print">
		<button type="button" onclick="window.print()">Imprimir Corte Z</button>
		<button type="button" onclick="if(parent && parent.$ && parent.$.colorbox){ parent.$.colorbox.close(); } else { window.close(); }">Cerrar</button>
	</div>
</body>
</html>
