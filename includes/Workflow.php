<?php

/**
 * @file Workflow.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

//
// Class aliases.
use TooBasic\MagicProp;
use TooBasic\Names;
use TooBasic\Paths;

/**
 * @class Workflow
 * This class represents a workflow configuration and it's able to execute it on
 * flowing items.
 */
class Workflow {
	//
	// Protected properties.
	/**
	 * @var \stdClass Current workflow loaded configuration.
	 */
	protected $_config = false;
	/**
	 * @var string Current workflow name.
	 */
	protected $_name = false;
	/**
	 * @var \TooBasic\Log Log shortcut.
	 */
	protected $_log = false;
	/**
	 * @var \TooBasic\MagicProp MagicProp shortcut.
	 */
	protected $_magic = false;
	/**
	 * @var boolean Current workflow status. It usually change after loading.
	 */
	protected $_status = false;
	//
	// Magic methods.
	/**
	 * Class constructor.
	 *
	 * @param string $name Name to assign to the created workflow
	 * representation.
	 */
	public function __construct($name) {
		//
		// Saving name.
		$this->_name = $name;
		//
		// Log shortcut.
		$this->_log = $this->magic()->log->workflows;
		//
		// Loading configuration.
		$this->load();
	}
	//
	// Public methods.
	/**
	 * This method provides access to this workflow's name.
	 *
	 * @return string Returns a name.
	 */
	public function name() {
		return $this->_name;
	}
	/**
	 * This method is the one in charge of executing a step of a specific
	 * item, analyze results and set the next step or errors.
	 *
	 * @param \TooBasic\Workflows\ItemFlowRepresentation $flow Item flow to be
	 * executed.
	 * @param \TooBasic\Workflows\Item $item Item to be analyzed
	 * @return boolean Returns TRUE the current step pointed the execution of
	 * a next step and the flow is not in a stopping status.
	 * @throws \TooBasic\Workflows\WorkflowsException
	 */
	public function runFor(ItemFlowRepresentation $flow, Item $item) {
		//
		// Default values.
		$continue = true;
		//
		// Global dependencies.
		global $WKFLDefaults;
		//
		// Guessing the current step name.
		$currentStep = $flow->step;
		if(!$currentStep) {
			//
			// Getting the fist step from workflow when there's none.
			$currentStep = $this->_config->startsAt;
			$flow->step = $currentStep;
			$flow->persist();
		}
		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Current step: '{$currentStep}'");
		//
		// Shortcuts.
		$stepConfig = $this->_config->steps->{$currentStep};
		$stepPath = Paths::Instance()->customPaths($WKFLDefaults[WKFL_DEFAULTS_PATHS][WKFL_DEFAULTS_PATH_STEPS], Names::SnakeFilename($stepConfig->manager), Paths::ExtensionPHP);
		$stepClass = Names::ClassNameWithSuffix($currentStep, WKFL_CLASS_SUFFIX_STEP);
		$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "        path: '{$stepPath}'");
		$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "       class: '{$stepClass}'");
		$this->_log->log(LGGR_LOG_LEVEL_DEBUG, '      config: '.json_encode($stepConfig));
		//
		// Checking if the step's class is already loaded.
		if(!class_exists($stepClass)) {
			//
			// Checking class code file.
			if(!$stepPath || !is_readable($stepPath)) {
				$msg = "Unable to access class for step '{$currentStep}'.";
				$this->_log->log(LGGR_LOG_LEVEL_FATAL, $msg);
				throw new WorkflowsException($msg);
			}
			require_once $stepPath;
		}
		//
		// Checking class existence.
		if(!class_exists($stepClass)) {
			$msg = "Class '{$stepClass}' not defined.";
			$this->_log->log(LGGR_LOG_LEVEL_FATAL, $msg);
			throw new WorkflowsException($msg);
		}
		//
		// Getting a step execution class.
		$step = new $stepClass($item);
		//
		// Step execution
		$step->execute();
		//
		// Reloading item to avoid outdated data.
		$item->reload();
		//
		// Checking that thers a connection configuration for the current
		// item status.
		$connection = false;
		if(isset($stepConfig->connections->{$item->status()})) {
			$connection = $stepConfig->connections->{$item->status()};
		} else {
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Unkwnon connection for item status '{$item->status()}'.");
			$continue = false;
			$flow->status = WKFL_ITEM_FLOW_STATUS_FAILED;
			$flow->persist();
		}
		//
		// Checking if there's a connection configuration to analyze.
		if($connection) {
			//
			// Checking what action should be taken.
			if(isset($connection->step) || isset($connection->status)) {
				//
				// Setting the next step to take.
				if(isset($connection->step)) {
					$flow->step = $connection->step;
				}
				//
				// Setting new flows status.
				if(isset($connection->status)) {
					$flow->status = $connection->status;
					$continue = false;
				}
			} else {
				$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Neither step not status configuration for connection on status '{$item->status()}'.");
				$continue = false;
				$flow->status = WKFL_ITEM_FLOW_STATUS_FAILED;
			}
			//
			// If the flow has been set to wait, it should check if
			// the waiting has conditions.
			if($flow->status == WKFL_ITEM_FLOW_STATUS_WAIT && isset($connection->wait)) {
				//
				// Checking configuration.
				if(!isset($connection->wait->attempts) || !isset($connection->wait->status)) {
					$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Waiting configuration is incorrect.");
					$continue = false;
					$flow->status = WKFL_ITEM_FLOW_STATUS_FAILED;
				} else {
					//
					// Incrising the attempts count.
					$flow->attempts++;
					//
					// Checking it already tried to many
					// times.
					if($flow->attempts > $connection->wait->attempts) {
						//
						// Changing flow status and
						// resetting counts.
						$flow->status = $connection->wait->status;
						$flow->attempts = 0;
						$this->_log->log(LGGR_LOG_LEVEL_INFO, "Maximum attempts reached ({$connection->wait->attempts} attempts), changing flow status to '{$connection->wait->status}'.");
					} else {
						$this->_log->log(LGGR_LOG_LEVEL_INFO, "Increasing attempts to {$flow->attempts}.");
					}
				}
			} else {
				//
				// The rest of the status keep the attempts to
				// zero.
				$flow->attempts = 0;
			}
			//
			// Saving flow status.
			$flow->persist();
			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Next step: '{$flow->step}'. Next flow status: '{$flow->status}'.");
		}

		return $continue;
	}
	/**
	 * This method provides access to this workflow's current status.
	 *
	 * @return boolean Returns TRUE when it is correctly loaded.
	 */
	public function status() {
		return $this->_status;
	}
	//
	// Protected methods.
	/**
	 * This method loads current workflow configuration.
	 */
	protected function load() {
		//
		// Avoiding multipe loads of configurations.
		if($this->_config === false) {
			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Loading workflow '{$this->name()}'.");
			$this->_status = false;
			//
			// Global dependencies.
			global $WKFLDefaults;
			//
			// Guessing names.
			$fileName = Names::SnakeFilename($this->name());
			$path = Paths::Instance()->customPaths($WKFLDefaults[WKFL_DEFAULTS_PATHS][WKFL_DEFAULTS_PATH_WORKFLOWS], $fileName, Paths::ExtensionJSON);
			//
			// Checking path existence.
			if($path && is_readable($path)) {
				$this->_config = json_decode(file_get_contents($path));
				if($this->_config) {
					$this->_status = true;
				}
			} elseif(!$path) {
				$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Unknown workflow '{$this->name()}'.");
			} else {
				$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Unable to read workflow path '{$path}'.");
			}
		}
	}
	/**
	 * This method provides access to a MagicProp instance shortcut.
	 *
	 * @return \TooBasic\MagicProp Returns the shortcut.
	 */
	protected function magic() {
		if($this->_magic === false) {
			$this->_magic = MagicProp::Instance();
		}

		return $this->_magic;
	}
}
