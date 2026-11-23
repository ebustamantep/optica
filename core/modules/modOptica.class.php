<?php
/* Copyright (C) 2026 Optica Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Optica
 */
class modOptica extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 958900;
		$this->rights_class = 'optica';
		$this->family = "crm";
		$this->module_position = '95';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "OpticaDescription";
		$this->descriptionlong = "OpticaDescriptionLong";
		$this->editor_name = 'Optica';
		$this->editor_url = '';
		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-eye';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array('/optica/css/optica.css'),
			'js' => array('/optica/js/optica.js'),
			'hooks' => array(),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0,
		);

		$this->dirs = array('/optica/temp');
		$this->config_page_url = array();

		$this->hidden = getDolGlobalInt('MODULE_OPTICA_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("optica@optica");
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(18, 0);

		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = 'Leer consultas, clientes y monturas de Optica';
		$this->rights[$r][4] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = 'Crear y modificar consultas, abonos, monturas y facturar';
		$this->rights[$r][4] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero + 3;
		$this->rights[$r][1] = 'Eliminar registros de Optica';
		$this->rights[$r][4] = 'delete';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'OpticaMenu',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'optica',
			'leftmenu' => '',
			'url' => '/optica/index.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		// Left menus
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaDashboard',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_dashboard',
			'url' => '/optica/index.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaConsultas',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_consultas',
			'url' => '/optica/consulta_list.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica,fk_leftmenu=optica_consultas',
			'type' => 'left',
			'titre' => 'OpticaNuevaConsulta',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_nueva_consulta',
			'url' => '/optica/consulta_card.php?action=create',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "write")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaClientes',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_clientes',
			'url' => '/optica/clientes.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaMonturas',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_monturas',
			'url' => '/optica/monturas.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaCristales',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_cristales',
			'url' => '/optica/cristales.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaReporteCaja',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_reporte_caja',
			'url' => '/optica/reporte_caja.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=optica',
			'type' => 'left',
			'titre' => 'OpticaSimulacion',
			'mainmenu' => 'optica',
			'leftmenu' => 'optica_simulacion',
			'url' => '/optica/simulacion.php',
			'langs' => 'optica@optica',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("optica")',
			'perms' => '$user->hasRight("optica", "write")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options when enabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/optica/sql/');
		if ($result < 0) {
			return -1;
		}
		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options when disabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
