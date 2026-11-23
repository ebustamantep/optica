<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');

$langs->loadLangs(array("optica@optica", "products"));
if (!$user->hasRight('optica', 'read')) accessforbidden();

$action = GETPOST('action', 'aZ09');
$clases = optica_get_clases_montura();
optica_ensure_schema($db);

if ($action === 'add' && $user->hasRight('optica', 'write')) {
	$ref = GETPOST('ref', 'alphanohtml');
	$label = GETPOST('label', 'alphanohtml');
	$clase = GETPOST('clase', 'aZ');
	$marca = GETPOST('marca', 'alphanohtml');
	$color = GETPOST('color', 'alphanohtml');
	$stock = GETPOSTINT('stock');
	$precio = isset($clases[$clase]) ? $clases[$clase]['precio'] : 60.00;

	if (!empty($ref) && !empty($label)) {
		$fk_product = optica_sync_product($db, $user, $ref, $label, $clase, $precio, $stock);
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."optica_montura (entity, ref, label, clase, marca, color, precio, stock, fk_product, active, datec) VALUES (";
		$sql .= ((int) $conf->entity).", '".$db->escape($ref)."', '".$db->escape($label)."', '".$db->escape($clase)."', '".$db->escape($marca)."', '".$db->escape($color)."', ".((float) $precio).", ".((int) $stock).", ".($fk_product > 0 ? (int) $fk_product : "NULL").", 1, '".$db->idate(dol_now())."')";
		if ($db->query($sql)) {
			setEventMessages('Montura agregada y sincronizada con Productos (IVA 16% incluido).', null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'reponer_lote' && $user->hasRight('optica', 'write')) {
	$cants = GETPOST('cant', 'array');
	$nota = GETPOST('nota_reposicion', 'alphanohtml') ?: 'Reposición de stock desde módulo Óptica';
	$actualizadas = 0;

	if (is_array($cants)) {
		foreach ($cants as $mid => $qty) {
			$mid = (int) $mid;
			$qty = (int) $qty;
			if ($mid > 0 && $qty > 0) {
				$resM = $db->query("SELECT fk_product, stock FROM ".MAIN_DB_PREFIX."optica_montura WHERE rowid = ".$mid." AND entity = ".((int) $conf->entity));
				if ($resM && ($objM = $db->fetch_object($resM))) {
					$db->query("UPDATE ".MAIN_DB_PREFIX."optica_montura SET stock = stock + ".$qty." WHERE rowid = ".$mid);
					if ((int) $objM->fk_product > 0) {
						optica_adjust_product_stock($db, $user, (int) $objM->fk_product, $qty, $nota);
					}
					$actualizadas++;
				}
			}
		}
	}
	if ($actualizadas > 0) {
		setEventMessages('Reposición aplicada a '.$actualizadas.' montura(s) y sincronizada con el almacén.', null, 'mesgs');
	} else {
		setEventMessages('Ingresa al menos una cantidad mayor a 0 para reponer.', null, 'warnings');
	}
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'adj_stock' && $user->hasRight('optica', 'write')) {
	$mid = GETPOSTINT('mid');
	$delta = GETPOSTINT('delta');
	if ($mid > 0 && $delta != 0) {
		$resM = $db->query("SELECT fk_product, stock FROM ".MAIN_DB_PREFIX."optica_montura WHERE rowid = ".((int) $mid));
		if ($resM && ($objM = $db->fetch_object($resM))) {
			$nuevoStock = max(0, (int) $objM->stock + (int) $delta);
			$realDelta = $nuevoStock - (int) $objM->stock;
			if ($realDelta != 0) {
				$db->query("UPDATE ".MAIN_DB_PREFIX."optica_montura SET stock = ".((int) $nuevoStock)." WHERE rowid = ".((int) $mid));
				if ((int) $objM->fk_product > 0) {
					optica_adjust_product_stock($db, $user, (int) $objM->fk_product, $realDelta, 'Ajuste manual desde módulo Óptica');
				}
			}
		}
	}
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', 'Óptica - Monturas (Stock)');
print load_fiche_titre('Inventario de Monturas por Clase', '', 'fa-glasses');

print '<p class="opacitymedium">Las monturas manejan stock y se clasifican en 4 tipos: <strong>Clase A ($60)</strong>, <strong>Clase B ($50)</strong>, <strong>Clase C ($40)</strong> y <strong>Clase D ($30)</strong>. Los cristales no manejan stock.</p>';

if ($user->hasRight('optica', 'write')) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="background:#f9f9f9; padding:12px; border:1px solid #ddd; border-radius:5px; margin-bottom:16px;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<strong>Nueva Montura:</strong> ';
	print 'Ref: <input type="text" name="ref" required placeholder="MON-A01" style="width:90px;"> ';
	print 'Modelo: <input type="text" name="label" required placeholder="Ray-Ban Aviator" style="width:160px;"> ';
	print 'Clase: <select name="clase">';
	foreach ($clases as $k => $v) print '<option value="'.$k.'">'.$v['label'].'</option>';
	print '</select> ';
	print 'Marca: <input type="text" name="marca" placeholder="Marca" style="width:100px;"> ';
	print 'Color: <input type="text" name="color" placeholder="Negro" style="width:80px;"> ';
	print 'Stock: <input type="number" name="stock" value="10" min="0" style="width:65px;"> ';
	print '<input type="submit" class="button" value="Agregar">';
	print '</form>';
}

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."optica_montura WHERE entity = ".((int) $conf->entity)." ORDER BY clase ASC, ref ASC";
$res = $db->query($sql);

if ($user->hasRight('optica', 'write')) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="reponer_lote">';
	print '<div style="margin-bottom:8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">';
	print '<strong>Formulario de Reposición de Stock:</strong> ';
	print 'Nota/Documento: <input type="text" name="nota_reposicion" placeholder="Ej. Llegada lote / Factura proveedor" class="minwidth300"> ';
	print '<input type="submit" class="button" value="Aplicar Reposición">';
	print '</div>';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Ref</th><th>Modelo</th><th class="center">Clase</th><th>Marca</th><th>Color</th><th class="right">Precio</th><th class="center">Stock Actual</th>';
if ($user->hasRight('optica', 'write')) {
	print '<th class="center">Cantidad a Reponer (+)</th><th class="center">Ajuste Rápido</th>';
}
print '</tr>';
if ($res && $db->num_rows($res) > 0) {
	while ($m = $db->fetch_object($res)) {
		print '<tr class="oddeven">';
		print '<td><strong>'.dol_escape_htmltag($m->ref).'</strong>'.($m->fk_product ? ' <small>(<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$m->fk_product.'">Producto</a>)</small>' : '').'</td>';
		print '<td>'.dol_escape_htmltag($m->label).'</td>';
		print '<td class="center"><strong>Clase '.$m->clase.'</strong></td>';
		print '<td>'.dol_escape_htmltag($m->marca ?: '-').'</td>';
		print '<td>'.dol_escape_htmltag($m->color ?: '-').'</td>';
		print '<td class="right">$'.number_format((float) $m->precio, 2).'</td>';
		print '<td class="center"><strong>'.((int) $m->stock).'</strong></td>';
		if ($user->hasRight('optica', 'write')) {
			print '<td class="center"><input type="number" name="cant['.$m->rowid.']" min="0" value="0" style="width:70px; text-align:center;"></td>';
			print '<td class="center"><a class="button small" href="'.$_SERVER['PHP_SELF'].'?action=adj_stock&mid='.$m->rowid.'&delta=-1&token='.newToken().'">-1</a></td>';
		}
		print '</tr>';
	}
} else {
	print '<tr><td colspan="9" class="opacitymedium center">No hay monturas registradas.</td></tr>';
}
print '</table>';

if ($user->hasRight('optica', 'write')) {
	print '<div class="right" style="margin-top:10px;"><input type="submit" class="button" value="Aplicar Reposición"></div>';
	print '</form>';
}

llxFooter();
$db->close();
