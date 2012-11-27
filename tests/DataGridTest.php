<?php
/**
 * @author Joe Green
 * DataGridTest
 * These tests are reproducible and should go a long way to
 * inspire confidence in my DataGrid class.
 */
require_once('Smrtr/DataGrid.php');
class Smrtr_Test_DataGridTest extends Smrtr_ControllerTestCase
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
        $g1->eachRow(function($key, $data) use($g2, $self){
            $cond = $g2->getColumn($key) == $data;
            $self->assertTrue($cond);
        })->eachColumn(function($key, $data) use($g2, $self){
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
    
    public function testJsonToCsv()
    {
        $DG = new Smrtr_DataGrid();
        $DG->loadJSON(
            "http://api.twitter.com/1/statuses/user_timeline.json?include_rts=true&screen_name=joegreen88&count=10",
            false, true
        );
        $unwanted = array('geo', 'coordinates', 'place', 'retweet_count', 'favorited',
            'truncated', 'user', 'retweeted', 'contributors');
        foreach ($unwanted as $label)
            if ($DG->hasLabel('column', $label))
                $DG->deleteColumn($label);
        $DG->printCSV(true, true);
    }
    
    public function testCsvToJson()
    {
        $DG = new Smrtr_DataGrid();
        $csv = <<<CSV
,created_at,id,id_str,text,source,in_reply_to_status_id,in_reply_to_status_id_str,in_reply_to_user_id,in_reply_to_user_id_str,in_reply_to_screen_name
0,"Sun Nov 25 11:18:25 +0000 2012",272660508740562945,272660508740562945,"Definitely going to get the data grid on github today. Watch out.",web,,,,,
1,"Fri Nov 23 16:21:42 +0000 2012",272012055769382913,272012055769382913,"@Stuey_L I can mess with java, but only really get to use it for mobile apps",web,,272011786729955328,272011786729955328,210535114,210535114
2,"Fri Nov 23 16:17:55 +0000 2012",272011104400572418,272011104400572418,"@Stuey_L lol nah dogg I went the technology route in the end, too much love for shiny things. Still love seein ppl put maths to use tho!!",web,,272010560298692609,272010560298692609,210535114,210535114
3,"Fri Nov 23 16:15:55 +0000 2012",272010600987631617,272010600987631617,"Anyone know any other specialised tools for database version control?",web,,,,,
4,"Fri Nov 23 16:14:34 +0000 2012",272010260833779712,272010260833779712,"@Stuey_L ACCA.... is that like the freemasons or something? ;)",web,,272009572506558464,272009572506558464,210535114,210535114
5,"Fri Nov 23 14:12:41 +0000 2012",271979586290585601,271979586290585601,"don't like liars. I asked him what he ate for lunch and he's telling me pork pies","TweetCaster for Android",,,,,
6,"Fri Nov 23 13:58:10 +0000 2012",271975934528196609,271975934528196609,"RT @sixthformpoet: That awkward moment when you feel awkward for a moment and tweet about it.","TweetCaster for Android",,,,,
7,"Fri Nov 23 11:40:36 +0000 2012",271941315284058112,271941315284058112,"http://t.co/z0FAE7ig a small php package which facilitates database version control through svn or your vcs.","TweetCaster for Android",,,,,
8,"Fri Nov 23 09:22:36 +0000 2012",271906586669219840,271906586669219840,"THis morning I defeated the notorious south eastern train service, although the fight did get delayed and most o the spectators had to stand",web,,,,,
9,"Fri Nov 23 08:04:53 +0000 2012",271887028621291520,271887028621291520,"It's Friday. There's no way I'm not winning my battles today. #dominatingTheSpace","TweetCaster for Android",,,,,
CSV;
        $DG->readCSV($csv);
        $DG->deleteRow(9);
        $DG->printJSON(true, true);
    }
    
}