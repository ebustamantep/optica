<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "companies", "bills"));
if (!$user->hasRight('optica', 'read')) accessforbidden();

$search_ref = GETPOST('search_ref', 'alphanohtml');
$search_estado = GETPOST('search_estado', 'intcomma');

llxHeader('', 'Óptica - Consultas y Órdenes');
print load_fiche_titre('Consultas y Órdenes Ópticas', '', 'fa-file-medical');

print '<div class="tabsAction" style="margin-bottom:10px;">';
if ($user->hasRight('optica', 'write')) {
	print '<a class="butAction" href="'.dol_buildpath('/optica/consulta_card.php?action=create', 1).'">+ Nueva Consulta</a>';
}
print '</div>';

$estados = optica_get_estados();
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:12px;">';
print 'Ref / Cliente: <input type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" class="minwidth200"> ';
print 'Estado: <select name="search_estado"><option value="">-- Todos --</option>';
foreach ($estados as $k => $v) {
	$sel = ($search_estado !== '' && (int) $search_estado === $k) ? ' selected' : '';
	print '<option value="'.$k.'"'.$sel.'>'.$v.'</option>';
}
print '</select> ';
print '<input type="submit" class="button" value="Filtrar">';
print '</form>';

$sql = "SELECT c.*, s.nom as cliente, m.ref as montura_ref, m.clase as montura_clase";
$sql .= " FROM ".MAIN_DB_PREFIX."optica_consulta c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."optica_montura m ON m.rowid = c.fk_montura";
$sql .= " WHERE c.entity = ".((int) $conf->entity);
if (!empty($search_ref)) {
	$sql .= " AND (c.ref LIKE '%".$db->escape($search_ref)."%' OR s.nom LIKE '%".$db->escape($search_ref)."%')";
}
if ($search_estado !== '') {
	$sql .= " AND c.estado = ".((int) $search_estado);
}
$sql .= " ORDER BY c.rowid DESC";

$res = $db->query($sql);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Ref</th><th>Fecha</th><th>Cliente</th><th>Montura</th><th>Cristal</th><th class="right">Total</th><th class="right">Abono</th><th class="right">Saldo</th><th class="center">Estado</th><th class="center">Factura</th>';
print '</tr>';

if ($res && $db->num_rows($res) > 0) {
	while ($obj = $db->fetch_object($res)) {
		print '<tr class="oddeven">';
		print '<td><a href="'.dol_buildpath('/optica/consulta_card.php?id='.$obj->rowid, 1).'"><strong>'.dol_escape_htmltag($obj->ref).'</strong></a></td>';
		print '<td>'.dol_print_date($db->jdate($obj->fecha_consulta), 'day').'</td>';
		print '<td>'.dol_escape_htmltag($obj->cliente ?: 'N/A').'</td>';
		print '<td>'.($obj->montura_ref ? dol_escape_htmltag($obj->montura_ref.' (Clase '.$obj->montura_clase.')') : '-').'</td>';
		print '<td>'.dol_escape_htmltag($obj->tipo_cristal ?: '-').'</td>';
		print '<td class="right">$'.number_format((float) $obj->total, 2).'</td>';
		print '<td class="right">$'.number_format((float) $obj->abono, 2).'</td>';
		print '<td class="right"><strong>$'.number_format((float) $obj->saldo, 2).'</strong></td>';
		print '<td class="center"><span class="optica-badge optica-badge-'.((int) $obj->estado).'">'.($estados[(int) $obj->estado] ?? '').'</span></td>';
		print '<td class="center">'.($obj->fk_facture ? '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->fk_facture.'">Ver Factura</a>' : '-').'</td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="10" class="opacitymedium center">No se encontraron consultas.</td></tr>';
}
print '</table>';

llxFooter();
$db->close();
