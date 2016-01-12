<?php

/**
 * @file InteractiveConnector.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

//
// Class aliases.
use TooBasic\Managers\DBManager;

/**
 * @class WorkflowManager
 */
class WorkflowManager extends \TooBasic\Managers\Manager {
	//
	// Protected properties.
	protected $_db = false;
	protected $_dbprefix = false;
	protected $_factories = false;
	protected $_log = false;
	//
	// Public methods.
	public function activeFlows($type = false, $id = false) {
		$out = $this->representation->item_flows(false, 'TooBasic\\Workflows')->activeFlows();

		if($type !== false && $id !== false) {
			foreach($out as $k => $v) {
				if($v->type != $type && $v->item != $id) {
					unset($out[$k]);
				}
			}
		}

		return $out;
	}
	public function getWorkflow($workflowName) {
		return WorkflowsFactory::Instance()->{$workflowName}($this->_log);
	}
	public function inject(Item $item, $workflowName, &$error = false) {
		$out = true;

		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Injecting into workflow '{$workflowName}'. Item: ".json_encode($item->toArray()));

		$workflow = $this->getWorkflow($workflowName);
		if($workflow) {
			$prefixes = array(
				GC_DBQUERY_PREFIX_TABLE => $this->_dbprefix,
				GC_DBQUERY_PREFIX_COLUMN => 'ifl_'
			);
			$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
				'workflow' => $workflowName,
				'type' => $item->type(),
				'item' => $item->id(),
				'status' => 'tmp'
				), $prefixes);
			$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);

			$injected = false;
			foreach(array(WKFL_ITEM_FLOW_STATUS_OK, WKFL_ITEM_FLOW_STATUS_WAIT) as $status) {
				$query[GC_AFIELD_PARAMS]['status'] = $status;
				$stmt->execute($query[GC_AFIELD_PARAMS]);
				if($stmt->rowCount() > 0) {
					$injected = true;
					break;
				}
			}

			if($injected) {
				$error = 'Item already injected and running';
				$out = false;
			} else {

				$query = $this->_db->queryAdapter()->insert('wkfl_item_flows', array(
					'workflow' => $workflowName,
					'type' => $item->type(),
					'item' => $item->id()
					), $prefixes);
				$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
				$out = boolval($stmt->execute($query[GC_AFIELD_PARAMS]));
			}
		} else {
			$out = false;
			$error = "Unknown workflow '{$workflowName}'";
		}

		if(!$out) {
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, $error);
		}

		return $out;
	}
	public function injectDirect($type, $id, $workflowName, &$error = false) {
		$out = true;

		$this->loadFactories();
		if(!isset($this->_factories[$type])) {
			$error = "Unknown item type '{$type}'";
			$out = false;
		} else {
			$item = $this->_factories[$type]->item($id);
			if($item) {
				$out = $this->inject($item, $workflowName, $error);
			} else {
				$error = "Unknown item with id '{$id}'";
				$out = false;
			}
		}

		return $out;
	}
	public function run(ItemFlowRepresentation $flow, &$error = false) {
		$out = true;

		$this->loadFactories();

		$workflow = $this->getWorkflow($flow->workflow);
		$item = $this->_factories[$flow->type]->item($flow->item);

		$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Running flow: '.json_encode($flow->toArray()));
		$this->_log->log(LGGR_LOG_LEVEL_INFO, '        item: '.json_encode($item->toArray()));

		$continue = true;
		while($continue && $flow->status == WKFL_ITEM_FLOW_STATUS_OK) {
			$continue = $workflow->runFor($flow, $item);
			$flow->load($flow->id);
			$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Current flow status: '.json_encode($flow->toArray()));
			$this->_log->log(LGGR_LOG_LEVEL_INFO, '        item status: '.json_encode($item->toArray()));
			$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Continue?: '.($continue ? 'Yes' : 'No'));
		}

		return $out;
	}
	public function wakeUpItems() {
		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Waking up items in status 'WAIT'.");

		$prefixes = array(
			GC_DBQUERY_PREFIX_TABLE => $this->_dbprefix,
			GC_DBQUERY_PREFIX_COLUMN => 'ifl_'
		);
		$query = $this->_db->queryAdapter()->update('wkfl_item_flows', array(
			'status' => WKFL_ITEM_FLOW_STATUS_OK
			), array(
			'status' => WKFL_ITEM_FLOW_STATUS_WAIT
			), $prefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
		return boolval($stmt->execute($query[GC_AFIELD_PARAMS]));
	}
	//
	// Protected methods.
	protected function init() {
		parent::init();

		$this->_db = DBManager::Instance()->getDefault();
		$this->_dbprefix = $this->_db->prefix();
		$this->_log = $this->log->startLog('workflows');
		$this->_log->separator();
	}
	protected function loadFactories() {
		if($this->_factories === false) {
			$this->_factories = array();
			//
			// Global dependencies.
			global $WKFLDefaults;
			foreach($WKFLDefaults[WKFL_DEFAULTS_FACTORIES] as $factoryName) {
				$factory = $this->representation->{$factoryName};
				if($factory && $factory instanceof ItemsFactory) {
					$this->_factories[$factory->type()] = $factory;
				}
			}

			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Loaded factories: ".implode(', ', array_keys($this->_factories)).'.');
		}
	}
}
