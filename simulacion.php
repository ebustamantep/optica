<?php
/* Copyright (C) 2026 Optica Module */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

dol_include_once('/optica/lib/optica.lib.php');
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$langs->loadLangs(array("optica@optica", "companies"));
if (!$user->hasRight('optica', 'write')) accessforbidden();

$action = GETPOST('action', 'aZ09');

if ($action === 'seed') {
	optica_ensure_schema($db);
	// 1. Crear monturas de ejemplo (Clases A, B, C, D)
	$monturasDemo = array(
		array('ref' => 'MON-A01', 'label' => 'Titanio Ejecutivo', 'clase' => 'A', 'marca' => 'Ray-Ban', 'color' => 'Negro', 'precio' => 60.00, 'stock' => 10),
		array('ref' => 'MON-B01', 'label' => 'Acetato Clásico', 'clase' => 'B', 'marca' => 'Oakley', 'color' => 'Carey', 'precio' => 50.00, 'stock' => 12),
		array('ref' => 'MON-C01', 'label' => 'Metal Ligero', 'clase' => 'C', 'marca' => 'Vogue', 'color' => 'Plata', 'precio' => 40.00, 'stock' => 15),
		array('ref' => 'MON-D01', 'label' => 'TR90 Económica', 'clase' => 'D', 'marca' => 'Genérica', 'color' => 'Azul', 'precio' => 30.00, 'stock' => 20),
	);
	$monturaIds = array();
	foreach ($monturasDemo as $m) {
		$fk_product = optica_sync_product($db, $user, $m['ref'], $m['label'], $m['clase'], $m['precio'], $m['stock']);
		$resChk = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."optica_montura WHERE entity = ".((int) $conf->entity)." AND ref = '".$db->escape($m['ref'])."'");
		if ($resChk && $db->num_rows($resChk) > 0) {
			$objM = $db->fetch_object($resChk);
			$monturaIds[$m['clase']] = (int) $objM->rowid;
			if ($fk_product > 0) {
				$db->query("UPDATE ".MAIN_DB_PREFIX."optica_montura SET fk_product = ".((int) $fk_product)." WHERE rowid = ".((int) $objM->rowid)." AND (fk_product IS NULL OR fk_product = 0)");
			}
		} else {
			$db->query("INSERT INTO ".MAIN_DB_PREFIX."optica_montura (entity, ref, label, clase, marca, color, precio, stock, fk_product, active, datec) VALUES (".((int) $conf->entity).", '".$db->escape($m['ref'])."', '".$db->escape($m['label'])."', '".$db->escape($m['clase'])."', '".$db->escape($m['marca'])."', '".$db->escape($m['color'])."', ".((float) $m['precio']).", ".((int) $m['stock']).", ".($fk_product > 0 ? (int) $fk_product : "NULL").", 1, '".$db->idate(dol_now())."')");
			$monturaIds[$m['clase']] = (int) $db->last_insert_id(MAIN_DB_PREFIX."optica_montura");
		}
	}

	// 2. Crear clientes de ejemplo en Dolibarr
	$clientesDemo = array(
		array('nom' => 'María Pérez (Demo Óptica)', 'phone' => '0414-1234567'),
		array('nom' => 'Carlos Rodríguez (Demo Óptica)', 'phone' => '0412-7654321'),
		array('nom' => 'Ana Gómez (Demo Óptica)', 'phone' => '0424-9876543'),
	);
	$socIds = array();
	foreach ($clientesDemo as $cd) {
		$resSoc = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom = '".$db->escape($cd['nom'])."' AND entity = ".((int) $conf->entity));
		if ($resSoc && $db->num_rows($resSoc) > 0) {
			$socIds[] = (int) $db->fetch_object($resSoc)->rowid;
		} else {
			$soc = new Societe($db);
			$soc->name = $cd['nom'];
			$soc->phone = $cd['phone'];
			$soc->client = 1;
			$sid = $soc->create($user);
			if ($sid > 0) $socIds[] = $sid;
		}
	}

	// 3. Crear 3 consultas de ejemplo en distintos estados
	if (count($socIds) >= 3) {
		$consultasDemo = array(
			array(
				'fk_soc' => $socIds[0], 'fk_montura' => $monturaIds['A'], 'precio_montura' => 60.00,
				'od_esf' => '-1.50', 'od_cil' => '-0.50', 'od_eje' => '90', 'od_add' => '', 'od_av' => '20/20', 'od_dnp' => '31',
				'oi_esf' => '-1.25', 'oi_cil' => '-0.25', 'oi_eje' => '85', 'oi_add' => '', 'oi_av' => '20/20', 'oi_dnp' => '31',
				'dp' => '62', 'altura' => '18', 'obs' => 'Miopía leve, uso permanente',
				'tipo_cristal' => 'Monofocal Antirreflejo Blue Block', 'desc_cristal' => 'Policarbonato 1.59', 'precio_cristales' => 45.00,
				'abono' => 50.00, 'estado' => 2 // Lista para entregar y facturar
			),
			array(
				'fk_soc' => $socIds[1], 'fk_montura' => $monturaIds['B'], 'precio_montura' => 50.00,
				'od_esf' => '+1.00', 'od_cil' => '-0.75', 'od_eje' => '180', 'od_add' => '+2.00', 'od_av' => '20/25', 'od_dnp' => '32',
				'oi_esf' => '+1.25', 'oi_cil' => '-0.50', 'oi_eje' => '175', 'oi_add' => '+2.00', 'oi_av' => '20/25', 'oi_dnp' => '32',
				'dp' => '64', 'altura' => '20', 'obs' => 'Presbicia + astigmatismo',
				'tipo_cristal' => 'Progresivo Fotocromático', 'desc_cristal' => 'Índice 1.60 Transitions', 'precio_cristales' => 90.00,
				'abono' => 70.00, 'estado' => 1 // En laboratorio
			),
			array(
				'fk_soc' => $socIds[2], 'fk_montura' => $monturaIds['D'], 'precio_montura' => 30.00,
				'od_esf' => '-0.75', 'od_cil' => '0.00', 'od_eje' => '0', 'od_add' => '', 'od_av' => '20/20', 'od_dnp' => '30',
				'oi_esf' => '-0.75', 'oi_cil' => '0.00', 'oi_eje' => '0', 'oi_add' => '', 'oi_av' => '20/20', 'oi_dnp' => '30',
				'dp' => '60', 'altura' => '17', 'obs' => 'Primera vez usando lentes',
				'tipo_cristal' => 'Monofocal CR-39', 'desc_cristal' => 'Antirreflejo estándar', 'precio_cristales' => 25.00,
				'abono' => 20.00, 'estado' => 0 // En consulta
			),
		);

		foreach ($consultasDemo as $cd) {
			$ref = optica_next_ref($db, $conf->entity);
			$total = $cd['precio_montura'] + $cd['precio_cristales'];
			$saldo = max(0, $total - $cd['abono']);
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."optica_consulta (entity, ref, fk_soc, fecha_consulta, od_esf, od_cil, od_eje, od_add, od_av, od_dnp, oi_esf, oi_cil, oi_eje, oi_add, oi_av, oi_dnp, dp, altura, observaciones, fk_montura, precio_montura, tipo_cristal, descripcion_cristal, precio_cristales, total, abono, saldo, estado, fk_user_author, datec) VALUES (";
			$sql .= ((int) $conf->entity).", '".$db->escape($ref)."', ".((int) $cd['fk_soc']).", '".$db->idate(dol_now())."',";
			$sql .= " '".$db->escape($cd['od_esf'])."', '".$db->escape($cd['od_cil'])."', '".$db->escape($cd['od_eje'])."', '".$db->escape($cd['od_add'])."', '".$db->escape($cd['od_av'])."', '".$db->escape($cd['od_dnp'])."',";
			$sql .= " '".$db->escape($cd['oi_esf'])."', '".$db->escape($cd['oi_cil'])."', '".$db->escape($cd['oi_eje'])."', '".$db->escape($cd['oi_add'])."', '".$db->escape($cd['oi_av'])."', '".$db->escape($cd['oi_dnp'])."',";
			$sql .= " '".$db->escape($cd['dp'])."', '".$db->escape($cd['altura'])."', '".$db->escape($cd['obs'])."',";
			$sql .= " ".((int) $cd['fk_montura']).", ".((float) $cd['precio_montura']).",";
			$sql .= " '".$db->escape($cd['tipo_cristal'])."', '".$db->escape($cd['desc_cristal'])."', ".((float) $cd['precio_cristales']).",";
			$sql .= " ".((float) $total).", ".((float) $cd['abono']).", ".((float) $saldo).", ".((int) $cd['estado']).", ".((int) $user->id).", '".$db->idate(dol_now())."')";
			if ($db->query($sql)) {
				$cid = $db->last_insert_id(MAIN_DB_PREFIX."optica_consulta");
				$db->query("INSERT INTO ".MAIN_DB_PREFIX."optica_abono (entity, fk_consulta, fecha_pago, monto, metodo_pago, nota, fk_user, datec) VALUES (".((int) $conf->entity).", ".((int) $cid).", '".$db->idate(dol_now())."', ".((float) $cd['abono']).", 'Efectivo', 'Abono inicial (Simulación)', ".((int) $user->id).", '".$db->idate(dol_now())."')");
			}
		}
	}

	setEventMessages('Simulación creada: 4 monturas (A/B/C/D), 3 clientes y 3 consultas de ejemplo.', null, 'mesgs');
	header("Location: ".dol_buildpath('/optica/index.php', 1));
	exit;
}

llxHeader('', 'Óptica - Simulación');
print load_fiche_titre('Simulación con Datos de Ejemplo', '', 'fa-flask');

print '<div class="info">';
print '<p>Esta herramienta genera datos de ejemplo para probar el flujo completo del módulo <strong>Óptica</strong>:</p>';
print '<ul>';
print '<li><strong>4 Monturas con stock</strong>: Clase A ($60), Clase B ($50), Clase C ($40) y Clase D ($30).</li>';
print '<li><strong>3 Clientes</strong> con sus datos ópticos completos (OD/OI, DP, Altura).</li>';
print '<li><strong>3 Consultas</strong> en distintos estados (En consulta, En laboratorio, y Lista para entregar y facturar) con abonos parciales registrados.</li>';
print '</ul>';
print '</div>';

print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=seed&token='.newToken().'">Cargar Datos de Simulación</a>';
print '<a class="butAction" href="'.dol_buildpath('/optica/index.php', 1).'">Ir al Panel de Óptica</a>';
print '</div>';

llxFooter();
$db->close();
