<?php

/**
 * @file ItemsFactory.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

/**
 * @interface ItemsFactory
 */
interface ItemsFactory {
	//
	// Public methods.
	public function item($id);
	public function type();
}
