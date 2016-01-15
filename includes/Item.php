<?php

/**
 * @file Item.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

/**
 * @interface Item
 * This interface specific all required method for a flowing item.
 */
interface Item {
	//
	// Public methods.
	/**
	 * This method provides a direct access to this flowing item's id.
	 *
	 * @return mixed Returns an ID.
	 */
	public function id();
	/**
	 * This method provides a direct access to this flowing item's name.
	 *
	 * @return string Returns a name.
	 */
	public function name();
	/**
	 * This method provides a mechanism to reload this flowing item's
	 * information from where it's stored.
	 */
	public function reload();
	/**
	 * This method provides access to this flowing item's current status.
	 *
	 * @return string Returns a status name.
	 */
	public function status();
	/**
	 * This method allows to change this flowing item's current status.
	 *
	 * @param string $status New status to be set.
	 */
	public function setStatus($status);
	/**
	 * This method provides access a proper URI to access this items.
	 *
	 * @return string Retuns a URI.
	 */
	public function viewLink();
	/**
	 * This method allows to access this flowing item as simple array.
	 * Useful to export this object into a view or log file.
	 *
	 * @return mixed[string] List of field names associated to their values.
	 */
	public function toArray();
	/**
	 * This method provides the flowing item type to use when selection flows.
	 *
	 * @return string Returns a flowing item code of no more than ten (10)
	 * characters.
	 */
	public function type();
}
