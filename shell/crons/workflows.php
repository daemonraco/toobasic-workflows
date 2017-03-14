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
 * @class WorkflowsCron
 * This cron tool provides a mechanism to run recurring workflow tasks.
 */
class WorkflowsCron extends TooBasic\Shell\ShellCron {
	//
	// Constants.
	const OPTION_RUN = 'Run';
	const OPTION_WORKFLOW = 'Workflow';
	//
	// Protected methods.
	/**
	 * This methods sets all non-core options handle by this tool.
	 */
	protected function setOptions() {
		$this->_options->setHelpText("This tool provides a mechanism to run recurring workflow tasks.");

		$text = "This option triggers the execution of all known workflows.\n";
		$text.= "You may filter the execution using option '--workflow'.";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_RUN, array('--run', '-r'), Option::TYPE_NO_VALUE, $text, 'value'));

		$text = "This options provides a way to filter a specific workflow.";
		$this->_options->addOption(Option::EasyFactory(self::OPTION_WORKFLOW, array('--workflow', '-w'), Option::TYPE_VALUE, $text, 'value'));
	}
	/**
	 * Ths method performs the execution of all active flows.
	 *
	 * @param string $spacer Prefix to add on each log line promptted on
	 * terminal.
	 */
	protected function taskRun($spacer = "") {
		echo "{$spacer}Running workflows:\n";
		//
		// Shortcuts.
		$manager = \TooBasic\Workflows\WorkflowManager::Instance();
		//
		// Loading filters.
		$workflowName = isset($this->params->opt->{self::OPTION_WORKFLOW}) ? $this->params->opt->{self::OPTION_WORKFLOW} : false;
		//
		// Loading known workflow names.
		$knownWorkflows = $manager->knownWorkflows();
		//
		// Waking up workflows.
		echo "{$spacer}\tWaking up items:\n";
		foreach($knownWorkflows as $name) {
			//
			// Filtering.
			if($workflowName && $workflowName != $name) {
				continue;
			}
			//
			// Shaking the bed :)
			echo "{$spacer}\t\t- '{$name}': ";
			$manager->wakeUpItems($name);
			echo Color::Green("Done\n");
		}
		//
		// Running workflows.
		echo "{$spacer}\tRunning workflows:\n";
		foreach($knownWorkflows as $name) {
			//
			// Filtering.
			if($workflowName && $workflowName != $name) {
				continue;
			}
			//
			// Retrieving active flow for certain workflow.
			echo "{$spacer}\t\tObtaining active flows for '{$name}': ";
			$flows = $manager->activeFlows(false, false, $name);
			$countFlows = count($flows);
			echo Color::Green("{$countFlows}\n");
			//
			// Running active flows.
			if($countFlows) {
				echo "{$spacer}\t\tRunning flows for '{$name}':\n";
				foreach($flows as $flow) {
					echo "{$spacer}\t\t\tRunning item '{$flow->type}:{$flow->item}' on workflow '{$flow->workflow}': ";
					if($manager->run($flow)) {
						echo Color::Green("Done\n");
					} else {
						echo Color::Red("Failed\n");
					}
				}
			}
		}
	}
}
