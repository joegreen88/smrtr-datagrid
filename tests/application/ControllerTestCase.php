<?php

class Smrtr_ControllerTestCase extends Zend_Test_PHPUnit_ControllerTestCase
{    
    protected $_inputPath;
    
    public function setUp()
    {        
        $this->_inputPath = realpath( dirname(__FILE__) . '/input' );
        parent::setUp();
    }
}
