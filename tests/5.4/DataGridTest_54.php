<?php
/**
 * @author Joe Green
 * DataGridTest_54
 * Additional tests to run in php environments from 5.4 up.
 */
require_once(TESTS_PATH.'/../DataGrid.php');
class Smrtr_Test_DataGridTest_54 extends Smrtr_DataGrid_ControllerTestCase
{
    public $simpleData = array(
        array('0.0', '0.1', '0.2'),
        array('1.0', '1.1', '1.2'),
        array('2.0', '2.1', '2.2')
    );
    
    // ASSOC_ROW_KEYS, ASSOC_COL_FIRST
    public $labelledData = array(
                    array('col0', 'col1', 'col2'),
        'row0' =>   array('0.0', '0.1', '0.2'),
        'row1' =>   array('1.0', '1.1', '1.2'),
        'row2' =>   array('2.0', '2.1', '2.2')
    );

    /**
     * Does some sanity checks on the Smrtr\DataGrid object and returns a boolean
     */
    protected function isValid( Smrtr\DataGrid $grid )
    {
        $info = $grid->info();
        $data = $grid->getArray();
        $rows = 0; $columns = 0;
        foreach ($data as $row) {
            $rows++;
            $count = count($row);
            $columns = max($columns, $count);
        }
        return (
            $rows == $info['rowCount'] && $columns == $info['columnCount'] 
            && $rows == count($info['rowKeys']) && $columns == count($info['columnKeys'])
        );
    }
    
    /**
     * PHP 5.4 Test 
     */
    public function testGetPoints()
    {
        $grid = new Smrtr\DataGrid($this->simpleData);
        $val = '1.0';
        $point1 = $grid->column(0)[1];
        $point2 = $grid->row(1)[0];
        $point3 = $grid->getValue(1, 0);
        $arr = $grid->getArray();
        $point4 = $arr[1][0];
        $this->assertSame($val, $point1, $point2, $point3, $point4);
        $this->assertTrue($this->isValid($grid));
        $this->assertTrue(false);
    }
    
    /**
     * PHP 5.4 Test 
     */
    public function testGetPointsWithLabels()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $val = '1.1';
        $point1 = $grid->column('col1')['row1'];
        $point2 = $grid->row('row1')['col1'];
        $point3 = $grid->getValue('row1', 'col1');
        $this->assertSame($val, $point1, $point2, $point3);
        $this->assertTrue($this->isValid($grid));
    }
    
    /**
     * PHP 5.4 Test 
     */
    public function testSetPoints()
    {
        $grid = new Smrtr\DataGrid($this->simpleData);
        $val = "foobar";
        $grid->setValue(1, 1, $val);
        $res3 = $grid->getValue(1, 1);
        $grid->column(1)[1] = $val;
        $res1 = $grid->getArray()[1][1];
        $grid->row(1)[1] = $val;
        $res2 = $grid->getArray()[1][1];
        $this->assertSame($res1, $res2, $res3);
        $this->assertTrue($this->isValid($grid));
    }
    
    /**
     * PHP 5.4 Test 
     */
    public function testSetPointsWithLabels()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $val = "foobar";
        
        $grid->setValue('row2', 'col2', $val);
        $res3 = $grid->getValue('row2', 'col2');
        $grid->setValue('row2', 'col2', $val);
        $res4 = $grid->getValue('row2', 'col2');
        $grid->column('col2')['row2'] = $val;
        $res1 = $grid->getValue('row2', 'col2');
        $grid->row('row2')['col2'] = $val;
        $res2 = $grid->getValue('row2', 'col2');
        
        $this->assertSame($val, $res1, $res2, $res3, $res4);
        $this->assertTrue($this->isValid($grid));
    }
    
}