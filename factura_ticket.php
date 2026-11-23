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

if (!$user->hasRight('optica', 'read')) accessforbidden();

$id = GETPOSTINT('id');
if ($id <= 0) die("ID de consulta inválido");

$sql = "SELECT c.*, s.nom as cliente, s.siren as cliente_rif, m.ref as montura_ref, m.label as montura_label, m.clase as montura_clase, f.ref as fac_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."optica_montura m ON m.rowid = c.fk_montura";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture";
$sql .= " WHERE c.rowid = ".((int) $id);
$res = $db->query($sql);
if (!$res) die("Error SQL: ".$db->lasterror());
if ($db->num_rows($res) == 0) die("Consulta no encontrada");
$c = $db->fetch_object($res);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura Ticket <?php echo dol_escape_htmltag($c->fac_ref ?: $c->ref); ?></title>
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
	<?php if (!empty($mysoc->address)) echo '<div class="center">'.dol_escape_htmltag($mysoc->address).'</div>'; ?>
	<div class="line"></div>
	<div class="center bold">FACTURA / ENTREGA</div>
	<div>Factura: <strong><?php echo dol_escape_htmltag($c->fac_ref ?: ('#'.$c->fk_facture)); ?></strong></div>
	<div>Orden Óptica: <?php echo dol_escape_htmltag($c->ref); ?></div>
	<div>Fecha: <?php echo dol_print_date($db->jdate($c->fecha_entrega ?: dol_now()), 'dayhour'); ?></div>
	<div>Cliente: <?php echo dol_escape_htmltag($c->cliente ?: 'N/A'); ?></div>
	<?php if (!empty($c->cliente_rif)) echo '<div>RIF/CI: '.dol_escape_htmltag($c->cliente_rif).'</div>'; ?>
	<div class="line"></div>
	<?php if (!empty($c->montura_ref)) : ?>
		<div class="row"><span>Montura <?php echo dol_escape_htmltag($c->montura_ref.' (Cl.'.$c->montura_clase.')'); ?></span><span>$<?php echo number_format((float) $c->precio_montura, 2); ?></span></div>
	<?php endif; ?>
	<?php if (!empty($c->tipo_cristal) || (float) $c->precio_cristales > 0) : ?>
		<div class="row"><span>Cristales: <?php echo dol_escape_htmltag($c->tipo_cristal ?: 'Ópticos'); ?></span><span>$<?php echo number_format((float) $c->precio_cristales, 2); ?></span></div>
	<?php endif; ?>
	<div class="line"></div>
	<?php
	$baseImp = round((float) $c->total / 1.16, 2);
	$iva16 = round((float) $c->total - $baseImp, 2);
	?>
	<div class="row"><span>Base Imponible:</span><span>$<?php echo number_format($baseImp, 2); ?></span></div>
	<div class="row"><span>IVA (16% incl.):</span><span>$<?php echo number_format($iva16, 2); ?></span></div>
	<div class="row bold"><span>TOTAL:</span><span>$<?php echo number_format((float) $c->total, 2); ?></span></div>
	<div class="row"><span>Abonado:</span><span>$<?php echo number_format((float) $c->abono, 2); ?></span></div>
	<div class="row bold"><span>Saldo:</span><span>$<?php echo number_format((float) $c->saldo, 2); ?></span></div>
	<div class="line"></div>
	<div class="center">¡Gracias por su compra!</div>
	<div class="no-print">
		<button type="button" onclick="window.print()">Imprimir Factura</button>
		<button type="button" onclick="if(parent && parent.$ && parent.$.colorbox){ parent.$.colorbox.close(); } else { window.close(); }">Cerrar</button>
	</div>
</body>
</html>
