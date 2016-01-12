<?php

/**
 * @file InteractiveConnector.php
 * @author Alejandro Dario Simi
 */

namespace TooBasic\Workflows;

//
// Class aliases.
use TooBasic\Managers\DBManager;
use TooBasic\Sanitizer;

/**
 * @class WorkflowManager
 */
class WorkflowManager extends \TooBasic\Managers\Manager {
	//
	// Protected properties.
	protected $_db = false;
	protected $_dbprefix = false;
	protected $_factories = false;
	protected $_log = false;
	//
	// Public methods.
	public function activeFlows($type = false, $id = false) {
		$out = $this->representation->item_flows(false, 'TooBasic\\Workflows')->activeFlows();

		if($type !== false && $id !== false) {
			foreach($out as $k => $v) {
				if($v->type != $type && $v->item != $id) {
					unset($out[$k]);
				}
			}
		}

		return $out;
	}
	public function getWorkflow($workflowName) {
		return WorkflowsFactory::Instance()->{$workflowName}($this->_log);
	}
	public function graphPath($workflowName) {
		$out = false;

		if(@require_once('Image/GraphViz.php')) {
			$this->checkGraphsDirectories();
			//
			// Global dependencies.
			global $WKFLDefaults;
			//
			// Default values.
			$generateIt = false;

			$workflow = $this->getWorkflow($workflowName);
			$graphPath = Sanitizer::DirPath("{$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH]}/{$workflowName}.png");
			$graphThumbPath = Sanitizer::DirPath("{$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH]}/{$workflowName}-256px.png");

			if(!$generateIt && (!is_file($graphPath) || !is_file($graphThumbPath))) {
				$generateIt = true;
			}
			if(!$generateIt) {
				$workflowTime = filemtime($workflow->path());
				$generateIt = filemtime($graphPath) < $workflowTime || filemtime($graphThumbPath) < $workflowTime;
			}

			if($generateIt) {
				$this->_log->log(LGGR_LOG_LEVEL_INFO, "Gereating graph for '{$workflowName}'.");
				$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "Workflow '{$workflowName}' graph path: '{$graphPath}'");
				$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "                           thumb path: '{$graphThumbPath}'");

				$graph = new \Image_GraphViz();

				$graph->addNode('BEGIN', [
					'shape' => 'circle',
					'label' => '',
					'color' => 'black'
				]);

				$workflowConfig = $workflow->config();

				foreach($workflowConfig->steps as $stepName => $step) {
					$graph->addNode($stepName, array(
						'label' => $stepName,
						'shape' => 'box'
					));
				}

				$graph->addNode('END', [
					'shape' => 'circle',
					'label' => '',
					'color' => 'black',
					'style' => 'filled'
				]);

				$graph->addEdge(['BEGIN' => $workflowConfig->startsAt]);

				foreach($workflowConfig->steps as $stepName => $step) {
					foreach($step->connections as $connName => $conn) {
						if(isset($conn->status)) {
							switch($conn->status) {
								case WKFL_ITEM_FLOW_STATUS_FAILED:
								case WKFL_ITEM_FLOW_STATUS_DONE:
									$graph->addEdge([$stepName => 'END'], [
										'label' => $connName,
										'fontcolor' => 'brown',
										'color' => 'brown'
									]);
									break;
								case WKFL_ITEM_FLOW_STATUS_WAIT:
									$attrs = [
										'label' => "{$connName}\n[wait]",
										'color' => 'orange',
										'fontcolor' => 'orange',
										'style' => 'dashed'
									];

									if(isset($conn->wait)) {
										$nodeName = "wait_{$stepName}";
										$graph->addNode($nodeName, [
											'shape' => 'box',
											'label' => "wait:{$stepName}",
											'fontcolor' => 'orange',
											'color' => 'orange'
										]);

										$graph->addEdge([$stepName => $nodeName], $attrs);

										$attrs['label'] = "{$connName}\n[wait<={$conn->wait->attempts}]";
										$graph->addEdge([$nodeName => $stepName], $attrs);

										$attrs['label'] = "{$connName}\n[wait>{$conn->wait->attempts}]";
										if(isset($conn->wait->status)) {
											$graph->addEdge([$nodeName => 'END'], $attrs);
										} elseif(isset($conn->wait->step)) {
											$graph->addEdge([$nodeName => $conn->wait->step], $attrs);
										}
									} else {
										$nextStep = isset($conn->step) ? $conn->step : $stepName;
										$graph->addEdge([$stepName => $nextStep], $attrs);
									}
									break;
							}
						} elseif(isset($conn->step)) {
							$graph->addEdge([$stepName => $conn->step], [
								'label' => $connName,
								'fontcolor' => 'darkgreen',
								'color' => 'darkgreen'
							]);
						}
					}
				}

				file_put_contents($graphPath, $graph->fetch('png'));
				file_put_contents($graphThumbPath, $graph->fetch('png'));
				self::CropImage($graphThumbPath, 256);
			}

			$out = array(
				WKFL_AFIELD_FILE => $graphPath,
				WKFL_AFIELD_THUMB => $graphThumbPath
			);
		} else {
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Pear GraphViz plugin hasn't been installed.");
		}

		return $out;
	}
	public function inject(Item $item, $workflowName, &$error = false) {
		$out = true;

		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Injecting into workflow '{$workflowName}'. Item: ".json_encode($item->toArray()));

		$workflow = $this->getWorkflow($workflowName);
		if($workflow) {
			$prefixes = array(
				GC_DBQUERY_PREFIX_TABLE => $this->_dbprefix,
				GC_DBQUERY_PREFIX_COLUMN => 'ifl_'
			);
			$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
				'workflow' => $workflowName,
				'type' => $item->type(),
				'item' => $item->id(),
				'status' => 'tmp'
				), $prefixes);
			$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);

			$injected = false;
			foreach(array(WKFL_ITEM_FLOW_STATUS_OK, WKFL_ITEM_FLOW_STATUS_WAIT) as $status) {
				$query[GC_AFIELD_PARAMS]['status'] = $status;
				$stmt->execute($query[GC_AFIELD_PARAMS]);
				if($stmt->rowCount() > 0) {
					$injected = true;
					break;
				}
			}

			if($injected) {
				$error = 'Item already injected and running';
				$out = false;
			} else {

				$query = $this->_db->queryAdapter()->insert('wkfl_item_flows', array(
					'workflow' => $workflowName,
					'type' => $item->type(),
					'item' => $item->id()
					), $prefixes);
				$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
				$out = boolval($stmt->execute($query[GC_AFIELD_PARAMS]));
			}
		} else {
			$out = false;
			$error = "Unknown workflow '{$workflowName}'";
		}

		if(!$out) {
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, $error);
		}

		return $out;
	}
	public function injectDirect($type, $id, $workflowName, &$error = false) {
		$out = true;

		$this->loadFactories();
		if(!isset($this->_factories[$type])) {
			$error = "Unknown item type '{$type}'";
			$out = false;
		} else {
			$item = $this->_factories[$type]->item($id);
			if($item) {
				$out = $this->inject($item, $workflowName, $error);
			} else {
				$error = "Unknown item with id '{$id}'";
				$out = false;
			}
		}

		return $out;
	}
	public function run(ItemFlowRepresentation $flow, &$error = false) {
		$out = true;

		$this->loadFactories();

		$workflow = $this->getWorkflow($flow->workflow);
		$item = $this->_factories[$flow->type]->item($flow->item);

		$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Running flow: '.json_encode($flow->toArray()));
		$this->_log->log(LGGR_LOG_LEVEL_INFO, '        item: '.json_encode($item->toArray()));

		$continue = true;
		while($continue && $flow->status == WKFL_ITEM_FLOW_STATUS_OK) {
			$continue = $workflow->runFor($flow, $item);
			$flow->load($flow->id);
			$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Current flow status: '.json_encode($flow->toArray()));
			$this->_log->log(LGGR_LOG_LEVEL_INFO, '        item status: '.json_encode($item->toArray()));
			$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Continue?: '.($continue ? 'Yes' : 'No'));
		}

		return $out;
	}
	public function wakeUpItems() {
		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Waking up items in status 'WAIT'.");

		$prefixes = array(
			GC_DBQUERY_PREFIX_TABLE => $this->_dbprefix,
			GC_DBQUERY_PREFIX_COLUMN => 'ifl_'
		);
		$query = $this->_db->queryAdapter()->update('wkfl_item_flows', array(
			'status' => WKFL_ITEM_FLOW_STATUS_OK
			), array(
			'status' => WKFL_ITEM_FLOW_STATUS_WAIT
			), $prefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
		return boolval($stmt->execute($query[GC_AFIELD_PARAMS]));
	}
	//
	// Protected methods.
	/**
	 * This method performs some basic checks on required directories.
	 */
	protected function checkGraphsDirectories() {
		//
		// Global dependencies.
		global $WKFLDefaults;
		//
		// Checking if it really is a directory.
		if(!is_dir($WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH])) {
			\TooBasic\debugThing("'{$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH]}' is not a directory", \TooBasic\DebugThingTypeError);
			die;
		}
		//
		// Checking if the current system user has permissions to write
		// inside it.
		if(!is_writable($WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH])) {
			\TooBasic\debugThing("'{$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH]}' is not writable", \TooBasic\DebugThingTypeError);
			die;
		}
	}
	protected function init() {
		parent::init();

		$this->_db = DBManager::Instance()->getDefault();
		$this->_dbprefix = $this->_db->prefix();
		$this->_log = $this->log->startLog('workflows');
		$this->_log->separator();
	}
	protected function loadFactories() {
		if($this->_factories === false) {
			$this->_factories = array();
			//
			// Global dependencies.
			global $WKFLDefaults;
			foreach($WKFLDefaults[WKFL_DEFAULTS_FACTORIES] as $factoryName) {
				$factory = $this->representation->{$factoryName};
				if($factory && $factory instanceof ItemsFactory) {
					$this->_factories[$factory->type()] = $factory;
				}
			}

			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Loaded factories: ".implode(', ', array_keys($this->_factories)).'.');
		}
	}
	//
	// Protected class methods.
	protected static function CropImage($path, $maxSize) {
		$ok = true;

		$info = pathinfo($path);
		$ext = strtolower($info["extension"]);
		$data = array(
			"width" => 0,
			"height" => 0,
			"errmsg" => "",
			"errcode" => 0
		);

		$img = @imagecreatefrompng("{$path}");
		if($ok && $img === false) {
			$ok = false;
		}

		if($ok) {
			//
			// Load image and get image size.
			$width = imagesx($img);
			$height = imagesy($img);
			if($width === false || $height === false) {
				$ok = false;
			} elseif($width <= $maxSize && $height < $maxSize) {
				$ok = false;
			} else {
				//
				// Calculate thumbnail size.
				if($width > $height) {
					$new_width = $maxSize;
					$new_height = floor($height * ($maxSize / $width));
				} else {
					$new_width = floor($width * ($maxSize / $height));
					$new_height = $maxSize;
				}
			}
		}
		//
		// create a new temporary image.
		if($ok) {
			$tmp_img = imagecreatetruecolor($new_width, $new_height);
			if($tmp_img === false) {
				$ok = false;
			}
		}
		//
		// copy and resize old image into a new image.
		if($ok) {
			$ok = imagecopyresampled($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			if($ok) {
				$data["width"] = $width;
				$data["height"] = $height;
			}
		}
		if($ok) {
			$ok = unlink($path);
		}
		if($ok) {
			$ok = @imagepng($tmp_img, "{$path}");
		}

		return $ok;
	}
}
