<?php
define('TESTS_PATH', realpath(dirname(__FILE__)));
define('ROOT_PATH', realpath(dirname(TESTS_PATH)));
//require_once ('PHPUnit/Autoload.php');
require_once(ROOT_PATH.'/vendor/autoload.php');

class Smrtr_DataGrid_ControllerTestCase extends PHPUnit_Framework_TestCase
{    
    protected $_inputPath;
    protected $_outputStream;
    protected $_outputPath;
    
    public function setUp()
    {
        $this->_inputPath = realpath( dirname(__FILE__) . '/input' );
        $this->_outputStream = org\bovigo\vfs\vfsStream::setup('output');
        $this->_outputPath = org\bovigo\vfs\vfsStream::url('output');
        parent::setUp();
    }
}
