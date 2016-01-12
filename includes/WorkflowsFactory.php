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
 * This class provides a centralized access to all known workflow configuration.
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
	 * This method provides access to any workflow using its name.
	 *
	 * @param string $name Name of the workflow to look for.
	 * @return \TooBasic\Workflows\Workflow Returns a workflow pointer.
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function __get($name) {
		return $this->get($name);
	}
	/**
	 * This method provides access to any workflow using its name and also
	 * provides a way to set the log to use.
	 * This method can be replaces by '__get()' unless it's the first time a
	 * workflow is called.
	 *
	 * @param string $name Name of the workflow to look for.
	 * @param mixed[string] $args Here only the first parameter is important
	 * and it should be a log pointer (check toobasic-logger module).
	 * @return \TooBasic\Workflows\Workflow Returns a workflow pointer.
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function __call($name, $args) {
		//
		// Checking parameters.
		if(count($args) < 1) {
			throw new WorkflowsException("Log not specified for workflow '{$name}'.");
		}
		//
		// Forwarding call.
		return $this->get($name, $args[0]);
	}
	//
	// Public methods.
	/**
	 * This method provides access to any workflow using its name. When it's
	 * the first time a workflow is called, this method must be called
	 * providing a second parameters with a log pointer (check toobasic-logger
	 * module).
	 *
	 * @param string $name Name of the workflow to look for.
	 * @param \TooBasic\Logs\AbstractLog $log Log porinter.
	 * @return \TooBasic\Workflows\Workflow Returns a workflow pointer.
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function get($name, AbstractLog $log = null) {
		//
		// Default values.
		$out = false;
		//
		// Checking it the request workflows was already loaded.
		if(isset($this->_workflow[$name])) {
			$out = $this->_workflow[$name];
		} else {
			//
			// Checking log parameter.
			if($log === null) {
				throw new WorkflowsException("First call of a workflow always requires a log pointer.");
			}
			//
			// Creating an object to manage the request workflow.
			$this->_workflow[$name] = new Workflow($name);
			//
			// Setting where the workflow should log information.
			$this->_workflow[$name]->setLog($log);
			//
			// Checking loaded workflow status.
			if($this->_workflow[$name]->status()) {
				//
				// Storing its pointer for future calles.
				$out = $this->_workflow[$name];
			} else {
				throw new WorkflowsException("Unable to load workflow '{$name}'.");
			}
		}

		return $out;
	}
}
