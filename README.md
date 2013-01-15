Smrtr DataGrid
=============

A 2D-array wrapper with methods for import/export. PHP5.3 +
 
This class is a one-stop-shop for transporting tabular data between formats. Planned formats include XML and MySQL. Handles unique string labels on rows and columns.

Examples and phpdoc can be found at http://grid.smrtr.co.uk

## Methods for CSV and JSON:

 * loadCSV, loadJSON
 * readCSV, readJSON
 * saveCSV, saveJSON
 * serveCSV, serveJSON
 * printCSV, printJSON

## Keys & Labels:

 * appendKey
 * appendKeys
 * updateKey
 * prependKey
 * deleteKey
 * swapKeys
 * moveKey
 * trimKeys
 * padKeys
 * getKey
 * getLabel
 * hasKey
 * hasLabel

## Rows & Columns:

 * appendRow, appendColumn
 * updateRow, updateColumn
 * prependRow, prependColumn
 * getRow, getColumn
 * emptyRow, emptyColumn
 * deleteRow, deleteColumn
 * renameRow, renameColumn
 * swapRows, swapColumns
 * moveRow, moveColumn
 * trimRows, trimColumns
 * takeRow, takeColumn
 * eachRow, eachColumn
 * row, column (PHP5.4 +, returns `Smrtr_DataGrid_Vector` object)

[![endorse](http://api.coderwall.com/joegreen88/endorsecount.png)](http://coderwall.com/joegreen88)