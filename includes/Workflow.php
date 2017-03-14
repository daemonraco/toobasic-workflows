<?php

/**
 * @file Workflow.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

//
// Class aliases.
use JSONValidator;
use TooBasic\Logs\AbstractLog;
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
	 * @var \TooBasic\Logs\AbstractLog Log shortcut.
	 */
	protected $_log = false;
	/**
	 * @var \TooBasic\MagicProp MagicProp shortcut.
	 */
	protected $_magic = false;
	/**
	 * @var string Current workflow configuration file path.
	 */
	protected $_path = false;
	/**
	 * @var boolean Current workflow status. It usually changes after loading.
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
	}
	//
	// Public methods.
	/**
	 * This method provides access to the loaded configuration.
	 *
	 * @return \stdClass Returns a configuration object.
	 */
	public function config() {
		//
		// Loading all required settings.
		$this->load();

		return $this->_config;
	}
	/**
	 * This method provides access to this workflow's name.
	 *
	 * @return string Returns a name.
	 */
	public function name() {
		return $this->_name;
	}
	/**
	 * This method provides access to this workflow's configuration file's
	 * path.
	 *
	 * @return string Returns an absolute path.
	 */
	public function path() {
		//
		// Loading all required settings.
		$this->load();

		return $this->_path;
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
		$logParams = array(
			'workflow' => $flow->workflow
		);
		//
		// Loading all required settings.
		$this->load();
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
		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Current step: '{$currentStep}'", $logParams);
		//
		// Shortcuts.
		$stepConfig = $this->_config->steps->{$currentStep};
		$stepPath = Paths::Instance()->customPaths($WKFLDefaults[WKFL_DEFAULTS_PATHS][WKFL_DEFAULTS_PATH_STEPS], Names::SnakeFilename($stepConfig->manager), Paths::EXTENSION_PHP);
		$stepClass = Names::ClassNameWithSuffix($stepConfig->manager, WKFL_CLASS_SUFFIX_STEP);
		$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "        path: '{$stepPath}'", $logParams);
		$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "       class: '{$stepClass}'", $logParams);
		$this->_log->log(LGGR_LOG_LEVEL_DEBUG, '      config: '.json_encode($stepConfig), $logParams);
		//
		// Checking if the step's class is already loaded.
		if(!class_exists($stepClass)) {
			//
			// Checking class code file.
			if(!$stepPath || !is_readable($stepPath)) {
				$msg = "Unable to access class for step '{$currentStep}'.";
				$this->_log->log(LGGR_LOG_LEVEL_FATAL, $msg, $logParams);
				throw new WorkflowsException($msg);
			}
			require_once $stepPath;
		}
		//
		// Checking class existence.
		if(!class_exists($stepClass)) {
			$msg = "Class '{$stepClass}' not defined.";
			$this->_log->log(LGGR_LOG_LEVEL_FATAL, $msg, $logParams);
			throw new WorkflowsException($msg);
		}
		//
		// Getting a step execution class.
		$step = new $stepClass($item);
		//
		// Sharing log pointer.
		$step->setLog($this->_log);
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
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Unkwnon connection for item status '{$item->status()}'.", $logParams);
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
				$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Neither step not status configuration for connection on status '{$item->status()}'.", $logParams);
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
					$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Waiting configuration is incorrect.", $logParams);
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
						$this->_log->log(LGGR_LOG_LEVEL_INFO, "Maximum attempts reached ({$connection->wait->attempts} attempts), changing flow status to '{$connection->wait->status}'.", $logParams);
					} else {
						$this->_log->log(LGGR_LOG_LEVEL_INFO, "Increasing attempts to {$flow->attempts}.", $logParams);
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
			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Next step: '{$flow->step}'. Next flow status: '{$flow->status}'.", $logParams);
		}

		return $continue;
	}
	/**
	 * This method updates the internal log shortcut.
	 *
	 * @param \TooBasic\Logs\AbstractLog $log Log poiner.
	 */
	public function setLog(AbstractLog $log) {
		$this->_log = $log;
	}
	/**
	 * This method provides access to this workflow's current status.
	 *
	 * @return boolean Returns TRUE when it is correctly loaded.
	 */
	public function status() {
		$this->load();
		return $this->_status;
	}
	//
	// Protected methods.
	/**
	 * This method check the presence of a valid log pointer.
	 *
	 * @throws WorkflowsException
	 */
	protected function checkLog() {
		if(!$this->_log) {
			throw new WorkflowsException("Log has not been set for this workflow");
		}
	}
	/**
	 * This method loads current workflow's configuration.
	 */
	protected function load() {
		//
		// Avoiding multipe loads of configurations.
		if($this->_config === false) {
			//
			// Checking log configuration
			$this->checkLog();
			//
			// Logging operation start.
			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Loading workflow '{$this->name()}'.");
			//
			// Reseting current stauts value.
			$this->_status = false;
			//
			// Global dependencies.
			global $WKFLDefaults;
			global $Defaults;
			//
			// Guessing names.
			$fileName = Names::SnakeFilename($this->name());
			$this->_path = Paths::Instance()->customPaths($WKFLDefaults[WKFL_DEFAULTS_PATHS][WKFL_DEFAULTS_PATH_WORKFLOWS], $fileName, Paths::EXTENSION_JSON);
			//
			// Checking path existence.
			if($this->_path && is_readable($this->_path)) {
				//
				// Loading configuration contents.
				$jsonString = file_get_contents($this->_path);
				//
				// Validating JSON strucutre.
				if(!$Defaults[GC_DEFAULTS_INSTALLED] && !self::GetValidator()->validate($jsonString, $info)) {
					$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Workflow '{$this->name()}' specification is not well formed. {$info[JV_FIELD_ERROR][JV_FIELD_MESSAGE]}");
				} else {
					//
					// Loading configuration.
					$this->_config = json_decode($jsonString);
					if($this->_config) {
						$this->_status = true;
					}
				}
			} elseif(!$this->_path) {
				$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Unknown workflow '{$this->name()}'.");
			} else {
				$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Unable to read workflow path '{$this->_path}'.");
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
	//
	// Protected class methods.
	/**
	 * This class method provides access to a JSONValidator object loaded for
	 * Workflow specifications.
	 *
	 * @return \JSONValidator Returns a loaded validator.
	 */
	protected static function GetValidator() {
		//
		// Validators cache.
		static $validator = false;
		//
		// Checking if the validators is loaded.
		if(!$validator) {
			global $Directories;
			$validator = JSONValidator::LoadFromFile(__DIR__.'/workflow-specs.json');
		}

		return $validator;
	}
}
