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
// Workflows defaults.
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
$WKFLDefaults[WKFL_DEFAULTS_GRAPHS_PATH] = \TooBasic\Sanitizer::DirPath("{$Directories[GC_DIRECTORIES_CACHE]}/wkflgraphs");
//
// Log configurations.
$LoggerDefaults[LGGR_TYPES_BY_LOG]['workflows'] = LGGR_LOG_TYPE_PREFIXED;
$LoggerDefaults[LGGR_PFXDLOG_PREFIXES]['workflows'] = [
	'workflow' => [
		LGGR_AFIELD_PREFIX => 'WF',
		LGGR_AFIELD_DEFAULT => ''
	]
];
