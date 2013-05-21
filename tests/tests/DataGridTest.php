<?php
/**
 * @author Joe Green
 * DataGridTest
 * These tests are reproducible and should go a long way to
 * inspire confidence in my DataGrid class.
 */
require_once(TESTS_PATH.'/../DataGrid.php');
class Smrtr_Test_DataGridTest extends Smrtr_DataGrid_ControllerTestCase
{
    public $simpleData = array(
        array('0.0', '0.1', '0.2'),
        array('1.0', '1.1', '1.2'),
        array('2.0', '2.1', '2.2')
    );
    
    public $labelledData = array(
        0 => array('col0', 'col1', 'col2'),
        'row0' => array('0.0', '0.1', '0.2'),
        'row1' => array('1.0', '1.1', '1.2'),
        'row2' => array('2.0', '2.1', '2.2')
    );
    
    public $partialData = array(
        array("one", 2, 3.3),
        array(null, null, null, null),
        array("two", 1),
        array()
    );
    
    public function testGetLabels()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        $expColKeys = array('col0', 'col1', 'col2');
        $expRowKeys = array('row0', 'row1', 'row2');
        $this->assertEquals($expColKeys, $grid->columnLabels());
        $this->assertEquals($expRowKeys, $grid->rowLabels());
        
    }
    
    public function testSetLabels()
    {
        $keys = array('one', 'two', 'three');
        $grid = new Smrtr_DataGrid($this->simpleData);
        $grid->rowLabels($keys);
        $grid->columnLabels($keys);
        $this->assertEquals($keys, $grid->columnLabels());
        $this->assertEquals($keys, $grid->rowLabels());
    }
    
    public function testGetPoints()
    {
        $grid = new Smrtr_DataGrid($this->simpleData);
        $val = '1.0';
        $point1 = $grid->column(0)[1];
        $point2 = $grid->row(1)[0];
        $point3 = $grid->getValue(1, 0);
        $arr = $grid->getArray();
        $point4 = $arr[1][0];
        $this->assertSame($val, $point1, $point2, $point3, $point4);
    }
    
    public function testGetPointsWithLabels()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        $val = '1.1';
        $point1 = $grid->column('col1')['row1'];
        $point2 = $grid->row('row1')['col1'];
        $point3 = $grid->getValue('row1', 'col1');
        $this->assertSame($val, $point1, $point2, $point3);
    }
    
    public function testSetPoints()
    {
        $grid = new Smrtr_DataGrid($this->simpleData);
        $val = "foobar";
        $grid->setValue(1, 1, $val);
        $res3 = $grid->getValue(1, 1);
        $grid->column(1)[1] = $val;
        // pre-PHP5.4 you must do $col = $grid->column(1); $col[1] = $val;
        $res1 = $grid->getArray()[1][1];
        $grid->row(1)[1] = $val;
        $res2 = $grid->getArray()[1][1];
        $this->assertSame($res1, $res2, $res3);
    }
    
    /**
     * PHP 5.4 Test 
     */
    public function testSetPointsWithLabels()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
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
    }
    
    public function testAppendColumn()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        $column = array('0.3', '1.3', '2.3');
        $grid->appendColumn($column, 'col3');
        $this->assertSame(
            $column, 
            $grid->column('col3')->data(),
            $grid->getColumn(3), 
            $grid->getColumn('col3')
        );
    }
    
    public function testAppendDuplicateRow()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        $grid->appendRow($grid->row('row1'), 'cloned');
        $this->assertSame($grid->getRow('row1'), $grid->getRow(1), $grid->row(1)->data());
    }
    
    public function testPrependDuplicateColumn()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        $grid->prependColumn($grid->column(0)->data(), 'duplicate');
        $this->assertSame($grid->column('col0')->data(), $grid->getColumn('duplicate'));
    }
    
    public function testPrependRow()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        $row = array(1.34234, 6.785, -7.2);
        $grid->prependRow($row, 'floats');
        $this->assertSame($row, $grid->row(0)->data(), $grid->getRow(0), $grid->getRow('floats'));
    }
    
    public function testSwapUnstickyRows()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getRow('row2');
        $res1_ = $grid->getRow(2);
        $res2 = $grid->swapRows('row2','row1', false)->getRow('row1');
        $res2_ = $grid->getRow(1);
        $res3 = $grid->swapRows('row0','row1', false)->getRow('row0');
        $res3_ = $grid->getRow(0);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
    }
    
    public function testSwapStickyRows()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getRow('row2');
        $res1_ = $grid->getRow(2);
        $res2 = $grid->swapRows('row2','row1')->getRow('row2');
        $res2_ = $grid->getRow(1);
        $res3 = $grid->swapRows('row0','row2')->getRow('row2');
        $res3_ = $grid->getRow(0);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
    }
    
    public function testSwapUnstickyColumns()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getColumn('col0');
        $res1_ = $grid->getColumn(0);
        $res2 = $grid->swapColumns('col0','col1', false)->getColumn('col1');
        $res2_ = $grid->getColumn(1);
        $res3 = $grid->swapColumns('col2','col1', false)->getColumn('col2');
        $res3_ = $grid->getColumn(2);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
    }
    
    public function testSwapStickyColumns()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getColumn('col0');
        $res1_ = $grid->getColumn(0);
        $res2 = $grid->swapColumns('col0','col1')->getColumn('col0');
        $res2_ = $grid->getColumn(1);
        $res3 = $grid->swapColumns('col2','col0')->getColumn('col0');
        $res3_ = $grid->getColumn(2);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
    }
    
    public function testMoveStickyRow()
    {
        $grid1 = new Smrtr_DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $grid1->moveRow('row0', 'row2');
        $cond = (
            $grid1->getRow(2) == $grid2->getRow(0) && 
            $grid1->getRow('row0') == $grid2->getRow('row0') &&
            $grid2->getRow('row0') == $grid2->getRow(0)
        );
        $this->assertTrue($cond);
    }
    
    public function testMoveUnstickyRow()
    {
        $grid1 = new Smrtr_DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $grid1->moveRow('row0', 'row2', false);
        $cond = (
            $grid1->getRow(2) == $grid2->getRow(0) && 
            $grid1->getRow('row2') == $grid2->getRow('row0') &&
            $grid2->getRow('row0') == $grid2->getRow(0)
        );
        $this->assertTrue($cond);
    }
    
    public function testMoveStickyColumn()
    {
        $grid1 = new Smrtr_DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $grid1->moveColumn('col2', 'col0');
        $cond = (
            $grid1->getColumn(0) == $grid2->getColumn(2) && 
            $grid1->getColumn('col2') == $grid2->getColumn('col2') &&
            $grid2->getColumn('col2') == $grid2->getColumn(2)
        );
        $this->assertTrue($cond);
    }
    
    public function testMoveUnstickyColumn()
    {
        $grid1 = new Smrtr_DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr_DataGrid($this->labelledData, true, true);
        
        $grid1->moveColumn('col2', 'col0', false);
        $cond = (
            $grid1->getColumn(0) == $grid2->getColumn(2) && 
            $grid1->getColumn('col0') == $grid2->getColumn('col2') &&
            $grid2->getColumn('col2') == $grid2->getColumn(2)
        );
        $this->assertTrue($cond);
    }
    
    public function testTransposition()
    {
        $g1 = new Smrtr_DataGrid($this->simpleData);
        $g2 = new Smrtr_DataGrid($this->simpleData);
        $g2->transpose();
        $self = $this;
        $g1->eachRow(function($key, $label, $data) use($g2, $self){
            $cond = $g2->getColumn($key) == $data;
            $self->assertTrue($cond);
        })->eachColumn(function($key, $label, $data) use($g2, $self){
            $cond = $g2->getRow($key) == $data;
            $self->assertTrue($cond);
        });
    }
    
    public function testRenameVectors()
    {
        $grid = new Smrtr_DataGrid($this->labelledData, true, true);
        for($i=0; $i<3; $i++)
            $grid->renameRow('row'.$i, 'across'.$i)->renameColumn('col'.$i, 'down'.$i);
        for ($i=2; $i>=0; $i--)
        {
            $cond1 = $grid->getLabel('row',$i) == 'across'.$i;
            $cond2 = $grid->getLabel('column',$i) == 'down'.$i;
            $this->assertTrue($cond1 && $cond2);
        }
    }
    
    public function testLoadingArrayAndBuildingInfo()
    {
        $grid = new Smrtr_DataGrid();
        $info = $grid->loadArray($this->simpleData)
            ->rowLabels(array('row0', 'row1', 'row2'))
            ->columnLabels(array('col0', 'col1', 'col2'))
            ->info();
        $cond = (
            $info['rowCount'] == 3 && $info['columnCount'] == 3 &&
            array('row0', 'row1', 'row2') == $info['rowKeys'] &&
            array('col0', 'col1', 'col2') == $info['columnKeys']
        );
        $this->assertTrue($cond);
    }
    
    public function testSearchRows()
    {
        $Grid = new Smrtr_DataGrid();
        $Grid->loadCSV($this->_inputPath.'/directgov_external_search_2012-02-05.csv', true, true);
        $this->assertEquals(84, $Grid->searchRows('term*=job, visits>"10,000"')->info('rowCount'));     // OR
        $this->assertEquals(9, $Grid->searchRows('term*=job + visits>"10,000"')->info('rowCount'));     // AND
        $this->assertEquals(51, $Grid->searchRows('term*=job - visits>"10,000"')->info('rowCount'));    // NOT
        $this->assertEquals(13, $Grid->searchRows('(term*=job - visits>"10,000") + (//>100 - //<400)')->info('rowCount'));
    }
    
    public function testDeleteEmptyColumnsAndRows()
    {
        $Grid = new Smrtr_DataGrid($this->partialData);
        $Grid = $Grid->deleteEmptyColumns()->deleteEmptyRows();
        $this->assertEquals(2, $Grid->info('rowCount'));
        $this->assertEquals(3, $Grid->info('columnCount'));
    }
}