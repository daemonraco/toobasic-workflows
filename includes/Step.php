<?php

/**
 * @file Step.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

//
// Class aliases.
use TooBasic\Logs\AbstractLog;
use TooBasic\MagicProp;
use TooBasic\MagicPropException;

/**
 * @class Step
 * @abstract
 * This is an abstract represetation of a workflow step logic.
 */
abstract class Step {
	//
	// Protected properties.
	/**
	 * @var \TooBasic\Workflows\Item Item being analyzed by this step.
	 */
	protected $_item = false;
	/**
	 * @var \TooBasic\Logs\AbstractLog Log shortcut.
	 */
	protected $_log = false;
	//
	// Magic methods.
	public function __construct(Item $item) {
		$this->_item = $item;
	}
	/**
	 * This magic method provides a shortcut for magicprops
	 *
	 * @param string $prop Name of the magic property to look for.
	 * @return mixed Returns the requested magic property or FALSE if it was
	 * not found.
	 */
	public function __get($prop) {
		//
		// Default values.
		$out = false;
		//
		// Looking for the requested property.
		try {
			$out = MagicProp::Instance()->{$prop};
		} catch(MagicPropException $ex) {
			//
			// Ignored to avoid unnecessary issues.
		}

		return $out;
	}
	//
	// Public methods.
	/**
	 * @abstract 
	 * This is the main method where a step execute a the logic related with
	 * it.
	 */
	abstract public function execute();
	/**
	 * This method updates the internal log shortcut.
	 *
	 * @param \TooBasic\Logs\AbstractLog $log Log poiner.
	 */
	public function setLog(AbstractLog $log) {
		$this->_log = $log;
	}
}
