<?php

/**
 * @file dummy.php
 * @author Alejandro Dario Simi
 */

/**
 * @class DummyStep
 * This class represents a simple workflow steps that does nothing.
 */
class DummyStep extends \TooBasic\Workflows\Step {
	//
	// Public methods.
	public function execute() {
		//
		// Nothing to do, it's just a dummy.
	}
}
