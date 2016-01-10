<?php

use TooBasic\Shell\Color;
use TooBasic\Shell\Option;

class WorkflowsTool extends TooBasic\Shell\ShellTool {
	//
	// Constants.
	const OptionInject = 'Inject';
	const OptionRun = 'Run';
	const OptionId = 'Id';
	const OptionType = 'Type';
	const OptionWorkflow = 'Workflow';
	//
	// Protected methods.
	protected function setOptions() {
		$this->_options->setHelpText("TODO tool summary");

		$text = "TODO help text for: '--inject', '-j'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionInject, array('--inject', '-j'), Option::TypeNoValue, $text, 'value'));

		$text = "TODO help text for: '--run', '-r'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionRun, array('--run', '-r'), Option::TypeNoValue, $text, 'value'));

		$text = "TODO help text for: '--id', '-i'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionId, array('--id', '-i'), Option::TypeValue, $text, 'value'));

		$text = "TODO help text for: '--type', '-t'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionType, array('--type', '-t'), Option::TypeValue, $text, 'value'));

		$text = "TODO help text for: '--workflow', '-w'.";
		$this->_options->addOption(Option::EasyFactory(self::OptionWorkflow, array('--workflow', '-w'), Option::TypeValue, $text, 'value'));
	}
	protected function taskInject($spacer = "") {
		if(!$this->params->opt->{self::OptionType}) {
			$this->setError(self::ErrorWrongParameters, 'No type specified');
		} elseif(!$this->params->opt->{self::OptionId}) {
			$this->setError(self::ErrorWrongParameters, 'No ID specified');
		} elseif(!$this->params->opt->{self::OptionWorkflow}) {
			$this->setError(self::ErrorWrongParameters, 'No workflow specified');
		} else {
			$type = $this->params->opt->{self::OptionType};
			$id = $this->params->opt->{self::OptionId};
			$workflow = $this->params->opt->{self::OptionWorkflow};

			$manager = \TooBasic\Workflows\WorkflowManager::Instance();

			echo "{$spacer}Injecting item '{$type}:{$id}' for workflow '{$workflow}': ";
			$error = false;
			if($manager->injectDirect($type, $id, $workflow, $error)) {
				echo Color::Green("Done\n");
			} else {
				echo Color::Red("Failed")." (Error ".Color::Yellow($error).")\n";
			}
		}
	}
	protected function taskRun($spacer = "") {
		$type = $this->params->opt->{self::OptionType};
		$id = $this->params->opt->{self::OptionId};

		$manager = \TooBasic\Workflows\WorkflowManager::Instance();

		echo "{$spacer}Running workflows:\n";

		echo "{$spacer}\tWaking up items: ";
		$manager->wakeUpItems();
		echo Color::Green("Done\n");

		echo "{$spacer}\tObtaining active flows: ";
		$flows = $manager->activeFlows();
		$countFlows = count($flows);
		echo Color::Green("{$countFlows}\n");

		if($countFlows) {
			echo "{$spacer}\tRunning flows:\n";
			foreach($flows as $flow) {
				echo "{$spacer}\t\tRunning item '{$flow->type}:{$flow->item}' on workflow '{$flow->workflow}': ";
				if($manager->run($flow)) {
					echo Color::Green("Done\n");
				} else {
					echo Color::Red("Failed\n");
				}
			}
		}
	}
}
