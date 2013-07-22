<?php
define('TESTS_PATH', realpath(dirname(__FILE__)));
require_once ('PHPUnit/Autoload.php');

class Smrtr_DataGrid_ControllerTestCase extends PHPUnit_Framework_TestCase
{    
    protected $_inputPath;
    protected $_outputPath;
    
    public function setUp()
    {
        $this->_inputPath = realpath( dirname(__FILE__) . '/input' );
        $this->_outputPath = realpath( dirname(__FILE__) . '/output' );
        parent::setUp();
    }
}