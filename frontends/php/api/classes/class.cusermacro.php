<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * @package API
 */

class CUserMacro extends CZBXAPI {

	protected $tableName = 'hostmacro';

	protected $tableAlias = 'hm';

	/**
	 * Get UserMacros data
	 *
	 * @param array $options
	 * @param array $options['nodeids'] Node IDs
	 * @param array $options['groupids'] UserMacrosGroup IDs
	 * @param array $options['macroids'] UserMacros IDs
	 * @param boolean $options['monitored_macros'] only monitored UserMacros
	 * @param boolean $options['templated_macros'] include templates in result
	 * @param boolean $options['with_items'] only with items
	 * @param boolean $options['with_monitored_items'] only with monitored items
	 * @param boolean $options['with_historical_items'] only with historical items
	 * @param boolean $options['with_triggers'] only with triggers
	 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
	 * @param boolean $options['with_httptests'] only with http tests
	 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
	 * @param boolean $options['with_graphs'] only with graphs
	 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
	 * @param int $options['count'] count UserMacros, returned column name is rowscount
	 * @param string $options['pattern'] search macros by pattern in macro names
	 * @param int $options['limit'] limit selection
	 * @param string $options['order'] deprecated parameter (for now)
	 * @return array|boolean UserMacros data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('macro');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('macros' => 'hm.hostmacroid'),
			'from'		=> array('hostmacro hm'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$sqlPartsGlobal = array(
			'select'	=> array('macros' => 'gm.globalmacroid'),
			'from'		=> array('globalmacro gm'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'hostmacroids'				=> null,
			'globalmacroids'			=> null,
			'templateids'				=> null,
			'globalmacro'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		elseif (!is_null($options['editable']) && !is_null($options['globalmacro'])) {
			return array();
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where']['hgh'] = 'hg.hostid=hm.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS('.
									' SELECT hgg.groupid'.
									' FROM hosts_groups hgg,rights rr,users_groups gg'.
									' WHERE hgg.hostid=hg.hostid'.
										' AND rr.id=hgg.groupid'.
										' AND rr.groupid=gg.usrgrpid'.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// global macro
		if (!is_null($options['globalmacro'])) {
			$options['groupids'] = null;
			$options['hostmacroids'] = null;
			$options['triggerids'] = null;
			$options['hostids'] = null;
			$options['itemids'] = null;
			$options['selectGroups'] = null;
			$options['selectTemplates'] = null;
			$options['selectHosts'] = null;
		}

		// globalmacroids
		if (!is_null($options['globalmacroids'])) {
			zbx_value2array($options['globalmacroids']);
			$sqlPartsGlobal['where'][] = DBcondition('gm.globalmacroid', $options['globalmacroids']);
		}

		// hostmacroids
		if (!is_null($options['hostmacroids'])) {
			zbx_value2array($options['hostmacroids']);
			$sqlParts['where'][] = DBcondition('hm.hostmacroid', $options['hostmacroids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['groupid'] = 'hg.groupid';
			}
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=hm.hostid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sqlParts['where'][] = DBcondition('hm.hostid', $options['hostids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['templateid'] = 'ht.templateid';
			}
			$sqlParts['from']['macros_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = DBcondition('ht.templateid', $options['templateids']);
			$sqlParts['where']['hht'] = 'hm.hostid=ht.hostid';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hostmacro hm', $options, $sqlParts);
			zbx_db_search('globalmacro gm', $options, $sqlPartsGlobal);
		}

		// filter
		if (is_array($options['filter'])) {
			if (isset($options['filter']['macro'])) {
				zbx_value2array($options['filter']['macro']);

				$sqlParts['where'][] = DBcondition('hm.macro', $options['filter']['macro']);
				$sqlPartsGlobal['where'][] = DBcondition('gm.macro', $options['filter']['macro']);
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['macros'] = 'hm.*';
			$sqlPartsGlobal['select']['macros'] = 'gm.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sqlParts['select'] = array('count(DISTINCT hm.hostmacroid) as rowscount');
			$sqlPartsGlobal['select'] = array('count(DISTINCT gm.globalmacroid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'hm');
		zbx_db_sorting($sqlPartsGlobal, $options, $sortColumns, 'gm');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
			$sqlPartsGlobal['limit'] = $options['limit'];
		}

		// init GLOBALS
		if (!is_null($options['globalmacro'])) {
			$sqlPartsGlobal['select'] = array_unique($sqlPartsGlobal['select']);
			$sqlPartsGlobal['from'] = array_unique($sqlPartsGlobal['from']);
			$sqlPartsGlobal['where'] = array_unique($sqlPartsGlobal['where']);
			$sqlPartsGlobal['order'] = array_unique($sqlPartsGlobal['order']);

			$sqlSelect = '';
			$sqlFrom = '';
			$sqlWhere = '';
			$sqlOrder = '';
			if (!empty($sqlPartsGlobal['select'])) {
				$sqlSelect .= implode(',', $sqlPartsGlobal['select']);
			}
			if (!empty($sqlPartsGlobal['from'])) {
				$sqlFrom .= implode(',', $sqlPartsGlobal['from']);
			}
			if (!empty($sqlPartsGlobal['where'])) {
				$sqlWhere .= ' AND '.implode(' AND ', $sqlPartsGlobal['where']);
			}
			if (!empty($sqlPartsGlobal['order'])) {
				$sqlOrder .= ' ORDER BY '.implode(',', $sqlPartsGlobal['order']);
			}
			$sqlLimit = $sqlPartsGlobal['limit'];

			$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.'
					FROM '.$sqlFrom.'
					WHERE '.DBin_node('gm.globalmacroid', $nodeids).
						$sqlWhere.
						$sqlOrder;
			$res = DBselect($sql, $sqlLimit);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$globalmacroids[$macro['globalmacroid']] = $macro['globalmacroid'];

					if ($options['output'] == API_OUTPUT_SHORTEN) {
						$result[$macro['globalmacroid']] = array('globalmacroid' => $macro['globalmacroid']);
					}
					else {
						if (!isset($result[$macro['globalmacroid']])) {
							$result[$macro['globalmacroid']] = array();
						}
						$result[$macro['globalmacroid']] += $macro;
					}
				}
			}
		}
		// init HOSTS
		else {
			$hostids = array();

			$sqlParts['select'] = array_unique($sqlParts['select']);
			$sqlParts['from'] = array_unique($sqlParts['from']);
			$sqlParts['where'] = array_unique($sqlParts['where']);
			$sqlParts['order'] = array_unique($sqlParts['order']);

			$sqlSelect = '';
			$sqlFrom = '';
			$sqlWhere = '';
			$sqlOrder = '';
			if (!empty($sqlParts['select'])) {
				$sqlSelect .= implode(',', $sqlParts['select']);
			}
			if (!empty($sqlParts['from'])) {
				$sqlFrom .= implode(',', $sqlParts['from']);
			}
			if (!empty($sqlParts['where'])) {
				$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
			}
			if (!empty($sqlParts['order'])) {
				$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
			}
			$sqlLimit = $sqlParts['limit'];

			$sql = 'SELECT '.$sqlSelect.'
					FROM '.$sqlFrom.'
					WHERE '.DBin_node('hm.hostmacroid', $nodeids).
						$sqlWhere.
						$sqlOrder;
			$res = DBselect($sql, $sqlLimit);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$hostmacroids[$macro['hostmacroid']] = $macro['hostmacroid'];

					if ($options['output'] == API_OUTPUT_SHORTEN) {
						$result[$macro['hostmacroid']] = $macro['hostmacroid'];
					}
					else {
						$hostids[$macro['hostid']] = $macro['hostid'];

						if (!isset($result[$macro['hostmacroid']])) {
							$result[$macro['hostmacroid']]= array();
						}

						// groups
						if ($options['selectGroups'] && !isset($result[$macro['hostmacroid']]['groups'])) {
							$result[$macro['hostmacroid']]['groups'] = array();
						}

						// templates
						if ($options['selectTemplates'] && !isset($result[$macro['hostmacroid']]['templates'])) {
							$result[$macro['hostmacroid']]['templates'] = array();
						}

						// hosts
						if ($options['selectHosts'] && !isset($result[$macro['hostmacroid']]['hosts'])) {
							$result[$macro['hostmacroid']]['hosts'] = array();
						}

						// groupids
						if (isset($macro['groupid'])) {
							if (!isset($result[$macro['hostmacroid']]['groups'])) {
								$result[$macro['hostmacroid']]['groups'] = array();
							}
							$result[$macro['hostmacroid']]['groups'][] = array('groupid' => $macro['groupid']);
							unset($macro['groupid']);
						}

						// templateids
						if (isset($macro['templateid'])) {
							if (!isset($result[$macro['hostmacroid']]['templates'])) {
								$result[$macro['hostmacroid']]['templates'] = array();
							}
							$result[$macro['hostmacroid']]['templates'][] = array('templateid' => $macro['templateid']);
							unset($macro['templateid']);
						}

						// hostids
						if (isset($macro['hostid'])) {
							if (!isset($result[$macro['hostmacroid']]['hosts'])) {
								$result[$macro['hostmacroid']]['hosts'] = array();
							}
							$result[$macro['hostmacroid']]['hosts'][] = array('hostid' => $macro['hostid']);
						}
						$result[$macro['hostmacroid']] += $macro;
					}
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding groups
		if (!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectGroups'],
				'hostids' => $hostids,
				'preservekeys' => true
			);
			$groups = API::HostGroup()->get($objParams);
			foreach ($groups as $group) {
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach ($ghosts as $host) {
					foreach ($result as $macroid => $macro) {
						if (bccomp($macro['hostid'], $host['hostid']) == 0) {
							$result[$macroid]['groups'][] = $group;
						}
					}
				}
			}
		}

		// adding templates
		if (!is_null($options['selectTemplates']) && str_in_array($options['selectTemplates'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectTemplates'],
				'hostids' => $hostids,
				'preservekeys' => true
			);
			$templates = API::Template()->get($objParams);
			foreach ($templates as $template) {
				$thosts = $template['hosts'];
				unset($template['hosts']);
				foreach ($thosts as $host) {
					foreach ($result as $macroid => $macro) {
						if (bccomp($macro['hostid'], $host['hostid']) == 0) {
							$result[$macroid]['templates'][] = $template;
						}
					}
				}
			}
		}

		// adding hosts
		if (!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectHosts'],
				'hostids' => $hostids,
				'preservekeys' => true
			);
			$hosts = API::Host()->get($objParams);
			foreach ($hosts as $hostid => $host) {
				foreach ($result as $macroid => $macro) {
					if (bccomp($macro['hostid'], $hostid) == 0) {
						$result[$macroid]['hosts'][] = $host;
					}
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

/**
 * Delete UserMacros
 *
 * @param array $hostmacroids
 * @param array $hostmacroids['hostmacroids']
 * @return boolean
 */
	public function deleteHostMacro($hostmacroids) {
		$hostmacroids = zbx_toArray($hostmacroids);

		if (empty($hostmacroids))
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ hostmacroids ]');

// permissions + existance
		$options = array(
			'hostmacroids' => $hostmacroids,
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$dbHMacros = $this->get($options);

		foreach ($hostmacroids as $hostmacroid) {
			if (!isset($dbHMacros[$hostmacroid]))
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
//--------

		$sql = 'DELETE FROM hostmacro WHERE '.DBcondition('hostmacroid', $hostmacroids);
		if (!DBExecute($sql))
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		return array('hostmacroids' => $hostmacroids);
	}

/**
 * Add global macros.
 *
 * @param array $macros
 * @param string $macros[0..]['macro']
 * @param string $macros[0..]['value']
 * @return array
 */
	public function createGlobal(array $macros) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create global macros.'));
		}

		$macros = zbx_toArray($macros);

		$this->validateGlobal($macros);

		$globalmacroids = DB::insert('globalmacro', $macros);

		return array('globalmacroids' => $globalmacroids);
	}


	/**
	 * Updates global macros.
	 *
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function updateGlobal(array $globalmacros) {
		$globalmacros = zbx_toArray($globalmacros);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update global macros.'));
		}

		$this->validateGlobal($globalmacros);

		// existence
		$ids = zbx_objectValues($globalmacros, 'globalmacroid');
		$dbGmacros = $this->get(array(
			'globalmacroids' => $ids,
			'globalmacro' => true,
			'editable' => true,
			'output'=> API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($globalmacros as $gmacro) {
			// check if the macro has an id
			if (!isset($gmacro['globalmacroid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
			// check if the macro exists in the DB
			if (!isset($dbGmacros[$gmacro['globalmacroid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro with globalmacroid "%1$s" does not exist.', $gmacro['globalmacroid']));
			}
		}

		// update macros
		$data = array();
		foreach ($globalmacros as $gmacro) {
			$globalmacroid = $gmacro['globalmacroid'];
			unset($gmacro['globalmacroid']);

			$data[] = array(
				'values'=> $gmacro,
				'where'=> array('globalmacroid' => $globalmacroid)
			);
		}
		DB::update('globalmacro', $data);

		return array('globalmacroids' => $ids);
	}


	/**
	 * Delete global macros.
	 *
	 * @param mixed $globalmacroids
	 *
	 * @return array
	 */
	public function deleteGlobal($globalmacroids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete global macros.'));
		}

		$globalmacroids = zbx_toArray($globalmacroids);

		if (empty($globalmacroids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// existence
		$dbGmacros = $this->get(array(
			'globalmacroids' => $globalmacroids,
			'globalmacro' => true,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		));
		foreach ($globalmacroids as $gmacroId) {
			if (!isset($dbGmacros[$gmacroId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Global macro with globalmacroid "%1$s" does not exist.', $gmacroId));
			}
		}

		// delete macros
		DB::delete('globalmacro', array('globalmacroid' => $globalmacroids));

		return array('globalmacroids' => $globalmacroids);
	}

	/**
	 * Performs global macro validation.
	 *
	 * @param array $macros
	 */
	protected function validateGlobal(array $macros) {
		$this->validate($macros);

		// check for duplicate names
		$nameMacro = zbx_toHash($macros, 'macro');
		$macroNames = zbx_objectValues($macros, 'macro');
		if ($macroNames) {
			$options = array(
				'globalmacro' => true,
				'filter' => array(
					'macro' => $macroNames
				),
				'output' => API_OUTPUT_EXTEND
			);
			$dbMacros = $this->get($options);
			foreach ($dbMacros as $dbMacro) {
				$macro = $nameMacro[$dbMacro['macro']];
				if (!isset($macro['globalmacroid']) || bccomp($macro['globalmacroid'], $dbMacro['globalmacroid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists.', $dbMacro['macro']));
				}
			}
		}
	}

/**
 * Add Macros to Hosts
 *
 * @param array $data
 * @param array $data['templates']
 * @param array $data['hosts']
 * @param array $data['macros']
 * @return boolean
 */
	public function massAdd($data) {
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : array();
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : array();

		$hostids = zbx_objectValues($hosts, 'hostid');
		$templateids = zbx_objectValues($templates, 'templateid');

		if (!isset($data['macros']) || empty($data['macros'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ macros ]');
		}
		elseif (empty($hosts) && empty($templates)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ hosts ] or [ templates ]');
		}

		// Host permission
		if (!empty($hosts)) {
			$updHosts = API::Host()->get(array(
				'hostids' => $hostids,
				'editable' => true,
				'output' => array('hostid', 'name'),
				'preservekeys' => true
			));
			foreach ($hosts as $host) {
				if (!isset($updHosts[$host['hostid']])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
		}

		// Template permission
		if (!empty($templates)) {
			$updTemplates = API::Template()->get(array(
				'templateids' => $templateids,
				'editable' => true,
				'output' => array('hostid', 'name'),
				'preservekeys' => true
			));
			foreach ($templates as $template) {
				if (!isset($updTemplates[$template['templateid']])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
		}

		// Check on existing
		$objectids = array_merge($hostids, $templateids);
		$existingMacros = $this->get(array(
			'hostids' => $objectids,
			'filter' => array('macro' => zbx_objectValues($data['macros'], 'macro')),
			'output' => API_OUTPUT_EXTEND,
			'limit' => 1
		));
		foreach ($existingMacros as $exstMacro) {
			if (isset($updHosts[$exstMacro['hostid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists on "%2$s".',
						$exstMacro['macro'], $updHosts[$exstMacro['hostid']]['name']));
			}
			elseif (isset($updTemplates[$exstMacro['hostid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists on "%2$s".',
						$exstMacro['macro'], $updTemplates[$exstMacro['hostid']]['name']));
			}
		}

		self::validate($data['macros']);

		$insertData = array();
		foreach ($data['macros'] as $macro) {
			foreach ($objectids as $hostid) {
				$insertData[] = array(
					'hostid' => $hostid,
					'macro' => $macro['macro'],
					'value' => $macro['value']
				);
			}
		}

		$hostmacroids = DB::insert('hostmacro', $insertData);

		return array('hostmacroids' => $hostmacroids);
	}

	/**
	 * Validates the input parameters for the massRemove method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostMacroIds
	 */
	protected function validateMassRemove(array $hostMacroIds) {
		if (!$hostMacroIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$dbHostMacros = $this->get(array(
			'hostmacroids' => $hostMacroIds,
			'output' => API_OUTPUT_EXTEND
		));

		// check if the macros exist
		$this->validateHostMacrosExistIn($hostMacroIds, $dbHostMacros);

		// check permissions for all affected hosts
		$this->validateHostPermissions(zbx_objectValues($dbHostMacros, 'hostid'));
	}

	/**
	 * Remove Macros from Hosts
	 *
	 * @param mixed $hostMacroIds
	 *
	 * @return boolean
	 */
	public function massRemove($hostMacroIds) {
		$hostMacroIds = zbx_toArray($hostMacroIds);

		$this->validateMassRemove($hostMacroIds);
		DB::delete('hostmacro', array('hostmacroid' => $hostMacroIds));

		return array('hostmacroids' => $hostMacroIds);
	}

	/**
	 * Validates the input parameters for the massUpdate method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostMacros
	 */
	protected function validateMassUpdate(array $hostMacros) {
		foreach ($hostMacros as $macro) {
			$this->validateHostMacroId($macro);
		}

		// make sure we have all the data we need
		$hostMacros = $this->extendObjects($this->tableName(), $hostMacros, array('macro', 'hostid'));

		foreach ($hostMacros as $hostMacro) {
			if (isset($hostMacro['macro'])) {
				$this->validateMacro($hostMacro);
			}
			if (isset($hostMacro['value'])) {
				$this->validateValue($hostMacro);
			}
			if (isset($hostMacro['hostid'])) {
				$this->validateHostId($hostMacro);
			}
		}

		$this->validateDuplicateMacros($hostMacros);

		$dbHostMacros = $this->get(array(
			'hostmacroids' => zbx_objectValues($hostMacro, 'hostmacroid'),
			'output' => API_OUTPUT_EXTEND
		));

		// check if the macros exist
		$this->validateHostMacrosExistIn(zbx_objectValues($hostMacros, 'hostmacroid'), $dbHostMacros);

		// check permissions for all affected hosts
		$affectedHostIds = array_merge(zbx_objectValues($dbHostMacros, 'hostid'), zbx_objectValues($hostMacro, 'hostid'));
		$this->validateHostPermissions($affectedHostIds);

		$this->validateHostMacrosDontRepeat($hostMacros);
	}

	/**
	 * Update host macros
	 *
	 * @param array $hostMacros an array of host macros
	 *
	 * @return boolean
	 */
	public function massUpdate($hostMacros) {
		$hostMacros = zbx_toArray($hostMacros);

		$this->validateMassUpdate($hostMacros);

		$hostMacroIds = array();
		$dataUpdate = array();
		foreach ($hostMacros as $macro) {
			$hostMacroId = $macro['hostmacroid'];
			unset($macro['hostmacroid']);

			$dataUpdate[] = array(
				'values' => $macro,
				'where' => array('hostmacroid' => $hostMacroId)
			);

			$hostMacroIds[] = $hostMacroId;
		}

		DB::update('hostmacro', $dataUpdate);

		return array('hostmacroids' => $hostMacroIds);
	}

// TODO: should be private
	public function getMacros($data) {
		$macros = $data['macros'];
		$itemid = isset($data['itemid']) ? $data['itemid'] : null;
		$triggerid = isset($data['triggerid']) ? $data['triggerid'] : null;

		zbx_value2array($macros);
		$macros = array_unique($macros);

		$result = array();

		$objOptions = array(
			'itemids' => $itemid,
			'triggerids' => $triggerid,
			'nopermissions' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_SHORTEN,
			'templated_hosts' => true,
		);
		$hosts = API::Host()->get($objOptions);
		$hostids = array_keys($hosts);

		do{
			$objOptions = array(
				'hostids' => $hostids,
				'macros' => $macros,
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => 1,
				'preservekeys' => 1,
			);
			$hostMacros = $this->get($objOptions);
			order_result($hostMacros, 'hostid');

			foreach ($macros as $mnum => $macro) {
				foreach ($hostMacros as $hmnum => $hmacro) {
					if ($macro == $hmacro['macro']) {
						$result[$macro] = $hmacro['value'];
						unset($hostMacros[$hmnum], $macros[$mnum]);
						break;
					}
				}
			}

			if (!empty($macros)) {
				$objOptions = array(
					'hostids' => $hostids,
					'nopermissions' => 1,
					'preservekeys' => 1,
					'output' => API_OUTPUT_SHORTEN,
				);
				$hosts = API::Template()->get($objOptions);
				$hostids = array_keys($hosts);
			}
		}while (!empty($macros) && !empty($hostids));


		if (!empty($macros)) {
			$objOptions = array(
				'output' => API_OUTPUT_EXTEND,
				'globalmacro' => 1,
				'nopermissions' => 1,
				'macros' => $macros
			);
			$gmacros = $this->get($objOptions);

			foreach ($macros as $macro) {
				foreach ($gmacros as $mid => $gmacro) {
					if ($macro == $gmacro['macro']) {
						$result[$macro] = $gmacro['value'];
						unset($gmacros[$mid]);
						break;
					}
				}
			}
		}

		return $result;
	}

	public function resolveTrigger($triggers) {
		$single = false;
		if (isset($triggers['triggerid'])) {
			$single = true;
			$triggers = array($triggers);
		}

		foreach ($triggers as $num => $trigger) {
			if (!isset($trigger['triggerid']) || !isset($trigger['expression'])) continue;

			if ($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $trigger['expression'], $arr)) {
				$macros = $this->getMacros(array('macros' => $arr[1], 'triggerid' => $trigger['triggerid']));

				$search = array_keys($macros);
				$values = array_values($macros);

				$triggers[$num]['expression'] = str_replace($search, $values, $trigger['expression']);
			}
		}

		if ($single) $triggers = reset($triggers);
		return $triggers;
	}


	public function resolveItem($items) {
		$single = false;
		if (isset($items['itemid'])) {
			$single = true;
			$items = array($items);
		}

		foreach ($items as $num => $item) {
			if (!isset($item['itemid']) || !isset($item['key_'])) continue;

			if ($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $item['key_'], $arr)) {
				$macros = $this->getMacros(array('macros' => $arr[1],'itemid' => $item['itemid']));

				$search = array_keys($macros);
				$values = array_values($macros);
				$items[$num]['key_'] = str_replace($search, $values, $item['key_']);
			}
		}

		if ($single) $items = $items[0];

		return $items;
	}

	/**
	 * Validates the "macro" field.
	 *
	 * @throws APIException if the field is empty, too long or doesn't match the ZBX_PREG_EXPRESSION_USER_MACROS
	 * regex.
	 *
	 * @param array $macro
	 */
	protected function validateMacro(array $macro) {
		if (!isset($macro['macro']) || zbx_empty($macro['macro'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty macro.'));
		}
		if (zbx_strlen($macro['macro']) > 64) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro name "%1$s" is too long, it should not exceed 64 chars.', $macro['macro']));
		}
		if (!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $macro['macro'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong macro "%1$s".', $macro['macro']));
		}
	}

	/**
	 * Validate the "value" field.
	 *
	 * @throws APIException if the field is empty or too long.
	 *
	 * @param array $macro
	 */
	protected function validateValue(array $macro) {
		if (!isset($macro['value']) || zbx_empty($macro['value'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Empty value for macro "%1$s".', $macro['macro']));
		}
		if (zbx_strlen($macro['value']) > 255) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" value is too long, it should not exceed 255 chars.', $macro['macro']));
		}
	}

	/**
	 * Validates the "hostid" field.
	 *
	 * @throw APIException if the field is empty.
	 *
	 * @param array $macro
	 */
	protected function validateHostId(array $macro) {
		if (!isset($macro['hostid']) || zbx_empty($macro['hostid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('No host given for macro "%1$s".', $macro['macro']));
		}
	}

	/**
	 * Validates the "hostmacroid" field.
	 *
	 * @throw APIException if the field is empty.
	 *
	 * @param array $macro
	 */
	protected function validateHostMacroId(array $macro) {
		if (!isset($macro['hostmacroid']) || zbx_empty($macro['hostmacroid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts
	 *
	 * @param array $hostIds    an array of host or template IDs
	 */
	protected function validateHostPermissions(array $hostIds) {
		// host permission
		$hosts = API::Host()->get(array(
			'hostids' => $hostIds,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		));

		// template permission
		$templates = API::Template()->get(array(
			'templateids' => $hostIds,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		));
		foreach ($hostIds as $hostId) {
			if (!isset($templates[$hostId]) && !isset($hosts[$hostId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}
	}

	/**
	 * Checks if the given macros contain duplicates. Assumes the "macro" field is valid.
	 *
	 * @throws APIException if the given macros contain duplicates
	 *
	 * @param array $macros
	 */
	protected function validateDuplicateMacros(array $macros) {
		$existingMacros = array();
		foreach ($macros as $macro) {
			if (isset($existingMacros[$macro['macro']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s is not unique."', $macro['macro']));
			}

			$existingMacros[$macro['macro']] = 1;
		}
	}

	/**
	 * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
	 * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
	 * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
	 *
	 * @throws APIException if any of the given macros already exist
	 *
	 * @param array $hostMacros
	 */
	protected function validateHostMacrosDontRepeat(array $hostMacros) {
		$dbHostMacros = $this->select($this->tableName(), array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => array(
				'macro' => zbx_objectValues($hostMacros, 'macro'),
				'hostid' => zbx_objectValues($hostMacros, 'hostid')
			)
		));

		foreach ($hostMacros as $hostMacro) {
			foreach ($dbHostMacros as $dbHostMacro) {
				$differentMacros = ((isset($hostMacro['hostmacroid'])
					&& bccomp($hostMacro['hostmacroid'], $dbHostMacro['hostmacroid']) != 0)
					|| !isset($hostMacro['hostmacroid']));

				if ($hostMacro['macro'] == $dbHostMacro['macro'] && bccomp($hostMacro['hostid'], $dbHostMacro['hostid']) == 0
					&& $differentMacros) {

					$hosts = $this->select('hosts', array(
						'output' => array('name'),
						'hostids' => $hostMacro['hostid']
					));
					$host = reset($hosts);
					$error = _s('Macro "%1$s" already exists on "%2$s".', $hostMacro['macro'], $host['name']);
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
	}

	/**
	 * Checks if all of the host macros with hostmacrosids given in $hostMacrosIds are present in $hostMacros.
	 * Assumes the "hostmacroid" field is valid.
	 *
	 * @throws APIException if any of the host macros is not present in $hostMacros
	 *
	 * @param array $hostMacrosIds
	 * @param array $hostMacros
	 */
	protected function validateHostMacrosExistIn(array $hostMacrosIds, array $hostMacros) {
		$hostMacros = zbx_toHash($hostMacros, 'hostmacroid');
		foreach ($hostMacrosIds as $hostMacroId) {
			if (!isset($hostMacros[$hostMacroId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro with hostmacroid "%1$s" does not exist.', $hostMacroId));
			}
		}
	}

}
?>
