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
	const OptionDirectory = 'Directory';
	const OptionGenGrapth = 'GenGrapth';
	const OptionInject = 'Inject';
	const OptionId = 'Id';
	const OptionType = 'Type';
	const OptionWorkflow = 'Workflow';
	//
	// Protected methods.
	/**
	 * This methods sets all non-core options handle by this tool.
	 */
	protected function setOptions() {
		$this->_options->setHelpText("This tool provides a mechanism to run some manual workflow tasks.");

		$text = 'This options specifies a directory where to drop images.';
		$this->_options->addOption(Option::EasyFactory(self::OptionDirectory, array('--directory', '-d'), Option::TypeValue, $text, 'path'));

		$text = "This options generates an graph for certain workflow configuration.\n";
		$text = "Requires the option '--directory'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionGenGrapth, array('--gen-flow', '-gf'), Option::TypeValue, $text, 'name'));

		$text = "This option triggers the injection of a new flow.\n";
		$text.= "It requires options: '--id', '--type' and '--workflow'";
		$this->_options->addOption(Option::EasyFactory(self::OptionInject, array('--inject', '-j'), Option::TypeNoValue, $text, 'value'));

		$text = "This options specifies a flowing item ID.";
		$this->_options->addOption(Option::EasyFactory(self::OptionId, array('--id', '-i'), Option::TypeValue, $text, 'value'));

		$text = "This options specifies a flowing item type.";
		$this->_options->addOption(Option::EasyFactory(self::OptionType, array('--type', '-t'), Option::TypeValue, $text, 'value'));

		$text = "This options specifies a workflow.";
		$this->_options->addOption(Option::EasyFactory(self::OptionWorkflow, array('--workflow', '-w'), Option::TypeValue, $text, 'name'));
	}
	protected function taskGenGrapth($spacer = "") {
		//
		// Checking required parameters.
		if(!$this->params->opt->{self::OptionDirectory}) {
			$this->setError(self::ErrorWrongParameters, 'No output directory specified');
		} else {
			//
			// Shortcuts.
			$directory = $this->params->opt->{self::OptionDirectory};
			$workflow = $this->params->opt->{self::OptionGenGrapth};
			//
			// Checking directory.
			if(!is_dir($directory) || !is_writable($directory)) {
				$this->setError(self::ErrorWrongParameters, "Directory '{$directory}' is not valid");
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
		if(!$this->params->opt->{self::OptionType}) {
			$this->setError(self::ErrorWrongParameters, 'No type specified');
		} elseif(!$this->params->opt->{self::OptionId}) {
			$this->setError(self::ErrorWrongParameters, 'No ID specified');
		} elseif(!$this->params->opt->{self::OptionWorkflow}) {
			$this->setError(self::ErrorWrongParameters, 'No workflow specified');
		} else {
			//
			// Shortcuts.
			$type = $this->params->opt->{self::OptionType};
			$id = $this->params->opt->{self::OptionId};
			$workflow = $this->params->opt->{self::OptionWorkflow};
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
