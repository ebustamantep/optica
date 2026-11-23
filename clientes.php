<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "companies"));
if (!$user->hasRight('optica', 'read')) accessforbidden();

$search = GETPOST('search', 'alphanohtml');

llxHeader('', 'Óptica - Clientes Ópticos');
print load_fiche_titre('Clientes con Historial Óptico', '', 'fa-users');

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:12px;">';
print '<input type="text" name="search" value="'.dol_escape_htmltag($search).'" placeholder="Buscar cliente por nombre o teléfono..." class="minwidth300"> ';
print '<input type="submit" class="button" value="Buscar">';
print '</form>';

$sql = "SELECT s.rowid as socid, s.nom, s.phone, s.email, COUNT(c.rowid) as num_consultas, MAX(c.fecha_consulta) as ultima_fecha,";
$sql .= " SUM(CASE WHEN c.estado < 3 THEN c.saldo ELSE 0 END) as saldo_pendiente";
$sql .= " FROM ".MAIN_DB_PREFIX."societe s";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."optica_consulta c ON c.fk_soc = s.rowid AND c.entity = ".((int) $conf->entity);
if (!empty($search)) {
	$sql .= " WHERE (s.nom LIKE '%".$db->escape($search)."%' OR s.phone LIKE '%".$db->escape($search)."%')";
}
$sql .= " GROUP BY s.rowid, s.nom, s.phone, s.email";
$sql .= " ORDER BY ultima_fecha DESC";

$res = $db->query($sql);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Cliente</th><th>Teléfono</th><th>Última Consulta</th><th>Última Fórmula (OD / OI)</th><th class="center">Consultas</th><th class="right">Saldo Pendiente</th><th class="center">Acciones</th>';
print '</tr>';

if ($res && $db->num_rows($res) > 0) {
	while ($obj = $db->fetch_object($res)) {
		// Obtener última fórmula del cliente
		$rxHtml = '-';
		$resRx = $db->query("SELECT rowid, od_esf, od_cil, od_eje, oi_esf, oi_cil, oi_eje, dp FROM ".MAIN_DB_PREFIX."optica_consulta WHERE fk_soc = ".((int) $obj->socid)." ORDER BY rowid DESC LIMIT 1");
		if ($resRx && ($rx = $db->fetch_object($resRx))) {
			$rxHtml = '<strong>OD:</strong> '.dol_escape_htmltag($rx->od_esf ?: '0').' / '.dol_escape_htmltag($rx->od_cil ?: '0').' x '.dol_escape_htmltag($rx->od_eje ?: '0°');
			$rxHtml .= '<br><strong>OI:</strong> '.dol_escape_htmltag($rx->oi_esf ?: '0').' / '.dol_escape_htmltag($rx->oi_cil ?: '0').' x '.dol_escape_htmltag($rx->oi_eje ?: '0°');
			if (!empty($rx->dp)) $rxHtml .= ' <small>(DP: '.dol_escape_htmltag($rx->dp).')</small>';
		}
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$obj->socid.'">'.dol_escape_htmltag($obj->nom).'</a></td>';
		print '<td>'.dol_escape_htmltag($obj->phone ?: '-').'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->ultima_fecha), 'day').'</td>';
		print '<td>'.$rxHtml.'</td>';
		print '<td class="center">'.((int) $obj->num_consultas).'</td>';
		print '<td class="right"><strong>$'.number_format((float) $obj->saldo_pendiente, 2).'</strong></td>';
		print '<td class="center"><a class="button small" href="'.dol_buildpath('/optica/consulta_card.php?action=create&fk_soc='.$obj->socid, 1).'">+ Nueva Consulta</a></td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="7" class="opacitymedium center">No hay clientes con consultas ópticas registradas.</td></tr>';
}
print '</table>';

llxFooter();
$db->close();
