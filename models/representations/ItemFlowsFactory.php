<?php

namespace TooBasic\Workflows;

class ItemFlowsFactory extends \TooBasic\Representations\ItemsFactory {
	//
	// Protected core properties.
	protected $_CP_ColumnsPerfix = 'ifl_';
	protected $_CP_IDColumn = 'id';
	protected $_CP_RepresentationClass = '\\TooBasic\\Workflows\\item_flow';
	protected $_CP_Table = 'wkfl_item_flows';
	//
	// Public methods.
	public function activeFlowIds() {
		$out = array();

		$this->queryAdapterPrefixes();

		$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
			'status' => WKFL_ITEM_FLOW_STATUS_OK
			), $this->_queryAdapterPrefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);

		$stmt->execute($query[GC_AFIELD_PARAMS]);
		$idx = "{$this->_CP_ColumnsPerfix}id";
		foreach($stmt->fetchAll() as $row) {
			$out[] = $row[$idx];
		}

		return $out;
	}
	public function activeFlows() {
		$out = array();

		foreach($this->activeFlowIds() as $id) {
			$out[] = $this->item($id);
		}

		return $out;
	}
	public function idByItem(Item $item) {
		$out = false;

		$this->queryAdapterPrefixes();

		$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
			'type' => $item->type(),
			'item' => $item->id()
			), $this->_queryAdapterPrefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);

		$stmt->execute($query[GC_AFIELD_PARAMS]);
		$row = $stmt->fetch();
		if($row) {
			$out = $row["{$this->_CP_ColumnsPerfix}id"];
		}

		return $out;
	}
	public function itemByItem(Item $item) {
		$id = $this->idByItem($item);
		return $id ? $this->item($id) : false;
	}
}
