<?php

/**
 * @file config.php
 * @author Alejandro Dario Simi
 */
require_once dirname(__DIR__)."/includes/define.php";
require_once dirname(__DIR__)."/includes/loader.php";

$WKFLDefaults = array();
$WKFLDefaults[WKFL_DEFAULTS_FACTORIES] = array();
$WKFLDefaults[WKFL_DEFAULTS_PATHS] = array(
	WKFL_DEFAULTS_PATH_STEPS => '/workflows/steps',
	WKFL_DEFAULTS_PATH_WORKFLOWS => '/workflows'
);
