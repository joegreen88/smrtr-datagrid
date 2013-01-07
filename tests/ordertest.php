<?php

require_once '../DataGrid.php';
$Grid = new Smrtr_DataGrid;
$Grid->loadCSV(dirname(__FILE__).'/grid.csv', true, true);
echo PHP_EOL;
$Grid->orderRows('A')->printCSV(true, true);
echo PHP_EOL;
$Grid->orderRows('B')->printCSV(true, true);
echo PHP_EOL;
$Grid->orderRows('C')->printCSV(true, true);
echo PHP_EOL;
$Grid->orderRows('D')->printCSV(true, true);
echo PHP_EOL;
$Grid->orderRows('E')->printCSV(true, true);
echo PHP_EOL;
$Grid->orderRows('A');
$Grid->orderColumns('a')->printCSV(true, true, "\t");
echo PHP_EOL;
$Grid->orderColumns('b')->printCSV(true, true, "\t");
echo PHP_EOL;
$Grid->orderColumns('c')->printCSV(true, true, "\t");
echo PHP_EOL;
$Grid->orderColumns('d')->printCSV(true, true, "\t");
echo PHP_EOL;
$Grid->orderColumns('e')->printCSV(true, true, "\t");