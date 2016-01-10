<?php

/**
 * @file Item.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

/**
 * @interface Item
 */
interface Item {
	//
	// Public methods.
	public function id();
	public function name();
	public function reload();
	public function status();
	public function setStatus($status);
	public function viewLink();
	public function toArray();
	public function type();
}
