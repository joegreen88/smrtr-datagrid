<?php
/**
 * @author Joe Green
 * DataGridTest
 * These tests apply to all PHP environments from 5.3 up.
 */
require_once(TESTS_PATH.'/../DataGrid.php');

class Smrtr_Test_DataGridTest extends Smrtr_DataGrid_ControllerTestCase
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
    
    // ASSOC_ROW_FIRST, ASSOC_COL_KEYS
    public $labelledDataInverse = array(
        array( 'row0', 'col0'=>'0.0', 'col1'=>'0.1', 'col2'=>'0.2'),
        array( 'row1', 'col0'=>'1.0', 'col1'=>'1.1', 'col2'=>'1.2'),
        array( 'row2', 'col0'=>'2.0', 'col1'=>'2.1', 'col2'=>'2.2')
    );
    
    // ASSOC_ROW_KEYS, ASSOC_COL_KEYS
    public $labelledDataAssoc = array(
        'row0' =>   array('col0'=>'0.0', 'col1'=>'0.1', 'col2'=>'0.2'),
        'row1' =>   array('col0'=>'1.0', 'col1'=>'1.1', 'col2'=>'1.2'),
        'row2' =>   array('col0'=>'2.0', 'col1'=>'2.1', 'col2'=>'2.2')
    );
    
    // ASSOC_ROW_FIRST, ASSOC_COL_FIRST
    public $labelledDataInline = array(
        array(null, 'col0', 'col1', 'col2'),
        array('row0', '0.0', '0.1', '0.2'),
        array('row1', '1.0', '1.1', '1.2'),
        array('row2', '2.0', '2.1', '2.2'),
    );
    
    public $partialData = array(
        array("one", 2, 3.3),
        array(null, null, null, null),
        array("two", 1),
        array()
    );
    
    public $numberData = array(
        array(1, 2, 3, 4, 5),
        array(5, 4, 3, 2, 1),
        array(4, 4, 3, 2),
        array(2, 1, 5, 3, 2),
        array(5, 1, 1)
    );
    
    public $csvData = array(
        array('A1', 'B1', 'C1', 'D1'),
        array('A2', 'B2', 'C2', 'D2'),
        array('A3', 'B3', 'C3', 'D3'),
        array('A4', 'B4', 'C4', 'D4'),
    );
    
    public $csvRowKeys = array('_1', '_2', '_3', '_4');
    
    public $csvColumnKeys = array('A', 'B', 'C', 'D');
    
    public $csvEmptyKeys = array(null, null, null, null);

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

    public function testLoadArray()
    {
        // non-associative array
        $grid1 = new Smrtr\DataGrid($this->simpleData);
        $grid2 = new Smrtr\DataGrid;
        $grid2->loadArray($this->simpleData);
        $this->assertSame($this->simpleData, $grid1->getArray(), $grid2->getArray());
        $this->assertTrue($this->isValid($grid1));
        $this->assertTrue($this->isValid($grid2));
        // row assoc, column inline (the backwards compatible way: true, true)
        $grid1 = new Smrtr\DataGrid($this->labelledData, \Smrtr\DataGrid::ASSOC_ROW_KEYS, \Smrtr\DataGrid::ASSOC_COLUMN_FIRST);
        $grid2 = new Smrtr\DataGrid;
        $grid2->loadArray($this->labelledData, true, true);
        $this->assertSame($this->simpleData, $grid1->getArray(), $grid2->getArray());
        $associativeData = array(
            'row0' => array('col0'=>'0.0', 'col1'=>'0.1', 'col2'=>'0.2'),
            'row1' => array('col0'=>'1.0', 'col1'=>'1.1', 'col2'=>'1.2'),
            'row2' => array('col0'=>'2.0', 'col1'=>'2.1', 'col2'=>'2.2')
        );
        $this->assertSame($associativeData, $grid1->getAssociativeArray(), $grid2->getAssociativeArray());
        $this->assertTrue($this->isValid($grid1));
        $this->assertTrue($this->isValid($grid2));
        // row inline, column assoc
        $grid = new Smrtr\DataGrid;
        $grid->loadArray($this->labelledDataInverse, Smrtr\DataGrid::ASSOC_ROW_FIRST, Smrtr\DataGrid::ASSOC_COLUMN_KEYS);
        $this->assertSame($this->simpleData, $grid->getArray());
        $this->assertSame($associativeData, $grid->getAssociativeArray());
        $this->assertTrue($this->isValid($grid));
        // row inline, column inline
        $grid = new Smrtr\DataGrid;
        $grid->loadArray($this->labelledDataInline, Smrtr\DataGrid::ASSOC_ROW_FIRST, Smrtr\DataGrid::ASSOC_COLUMN_FIRST);
        $this->assertSame($this->simpleData, $grid->getArray());
        $this->assertSame($associativeData, $grid->getAssociativeArray());
        $this->assertTrue($this->isValid($grid));
        // row assoc, column assoc
        $grid = new Smrtr\DataGrid;
        $grid->loadArray($this->labelledDataAssoc, Smrtr\DataGrid::ASSOC_ROW_KEYS, Smrtr\DataGrid::ASSOC_COLUMN_KEYS);
        $this->assertSame($this->simpleData, $grid->getArray());
        $this->assertSame($associativeData, $grid->getAssociativeArray());
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testGetKeysAndGetLabels()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $expColKeys = array('col0', 'col1', 'col2');
        $expRowKeys = array('row0', 'row1', 'row2');
        // Keys
        $this->assertSame(range(0, 2), $grid->getRowKeys(), $grid->getColumnKeys());
        $this->assertSame(1, $grid->getRowKey('row1'), $grid->getColumnKey('col1'));
        // Labels
        $this->assertSame($expRowKeys, $grid->rowLabels(), $grid->getRowLabels());
        $this->assertSame($expColKeys, $grid->columnLabels(), $grid->getColumnLabels());
        $this->assertSame('row1', $grid->getRowLabel(1));
        $this->assertSame('col1', $grid->getColumnLabel(1));
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSetLabels()
    {
        $columnLabels = array('col0', 'col1', 'col2');
        $rowLabels = array('row0', 'row1', 'row2');
        $grid = new Smrtr\DataGrid($this->simpleData);
        $grid->rowLabels($rowLabels);
        $grid->columnLabels($columnLabels);
        $this->assertSame($rowLabels, $grid->rowLabels());
        $this->assertSame($columnLabels, $grid->columnLabels());
        $this->assertTrue($this->isValid($grid));
        $grid->updateLabel('row', 0, 'first_row');
        $grid->updateLabel('column', 2, 'last_column');
        $columnLabels = array('col0', 'col1', 'last_column');
        $rowLabels = array('first_row', 'row1', 'row2');
        $this->assertSame($rowLabels, $grid->rowLabels());
        $this->assertSame($columnLabels, $grid->columnLabels());
        $this->assertTrue($this->isValid($grid));
    }

    public function testHasKeyAndHasLabel()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        // Keys
        $this->assertTrue($grid->hasRowKey(2) && $grid->hasColumnKey(0));
        $this->assertFalse($grid->hasRowKey(4) || $grid->hasColumnKey(4));
        // Labels
        $this->assertTrue($grid->hasRowLabel('row0') && $grid->hasColumnLabel('col2'));
        $this->assertFalse($grid->hasRowLabel('noMatch') || $grid->hasColumnLabel('noMatch'));
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testGetPoints()
    {
        $grid = new Smrtr\DataGrid($this->simpleData);
        $val = '1.0';
        $tmp = $grid->column(0);
        $point1 = $tmp[1];
        $tmp = $grid->row(1);
        $point2 = $tmp[0];
        $point3 = $grid->getValue(1, 0);
        $arr = $grid->getArray();
        $point4 = $arr[1][0];
        $this->assertSame($val, $point1, $point2, $point3, $point4);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testGetPointsWithLabels()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $val = '1.1';
        $tmp = $grid->column('col1');
        $point1 = $tmp['row1'];
        $tmp = $grid->row('row1');
        $point2 = $tmp['col1'];
        $point3 = $grid->getValue('row1', 'col1');
        $this->assertSame($val, $point1, $point2, $point3);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSetPoints()
    {
        $grid = new Smrtr\DataGrid($this->simpleData);
        $val = "foobar";
        $grid->setValue(1, 1, $val);
        $res3 = $grid->getValue(1, 1);
        $col = $grid->column(1);
        $col[1] = $val;
        $arr = $grid->getArray();
        $res1 = $arr[1][1];
        $row = $grid->row(1);
        $row[1] = $val;
        $arr = $grid->getArray();
        $res2 = $arr[1][1];
        $this->assertSame($res1, $res2, $res3);
        $this->assertTrue($this->isValid($grid));
    }

    public function testSetPointsWithLabels()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $val = "foobar";
        
        $grid->setValue('row2', 'col2', $val);
        $res3 = $grid->getValue('row2', 'col2');
        $grid->setValue('row2', 'col2', $val);
        $res4 = $grid->getValue('row2', 'col2');
        $col = $grid->column('col2');
        $col['row2'] = $val;
        $res1 = $grid->getValue('row2', 'col2');
        $row = $grid->row('row2');
        $row['col2'] = $val;
        $res2 = $grid->getValue('row2', 'col2');
        
        $this->assertSame($val, $res1, $res2, $res3, $res4);
        $this->assertTrue($this->isValid($grid));
    }

    public function testGetColumnAssociative()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $this->assertSame(
            array('col0'=>'1.0', 'col1'=>'1.1', 'col2'=>'1.2'),
            $grid->getRow(1, true)
        );
    }

    public function testGetRowAssociative()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $this->assertSame(
            array('row0'=>'0.1', 'row1'=>'1.1', 'row2'=>'2.1'),
            $grid->getColumn(1, true)
        );
    }
    
    public function testAppendColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $column = array('0.3', '1.3', '2.3');
        $grid->appendColumn($column, 'col3');
        $grid->appendColumn($grid->column(3), 'copy');
        $this->assertSame(
            $column, 
            $grid->column('col3')->data(), $grid->column(3)->data(),
            $grid->column('copy')->data(), $grid->column(4)->data(),
            $grid->getColumn('col3'), $grid->getColumn(3),
            $grid->getColumn('copy'), $grid->getColumn(4)
        );
        $this->assertTrue($this->isValid($grid));
        // long column
        $column = array('0.5', '1.5', '2.5', '3.5');
        $grid->appendColumn($column, 'col5');
        $this->assertEquals(4, $grid->info('rowCount'));
        $this->assertSame(array(null, null, null, null, null, '3.5'), $grid->getRow(3));
        $this->assertSame(array('0.0', '1.0', '2.0', null), $grid->getColumn(0));
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testAppendRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $row = array('3.0', '3.1', '3.2');
        $grid->appendRow($row, 'row3');
        $grid->appendRow($grid->row('row3'), 'dupe');
        $this->assertSame(
            $row,
            $grid->row('row3')->data(), $grid->row(3)->data(),
            $grid->row('dupe')->data(), $grid->row(4)->data(),
            $grid->getRow('row3'), $grid->getRow(3),
            $grid->getRow('dupe'), $grid->getRow(4)
        );
        $this->assertTrue($this->isValid($grid));
        // long row
        $row = array('5.0', '5.1', '5.2', '5.3');
        $grid->appendRow($row, 'row5');
        $this->assertEquals(4, $grid->info('columnCount'));
        $this->assertSame(array(null, null, null, null, null, '5.3'), $grid->getColumn(3));
        $this->assertSame(array('0.0', '0.1', '0.2', null), $grid->getRow(0));
        $this->assertTrue($this->isValid($grid));
    }

    public function testPrependColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $column = array('a', 'b', 'c');
        $grid->prependColumn($column, 'new');
        $this->assertEquals($grid->getLabel('column', 0), 'new');
        $grid->prependColumn($grid->column(0), 'copy');
        $this->assertEquals($grid->getLabel('column', 0), 'copy');
        $this->assertEquals($grid->getLabel('column', 1), 'new');
        $this->assertSame(
            $column, 
            $grid->column('new')->data(), $grid->column(1)->data(),
            $grid->column('copy')->data(), $grid->column(0)->data(),
            $grid->getColumn('new'), $grid->getColumn(1),
            $grid->getColumn('copy'), $grid->getColumn(0)
        );
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testPrependRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $row = array('a', 'b', 'c');
        $grid->prependRow($row, 'new');
        $this->assertEquals($grid->getLabel('row', 0), 'new');
        $grid->prependRow($grid->row(0), 'copy');
        $this->assertEquals($grid->getLabel('row', 0), 'copy');
        $this->assertEquals($grid->getLabel('row', 1), 'new');
        $this->assertSame(
            $row, 
            $grid->row('new')->data(), $grid->row(1)->data(),
            $grid->row('copy')->data(), $grid->row(0)->data(),
            $grid->getRow('new'), $grid->getRow(1),
            $grid->getRow('copy'), $grid->getRow(0)
        );
        $this->assertTrue($this->isValid($grid));
    }

    public function testDeleteColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->deleteColumn(2);
        $this->assertSame(2, count($grid->getRow(0)), count($grid->getColumnKeys()), $grid->info('columnCount'));
        $grid->deleteColumn('col0');
        $this->assertSame(1, count($grid->getRow(0)), count($grid->getColumnKeys()), $grid->info('columnCount'));
        $this->assertSame(array('col1'), $grid->getColumnLabels());
        $this->assertTrue($this->isValid($grid));
    }

    public function testDeleteRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->deleteRow('row2');
        $this->assertSame(2, count($grid->getColumn(0)), count($grid->getRowKeys()), $grid->info('rowCount'));
        $grid->deleteRow(0);
        $this->assertSame(1, count($grid->getColumn(0)), count($grid->getRowKeys()), $grid->info('rowCount'));
        $this->assertSame(array('row1'), $grid->getRowLabels());
        $this->assertTrue($this->isValid($grid));
    }

    public function testEmptyColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->emptyColumn(1);
        $grid->emptyColumn('col0');
        $this->assertSame(array(null, null, null), $grid->getColumn('col1'), $grid->getColumn(0));
        $this->assertTrue($this->isValid($grid));
    }

    public function testEmptyRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->emptyRow('row1');
        $grid->emptyRow(0);
        $this->assertSame(array(null, null, null), $grid->getRow(1), $grid->getRow('row0'));
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testOrderColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->orderColumns('row2', 'desc', true);
        $result = array(array('0.2','0.1','0.0'),array('1.2','1.1','1.0'),array('2.2','2.1','2.0'));
        $labelsResult = array('col2', 'col1', 'col0');
        $this->assertSame($result, $grid->getArray());
        $this->assertSame($labelsResult, $grid->columnLabels());
        $grid->orderColumns(1, 'asc', false);
        $this->assertSame($this->simpleData, $grid->getArray());
        $this->assertSame($labelsResult, $grid->columnLabels());
    }

    public function testOrderRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->orderRows('col0', 'desc', false);
        $result = array(array('2.0', '2.1', '2.2'),array('1.0', '1.1', '1.2'),array('0.0','0.1','0.2'));
        $this->assertSame($result, $grid->getArray());
        $this->assertSame(array('col0','col1','col2'), $grid->columnLabels());
        $grid->orderRows(0, 'asc', true);
        $labelsResult = array('row2','row1','row0');
        $this->assertSame($this->simpleData, $grid->getArray());
        $this->assertSame($labelsResult, $grid->rowLabels());
    }
    
    public function testSwapUnstickyRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getRow('row2');
        $res1_ = $grid->getRow(2);
        $res2 = $grid->swapRows('row2','row1', false)->getRow('row1');
        $res2_ = $grid->getRow(1);
        $res3 = $grid->swapRows('row0','row1', false)->getRow('row0');
        $res3_ = $grid->getRow(0);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSwapStickyRows()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getRow('row2');
        $res1_ = $grid->getRow(2);
        $res2 = $grid->swapRows('row2','row1')->getRow('row2');
        $res2_ = $grid->getRow(1);
        $res3 = $grid->swapRows('row0','row2')->getRow('row2');
        $res3_ = $grid->getRow(0);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSwapUnstickyColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getColumn('col0');
        $res1_ = $grid->getColumn(0);
        $res2 = $grid->swapColumns('col0','col1', false)->getColumn('col1');
        $res2_ = $grid->getColumn(1);
        $res3 = $grid->swapColumns('col2','col1', false)->getColumn('col2');
        $res3_ = $grid->getColumn(2);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSwapStickyColumns()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $res1 = $grid->getColumn('col0');
        $res1_ = $grid->getColumn(0);
        $res2 = $grid->swapColumns('col0','col1')->getColumn('col0');
        $res2_ = $grid->getColumn(1);
        $res3 = $grid->swapColumns('col2','col0')->getColumn('col0');
        $res3_ = $grid->getColumn(2);
        
        $result = ( $res1 == $res2 && $res2 == $res3 && $res3 == $res3_ && $res3_ == $res2_ && $res2_ == $res1_ );
        $this->assertTrue($result);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testMoveStickyRow()
    {
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $grid1->moveRow('row0', 'row2');
        $this->assertTrue($this->isValid($grid1));
        $cond = (
            $grid1->getRow(2) == $grid2->getRow(0) && 
            $grid1->getRow('row0') == $grid2->getRow('row0') &&
            $grid2->getRow('row0') == $grid2->getRow(0)
        );
        $this->assertTrue($cond);
        
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid1->moveRow('row2', 'row0');
        $this->assertTrue($this->isValid($grid1));
        $cond = (
            $grid1->getRow(0) == $grid2->getRow(2) && 
            $grid1->getRow(1) == $grid2->getRow(0) && 
            $grid1->getRow(2) == $grid2->getRow(1) && 
            $grid1->getRow('row0') == $grid2->getRow('row0') &&
            $grid2->getRow('row0') == $grid2->getRow(0)
        );
        $this->assertTrue($cond);
    }
    
    public function testMoveUnstickyRow()
    {
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $grid1->moveRow('row0', 'row2', false);
        $this->assertTrue($this->isValid($grid1));
        $cond = (
            $grid1->getRow(2) == $grid2->getRow(0) && 
            $grid1->getRow('row2') == $grid2->getRow('row0') &&
            $grid2->getRow('row0') == $grid2->getRow(0)
        );
        $this->assertTrue($cond);
    }
    
    public function testMoveStickyColumn()
    {
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $grid1->moveColumn('col2', 'col0');
        $this->assertTrue($this->isValid($grid1));
        $cond = (
            $grid1->getColumn(0) == $grid2->getColumn(2) && 
            $grid1->getColumn('col2') == $grid2->getColumn('col2') &&
            $grid2->getColumn('col2') == $grid2->getColumn(2)
        );
        $this->assertTrue($cond);
    }
    
    public function testMoveUnstickyColumn()
    {
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid2 = new Smrtr\DataGrid($this->labelledData, true, true);
        
        $grid1->moveColumn('col2', 'col0', false);
        $this->assertTrue($this->isValid($grid1));
        $cond = (
            $grid1->getColumn(0) == $grid2->getColumn(2) && 
            $grid1->getColumn('col0') == $grid2->getColumn('col2') &&
            $grid2->getColumn('col2') == $grid2->getColumn(2)
        );
        $this->assertTrue($cond);
        
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid1->moveColumn('col0', 'col2', false);
        $this->assertTrue($this->isValid($grid1));
        $cond = (
            $grid1->getColumn(2) == $grid2->getColumn(0) && 
            $grid1->getColumn('col2') == $grid2->getColumn('col0') &&
            $grid2->getColumn('col2') == $grid2->getColumn(2)
        );
        $this->assertTrue($cond);
    }
    
    public function testTransposition()
    {
        $g1 = new Smrtr\DataGrid($this->simpleData);
        $g2 = new Smrtr\DataGrid($this->simpleData);
        $g2->transpose();
        $self = $this;
        $g1->eachRow(function($key, $label, $data) use($g2, $self){
            $cond = $g2->getColumn($key) == $data;
            $self->assertTrue($cond);
        })->eachColumn(function($key, $label, $data) use($g2, $self){
            $cond = $g2->getRow($key) == $data;
            $self->assertTrue($cond);
        });
        $this->assertTrue($this->isValid($g2));
    }
    
    public function testRenameVectors()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        for($i=0; $i<3; $i++)
            $grid->renameRow('row'.$i, 'across'.$i)->renameColumn('col'.$i, 'down'.$i);
        for ($i=2; $i>=0; $i--)
        {
            $cond1 = $grid->getLabel('row',$i) == 'across'.$i;
            $cond2 = $grid->getLabel('column',$i) == 'down'.$i;
            $this->assertTrue($cond1 && $cond2);
        }
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testLoadingArrayAndBuildingInfo()
    {
        $grid = new Smrtr\DataGrid();
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
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSearch()
    {
        $Grid = new Smrtr\DataGrid();
        $Grid->loadCSV($this->_inputPath.'/directgov_external_search_2012-02-05.csv', true, true);
        $this->assertEquals(1, $Grid->searchRows('term="cold weather payments"')->info('rowCount'));
        $this->assertEquals(84, $Grid->searchRows('term*=job, visits>"10,000"')->info('rowCount'));     // OR
        $this->assertEquals(9, $Grid->searchRows('/0*=job + visits>"10,000"')->info('rowCount'));     // AND
        $Grid->transpose();
        $this->assertEquals(51, $Grid->searchColumns('term*=job - "visits">"10,000"')->info('columnCount'));    // NOT
        $this->assertEquals(13, $Grid->searchColumns('("term"*=job - /1>"10,000") + (//>100 - //<400)')->info('columnCount'));
        $Grid->rowLabels(array('"term"','visits'));
        $this->assertEquals(1, $Grid->searchColumns('//<=20 + "\"term\""^=job + visits$=559 + visits>=50000')->info('columnCount'));
        $Grid->setValue('visits', 0, '"')->setValue('visits', 1, 'pipe|');
        $this->assertEquals(2, $Grid->searchColumns('visits="\""|pipe\|')->info('columnCount'));
    }
    
    public function testDeleteEmptyColumnsAndRows()
    {
        $Grid = new Smrtr\DataGrid($this->partialData);
        $Grid = $Grid->deleteEmptyColumns()->deleteEmptyRows();
        $this->assertEquals(2, $Grid->info('rowCount'));
        $this->assertEquals(3, $Grid->info('columnCount'));
        $this->assertTrue($this->isValid($Grid));
    }
    
    public function testGetDistinct()
    {
        $Grid = new Smrtr\DataGrid($this->numberData);
        // getColumnDistinct
        $col0 = ($Grid->getColumnDistinct(0) === array(1, 5, 4, 2));
        $col1 = ($Grid->getColumnDistinct(1) === array(2, 4, 1));
        $col2 = ($Grid->getColumnDistinct(2) === array(3, 5, 1));
        $col3 = ($Grid->getColumnDistinct(3) === array(4, 2, 3));
        $col4 = ($Grid->getColumnDistinct(4) === array(5, 1, 2));
        $this->assertTrue($col0 && $col1 && $col2 && $col3 && $col4);
        // getRowDistinct()
        $row0 = ($Grid->getRowDistinct(0) === array(1, 2, 3, 4, 5));
        $row1 = ($Grid->getRowDistinct(1) === array(5, 4, 3, 2, 1));
        $row2 = ($Grid->getRowDistinct(2) === array(4, 3, 2));
        $row3 = ($Grid->getRowDistinct(3) === array(2, 1, 5, 3));
        $row4 = ($Grid->getRowDistinct(4) === array(5, 1));
        $this->assertTrue($row0 && $row1 && $row2 && $row3 && $row4);
    }
    
    public function testGetCounts()
    {
        $Grid = new Smrtr\DataGrid($this->numberData);
        // getColumnCounts
        $col0 = ($Grid->getColumnCounts(0) === array(1=>1, 5=>2, 4=>1, 2=>1));
        $col1 = ($Grid->getColumnCounts(1) === array(2=>1, 4=>2, 1=>2));
        $col2 = ($Grid->getColumnCounts(2) === array(3=>3, 5=>1, 1=>1));
        $col3 = ($Grid->getColumnCounts(3) === array(4=>1, 2=>2, 3=>1, ''=>1));
        $col4 = ($Grid->getColumnCounts(4) === array(5=>1, 1=>1, ''=>2, 2=>1));
        $this->assertTrue($col0 && $col1 && $col2 && $col3 && $col4);
        // getRowCounts
        $row0 = ($Grid->getRowCounts(0) === array(1=>1, 2=>1, 3=>1, 4=>1, 5=>1));
        $row1 = ($Grid->getRowCounts(1) === array(5=>1, 4=>1, 3=>1, 2=>1, 1=>1));
        $row2 = ($Grid->getRowCounts(2) === array(4=>2, 3=>1, 2=>1, ''=>1));
        $row3 = ($Grid->getRowCounts(3) === array(2=>2, 1=>1, 5=>1, 3=>1));
        $row4 = ($Grid->getRowCounts(4) === array(5=>1, 1=>2, ''=>2));
        $this->assertTrue($row0 && $row1 && $row2 && $row3 && $row4);
        // verbose getColumnCounts
        $col0 = ($Grid->getColumnCounts(0, true) === array( 
            array(1, 1), array(5, 2), array(4, 1), array(2, 1), array(5, 2)
        ));
        $col1 = ($Grid->getColumnCounts(1, true) === array( 
            array(2, 1), array(4, 2), array(4, 2), array(1, 2), array(1, 2)
        ));
        $col2 = ($Grid->getColumnCounts(2, true) === array( 
            array(3, 3), array(3, 3), array(3, 3), array(5, 1), array(1, 1)
        ));
        $col3 = ($Grid->getColumnCounts(3, true) === array( 
            array(4, 1), array(2, 2), array(2, 2), array(3, 1), array('', 1)
        ));
        $col4 = ($Grid->getColumnCounts(4, true) === array( 
            array(5, 1), array(1, 1), array('', 2), array(2, 1), array('', 2)
        ));
        $this->assertTrue($col0 &&$col1 && $col2 && $col3 && $col4);
        // verbose getRowCounts
        $row0 = ($Grid->getRowCounts(0, true) === array( 
            array(1, 1), array(2, 1), array(3, 1), array(4, 1), array(5, 1)
        ));
        $row1 = ($Grid->getRowCounts(1, true) === array( 
            array(5, 1), array(4, 1), array(3, 1), array(2, 1), array(1, 1)
        ));
        $row2 = ($Grid->getRowCounts(2, true) === array( 
            array(4, 2), array(4, 2), array(3, 1), array(2, 1), array('', 1)
        ));
        $row3 = ($Grid->getRowCounts(3, true) === array( 
            array(2, 2), array(1, 1), array(5, 1), array(3, 1), array(2, 2)
        ));
        $row4 = ($Grid->getRowCounts(4, true) === array( 
            array(5, 1), array(1, 2), array(1, 2), array('', 2), array('', 2)
        ));
        $this->assertTrue($row0 && $row1 && $row2 && $row3 && $row4);
    }
    
    public function testHasValue()
    {
        $grid = new Smrtr\DataGrid($this->numberData);
        $this->assertTrue($grid->hasValue(5));
        $this->assertTrue($grid->hasValue(null));
        $this->assertFalse($grid->hasValue(6));
        $this->assertTrue($grid->rowHasValue(3, 0));
        $this->assertFalse($grid->rowHasValue(3, 4));
        $this->assertTrue($grid->columnHasValue(null, 4));
        $this->assertFalse($grid->columnHasValue(1, 3));
        try { $grid->hasValue('value', 'fooBar'); }
        catch (Exception $e) { $this->assertInstanceOf('Smrtr\DataGridException', $e); }
    }
    
    public function testSerializable()
    {
        $grid1 = new Smrtr\DataGrid($this->labelledData, true, true);
        $s1 = serialize($grid1);
        $grid2 = unserialize($s1);
        $this->assertTrue(
            $grid1->info() === $grid2->info() &&
            $grid1->getArray() === $grid2->getArray() &&
            $grid1->getAssociativeArray() === $grid2->getAssociativeArray() &&
            $this->isValid($grid1) && $this->isValid($grid2)
        );
    }
    
    public function testLoadCSV()
    {
        // Labelled Grid
        $grid = new Smrtr\DataGrid;
        $grid->loadCSV($this->_inputPath.'/labelled.csv', true, true);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvRowKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvColumnKeys);
        $this->assertTrue($this->isValid($grid));
        
        // Row-Labelled Grid
        $grid = new Smrtr\DataGrid;
        $grid->loadCSV($this->_inputPath.'/labelled_rows.csv', true, false);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvRowKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvEmptyKeys);
        $this->assertTrue($this->isValid($grid));
        
        // Column-Labelled Grid
        $grid = new Smrtr\DataGrid;
        $grid->loadCSV($this->_inputPath.'/labelled_columns.csv', false, true);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvEmptyKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvColumnKeys);
        $this->assertTrue($this->isValid($grid));
        
        // Unlabelled Grid
        $grid = new Smrtr\DataGrid;
        $grid->loadCSV($this->_inputPath.'/unlabelled.csv', false, false);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvEmptyKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvEmptyKeys);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testReadCSV()
    {
        // Labelled Grid
        $grid = new Smrtr\DataGrid;
        $csv = file_get_contents($this->_inputPath.'/labelled.csv');
        $grid->readCSV($csv, true, true);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvRowKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvColumnKeys);
        $this->assertTrue($this->isValid($grid));
        
        // Row-Labelled Grid
        $grid = new Smrtr\DataGrid;
        $csv = file_get_contents($this->_inputPath.'/labelled_rows.csv');
        $grid->readCSV($csv, true, false);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvRowKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvEmptyKeys);
        $this->assertTrue($this->isValid($grid));
        
        // Column-Labelled Grid
        $grid = new Smrtr\DataGrid;
        $csv = file_get_contents($this->_inputPath.'/labelled_columns.csv');
        $grid->readCSV($csv, false, true);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvEmptyKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvColumnKeys);
        $this->assertTrue($this->isValid($grid));
        
        // Unlabelled Grid
        $grid = new Smrtr\DataGrid;
        $csv = file_get_contents($this->_inputPath.'/unlabelled.csv');
        $grid->readCSV($csv, false, false);
        $this->assertSame($grid->getArray(), $this->csvData);
        $this->assertSame($grid->getRowLabels(), $this->csvEmptyKeys);
        $this->assertSame($grid->getColumnLabels(), $this->csvEmptyKeys);
        $this->assertTrue($this->isValid($grid));
    }
    
    public function testSaveCSV()
    {
        $grid = new Smrtr\DataGrid;
        $grid->loadArray($this->csvData);
        $grid->rowLabels($this->csvRowKeys);
        $grid->columnLabels($this->csvColumnKeys);
        
        // Labelled Grid
        $grid->saveCSV($this->_outputPath.'/labelled.csv', true, true, ',', '"', PHP_EOL);
        $this->assertFileEquals($this->_inputPath.'/labelled.csv', $this->_outputPath.'/labelled.csv');
        
        // Row-Labelled Grid
        $grid->saveCSV($this->_outputPath.'/labelled_rows.csv', true, false, ',', '"', PHP_EOL);
        $this->assertFileEquals($this->_inputPath.'/labelled_rows.csv', $this->_outputPath.'/labelled_rows.csv');
        
        // Column-Labelled Grid
        $grid->saveCSV($this->_outputPath.'/labelled_columns.csv', false, true, ',', '"', PHP_EOL);
        $this->assertFileEquals($this->_inputPath.'/labelled_columns.csv', $this->_outputPath.'/labelled_columns.csv');
        
        // Unlabelled Grid
        $grid->saveCSV($this->_outputPath.'/unlabelled.csv', false, false, ',', '"', PHP_EOL);
        $this->assertFileEquals($this->_inputPath.'/unlabelled.csv', $this->_outputPath.'/unlabelled.csv');
    }
    
    public function testPrintCSV()
    {
        $grid = new Smrtr\DataGrid;
        $grid->loadArray($this->csvData);
        $grid->rowLabels($this->csvRowKeys);
        $grid->columnLabels($this->csvColumnKeys);
        
        // Labelled Grid
        ob_start();
        $grid->printCSV(true, true);
        $this->assertSameLines(ob_get_clean(), file_get_contents($this->_inputPath.'/labelled.csv'));
        
        // Row-Labelled Grid
        ob_start();
        $grid->printCSV(true, false);
        $this->assertSameLines(ob_get_clean(), file_get_contents($this->_inputPath.'/labelled_rows.csv'));
        
        // Column-Labelled Grid
        ob_start();
        $grid->printCSV(false, true);
        $this->assertSameLines(ob_get_clean(), file_get_contents($this->_inputPath.'/labelled_columns.csv'));
        
        // Unlabelled Grid
        ob_start();
        $grid->printCSV(false, false);
        $this->assertSameLines(ob_get_clean(), file_get_contents($this->_inputPath.'/unlabelled.csv'));
    }

    protected function assertSameLines($str1, $str2)
    {
        $lines1 = preg_split("/\r\n|\n|\r/", $str1);
        $lines2 = preg_split("/\r\n|\n|\r/", $str2);
        for ($i=0; $i<max(count($lines1), count($lines2)); $i++) {
            $this->assertSame($lines1[$i], $lines2[$i]);
        }
    }
    
    public function testScalarValuesOnlyByDefault()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->setValue(1, 1, "foobarbodiddly");
        $this->assertSame("foobarbodiddly", $grid->getValue(1, 1));
        // try to set non-scalar, it gets cast to null
        $grid->setValue(1, 1, ['hey']);
        $this->assertSame(null, $grid->getValue(1, 1));
    }

    public function testNonScalarValues()
    {
        $grid = new Smrtr\DataGrid($this->labelledData, true, true);
        $grid->setValue(1, 1, "foobarbodiddly");
        $this->assertSame("foobarbodiddly", $grid->getValue(1, 1));
        // try to set non-scalar, it works this time
        $grid->scalarValuesOnly(false);
        $grid->setValue(1, 1, ['hey']);
        $this->assertSame(['hey'], $grid->getValue(1, 1));
    }
}