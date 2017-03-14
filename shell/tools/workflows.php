<?php

/**
 * @file workflows.php
 * @author Alejandro Dario Simi
 */
//
// Class aliases.
use TooBasic\Shell\Color;
use TooBasic\Shell\Option;

/**
 * @class WorkflowsTool
 * This shell tool provides a mechanism to run some manual workflow tasks.
 */
class WorkflowsTool extends TooBasic\Shell\ShellTool {
	//
	// Constants.
	const OPTION_DIRECTORY = 'Directory';
	const OPTION_GEN_GRAPTH = 'GenGrapth';
	const OPTION_INJECT = 'Inject';
	const OPTION_ID = 'Id';
	const OPTION_TYPE = 'Type';
	const OPTION_WORKFLOW = 'Workflow';
	//
	// Protected methods.
	/**
	 * This methods sets all non-core options handle by this tool.
	 */
	protected function setOptions() {
		$this->_options->setHelpText("This tool provides a mechanism to run some manual workflow tasks.");

		$text = 'This options specifies a directory where to drop images.';
		$this->_options->addOption(Option::EasyFactory(self::OPTION_DIRECTORY, array('--directory', '-d'), Option::TYPE_VALUE, $text, 'path'));

		$text = "This options generates an graph for certain workflow configuration.\n";
		$text = "Requires the option '--directory'.";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_GEN_GRAPTH, array('--gen-flow', '-gf'), Option::TYPE_VALUE, $text, 'name'));

		$text = "This option triggers the injection of a new flow.\n";
		$text.= "It requires options: '--id', '--type' and '--workflow'";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_INJECT, array('--inject', '-j'), Option::TYPE_NO_VALUE, $text, 'value'));

		$text = "This options specifies a flowing item ID.";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_ID, array('--id', '-i'), Option::TYPE_VALUE, $text, 'value'));

		$text = "This options specifies a flowing item type.";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_TYPE, array('--type', '-t'), Option::TYPE_VALUE, $text, 'value'));

		$text = "This options specifies a workflow.";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_WORKFLOW, array('--workflow', '-w'), Option::TYPE_VALUE, $text, 'name'));
	}
	protected function taskGenGrapth($spacer = "") {
		//
		// Checking required parameters.
		if(!$this->params->opt->{self::OPTION_DIRECTORY}) {
			$this->setError(self::ERROR_WRONG_PARAMETERS, 'No output directory specified');
		} else {
			//
			// Shortcuts.
			$directory = $this->params->opt->{self::OPTION_DIRECTORY};
			$workflow = $this->params->opt->{self::OPTION_GEN_GRAPTH};
			//
			// Checking directory.
			if(!is_dir($directory) || !is_writable($directory)) {
				$this->setError(self::ERROR_WRONG_PARAMETERS, "Directory '{$directory}' is not valid");
			} else {
				//
				// Loading manager.
				$manager = \TooBasic\Workflows\WorkflowManager::Instance();
				//
				// Injecting...
				echo "{$spacer}Generating graph for workflow '{$workflow}': ";
				$path = $manager->graphPath($workflow);
				if($path) {
					$dest = "{$directory}/{$workflow}.png";
					if(copy($path['file'], $dest)) {
						echo Color::Green('Done')." (result saved at '{$dest}')\n";
					} else {
						echo Color::Red('Failed')." (unable to copy graph)\n";
					}
				} else {
					echo Color::Red('Failed')." (graph was not generated)\n";
				}
			}
		}
	}
	/**
	 * Ths method inserts a new flow in the system.
	 *
	 * @param string $spacer Prefix to add on each log line promptted on
	 * terminal.
	 */
	protected function taskInject($spacer = "") {
		//
		// Checking required parameters.
		if(!$this->params->opt->{self::OPTION_TYPE}) {
			$this->setError(self::ERROR_WRONG_PARAMETERS, 'No type specified');
		} elseif(!$this->params->opt->{self::OPTION_ID}) {
			$this->setError(self::ERROR_WRONG_PARAMETERS, 'No ID specified');
		} elseif(!$this->params->opt->{self::OPTION_WORKFLOW}) {
			$this->setError(self::ERROR_WRONG_PARAMETERS, 'No workflow specified');
		} else {
			//
			// Shortcuts.
			$type = $this->params->opt->{self::OPTION_TYPE};
			$id = $this->params->opt->{self::OPTION_ID};
			$workflow = $this->params->opt->{self::OPTION_WORKFLOW};
			//
			// Loading manager.
			$manager = \TooBasic\Workflows\WorkflowManager::Instance();
			//
			// Injecting...
			echo "{$spacer}Injecting item '{$type}:{$id}' for workflow '{$workflow}': ";
			$error = false;
			if($manager->injectDirect($type, $id, $workflow, $error)) {
				echo Color::Green("Done\n");
			} else {
				echo Color::Red("Failed")." (Error ".Color::Yellow($error).")\n";
			}
		}
	}
}
