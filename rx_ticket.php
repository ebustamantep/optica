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

$sql = "SELECT c.*, s.nom as cliente, s.phone as cliente_tel FROM ".MAIN_DB_PREFIX."optica_consulta c LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc WHERE c.rowid = ".((int) $id);
$res = $db->query($sql);
if (!$res || $db->num_rows($res) == 0) die("Consulta no encontrada");
$c = $db->fetch_object($res);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket Fórmula Óptica <?php echo dol_escape_htmltag($c->ref); ?></title>
<style>
	@page { margin: 4mm; size: 80mm auto; }
	body { font-family: 'Courier New', monospace; font-size: 11px; width: 100%; max-width: 74mm; margin: 0 auto; padding: 6px; color: #000; background: #fff; box-sizing: border-box; }
	.center { text-align: center; }
	.line { border-top: 1px dashed #000; margin: 6px 0; }
	.bold { font-weight: bold; }
	table { width: 100%; border-collapse: collapse; font-size: 10px; }
	th, td { border: 1px solid #000; padding: 2px 3px; text-align: center; }
	.no-print { margin-top: 12px; text-align: center; }
	@media print { .no-print { display: none; } }
</style>
</head>
<body onload="window.print()">
	<div class="center bold"><?php echo dol_escape_htmltag($mysoc->name ?: 'ÓPTICA'); ?></div>
	<div class="center bold">FÓRMULA ÓPTICA (RX)</div>
	<div class="line"></div>
	<div>Orden: <strong><?php echo dol_escape_htmltag($c->ref); ?></strong></div>
	<div>Fecha: <?php echo dol_print_date($db->jdate($c->fecha_consulta), 'day'); ?></div>
	<div>Cliente: <?php echo dol_escape_htmltag($c->cliente ?: 'N/A'); ?></div>
	<div class="line"></div>
	<table>
		<tr><th>Ojo</th><th>ESF</th><th>CIL</th><th>EJE</th><th>ADD</th><th>AV</th></tr>
		<tr><td><strong>OD</strong></td><td><?php echo dol_escape_htmltag($c->od_esf ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->od_cil ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->od_eje ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->od_add ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->od_av ?: '-'); ?></td></tr>
		<tr><td><strong>OI</strong></td><td><?php echo dol_escape_htmltag($c->oi_esf ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->oi_cil ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->oi_eje ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->oi_add ?: '-'); ?></td><td><?php echo dol_escape_htmltag($c->oi_av ?: '-'); ?></td></tr>
	</table>
	<div style="margin-top:5px;">DNP OD: <?php echo dol_escape_htmltag($c->od_dnp ?: '-'); ?> | DNP OI: <?php echo dol_escape_htmltag($c->oi_dnp ?: '-'); ?></div>
	<div>DP Total: <?php echo dol_escape_htmltag($c->dp ?: '-'); ?> | Altura: <?php echo dol_escape_htmltag($c->altura ?: '-'); ?></div>
	<?php if (!empty($c->tipo_cristal)) echo '<div>Cristal: '.dol_escape_htmltag($c->tipo_cristal).'</div>'; ?>
	<?php if (!empty($c->observaciones)) echo '<div>Obs: '.dol_escape_htmltag($c->observaciones).'</div>'; ?>
	<div class="line"></div>
	<div class="no-print">
		<button type="button" onclick="window.print()">Imprimir</button>
		<button type="button" onclick="if(parent && parent.$ && parent.$.colorbox){ parent.$.colorbox.close(); } else { window.close(); }">Cerrar</button>
	</div>
</body>
</html>
