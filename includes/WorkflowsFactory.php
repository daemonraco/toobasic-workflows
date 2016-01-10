<?php

namespace TooBasic\Workflows;

/**
 * @class WorkflowsFactory
 */
class WorkflowsFactory extends \TooBasic\Singleton {
	//
	// Protected properties.
	protected $_workflow = array();
	//
	// Magic methods.
	public function __get($name) {
		return $this->get($name);
	}
	//
	// Public methods.
	public function get($name) {
		$out = false;

		if(isset($this->_workflow[$name])) {
			$out = $this->_workflow[$name];
		} else {
/////@todo controlar que exista sino excepcion.
			$this->_workflow[$name] = new Workflow($name);
			if($this->_workflow[$name]->status()) {
				$out = $this->_workflow[$name];
			}
		}

		return $out;
	}
}
