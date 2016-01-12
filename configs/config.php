<?php

/**
 * @file config.php
 * @author Alejandro Dario Simi
 */
//
// Loading basic definitions.
require_once dirname(__DIR__)."/includes/define.php";
require_once dirname(__DIR__)."/includes/loader.php";
//
// Logger defaults.
$WKFLDefaults = array();
//
// Knwon item factories.
$WKFLDefaults[WKFL_DEFAULTS_FACTORIES] = array();
//
// Paths.
$WKFLDefaults[WKFL_DEFAULTS_PATHS] = array(
	WKFL_DEFAULTS_PATH_STEPS => '/workflows/steps',
	WKFL_DEFAULTS_PATH_WORKFLOWS => '/workflows'
);
