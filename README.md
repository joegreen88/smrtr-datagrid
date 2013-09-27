# Smrtr DataGrid [![Build Status](https://travis-ci.org/joegreen88/smrtr-datagrid.png?branch=master)](https://travis-ci.org/joegreen88/smrtr-datagrid) [ ![Codeship Status for joegreen88/smrtr-datagrid](https://www.codeship.io/projects/fb908060-a44b-0130-ffc3-1e80e73e8cde/status?branch=master)](https://www.codeship.io/projects/3644) [![Coverage Status](https://coveralls.io/repos/joegreen88/smrtr-datagrid/badge.png?branch=master)](https://coveralls.io/r/joegreen88/smrtr-datagrid?branch=master)

A tool for working with 2d arrays, spreadsheets and data grids in PHP.

 - CSVs
 - JSON
 - [Search](http://datagrid.smrtr.co.uk/tutorial/searching)
 - Transformations
 - Fluent API
 - [Iteration & Filtering](http://datagrid.smrtr.co.uk/tutorial/iterating--filtering)

## Examples

    <?php
    
    use Smrtr\DataGrid;
    
    $grid = new DataGrid(
      array(
          array("First name", "Last Name", "Position", "Age"),
          "WR" => array("Wayne", "Rooney", "striker", 27),
          "KG" => array("Kieran", "Gibbs", "left back", 23),
          "GB" => array("Gareth", "Barry", "midfielder", 32),
          "TW" => array("Theo", "Walcott", "striker", 24)
      ),
      DataGrid::ASSOC_COLUMN_FIRST,
      DataGrid::ASSOC_ROW_KEYS
    );
    
    $grid->saveCSV('/path/to/file.csv');
    
    $grid->serveJSON('download.json');
    
    $grid2 = new DataGrid;
    $grid2->loadCSV('/path/to/file.csv', true, true);

    print_r($grid->searchRows('Age<25 + Position=striker')->getRowLabels()); // [ "TW" ]
    
    print_r($grid->searchRows('Age<25 - Position=striker')->getRowLabels()); // [ "KG" ]
    
    print_r($grid->searchRows('Age<30 + (Position="left back" , Position=midfielder)')->getRowLabels()); // [ "KG" ]
    
    print_r($grid->searchRows('(Age<30 + Position="left back") , Position=midfielder')->getRowLabels()); // [ "KG", "GB" ]
    
    echo $grid->row('WR')['First name']; // "Wayne"
    
    $grid->renameColumn('First name', 'Forename');
    
    $s = serialize($grid);
    
    $grid = unserialize($s);
    
    $inverse = $grid->transpose();
    
    echo ($grid->getRowKeys() === $invers->getColumnKeys()) ? 'Y' : 'N'; // "Y"

## Install with composer

    {
        "require": {
            "smrtr/datagrid": ">=1.3.2"
        }
    }
    
`composer update`

## Tutorial
[Click here](http://datagrid.smrtr.co.uk/tutorial/getting-started).
