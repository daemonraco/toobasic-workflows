<?php

use TooBasic\Shell\Color;
use TooBasic\Shell\Option;

class WorkflowsCron extends TooBasic\Shell\ShellCron {
	//
	// Constants.
	const OptionRun = 'Run';
	const OptionWorkflow = 'Workflow';
	//
	// Protected methods.
	protected function setOptions() {
		$this->_options->setHelpText("This tool provides a mechanism to run preodic workflow tasks.");

		$text = "This option triggers the execution of all known workflows. ";
		$text.= "You may filter the execution using option '--workflow'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionRun, array('--run', '-r'), Option::TypeNoValue, $text, 'value'));

		$text = "TODO help text for: '--workflow', '-w'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionWorkflow, array('--workflow', '-w'), Option::TypeValue, $text, 'value'));
	}
	protected function taskRun($spacer = "") {
		echo "{$spacer}Running workflows:\n";
		//
		// Shortcuts.
		$manager = \TooBasic\Workflows\WorkflowManager::Instance();
		//
		// Loading filters.
		$workflowName = isset($this->params->opt->{self::OptionWorkflow}) ? $this->params->opt->{self::OptionWorkflow} : false;
		//
		// Loading known workflow names.
		$knownWorkflows = $manager->knownWorkflows();
		//
		// Waking up workflows.
		echo "{$spacer}\tWaking up items:\n";
		foreach($knownWorkflows as $name) {
			if($workflowName && $workflowName != $name) {
				continue;
			}

			echo "{$spacer}\t\t- '{$name}': ";
			$manager->wakeUpItems($name);
			echo Color::Green("Done\n");
		}

		echo "{$spacer}\tRunning workflows:\n";
		foreach($knownWorkflows as $name) {
			if($workflowName && $workflowName != $name) {
				continue;
			}

			echo "{$spacer}\t\tObtaining active flows for '{$name}': ";
			$flows = $manager->activeFlows(false, false, $name);
			$countFlows = count($flows);
			echo Color::Green("{$countFlows}\n");

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
