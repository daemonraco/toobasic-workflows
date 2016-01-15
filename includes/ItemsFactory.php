<?php

/**
 * @file ItemsFactory.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

/**
 * @interface ItemsFactory
 * This interface specific all required method for a factory that can provide
 * access to flowing items.
 */
interface ItemsFactory {
	//
	// Public methods.
	/**
	 * This method allows to obtain an flowing item based on its ID.
	 *
	 * @param int $id Id to look for.
	 * @return \TooBasic\Workflows\Item Returns a flowing item when found or
	 * NULL if it's not.
	 */
	public function item($id);
	/**
	 * This method provides the flowing item type to use when selection flows.
	 *
	 * @return string Returns a flowing item code of no more than ten (10)
	 * characters.
	 */
	public function type();
}
