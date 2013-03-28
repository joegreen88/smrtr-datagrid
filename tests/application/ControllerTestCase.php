<?php

class Smrtr_ControllerTestCase extends Zend_Test_PHPUnit_ControllerTestCase
{
    /**
     * @var Zend_Application
     */
    protected $application;
    
    protected $_inputPath;
    
    public function setUp()
    {        
        $this->_inputPath = realpath( dirname(__FILE__) . '/input' );
        
        parent::setUp();
    }
}
