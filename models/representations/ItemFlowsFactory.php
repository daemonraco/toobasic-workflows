<?php

/**
 * @file ItemFlowsFactory.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

/**
 * @class ItemFlowsFactory
 * This is a factory to flow representations.
 */
class ItemFlowsFactory extends \TooBasic\Representations\ItemsFactory {
	//
	// Protected core properties.
	protected $_CP_ColumnsPerfix = 'ifl_';
	protected $_CP_IDColumn = 'id';
	protected $_CP_RepresentationClass = '\\TooBasic\\Workflows\\item_flow';
	protected $_CP_Table = 'wkfl_item_flows';
	//
	// Public methods.
	/**
	 * This method provides access to a list of IDs for active flows. In other
	 * words, flows in status 'OK'.
	 *
	 * @return int[] Returns a list of IDs.
	 */
	public function activeFlowIds() {
		//
		// Default values.
		$out = array();
		//
		// Enforcing basic prefixes to be loaded.
		$this->queryAdapterPrefixes();
		//
		// Creating a query to retieve ids.
		$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
			'status' => WKFL_ITEM_FLOW_STATUS_OK
			), $this->_queryAdapterPrefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
		//
		// Retrieving.
		$stmt->execute($query[GC_AFIELD_PARAMS]);
		//
		// Generating the list.
		$idx = "{$this->_CP_ColumnsPerfix}id";
		foreach($stmt->fetchAll() as $row) {
			$out[] = $row[$idx];
		}

		return $out;
	}
	/**
	 * This method provides access to a list of representation for active
	 * flows. In other words, flows in status 'OK'.
	 *
	 * @return \TooBasic\Workflows\ItemFlowRepresentation[] Returns a list of
	 * active flow representations.
	 */
	public function activeFlows() {
		//
		// Default values.
		$out = array();
		//
		// Forwarding call and transforming the list of IDs into objects.
		foreach($this->activeFlowIds() as $id) {
			$out[] = $this->item($id);
		}

		return $out;
	}
	/**
	 * This method provides access to a list of IDs for flows associated with
	 * certain item.
	 *
	 * @param \TooBasic\Workflows\Item $item Associated item.
	 * @return int[] Returns a list of IDs.
	 */
	public function idsByItem(Item $item) {
		//
		// Default values.
		$out = array();
		//
		// Enforcing basic prefixes to be loaded.
		$this->queryAdapterPrefixes();
		//
		// Creating a query to retieve associated flows.
		$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
			'type' => $item->type(),
			'item' => $item->id()
			), $this->_queryAdapterPrefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
		//
		// Retrieving...
		$stmt->execute($query[GC_AFIELD_PARAMS]);
		//
		// Genearating a list.
		foreach($stmt->fetchAll() as $row) {
			$idx = "{$this->_CP_ColumnsPerfix}id";
			$out[] = $row[$idx];
		}

		return $out;
	}
	/**
	 * This method provides access to a list of flows associated with certain
	 * item.
	 *
	 * @param \TooBasic\Workflows\Item $item Associated item.
	 * @return \TooBasic\Workflows\ItemFlowRepresentation[] Returns a list of
	 * flow representations.
	 */
	public function itemsByItem(Item $item) {
		//
		// Default values.
		$out = array();
		//
		// Forwarding call and transforming the list of IDs into objects.
		foreach($this->idsByItem($item) as $id) {
			$out[] = $this->item($id);
		}

		return $out;
	}
}
