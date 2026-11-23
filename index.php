<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "companies", "bills"));
if (!$user->hasRight('optica', 'read')) accessforbidden();

llxHeader('', 'Óptica - Resumen');
print load_fiche_titre('Módulo Óptica - Panel General', '', 'fa-eye');

// KPIs
$totalConsultas = 0;
$pendientes = 0;
$saldoPendiente = 0;
$stockMonturas = 0;

$res1 = $db->query("SELECT COUNT(*) as cnt, SUM(CASE WHEN estado < 3 THEN 1 ELSE 0 END) as pend, SUM(CASE WHEN estado < 3 THEN saldo ELSE 0 END) as saldo FROM ".MAIN_DB_PREFIX."optica_consulta WHERE entity = ".((int) $conf->entity));
if ($res1 && ($o = $db->fetch_object($res1))) {
	$totalConsultas = (int) $o->cnt;
	$pendientes = (int) $o->pend;
	$saldoPendiente = (float) $o->saldo;
}
$res2 = $db->query("SELECT SUM(stock) as stk FROM ".MAIN_DB_PREFIX."optica_montura WHERE entity = ".((int) $conf->entity)." AND active = 1");
if ($res2 && ($o2 = $db->fetch_object($res2))) {
	$stockMonturas = (int) $o2->stk;
}

print '<div class="optica-cards">';
print '<div class="optica-card"><div class="optica-card-title">Total Consultas</div><div class="optica-card-value">'.$totalConsultas.'</div></div>';
print '<div class="optica-card"><div class="optica-card-title">En Proceso / Por Entregar</div><div class="optica-card-value">'.$pendientes.'</div></div>';
print '<div class="optica-card"><div class="optica-card-title">Saldo Pendiente</div><div class="optica-card-value">$'.number_format($saldoPendiente, 2).'</div></div>';
print '<div class="optica-card"><div class="optica-card-title">Stock Monturas</div><div class="optica-card-value">'.$stockMonturas.'</div></div>';
print '</div>';

print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_buildpath('/optica/consulta_card.php?action=create', 1).'">+ Nueva Consulta</a>';
print '<a class="butAction" href="'.dol_buildpath('/optica/monturas.php', 1).'">Gestionar Monturas</a>';
print '<a class="butAction" href="'.dol_buildpath('/optica/cristales.php', 1).'">Cristales (Servicios)</a>';
print '<a class="butAction" href="'.dol_buildpath('/optica/clientes.php', 1).'">Clientes Ópticos</a>';
print '<a class="butAction" href="'.dol_buildpath('/optica/reporte_caja.php', 1).'">Reporte de Caja</a>';
print '<a class="butAction" href="'.dol_buildpath('/optica/simulacion.php', 1).'">Simulación / Datos Demo</a>';
print '</div>';

// Últimas consultas
$estados = optica_get_estados();
$sql = "SELECT c.rowid, c.ref, c.fecha_consulta, c.total, c.abono, c.saldo, c.estado, s.nom as cliente, m.ref as montura_ref, m.clase as montura_clase";
$sql .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."optica_montura m ON m.rowid = c.fk_montura";
$sql .= " WHERE c.entity = ".((int) $conf->entity);
$sql .= " ORDER BY c.rowid DESC LIMIT 15";

$resList = $db->query($sql);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Ref</th><th>Fecha</th><th>Cliente</th><th>Montura</th><th class="right">Total</th><th class="right">Abono</th><th class="right">Saldo</th><th class="center">Estado</th>';
print '</tr>';
if ($resList && $db->num_rows($resList) > 0) {
	while ($obj = $db->fetch_object($resList)) {
		print '<tr class="oddeven">';
		print '<td><a href="'.dol_buildpath('/optica/consulta_card.php?id='.$obj->rowid, 1).'"><strong>'.dol_escape_htmltag($obj->ref).'</strong></a></td>';
		print '<td>'.dol_print_date($db->jdate($obj->fecha_consulta), 'day').'</td>';
		print '<td>'.dol_escape_htmltag($obj->cliente ?: 'N/A').'</td>';
		print '<td>'.($obj->montura_ref ? dol_escape_htmltag($obj->montura_ref.' (Clase '.$obj->montura_clase.')') : '-').'</td>';
		print '<td class="right">$'.number_format((float) $obj->total, 2).'</td>';
		print '<td class="right">$'.number_format((float) $obj->abono, 2).'</td>';
		print '<td class="right"><strong>$'.number_format((float) $obj->saldo, 2).'</strong></td>';
		print '<td class="center"><span class="optica-badge optica-badge-'.((int) $obj->estado).'">'.($estados[(int) $obj->estado] ?? '').'</span></td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="8" class="opacitymedium center">No hay consultas registradas aún. Puedes cargar datos de ejemplo en <a href="'.dol_buildpath('/optica/simulacion.php', 1).'">Simulación / Datos Demo</a>.</td></tr>';
}
print '</table>';

llxFooter();
$db->close();
