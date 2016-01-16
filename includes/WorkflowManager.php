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
 * This singleton class holds the logic to manage and trigger several workflows
 * tasks.
 */
class WorkflowManager extends \TooBasic\Managers\Manager {
	//
	// Protected properties.
	/**
	 * @var \TooBasic\Adapters\DB\Adapter Database connection shortcut
	 */
	protected $_db = false;
	/**
	 * @var string Database connection name shortcut.
	 */
	protected $_dbprefix = '';
	/**
	 * @var \TooBasic\Workflows\ItemsFactory[string] List of loaded factories.
	 */
	protected $_factories = false;
	/**
	 * @var \TooBasic\Logs\AbstractLog Log shortcut.
	 */
	protected $_log = false;
	//
	// Public methods.
	/**
	 * This method returs a list of active flows. It also alloes to filter a
	 * specific item.
	 *
	 * @param string $type Flowing item type.
	 * @param mixed $id Flowing item id.
	 * @return \TooBasic\Workflows\ItemFlowRepresentation[] Returns a list of
	 * item flows.
	 */
	public function activeFlows($type = false, $id = false) {
		//
		// Retrieving the list of active flows from the representation
		// factory.
		$out = $this->representation->item_flows(false, 'TooBasic\\Workflows')->activeFlows();
		//
		// Checking if there's a filter active.
		if($type !== false && $id !== false) {
			//
			// Filtering.
			foreach($out as $k => $v) {
				if($v->type != $type && $v->item != $id) {
					unset($out[$k]);
				}
			}
		}

		return $out;
	}
	/**
	 * This method provides access to a specific workflow.
	 *
	 * @param string $workflowName Workflow name to look for.
	 * @return \TooBasic\Workflows\Workflow Returns a workflow porinter.
	 */
	public function getWorkflow($workflowName) {
		return WorkflowsFactory::Instance()->{$workflowName}($this->_log);
	}
	/**
	 * This method returns a list of absolute paths containing the location of
	 * images that inlustrate a specific workflow. Such images are generated
	 * each workflow specification.
	 * If any requested image is not present or if it's older than the
	 * workflow's configuration file, all images for such workflow are
	 * generated.
	 *
	 * @warning This method requires GraphViz.
	 *
	 * @param string $workflowName Workflow name to look for.
	 * @return string[string] Returns a list of paths containing these keys:
	 * WKFL_AFIELD_FILE, WKFL_AFIELD_THUMB (these are defined constants).
	 */
	public function graphPath($workflowName) {
		//
		// Defautl values.
		$out = false;
		//
		// Checking and including library.
		if(@require_once('Image/GraphViz.php')) {
			//
			// Checking required directories permissions.
			$this->checkGraphsDirectories();
			//
			// Global dependencies.
			global $WKFLDefaults;
			//
			// Default values.
			$generateIt = false;
			//
			// Retrieving the workflow.
			$workflow = $this->getWorkflow($workflowName);
			//
			// Guessing paths.
			$graphPath = Sanitizer::DirPath("{$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH]}/{$workflowName}.png");
			$graphThumbPath = Sanitizer::DirPath("{$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH]}/{$workflowName}-256px.png");
			//
			// Checking paths existence.
			if(!$generateIt && (!is_file($graphPath) || !is_file($graphThumbPath))) {
				$generateIt = true;
			}
			//
			// Checking paths' last modification dates.
			if(!$generateIt) {
				$workflowTime = filemtime($workflow->path());
				$generateIt = filemtime($graphPath) < $workflowTime || filemtime($graphThumbPath) < $workflowTime;
			}
			//
			// Do these images have to be generated?
			if($generateIt) {
				//
				// Logging pre-generation information.
				$this->_log->log(LGGR_LOG_LEVEL_INFO, "Gereating graph for '{$workflowName}'.");
				$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "Workflow '{$workflowName}' graph path: '{$graphPath}'");
				$this->_log->log(LGGR_LOG_LEVEL_DEBUG, "                           thumb path: '{$graphThumbPath}'");
				//
				// Creating an image.
				$graph = new \Image_GraphViz();
				//
				// Addind begining node.
				$graph->addNode('BEGIN', [
					'shape' => 'circle',
					'label' => '',
					'color' => 'black'
				]);
				//
				// Configuration shortcut.
				$workflowConfig = $workflow->config();
				//
				// Creating a squared node for each step.
				foreach($workflowConfig->steps as $stepName => $step) {
					$graph->addNode($stepName, array(
						'label' => $stepName,
						'shape' => 'box'
					));
				}
				//
				// Addind ending node.
				$graph->addNode('END', [
					'shape' => 'circle',
					'label' => '',
					'color' => 'black',
					'style' => 'filled'
				]);
				//
				// Linking the beginning node to the first step.
				$graph->addEdge(['BEGIN' => $workflowConfig->startsAt]);
				//
				// Linking all steps based on connections
				// confiugrations.
				foreach($workflowConfig->steps as $stepName => $step) {
					//
					// Checking each connection for current
					// step.
					foreach($step->connections as $connName => $conn) {
						//
						// Checking if this connection
						// either changes the flow status
						// or sets the next step.
						if(isset($conn->status)) {
							//
							// Checking what change is taking.
							switch($conn->status) {
								case WKFL_ITEM_FLOW_STATUS_FAILED:
								case WKFL_ITEM_FLOW_STATUS_DONE:
									//
									// These statuses link to the end.
									$graph->addEdge([$stepName => 'END'], [
										'label' => $connName,
										'fontcolor' => 'brown',
										'color' => 'brown'
									]);
									break;
								case WKFL_ITEM_FLOW_STATUS_WAIT:
									//
									// Basic attributes for each link.
									$attrs = [
										'label' => "{$connName}\n[wait]",
										'color' => 'orange',
										'fontcolor' => 'orange',
										'style' => 'dashed'
									];
									//
									// Checking if this waiting has some extra
									// configuration.
									if(isset($conn->wait)) {
										//
										// This status creates a vitual node
										// representing the waiting step.
										$nodeName = "wait_{$stepName}";
										$graph->addNode($nodeName, [
											'shape' => 'box',
											'label' => "wait:{$stepName}",
											'fontcolor' => 'orange',
											'color' => 'orange'
										]);
										//
										// Linking the current step to this
										// virtual node.
										$graph->addEdge([$stepName => $nodeName], $attrs);
										//
										// Linking the virtual node to the current
										// one for less attempts than a limit.
										$attrs['label'] = "{$connName}\n[wait<={$conn->wait->attempts}]";
										$graph->addEdge([$nodeName => $stepName], $attrs);
										//
										// Linking the virtual node something else
										// for more attempts than a limit.
										$attrs['label'] = "{$connName}\n[wait>{$conn->wait->attempts}]";
										if(isset($conn->wait->status)) {
											//
											// Linking to the end.
											$graph->addEdge([$nodeName => 'END'], $attrs);
										} elseif(isset($conn->wait->step)) {
											//
											// Linking to another step.
											$graph->addEdge([$nodeName => $conn->wait->step], $attrs);
										}
									} else {
										$nextStep = isset($conn->step) ? $conn->step : $stepName;
										$graph->addEdge([$stepName => $nextStep], $attrs);
									}
									break;
							}
						} elseif(isset($conn->step)) {
							//
							// Linking this step to
							// the next one.
							$graph->addEdge([$stepName => $conn->step], [
								'label' => $connName,
								'fontcolor' => 'darkgreen',
								'color' => 'darkgreen'
							]);
						}
					}
				}
				//
				// Saving the generated graphic in two locations,
				// one for the actual image and other for 256
				// pixels thumbnail.
				file_put_contents($graphPath, $graph->fetch('png'));
				file_put_contents($graphThumbPath, $graph->fetch('png'));
				//
				// Croping thumbnail.
				self::CropImage($graphThumbPath, 256);
			}
			//
			// Building the returning information.
			$out = array(
				WKFL_AFIELD_FILE => $graphPath,
				WKFL_AFIELD_THUMB => $graphThumbPath
			);
		} else {
			//
			// Logging the error of not having the proper library.
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, "Pear GraphViz plugin hasn't been installed.");
		}

		return $out;
	}
	/**
	 * This method associates a flowing item with certain workflow and creates
	 * an active 'flow' for it, unless there's already another active 'flow'
	 * for such combination.
	 *
	 * @param \TooBasic\Workflows\Item $item Flowing item to be injected.
	 * @param string $workflowName Name of the workflow to associate it with.
	 * @param string $error Returns an error message when something went
	 * wrong.
	 * @return boolean Returns TRUE if the items was injectected successfully.
	 */
	public function inject(Item $item, $workflowName, &$error = false) {
		//
		// Default values.
		$out = true;
		//
		// Logging operation.
		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Injecting into workflow '{$workflowName}'. Item: ".json_encode($item->toArray()));
		//
		// Loading and checking workflow.
		$workflow = $this->getWorkflow($workflowName);
		if($workflow) {
			//
			// Basic query prefixes.
			$prefixes = array(
				GC_DBQUERY_PREFIX_TABLE => $this->_dbprefix,
				GC_DBQUERY_PREFIX_COLUMN => 'ifl_'
			);
			//
			// Creating a query to search for active flows.
			$query = $this->_db->queryAdapter()->select('wkfl_item_flows', array(
				'workflow' => $workflowName,
				'type' => $item->type(),
				'item' => $item->id(),
				'status' => 'tmp'
				), $prefixes);
			$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
			//
			// Checking active flows.
			$injected = false;
			foreach(array(WKFL_ITEM_FLOW_STATUS_OK, WKFL_ITEM_FLOW_STATUS_WAIT) as $status) {
				$query[GC_AFIELD_PARAMS]['status'] = $status;
				$stmt->execute($query[GC_AFIELD_PARAMS]);
				if($stmt->rowCount() > 0) {
					$injected = true;
					break;
				}
			}
			//
			// Checking if the new flow can be injected.
			if($injected) {
				$error = 'Item already injected and running';
				$out = false;
			} else {
				//
				// Creating a query to inject a flow.
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
		//
		// Logging errors if any.
		if(!$out) {
			$this->_log->log(LGGR_LOG_LEVEL_ERROR, $error);
		}

		return $out;
	}
	/**
	 * This method is similar to 'inject()' but it uses an item ID and its type
	 * instead of a '\TooBasic\Workflows\Item'.
	 *
	 * @param string $type Flowing item type.
	 * @param mixed $id Flowing item ID.
	 * @param string $workflowName Name of the workflow to associate it with.
	 * @param string $error Returns an error message when something went
	 * wrong.
	 * @return boolean Returns TRUE if the items was injectected successfully.
	 */
	public function injectDirect($type, $id, $workflowName, &$error = false) {
		//
		// Default values.
		$out = true;
		//
		// Enforcing known factories to be loaded.
		$this->loadFactories();
		//
		// Checking specified type.
		if(!isset($this->_factories[$type])) {
			$error = "Unknown item type '{$type}'";
			$out = false;
		} else {
			//
			// Retieveing the actual item from its factory.
			$item = $this->_factories[$type]->item($id);
			//
			// Checking item existence.
			if($item) {
				//
				// Forwarding the injection call.
				$out = $this->inject($item, $workflowName, $error);
			} else {
				$error = "Unknown item with id '{$id}'";
				$out = false;
			}
		}

		return $out;
	}
	/**
	 * This method is capable of taking an active flow and trigger the
	 * execution its steps until it finishes (ok or not) or reach a waiting
	 * point.
	 *
	 * @param \TooBasic\Workflows\ItemFlowRepresentation $flow Flow to be
	 * executed.
	 * @param string $error Returns an error message when something went
	 * wrong.
	 * @return boolean Returns FALSE when an error was found.
	 */
	public function run(ItemFlowRepresentation $flow, &$error = false) {
		//
		// Defautl values.
		$out = true;
		//
		// Enforcing known factories to be loaded.
		$this->loadFactories();
		//
		// Loading flow's workflow.
		$workflow = $this->getWorkflow($flow->workflow);
		//
		// Loading flow's workflow.
		$item = $this->_factories[$flow->type]->item($flow->item);
		//
		// Basic log prefixes.
		$logParams = array(
			'workflow' => $flow->workflow
		);
		//
		// Logging operation start.
		$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Running flow: '.json_encode($flow->toArray()), $logParams);
		$this->_log->log(LGGR_LOG_LEVEL_INFO, '        item: '.json_encode($item->toArray()), $logParams);
		//
		// Walking through each step until it's no longer ok or flagged to
		// stop.
		$continue = true;
		while($continue && $flow->status == WKFL_ITEM_FLOW_STATUS_OK) {
			//
			// Asking the workflow to run a step.
			$continue = $workflow->runFor($flow, $item);
			//
			// Reloading flow to avoid errors.
			$flow->load($flow->id);
			//
			// Logging step execution results.
			$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Current flow status: '.json_encode($flow->toArray()), $logParams);
			$this->_log->log(LGGR_LOG_LEVEL_INFO, '        item status: '.json_encode($item->toArray()), $logParams);
			$this->_log->log(LGGR_LOG_LEVEL_INFO, 'Continue?: '.($continue ? 'Yes' : 'No'), $logParams);
		}

		return $out;
	}
	/**
	 * @fixme this should wake up flows for a certain workflow, not all of them.
	 * This method changes the status on any flow flagged as waiting to OK.
	 *
	 * @return boolean Returns TRUE when there was no problems updating.
	 */
	public function wakeUpItems() {
		//
		// Logging operation start.
		$this->_log->log(LGGR_LOG_LEVEL_INFO, "Waking up items in status 'WAIT'.");
		//
		// Basic query prefixes.
		$prefixes = array(
			GC_DBQUERY_PREFIX_TABLE => $this->_dbprefix,
			GC_DBQUERY_PREFIX_COLUMN => 'ifl_'
		);
		//
		// Creating a query to update flow statuses.
		$query = $this->_db->queryAdapter()->update('wkfl_item_flows', array(
			'status' => WKFL_ITEM_FLOW_STATUS_OK
			), array(
			'status' => WKFL_ITEM_FLOW_STATUS_WAIT
			), $prefixes);
		$stmt = $this->_db->prepare($query[GC_AFIELD_QUERY]);
		//
		// Executiong and returning it's result.
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
	/**
	 * Manager initialized.
	 */
	protected function init() {
		parent::init();
		//
		// Database shortcuts.
		$this->_db = DBManager::Instance()->getDefault();
		$this->_dbprefix = $this->_db->prefix();
		//
		// Log shortcuts and initializtions.
		/** @fixme the workflow name should be un a 'defined()' constant. */
		$this->_log = $this->log->startLog('workflows');
		$this->_log->separator();
	}
	/**
	 * This method loads all configured flowing item factories.
	 */
	protected function loadFactories() {
		//
		// Avoiding multipe loads.
		if($this->_factories === false) {
			//
			// Default values.
			$this->_factories = array();
			//
			// Global dependencies.
			global $WKFLDefaults;
			//
			// Checking each configured factory name.
			foreach($WKFLDefaults[WKFL_DEFAULTS_FACTORIES] as $factoryName) {
				//
				// Loading the right factory.
				$factory = $this->representation->{$factoryName};
				//
				// Checking existence and implemented methods.
				if($factory && $factory instanceof ItemsFactory) {
					$this->_factories[$factory->type()] = $factory;
				}
			}
			//
			// Logging loaded factories.
			$this->_log->log(LGGR_LOG_LEVEL_INFO, "Loaded factories: ".implode(', ', array_keys($this->_factories)).'.');
		}
	}
	//
	// Protected class methods.
	/**
	 * This class method takes an image and reduces its size to fit in a
	 * square box of no more pixels than a specified size.
	 *
	 * @param string $path Absolute path the an image file.
	 * @param int $maxSize Maximum size in pixels.
	 * @return boolean Returns TRUE if the image was cropped successfully.
	 */
	protected static function CropImage($path, $maxSize) {
		//
		// Default values.
		$ok = true;
		//
		// Basic image information.
		$data = array(
			"width" => 0,
			"height" => 0,
			"errmsg" => "",
			"errcode" => 0
		);
		//
		// Loading image as an object.
		$img = @imagecreatefrompng("{$path}");
		if($ok && $img === false) {
			$ok = false;
		}
		//
		// Retrieving current size information.
		if($ok) {
			//
			// Getting size.
			$width = imagesx($img);
			$height = imagesy($img);
			//
			// Checking sizes.
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
		// Create a new temporary image.
		if($ok) {
			$tmp_img = imagecreatetruecolor($new_width, $new_height);
			if($tmp_img === false) {
				$ok = false;
			}
		}
		//
		// Copy and resize old image into a new image.
		if($ok) {
			$ok = imagecopyresampled($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			if($ok) {
				$data["width"] = $width;
				$data["height"] = $height;
			}
		}
		//
		// Removing image file before replacing it.
		if($ok) {
			$ok = unlink($path);
		}
		//
		// Saving the new image as a file.
		if($ok) {
			$ok = @imagepng($tmp_img, "{$path}");
		}

		return $ok;
	}
}
