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

$langs->loadLangs(array("optica@optica", "companies"));
if (!$user->hasRight('optica', 'read')) accessforbidden();

$id = GETPOSTINT('id');
if ($id <= 0) die("ID de abono inválido");

$sql = "SELECT a.*, c.ref as consulta_ref, c.total, c.abono as total_abonado, c.saldo, s.nom as cliente, s.phone as cliente_tel";
$sql .= " FROM ".MAIN_DB_PREFIX."optica_abono a";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."optica_consulta c ON c.rowid = a.fk_consulta";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
$sql .= " WHERE a.rowid = ".((int) $id);

$res = $db->query($sql);
if (!$res || $db->num_rows($res) == 0) die("Abono no encontrado");
$ab = $db->fetch_object($res);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket Abono #<?php echo (int) $ab->rowid; ?></title>
<style>
	@page { margin: 4mm; size: 80mm auto; }
	body { font-family: 'Courier New', monospace; font-size: 12px; width: 100%; max-width: 74mm; margin: 0 auto; padding: 6px; color: #000; background: #fff; box-sizing: border-box; }
	.center { text-align: center; }
	.row { display: flex; justify-content: space-between; align-items: flex-start; gap: 6px; margin: 2px 0; }
	.row span:last-child { text-align: right; white-space: nowrap; flex-shrink: 0; }
	.line { border-top: 1px dashed #000; margin: 6px 0; }
	.bold { font-weight: bold; }
	.no-print { margin-top: 12px; text-align: center; }
	@media print { .no-print { display: none; } }
</style>
</head>
<body onload="window.print()">
	<div class="center bold"><?php echo dol_escape_htmltag($mysoc->name ?: 'ÓPTICA'); ?></div>
	<?php if (!empty($mysoc->idprof1)) echo '<div class="center">RIF/NIT: '.dol_escape_htmltag($mysoc->idprof1).'</div>'; ?>
	<?php if (!empty($mysoc->phone)) echo '<div class="center">Tel: '.dol_escape_htmltag($mysoc->phone).'</div>'; ?>
	<div class="line"></div>
	<div class="center bold">COMPROBANTE DE ABONO</div>
	<div>Ticket N°: <strong>#<?php echo str_pad((string) $ab->rowid, 6, '0', STR_PAD_LEFT); ?></strong></div>
	<div>Fecha: <?php echo dol_print_date($db->jdate($ab->fecha_pago), 'dayhour'); ?></div>
	<div>Orden: <strong><?php echo dol_escape_htmltag($ab->consulta_ref); ?></strong></div>
	<div>Cliente: <?php echo dol_escape_htmltag($ab->cliente ?: 'N/A'); ?></div>
	<div class="line"></div>
	<div>Método: <?php echo dol_escape_htmltag($ab->metodo_pago); ?></div>
	<?php if (!empty($ab->currency_code) && $ab->currency_code !== $conf->currency) : ?>
		<div>Moneda pago: <?php echo dol_escape_htmltag($ab->currency_code); ?> (Tasa: <?php echo number_format((float) $ab->tasa, 2); ?>)</div>
		<div class="row"><span>Recibido:</span><span><?php echo number_format((float) $ab->monto_moneda, 2).' '.dol_escape_htmltag($ab->currency_code); ?></span></div>
	<?php endif; ?>
	<div class="row bold"><span>Abono aplicado:</span><span>$<?php echo number_format((float) $ab->monto, 2); ?></span></div>
	<?php if (!empty($ab->nota)) echo '<div>Nota: '.dol_escape_htmltag($ab->nota).'</div>'; ?>
	<div class="line"></div>
	<div class="row"><span>Total Orden:</span><span>$<?php echo number_format((float) $ab->total, 2); ?></span></div>
	<div class="row"><span>Total Abonado:</span><span>$<?php echo number_format((float) $ab->total_abonado, 2); ?></span></div>
	<div class="row bold"><span>Saldo Restante:</span><span>$<?php echo number_format((float) $ab->saldo, 2); ?></span></div>
	<div class="line"></div>
	<div class="center">¡Gracias por su preferencia!</div>
	<div class="no-print">
		<button type="button" onclick="window.print()">Imprimir Ticket</button>
		<button type="button" onclick="if(parent && parent.$ && parent.$.colorbox){ parent.$.colorbox.close(); } else { window.close(); }">Cerrar</button>
	</div>
</body>
</html>
