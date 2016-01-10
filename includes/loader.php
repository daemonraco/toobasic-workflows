<?php

/**
 * @file loader.php
 * @author Alejandro Dario Simi
 */
//
// Interfaces
$SuperLoader['TooBasic\\Workflows\\Item'] = __DIR__."/Item.php";
$SuperLoader['TooBasic\\Workflows\\ItemsFactory'] = __DIR__."/ItemsFactory.php";
//
// Classes
$SuperLoader['TooBasic\\Workflows\\Step'] = __DIR__."/Step.php";
$SuperLoader['TooBasic\\Workflows\\Workflow'] = __DIR__."/Workflow.php";
$SuperLoader['TooBasic\\Workflows\\WorkflowManager'] = __DIR__."/WorkflowManager.php";
$SuperLoader['TooBasic\\Workflows\\WorkflowsException'] = __DIR__."/WorkflowsException.php";
$SuperLoader['TooBasic\\Workflows\\WorkflowsFactory'] = __DIR__."/WorkflowsFactory.php";
