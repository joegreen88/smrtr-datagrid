<?php
/**
 * @author Joe Green
 */

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../..'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', 'testing');

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();

/* Zend_Application */
require_once 'Zend/Application.php';

require_once('ControllerTestCase.php');