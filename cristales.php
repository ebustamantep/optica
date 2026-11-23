<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "products"));
if (!$user->hasRight('optica', 'read')) accessforbidden();
optica_ensure_schema($db);

$action = GETPOST('action', 'aZ09');

if ($action === 'add_service' && $user->hasRight('optica', 'write')) {
	$ref = trim(GETPOST('ref', 'alphanohtml'));
	$label = trim(GETPOST('label', 'alphanohtml'));
	$precio = (float) GETPOST('precio', 'alphanohtml');

	if (!empty($ref) && !empty($label) && $precio >= 0) {
		$pid = optica_sync_cristal_service($db, $user, $ref, $label, $precio);
		if ($pid > 0) {
			setEventMessages('Servicio de cristal registrado en Productos/Servicios (#'.$pid.').', null, 'mesgs');
		} else {
			setEventMessages('No se pudo crear el servicio (verifica si la referencia ya existe).', null, 'errors');
		}
	}
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'seed_catalogo' && $user->hasRight('optica', 'write')) {
	$creados = optica_seed_cristales_services($db, $user);
	setEventMessages('Catálogo sincronizado: '.$creados.' servicio(s) nuevo(s) creado(s) en Dolibarr.', null, 'mesgs');
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', 'Óptica - Cristales (Servicios)');
print load_fiche_titre('Catálogo de Cristales (Servicios sin Stock)', '', 'fa-concierge-bell');

print '<p class="opacitymedium">Los cristales se registran como <strong>Servicios</strong> nativos en Dolibarr (IVA 16% incluido, en venta y en compra, sin manejo de stock).</p>';

if ($user->hasRight('optica', 'write')) {
	print '<div style="margin-bottom:12px;">';
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=seed_catalogo&token='.newToken().'">Cargar Catálogo Estándar de Cristales (19 Servicios)</a>';
	print '</div>';

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="background:#f9f9f9; padding:12px; border:1px solid #ddd; border-radius:5px; margin-bottom:16px;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add_service">';
	print '<strong>Nuevo Servicio de Cristal:</strong> ';
	print 'Ref: <input type="text" name="ref" required placeholder="VSANREL" style="width:110px;"> ';
	print 'Nombre: <input type="text" name="label" required placeholder="VS AntiReflejos Terminados" class="minwidth250"> ';
	print 'Precio (IVA 16% incl.): <input type="number" step="0.01" name="precio" required placeholder="45.00" style="width:90px;"> ';
	print '<input type="submit" class="button" value="Registrar Servicio">';
	print '</form>';
}

$servicios = optica_get_servicios_cristales($db);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Ref</th><th>Nombre del Servicio (Cristal)</th><th class="right">Base Imponible</th><th class="right">IVA (%)</th><th class="right">Precio Final (IVA incl.)</th><th class="center">Ficha Dolibarr</th></tr>';
if (count($servicios) > 0) {
	foreach ($servicios as $s) {
		print '<tr class="oddeven">';
		print '<td><strong>'.dol_escape_htmltag($s->ref).'</strong></td>';
		print '<td>'.dol_escape_htmltag($s->label).'</td>';
		print '<td class="right">$'.number_format((float) $s->price, 2).'</td>';
		print '<td class="right">'.number_format((float) $s->tva_tx, 2).'%</td>';
		print '<td class="right"><strong>$'.number_format((float) $s->precio_final, 2).'</strong></td>';
		print '<td class="center"><a class="button small" href="'.DOL_URL_ROOT.'/product/card.php?id='.$s->rowid.'">Ver Servicio</a></td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="6" class="opacitymedium center">No hay servicios de cristales registrados. Haz clic en "Cargar Catálogo Estándar de Cristales (19 Servicios)".</td></tr>';
}
print '</table>';

llxFooter();
$db->close();
