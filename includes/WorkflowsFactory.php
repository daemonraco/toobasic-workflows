<?php

/**
 * @file WorkflowsFactory.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

//
// Class aliases.
use TooBasic\Logs\AbstractLog;

/**
 * @class WorkflowsFactory
 * @todo doc
 */
class WorkflowsFactory extends \TooBasic\Singleton {
	//
	// Protected properties.
	/**
	 * @var \TooBasic\Workflows\Workflow[string] List of already loaded
	 * workflows.
	 */
	protected $_workflow = array();
	//
	// Magic methods.
	/**
	 * @todo doc
	 * @param type $name
	 * @return type
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function __get($name) {
		return $this->get($name);
	}
	/**
	 * @todo doc
	 * @param type $name
	 * @param type $args
	 * @return type
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function __call($name, $args) {
		if(count($args) < 1) {
			throw new WorkflowsException("Log not specified for workflow '{$name}'.");
		}
		return $this->get($name, $args[0]);
	}
	//
	// Public methods.
	/**
	 * @todo doc
	 *
	 * @param string $name
	 * @param \TooBasic\Logs\AbstractLog $log
	 * @return \TooBasic\Workflows\Workflow 
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function get($name, AbstractLog $log) {
		$out = false;

		if(isset($this->_workflow[$name])) {
			$out = $this->_workflow[$name];
		} else {
			$this->_workflow[$name] = new Workflow($name);
			$this->_workflow[$name]->setLog($log);

			if($this->_workflow[$name]->status()) {
				$out = $this->_workflow[$name];
			} else {
				throw new WorkflowsException("Unable to load workflow '{$name}'.");
			}
		}

		return $out;
	}
}
