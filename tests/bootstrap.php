<?php
define('TESTS_PATH', realpath(TESTS_APPLICATION_PATH.'/..'));
require_once ('PHPUnit/Autoload.php');

class Smrtr_DataGrid_ControllerTestCase extends PHPUnit_Framework_TestCase
{    
    protected $_inputPath;
    
    public function setUp()
    {        
        $this->_inputPath = realpath( dirname(__FILE__) . '/input' );
        parent::setUp();
    }
}