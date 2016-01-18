<?php

/**
 * @file ItemFlowRepresentation.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

/**
 * @class ItemFlowRepresentation
 * This is a representations of a flow.
 */
class ItemFlowRepresentation extends \TooBasic\Representations\ItemRepresentation {
	//
	// Protected core properties.
	protected $_CP_ColumnsPerfix = 'ifl_';
	protected $_CP_IDColumn = 'id';
	protected $_CP_Table = 'wkfl_item_flows';
}
