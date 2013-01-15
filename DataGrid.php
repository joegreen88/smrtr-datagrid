<?php class Smrtr_DataGrid_Exception extends Exception {}
require_once('DataGridVector.php');

/**
 * 2D-array wrapper with methods for import/export. PHP5.3 +
 * 
 * This class is a one-stop-shop for transporting tabular data between formats.
 * We currently provide methods for CSV and JSON.
 * Planned formats include XML. 
 * Handles custom keys (a.k.a. labels) on rows and columns.
 * 
 * @author Joe Green
 * @package SmrtrLib
 * @version 1.0
 * @todo XML import/export and methods
 */

class Smrtr_DataGrid
{
    
    // Factory
    public static $registry = array();
    public static $IDcounter=0;
    public $ID;
    
    /**
     * A count of columns 
     * @var int
     */
    protected $columns;
    
    /**
     * A count of rows
     * @var int 
     */
    protected $rows;
    
    /**
     * A map from column keys to column labels or null
     * @var array 
     */
    protected $columnKeys = array();
    
    /**
     * A map from row keys to row labels or null
     * @var array 
     */
    protected $rowKeys = array();
    
    /**
     * A 2-Dimensional array of scalar data
     * @var array 
     */
    protected $data = array();
    
    /**
     * A map of operators to matching-functions
     * @var array
     */
    protected $selectors;
    
    /**
     * An array cache of operator characters
     * @var array
     */
    protected $operatorChars;
    
    /**
     * Maximum number of selectors in any given selector string 
     * @int
     */
    const maxSelectors = 10;
    
    /**
     * Maximum length of field string
     * @int 
     */
    const maxFieldLength = 50;
    
    /**
     * Maximum length of operator string
     * @int 
     */
    const maxOperatorLength = 2;
    
    /**
     * Maximum length of value string 
     * @int
     */
    const maxValueLength = 1000;
    
    
    /*
     * ================================================================
     * Keys & Labels
     * ================================================================
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
     * ________________________________________________________________
     */
    
    /**
     * @internal
     */
    public function appendKey( $rowOrColumn, $label=null )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (is_string($label) && strlen($label))
        {
            if (in_array($label, $this->{$rowOrColumn.'Keys'}))
                throw new Smrtr_DataGrid_Exception($rowOrColumn."Key '$label' already exists");
        }
        elseif (!is_null($label))
            throw new Smrtr_DataGrid_Exception("non-empty string \$label or null expected");
        
        array_push($this->{$rowOrColumn.'Keys'}, $label);
        return $this;
    }
    
    /**
     * @internal
     */
    public function appendKeys( $rowOrColumn, array $labels)
    {
        foreach ($labels as $label)
            $this->appendKey($rowOrColumn, $label);
    }
    
    /**
     * Update the label for an existing key
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @param string|null $label
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception 
     */
    public function updateKey( $rowOrColumn, $key, $label=null )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (!is_int($key) || !array_key_exists($key, $this->{$rowOrColumn.'Keys'}))
            throw new Smrtr_DataGrid_Exception("key not found");
        if (is_string($label) && strlen($label))
        {
            if (in_array($label, $this->{$rowOrColumn.'Keys'}))
                throw new Smrtr_DataGrid_Exception($rowOrColumn."Key '$label' already exists");
        }
        elseif (!is_null($label))
            throw new Smrtr_DataGrid_Exception("non-empty string \$label or null expected");
        $this->{$rowOrColumn.'Keys'}[$key] = $label;
        return $this;
    }
    
    /**
     * Get row labels, or optionally update row labels
     * 
     * @api
     * @param array|false $labels [optional]
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::updateKey() if updating row labels
     */
    public function rowLabels( $labels=false )
    {
        if (false === $labels)
            return $this->rowKeys;
        elseif (is_array($labels))
        {
            if ($this->rows == 0)
                throw new Smrtr_DataGrid_Exception("Cannot assign labels to empty DataGrid");
            $rowKeys = $this->_normalizeKeys($labels, $this->rows);
            $this->rowKeys = $rowKeys;
            return $this;
        }
        throw new Smrtr_DataGrid_Exception("\$labels Array or false|void expected");
    }
    
    /**
     * Get column labels, or optionally update column labels
     * 
     * @api
     * @param array|false $labels [optional]
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception 
     * @uses Smrtr_DataGrid::updateKey() if updating column labels
     */
    public function columnLabels( $labels=false )
    {
        if (false === $labels)
            return $this->columnKeys;
        elseif (is_array($labels))
        {
            if ($this->columns == 0)
                throw new Smrtr_DataGrid_Exception("Cannot assign labels to empty DataGrid");
            $columnKeys = $this->_normalizeKeys($labels, $this->columns);
            $this->columnKeys = $columnKeys;
            return $this;
        }
        throw new Smrtr_DataGrid_Exception("\$labels Array or false|void expected");
    }
    
    /**
     * @internal
     */
    public function prependKey( $rowOrColumn, $label=null )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (is_string($label) && strlen($label))
        {
            if (in_array($label, $this->{$rowOrColumn.'Keys'}))
                throw new Smrtr_DataGrid_Exception($rowOrColumn."Key '$label' already exists");
        }
        elseif (!is_null($label))
            throw new Smrtr_DataGrid_Exception("non-empty string \$label or null expected");
        
        array_unshift($this->{$rowOrColumn.'Keys'}, $label);
        $this->{$rowOrColumn.'s'}++;
        return $this;
    }
    
    /**
     * @internal
     */
    public function deleteKey( $rowOrColumn, $keyOrLabel )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        $key = $this->getKey($rowOrColumn, $keyOrLabel);
        unset($this->{$rowOrColumn.'Keys'}[$key]);
        if ($key != $this->{$rowOrColumn.'s'}-1)
            $this->{$rowOrColumn.'Keys'} = $this->_normalizeKeys(
                $this->{$rowOrColumn.'Keys'}, $this->{$rowOrColumn.'s'}-1
            );
        return $this;
    }
    
    /**
     * Remove label identified by key/label
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int|string $keyOrLabel
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     */
    public function emptyKey( $rowOrColumn, $keyOrLabel )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        $key = $this->getKey($rowOrColumn, $keyOrLabel);
        $this->{$rowOrColumn.'Keys'}[$key] = null;
        return $this;
    }
    
    /**
     * Swap two key-labels positionally
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int|string $keyOrLabel1
     * @param int|string $keyOrLabel2
     * @param boolean $stickyData [optional] true by default. swap rows/columns with keys.
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::getLabel()
     * @uses Smrtr_DataGrid::emptyKey()
     * @uses Smrtr_DataGrid::updateKey()
     */
    public function swapKeys( $rowOrColumn, $keyOrLabel1, $keyOrLabel2, $stickyData=true )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if ($stickyData)
            $this->{'swap'.ucfirst($rowOrColumn)}('row', $keyOrLabel1, $keyOrLabel2, false);
        $Key1 = $this->getKey($rowOrColumn, $keyOrLabel1);
        $Key2 = $this->getKey($rowOrColumn, $keyOrLabel2);
        $Label1 = $this->getLabel($rowOrColumn, $Key1);
        $Label2 = $this->getLabel($rowOrColumn, $Key2);
        $this->emptyKey($rowOrColumn, $Key2);
        $this->updateKey($rowOrColumn, $Key1, $Label2);
        $this->updateKey($rowOrColumn, $Key2, $Label1);
        return $this;
    }
    
    /**
     * Move a key-label to position of existing key/label
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int|string $from_KeyOrLabel
     * @param int|string $to_KeyOrLabel
     * @param boolean $stickyData [optional] true by default. move row/column with key.
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::getLabel()
     * @uses Smrtr_DataGrid::emptyKey()
     * @uses Smrtr_DataGrid::updateKey()
     */
    public function moveKey( $rowOrColumn, $from_KeyOrLabel, $to_KeyOrLabel, $stickyData=true )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if ($stickyData)
            $this->{'move'.ucfirst($rowOrColumn)}(
                $rowOrColumn, $to_KeyOrLabel, $from_KeyOrLabel, false
            );
            
        $keyTo = $this->getKey($rowOrColumn, $to_KeyOrLabel);        
        $keyFrom = $this->getKey($rowOrColumn, $from_KeyOrLabel);
        if ($keyFrom === $keyTo)
            return $this;
        $Label = $this->getLabel($rowOrColumn, $keyFrom);
        if ($keyFrom < $keyTo)
            for ($i = $keyFrom; $i < $keyTo; $i++)
            {
                $tmpLabel = $this->getLabel($rowOrColumn, $i+1);
                $this->emptyKey($rowOrColumn, $i+1);
                $this->updateKey($rowOrColumn, $i, $tmpLabel);
            }
        else
            for ($i = $keyFrom; $i > $keyTo; $i--)
            {
                $tmpLabel = $this->getLabel($rowOrColumn, $i-1);
                $this->emptyKey($rowOrColumn, $i-1);
                $this->updateKey($rowOrColumn, $i, $tmpLabel);
            }
            
        $this->updateKey($rowOrColumn, $keyTo, $Label);
        return $this;
    }
    
    /**
     * @internal
     */
    public function trimKeys( $rowOrColumn, $length )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (!is_int($length) || $length < 0)
            throw new Smrtr_DataGrid_Exception("positive int \$length expected");
        $this->{$rowOrColumn.'Keys'} = array_slice(
            $this->{$rowOrColumn.'Keys'}, 0, $length
        );
        return $this;
    }
    
    /**
     * @internal
     */
    public function padKeys( $rowOrColumn, $length )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (!is_int($length) || $length < 0)
            throw new Smrtr_DataGrid_Exception("positive int \$length expected");
        if (count($this->{$rowOrColumn.'Keys'}) < $length)
            $this->{$rowOrColumn.'Keys'} = array_pad(
                $this->{$rowOrColumn.'Keys'}, $length, null
            );
        return $this;
    }
    
    /**
     * Get key from a key or label
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int|string $keyOrLabel
     * @return int
     * @throws Smrtr_DataGrid_Exception 
     */
    public function getKey( $rowOrColumn, $keyOrLabel )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (is_string($keyOrLabel))
        {
            $offset = array_search($keyOrLabel, $this->{$rowOrColumn.'Keys'});
            if (false !== $offset)
                return $offset;
            throw new Smrtr_DataGrid_Exception("Label '$keyOrLabel' not found");
        }
        elseif (is_int($keyOrLabel))
        {
            if (array_key_exists($keyOrLabel, $this->{$rowOrColumn.'Keys'}))
                return $keyOrLabel;
            throw new Smrtr_DataGrid_Exception("$rowOrColumn Key $keyOrLabel not found");
        }
        else
            throw new Smrtr_DataGrid_Exception("\$keyOrLabel can be int or string only");
    }
    
    /**
     * Get keys array for rows or columns
     * 
     * @param string $rowOrColumn 'row' or 'column'
     * @return array 
     */
    public function getKeys( $rowOrColumn )
    {
        return array_keys($this->{$rowOrColumn.'Keys'});
    }
    
    /**
     * Get label from a key
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @return string
     * @throws Smrtr_DataGrid_Exception 
     */
    public function getLabel( $rowOrColumn, $key )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGridException("'column' or 'row' expected");
        if (!is_int($key))
            throw new Smrtr_DataGrid_Exception("int \$key expected");
        if (array_key_exists($key, $this->{$rowOrColumn.'Keys'}))
            return $this->{$rowOrColumn.'Keys'}[$key];
        return false;
    }
    
    /**
     * Get labels array for rows or columns, indexed by keys
     * 
     * @param string $rowOrColumn 'row' or 'column'
     * @return array
     */
    public function getLabels( $rowOrColumn )
    {
        return $this->{$rowOrColumn.'Keys'};
    }
    
    /**
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @return boolean
     * @throws Smrtr_DataGrid_Exception 
     */
    public function hasKey( $rowOrColumn, $key )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (!is_int($key))
            throw new Smrtr_DataGrid_Exception("int \$key expected");
        return array_key_exists($key, $this->{$rowOrColumn.'Keys'});
    }
    
    /**
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param string $label
     * @return boolean
     * @throws Smrtr_DataGrid_Exception 
     */
    public function hasLabel( $rowOrColumn, $label )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new Smrtr_DataGrid_Exception("'column' or 'row' expected");
        if (!is_string($label))
            throw new Smrtr_DataGrid_Exception("string \$label expected");
        return in_array($label, $this->{$rowOrColumn.'Keys'});
    }
    
    
    /*
     * ================================================================
     * Rows
     * ================================================================
     * appendRow
     * updateRow
     * prependRow
     * getRow
     * emptyRow
     * deleteRow
     * renameRow
     * swapRows
     * moveRow
     * trimRows
     * takeRow
     * eachRow
     * orderRows
     * ________________________________________________________________
     */
    
    /**
     * Append a row to the end of the grid
     * 
     * @api
     * @param array $row
     * @param string|null $label [optional] string label for the appended row
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::appendKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function appendRow($row, $label=null)
    {
        if ($row instanceof Smrtr_DataGridVector)
            $row = $row->data();
        if (!is_array($row))
            throw new Smrtr_DataGrid_Exception("array expected");
        
        $this->appendKey('row', $label);
        $rowVector = $this->_normalizeVector($row, $this->columns);
        
        if (count($rowVector) > $this->columns)
        {
            $lim = count($rowVector) - $this->columns;
            for ($i=0; $i<$lim; $i++)
                $this->appendColumn(array(), null);
        }
        
        array_push($this->data, $rowVector);
        $this->rows++;
        return $this;
    }
    
    /**
     * Set an array to the grid by overwriting an existing row
     * 
     * @api
     * @param int|string $keyOrLabel
     * @param array $row
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function updateRow($keyOrLabel, $row)
    {
        if ($row instanceof Smrtr_DataGridVector)
            $row = $row->data();
        if (!is_array($row))
            throw new Smrtr_DataGrid_Exception("array expected");
        
        $key = $this->getKey('row', $keyOrLabel);
        $this->data[$key] = $this->_normalizeVector($row, $this->columns);
        return $this;
    }
    
    /**
     * Prepend a row to the start of the grid
     * 
     * @api
     * @param array $row
     * @param string|null $label [optional] string label for the prepended row
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::prependKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function prependRow($row, $label=null)
    {
        if ($row instanceof Smrtr_DataGridVector)
            $row = $row->data();
        if (!is_array($row))
            throw new Smrtr_DataGrid_Exception("array expected");
        
        $this->prependKey('row', $label);
        array_unshift($this->data, $this->_normalizeVector($row, $this->columns));
        $this->rows++;
        return $this;
    }
    
    /**
     * Get the values from a row
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return array
     * @uses Smrtr_DataGrid::getKey()
     */
    public function getRow( $keyOrLabel )
    {
        $key = $this->getKey('row', $keyOrLabel);
        return $this->data[$key];
    }
    
    /**
     * Fill a row with null values
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function emptyRow( $keyOrLabel )
    {
        $key = $this->getKey('row', $keyOrLabel);
        $this->data[$key] = $this->normalizeVector(array(), $this->columns);
        return $this;
    }
    
    /**
     * Delete a row from the grid
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::moveRow()
     * @uses Smrtr_DataGrid::deleteKey()
     */
    public function deleteRow( $keyOrLabel )
    {
        $lastRowKey = $this->rows - 1;
        $this->moveRow($keyOrLabel, $lastRowKey, true);
        $this->deleteKey('row', $lastRowKey);
        unset($this->data[$lastRowKey]);
        $this->rows = $lastRowKey;
        return $this;
    }
    
    /**
     * Rename a row i.e. update the label on the key for that row
     * 
     * @api
     * @param int|string $from_KeyOrLabel
     * @param string $to_Label
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::updateKey()
     */
    public function renameRow( $from_KeyOrLabel, $to_Label )
    {
        $keyFrom = $this->getKey('row', $from_KeyOrLabel);
        $this->updateKey('row', $keyFrom, $to_Label);
        return $this;
    }
    
    /**
     * Swap two rows positionally
     * 
     * @api
     * @param int|string $keyOrLabel1
     * @param int|string $keyOrLabel2
     * @param boolean $stickyLabels [optional] defaults to true. Swap labels with rows.
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::swapKeys() if $stickyLabels
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::getRow()
     * @uses Smrtr_DataGrid::updateRow()
     */
    public function swapRows($keyOrLabel1, $keyOrLabel2, $stickyLabels=true)
    {
        if ($stickyLabels)
            $this->swapKeys('row', $keyOrLabel1, $keyOrLabel2, false);
        $Key1 = $this->getKey('row', $keyOrLabel1);
        $Key2 = $this->getKey('row', $keyOrLabel2);
        $row1 = $this->getRow($Key1);
        $this->updateRow($Key1, $this->getRow($Key2));
        $this->updateRow($Key2, $row1);
        return $this;
    }
    
    /**
     * Move a row to the position of an existing row
     * 
     * @api
     * @param int|string $from_KeyOrLabel
     * @param int|string $to_KeyOrLabel
     * @param boolean $stickyLabels [optional] Defaults to true. Move label with row.
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::moveKey() if $stickyLabels
     * @uses Smrtr_DataGrid::getRow()
     * @uses Smrtr_DataGrid::updateRow()
     */
    public function moveRow( $from_KeyOrLabel, $to_KeyOrLabel, $stickyLabels=true )
    {
        $keyTo = $this->getKey('row', $to_KeyOrLabel);
        $keyFrom = $this->getKey('row', $from_KeyOrLabel);
        if ($stickyLabels)
            $this->moveKey('row', $from_KeyOrLabel, $to_KeyOrLabel, false);
        if ($keyFrom === $keyTo)
            return $this;
        $rowData = $this->getRow($keyFrom);
        if ($keyFrom < $keyTo)
            for ($i = $keyFrom; $i < $keyTo; $i++)
                $this->updateRow( $i, $this->getRow($i+1) );
        else
            for ($i = $keyFrom; $i > $keyTo; $i--)
                $this->updateRow( $i, $this->getRow($i-1) );
        $this->updateRow( $keyTo, $rowData );
        return $this;
    }
    
    /**
     * @internal
     */
    public function trimRows( $length )
    {
        if (!is_int($length) || $length < 0)
            throw new Smrtr_DataGrid_Exception("positive int \$length expected");
        $this->data = array_slice(
            $this->data, 0, $length
        );
        return $this;
    }
    
    /**
     * Delete row fro the grid and return its data
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return array
     * @uses Smrtr_DataGrid::getRow()
     * @uses Smrtr_DataGrid::deleteRow() 
     */
    public function takeRow( $keyOrLabel )
    {
        $return = $this->getRow($keyOrLabel);
        $this->deleteRow($keyOrLabel);
        return $return;
    }
    
    /**
     * Loop through rows and execute a callback function on each row. f(key, row)
     * Row provided to callback as array by default (faster), or optionally as Smrtr_DataGridVector object.
     * 
     * @api
     * @param callable $callback
     * @param boolean $returnVectorObject 
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception 
     * @uses Smrtr_DataGrid::row()
     * @uses Smrtr_DataGrid::getRow()
     */
    public function eachRow( $callback, $returnVectorObject=false )
    {
        if (!is_callable($callback))
            throw new Smrtr_DataGrid_Exception("\$callback provided is not callable");
        foreach (array_keys($this->rowKeys) as $key)
        {
            $row = $returnVectorObject ? $this->row($key) : $this->getRow($key);
            $callback($key, $row);
        }
        return $this;
    }
    
    /**
     * Order rows by a particular column (ascending or descending)
     * 
     * @api
     * @param int|string $byColumnKeyOrLabel
     * @param string $order 'asc' or 'desc'. Defaults to 'asc'
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::getLabel()
     * @uses Smrtr_DataGrid::rowLabels()
     */
    public function orderRows( $byColumnKeyOrLabel, $order='asc', $stickyLabels=true )
    {
        switch ($order)
        {
            case 'asc': $sortFunction = 'ksort'; break;
            case 'desc': $sortFunction = 'krsort'; break;
            default: throw new Smrtr_DataGrid_Exception("\$order of 'asc' or 'desc' expected"); break;
        }
        $searchKey = $this->getKey('column', $byColumnKeyOrLabel);
        $stack = array(); $keyStack = array();
        $self = $this;
        $this->eachRow( function($i, $row) use(&$stack, &$keyStack, $searchKey, $self)
        {
            $val = $row[$searchKey];
            if (!array_key_exists($val, $stack))
            {
                $stack[$val] = array();
                $keyStack[$val] = array();
            }
            $stack[$val][] = $row;
            $keyStack[$val][] = $self->getLabel('row', $i);
        });
        $sortFunction($stack);
        $sortFunction($keyStack);
        $data = array();
        $keys = array();
        foreach ($stack as $val => $stack2)
        {
            foreach ($stack2 as $key => $row)
            {
                $keys[] = $keyStack[$val][$key];
                $data[] = $row;
            }
        }
        $this->data = $data;
        if ($stickyLabels)
            $this->rowLabels($keys);
        return $this;
    }
    
    /**
     * Filters rows by use of a callback function as a filter.
     * To overwrite this object just call like so: $Grid = $Grid->filterRows();
     * 
     * @param callable $filter Called on each row: $filter($key, $label, $row) where $row is a natural-indexed array
     * @return Smrtr_DataGrid new Smrtr_DataGrid with results
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getRow()
     * @uses Smrtr_DataGrid::appendRow()
     */
    public function filterRows( $filter )
    {
        if (!is_callable($filter))
            throw new Smrtr_DataGrid_Exception("\$filter provided is not callable");
        $Grid = new Smrtr_DataGrid;
        $Grid->appendKeys('column', $this->columnKeys);
        foreach ($this->rowKeys as $key => $label)
        {
            $row = $this->getRow($key);
            $result = (boolean) $filter($key, $label, $row);
            if ($result)
                $Grid->appendRow($row, $label);
        }
        return $Grid;
    }
    
    
    /*
     * ================================================================
     * Columns
     * ================================================================
     * appendColumn
     * updateColumn
     * prependColumn
     * getColumn
     * emptyColumn
     * deleteColumn
     * renameColumn
     * swapColumns
     * moveColumn
     * trimColumns
     * takeColumn
     * eachColumn
     * orderColumns
     * ________________________________________________________________
     */
    
    /**
     * Append a column to the end of the grid
     * 
     * @api
     * @param array $column
     * @param string|null $label [optional] string label for the appended column
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::appendKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function appendColumn( $column, $label=null )
    {
        if ($column instanceof Smrtr_DataGridVector)
            $column = $column->data();
        if (!is_array($column))
            throw new Smrtr_DataGrid_Exception("array expected");
        
        $this->appendKey('column', $label);
        $colVector = $this->_normalizeVector($column, $this->rows);
        
        if (count($colVector) > $this->rows)
        {
            $lim = count($colVector) - $this->rows;
            for ($i=0; $i<$lim; $i++)
                $this->appendRow(array(), null);
        }
        
        foreach ($this->data as $i => $row)
            array_push($this->data[$i], array_shift($colVector));
        $this->columns++;
        return $this;
    }
    
    /**
     * Set an array to the grid by overwriting an existing column
     * 
     * @api
     * @param int|string $keyOrLabel
     * @param array $column
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function updateColumn($keyOrLabel, $column)
    {
        if ($column instanceof Smrtr_DataGridVector)
            $column = $column->data();
        if (!is_array($column))
            throw new Smrtr_DataGrid_Exception("array expected");
        
        $key = $this->getKey('column', $keyOrLabel);
        $colVector = $this->_normalizeVector($column, $this->rows);
        foreach ($this->data as $i => $row)
            $this->data[$i][$key] = array_shift($colVector);
        return $this;
    }
    
    /**
     * Prepend a column to the start of the grid
     * 
     * @api
     * @param array $column
     * @param string|null $label [optional] string label for the prepended column
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::prependKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function prependColumn( $column, $label=false )
    {
        if ($column instanceof Smrtr_DataGridVector)
            $column = $column->data();
        if (!is_array($column))
            throw new Smrtr_DataGrid_Exception("array expected");
        
        $this->prependKey('column', $label);
        $colVector = $this->_normalizeVector($column, $this->rows);
        foreach ($this->data as $i => $row)
            array_unshift($this->data[$i], array_shift($colVector));
        $this->columns++;
        return $this;
    }
    
    /**
     * Get the values from a column
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return array
     * @uses Smrtr_DataGrid::getKey()
     */
    public function getColumn( $keyOrLabel )
    {
        $key = $this->getKey('column', $keyOrLabel);
        $column = array();
        foreach ($this->data as $i => $row)
            $column[$i] = $row[$key];
        return $column;
    }
    
    /**
     * Fill a column with null values
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::_normalizeVector()
     */
    public function emptyColumn( $keyOrLabel )
    {
        $key = $this->getKey('column', $keyOrLabel);
        foreach ($this->data as $i => $row)
            $this->data[$i][$key] = null;
        return $this;
    }
    
    /**
     * Delete a column from the grid
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::moveColumn()
     * @uses Smrtr_DataGrid::deleteKey()
     */
    public function deleteColumn( $keyOrLabel )
    {
        $lastColKey = $this->columns - 1;
        $this->moveColumn($keyOrLabel, $lastColKey, true);
        $this->deleteKey('column', $lastColKey);
        foreach ($this->data as $i => $row)
            unset($this->data[$i][$lastColKey]);
        $this->columns = $lastColKey;
        return $this;
    }
    
    /**
     * Rename a column i.e. update the label on the key for that row
     * 
     * @api
     * @param int|string $from_KeyOrLabel
     * @param string $to_Label
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::updateKey()
     */
    public function renameColumn( $from_KeyOrLabel, $to_Label )
    {
        $keyFrom = $this->getKey('column', $from_KeyOrLabel);
        $this->updateKey('column', $keyFrom, $to_Label);
        return $this;
    }
    
    /**
     * Swap two columns positionally
     * 
     * @api
     * @param int|string $keyOrLabel1
     * @param int|string $keyOrLabel2
     * @param boolean $stickyLabels [optional] defaults to true. Swap labels with columns.
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::swapKeys() if $stickyLabels
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::getColumn()
     * @uses Smrtr_DataGrid::updateColumn()
     */
    public function swapColumns($keyOrLabel1, $keyOrLabel2, $stickyLabels=true)
    {
        if ($stickyLabels)
            $this->swapKeys('column', $keyOrLabel1, $keyOrLabel2, false);
        $Key1 = $this->getKey('column', $keyOrLabel1);
        $Key2 = $this->getKey('column', $keyOrLabel2);
        $column1 = $this->getColumn($Key1);
        $this->updateColumn($Key1, $this->getColumn($Key2));
        $this->updateColumn($Key2, $column1);
        return $this;
    }
    
    /**
     * Move a column to the position of an existing column
     * 
     * @api
     * @param int|string $from_KeyOrLabel
     * @param int|string $to_KeyOrLabel
     * @param boolean $stickyLabels [optional] Defaults to true. Move label with column.
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::moveKey() if $stickyLabels
     * @uses Smrtr_DataGrid::getColumn()
     * @uses Smrtr_DataGrid::updateColumn()
     */
    public function moveColumn( $from_KeyOrLabel, $to_KeyOrLabel, $stickyLabels=true )
    {
        $keyTo = $this->getKey('column', $to_KeyOrLabel);
        $keyFrom = $this->getKey('column', $from_KeyOrLabel);
        if ($stickyLabels)
            $this->moveKey('column', $from_KeyOrLabel, $to_KeyOrLabel, false);
        if ($keyFrom === $keyTo)
            return $this;
        $columnData = $this->getColumn($keyFrom);
        if ($keyFrom < $keyTo)
            for ($i = $keyFrom; $i < $keyTo; $i++)
                $this->updateColumn( $i, $this->getColumn($i+1) );
        else
            for ($i = $keyFrom; $i > $keyTo; $i--)
                $this->updateColumn( $i, $this->getColumn($i-1) );
        $this->updateColumn( $keyTo, $columnData );        
        return $this;
    }
    
    /**
     * @internal
     */
    public function trimColumns( $length )
    {
        if (!is_int($length) || $length < 0)
            throw new Smrtr_DataGrid_Exception("positive int \$length expected");
        if ($length > $this->columns)
            return $this;
        foreach ($this->data as $i => $row)
            $this->data[$i] = array_slice(
                $this->data[$i], 0, $length
            );
        return $this;
    }
    
    /**
     * Delete column from the grid and return its data
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return array
     * @uses Smrtr_DataGrid::getColumn()
     * @uses Smrtr_DataGrid::deleteColumn() 
     */
    public function takeColumn( $keyOrLabel )
    {
        $return = $this->getColumn($keyOrLabel);
        $this->deleteColumn($keyOrLabel);
        return $return;
    }
    
    /**
     * Loop through columns and execute a callback function on each column. f(key, columndata)
     * Column provided to callback as array by default (faster), or optionally as Smrtr_DataGridVector object.
     * 
     * @api
     * @param callable $callback
     * @param boolean $returnVectorObject 
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception 
     * @uses Smrtr_DataGrid::column()
     * @uses Smrtr_DataGrid::getColumn()
     */
    public function eachColumn( $callback, $returnVectorObject=false )
    {
        if (!is_callable($callback))
            throw new Smrtr_DataGrid_Exception("\$callback provided is not callable");
        foreach (array_keys($this->columnKeys) as $key)
        {
            $column = $returnVectorObject ? $this->column($key) : $this->getColumn($key);
            $callback($key, $column);
        }
        return $this;
    }
    
    /**
     * Order columns by a particular row (ascending or descending)
     * 
     * @api
     * @param int|string $byRowKeyOrLabel
     * @param string $order 'asc' or 'desc'. Defaults to 'asc'
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::getLabel()
     * @uses Smrtr_DataGrid::columnLabels()
     */
    public function orderColumns( $byRowKeyOrLabel, $order='asc', $stickyLabels=true )
    {
        switch ($order)
        {
            case 'asc': $sortFunction = 'ksort'; break;
            case 'desc': $sortFunction = 'krsort'; break;
            default: throw new Smrtr_DataGrid_Exception("\$order of 'asc' or 'desc' expected"); break;
        }        
        $searchKey = $this->getKey('row', $byRowKeyOrLabel);
        $stack = array(); $keyStack = array();
        $self = $this;
        $this->eachColumn( function($i, $column) use(&$stack, &$keyStack, $searchKey, $self)
        {
            $val = $column[$searchKey];
            if (!array_key_exists($val, $stack))
            {
                $stack[$val] = array();
                $keyStack[$val] = array();
            }
            $stack[$val][] = $column;
            $keyStack[$val][] = $self->getLabel('column', $i);
        });
        $sortFunction($stack);
        $sortFunction($keyStack);
        $data = array();
        $keys = array();
        for ($i=0; $i<$this->rows; $i++)
        {
            $data[$i] = array();
            for ($j=0; $j<$this->columns; $j++)
                $data[$i][$j] = null;
        }
        $i = 0;
        foreach ($stack as $val => $stack2)
        {
            foreach ($stack2 as $key => $column)
            {
                $keys[] = $keyStack[$val][$key];
                foreach ($column as $j => $value)
                    $data[$j][$i] = $value;
                $i++;
            }
        }
        $this->data = $data;
        if ($stickyLabels)
            $this->columnLabels($keys);
        return $this;
    }
    
    /**
     * Filters columns by use of a callback function as a filter.
     * To overwrite this object just call like so: $Grid = $Grid->filterColumns();
     * 
     * @param callable $filter Called on each column: $filter($key, $label, $column) where $column is a natural-indexed array
     * @return Smrtr_DataGrid new Smrtr_DataGrid with results
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::getColumn()
     * @uses Smrtr_DataGrid::appendColumn()
     */
    public function filterColumns( $filter )
    {
        if (!is_callable($filter))
            throw new Smrtr_DataGrid_Exception("\$filter provided is not callable");
        $Grid = new Smrtr_DataGrid;
        $Grid->appendKeys('row', $this->rowKeys);
        foreach ($this->columnKeys as $key => $label)
        {
            $column = $this->getColumn($key);
            $result = (boolean) $filter($key, $label, $column);
            if ($result)
                $Grid->appendColumn($row, $label);
        }
        return $Grid;
    }
    
    
    /*
     * ================================================================
     * Search Functionality
     * ================================================================
     * selectors
     * operators
     * extractSearchString
     * extractSearchField
     * extractSearchOperator
     * extractSearchValue
     * findRows
     * findColumns
     * ________________________________________________________________
     */
    
    /**
     * Filter rows by use of a selector string
     * /=1                      search for row with key 1
     * //=1                     search for row with label "1"
     * //=1,2                   search for rows with label "1" or "2"
     * //$=3                    search for rows with label ending in "3"
     * name=Jeff                search for rows with "Jeff" in the "name" column
     * name|surname=Jeff        search for rows with "Jeff" in the "name" or "surname" column
     * "name|surname"=Jeff      search for rows with "Jeff" in the "name|surname" column
     * 
     * @param string $query 
     * @return Smrtr_DataGrid new Smrtr_DataGrid with results
     */
    public function findRows( $selectorString )
    {
        if (!strlen($selectorString))
            return $this;
        $selectors = $this->extractSearchString($selectorString);
        echo print_r($selectors, true) . PHP_EOL;
        $selectorMaps = $this->selectors();
        $Grid = new Smrtr_DataGrid();
        $Grid->appendKeys('row', $this->rowKeys);
        $Grid->appendKeys('column', $this->columnKeys);
        $Grid->loadArray($this->data);
        $subtractor = 0;
        foreach ($this->getLabels('row') as $rowkey => $label)
        {
            $key = $rowkey - $subtractor;
            $row = $Grid->getRow($key);
            $keep = true;
            foreach ($selectors as $selector)
            {
                list($fields, $operator, $values) = $selector;
                if (empty($operator) || !array_key_exists($operator, $selectorMaps))
                    continue;
                $matchingFunction = $selectorMaps[$operator];
                $match = false;
                foreach ($fields as $field)
                {
                    foreach ($values as $value)
                    {
                        if ('/' == $field) // Key search
                            $val1 = $rowkey;
                        elseif ('//' == $field) // Label search
                            $val1 = $label;
                        else // Field search
                            $val1 = $row[$Grid->getKey('column', $field)];
                        
                        if ($matchingFunction($val1, $value))
                        {
                            $match = true;
                            break 2;
                        }
                    }
                }
                if (!$match)
                {
                    $keep = false;
                    break;
                }
            }
            if (!$keep)
            {
                $Grid->deleteRow($key);
                $subtractor++;
            }
        }
        return $Grid;
    }
    
    /**
     * Filter columns by use of a selector string
     * /=1                      search for column with key 1
     * //=1                     search for column with label "1"
     * //=1,2                   search for columns with label "1" or "2"
     * //$=3                    search for columns with label ending in "3"
     * name=Jeff                search for columns with "Jeff" in the "name" row
     * name|surname=Jeff        search for columns with "Jeff" in the "name" or "surname" row
     * "name|surname"=Jeff      search for columns with "Jeff" in the "name|surname" row
     * 
     * @param string $query 
     * @return Smrtr_DataGrid new Smrtr_DataGrid with results
     */
    public function findColumns( $selectorString )
    {
        if (!strlen($selectorString))
            return $this;
        $selectors = $this->extractSearchString($selectorString);
        echo print_r($selectors, true) . PHP_EOL;
        $selectorMaps = $this->selectors();
        $Grid = new Smrtr_DataGrid();
        $Grid->appendKeys('column', $this->columnKeys);
        $Grid->appendKeys('row', $this->rowKeys);
        $Grid->loadArray($this->data);
        $subtractor = 0;
        foreach ($this->getLabels('column') as $columnkey => $label)
        {
            $key = $columnkey - $subtractor;
            $column = $Grid->getColumn($key);
            $keep = true;
            foreach ($selectors as $selector)
            {
                list($fields, $operator, $values) = $selector;
                if (empty($operator) || !array_key_exists($operator, $selectorMaps))
                    continue;
                $matchingFunction = $selectorMaps[$operator];
                $match = false;
                foreach ($fields as $field)
                {
                    foreach ($values as $value)
                    {
                        if ('/' == $field) // Key search
                            $val1 = $columnkey;
                        elseif ('//' == $field) // Label search
                            $val1 = $label;
                        else // Field search
                            $val1 = $column[$Grid->getKey('row', $field)];
                        
                        if ($matchingFunction($val1, $value))
                        {
                            $match = true;
                            break 2;
                        }
                    }
                }
                if (!$match)
                {
                    $keep = false;
                    break;
                }
            }
            if (!$keep)
            {
                $Grid->deleteColumn($key);
                $subtractor++;
            }
        }
        return $Grid;
    }
    
    /**
     * @internal 
     */
    protected function selectors()
    {
        if (is_null($this->selectors))
            $this->selectors = array(
                '=' => function($val1, $val2) { return ($val1 == $val2); },
                '!=' => function($val1, $val2) { return ($val1 != $val2); },
                '>' => function($val1, $val2) { return ($val1 > $val2); },
                '<' => function($val1, $val2) { return ($val1 < $val2); },
                '>=' => function($val1, $val2) { return ($val1 >= $val2); },
                '<=' => function($val1, $val2) { return ($val1 <= $val2); },
                '*=' => function($val1, $val2) {
                    return (stripos($val1, $val2) !== false);
                },
                '^=' => function($val1, $val2) {
                    return (stripos(trim($val1), $val2) === 0);
                },
                '$=' => function($val1, $val2) {
                    $val2 = trim($val2);
                    $val1 = substr($val1, -1 * strlen($val2));
                    return (strcasecmp($val1, $val2) == 0);
                }
            );
        return $this->selectors;
    }
    
    /**
     * @internal 
     */
    protected function operatorChars()
    {
        if (is_null($this->operatorChars))
        {
            $this->operatorChars = array();
            $operators = array_keys($this->selectors());
            foreach ($operators as $operator)
            {
                for ($n=0; $n < strlen($operator); $n++)
                    if (! in_array($operator[$n], $this->operatorChars))
                        $this->operatorChars[] = $operator[$n];
            }
        }
        return $this->operatorChars;
    }
    
    /**
     * @internal 
     */
    protected function extractSearchString( $str )
    {
        $count = 0;
        $selectors = array();
        while(strlen($str))
        {
            $field = (array) $this->extractSearchField($str);
            $operator = $this->extractSearchOperator($str, $this->operatorChars());
            $value = (array) $this->extractSearchValue($str);
            if ($field || strlen("$value"))
                $selectors[] = array($field, $operator, $value);
            if (++$count > self::maxSelectors) break;
        }
        return $selectors;
    }
    
    /**
     * @internal 
     */
    protected function extractSearchField( &$str )
    {
        $field ='';
        if (preg_match('/^"(.*?[^\\\])"(.*)/', $str, $matches)) // quoted (complex) string
        {
            $fields = array();
            $fields[] = $matches[1];
            $str = $matches[2];
            $fields = array_merge($fields, (array)$this->extractSearchField($str));
            return count($fields) == 1 ? $fields[0] : $fields;
        }
        elseif (preg_match('/^(!?[_|.a-zA-Z0-9\/]+)(.*)/', $str, $matches)) // unquoted (simple) string
        {
            $field = trim($matches[1], '|');
            $str = $matches[2];
            if (strpos($field, '|'))
                $field = explode('|', $field);
            return $field;
        }
    }
    
    /**
     * @internal 
     */
    protected function extractSearchOperator( &$str, array $operators)
    {
        $n = 0;
        $operator = '';
        while(isset($str[$n]) && in_array($str[$n], $operators) && $n < self::maxOperatorLength)
        {
            $operator.= $str[$n];
            $n++;
        }
        if ($operator)
            $str = substr($str, $n);
        return $operator;
    }
    
    /**
     * @internal 
     */
    protected function extractSearchValue(&$str)
    {
        $str = trim($str);
        if (! strlen($str)) 
            return '';
        if ($str[0] == '"' || $str[0] == "'")
        {
            $openingQuote = $str[0];
            $n = 1;
        }
        else
        {
            $openingQuote = '';
            $n = 0;
        }
        $value = '';
        $lastChar = '';
        do {
            if (! isset($str[$n])) break;
            $c = $str[$n];
            if ($openingQuote)
            {   // we are in a quoted value string
                if ($c == $openingQuote)
                {
                    if ($lastChar != '\\')
                    {   // same quote as opening quote, and not escaped = closing quote
                        $n++;
                        break;
                    }
                    else
                    {   // intentionally escaped quote (remove the escape char)
                        $value = rtrim($value, '\\');
                    }
                }
            }
            else
            {   // we are in an unquoted value string
                if ($c == ',' || $c == '|')
                {
                    if ($lastChar != '\\') // non-quoted, non-escaped comma terminates the value
                        break;
                    else // intentionally escaped comma (remove the escape char)
                        $value = rtrim($value, '\\');
                }
            }
            $value.= $c;
            $lastChar = $c;
        } while(++$n < self::maxValueLength);
        if (strlen("$value"))
            $str = substr($str, $n);
        $str = ltrim($str, ' ,');
        if (strlen($str) > 1 && substr($str, 0, 1) == '|')
        {
            $str = substr($str, 1);
            // recursive extraction to get all OR values
            $v = $this->extractSearchValue($str);
            $value = array($value);
            if (is_array($v))
                $value = array_merge($value, $v);
            else
                $value[] = $v;
        }
        return $value;
    }
    
    
    /*
     * ================================================================
     * The Grid
     * ================================================================
     * setValue
     * getValue
     * loadArray
     * getArray
     * getAssociativeArray
     * transpose
     * info
     * row
     * column
     * _importMatrix
     * _normalizeVector
     * _normalizePoint
     * __construct
     * getByID
     * ________________________________________________________________
     */
    
    /**
     * Update value at a particular point
     * 
     * @api
     * @param int|string $rowKeyOrLabel
     * @param int|string $columnKeyOrLabel
     * @param scalar|null $value
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getKey()
     * @uses Smrtr_DataGrid::_normalizePoint() 
     */
    public function setValue( $rowKeyOrLabel, $columnKeyOrLabel, $value )
    {
        $rowKey = $this->getKey('row', $rowKeyOrLabel);
        $columnKey = $this->getKey('column', $columnKeyOrLabel);
        $this->data[$rowKey][$columnKey] = $this->_normalizePoint($value);
        return $this;
    }
    
    /**
     * Get value of a particular point
     * 
     * @api
     * @param int|string $rowKeyOrLabel
     * @param int|string $columnKeyOrLabel
     * @return scalar|null
     * @uses Smrtr_DataGrid::getKey()
     */
    public function getValue( $rowKeyOrLabel, $columnKeyOrLabel )
    {
        $rowKey = $this->getKey('row', $rowKeyOrLabel);
        $columnKey = $this->getKey('column', $columnKeyOrLabel);
        return $this->data[$rowKey][$columnKey];
    }
    
    /**
     * Import data from array
     * 
     * @api
     * @param array $data
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::_importMatrix()
     * @uses Smrtr_DataGrid::padKeys()
     */
    public function loadArray( $data )
    {
        $this->_importMatrix($data);
        $this->padKeys('row', $this->rows);
        $this->padKeys('column', $this->columns);
        return $this;
    }
    
    /**
     * Get 2D array of grid data
     * 
     * @api
     * @return array 
     */
    public function getArray()
    {
        return $this->data;
    }
    
    /**
     * Get 2D associative array of grid data (labels used for keys)
     * 
     * @api
     * @param boolean $associateRows [optional] Defaults to true
     * @param boolean $associateColumns [optional] Defaults to true
     * @return array 
     */
    public function getAssociativeArray( $associateRows=true, $associateColumns=true )
    {
        $out = array();
        if (!count($this->data))
            return $out;
        $colKeys = array();
        for ($j=0; $j<$this->columns; $j++)
        {
            if ($associateColumns && is_string($this->columnKeys[$j]))
                $colKeys[] = $this->columnKeys[$j];
            else
                $colKeys[] = $j;
        }
        for ($i=0; $i<$this->rows; $i++)
        {
            if ($associateRows && is_string($this->rowKeys[$i]))
                $rowKey = $this->rowKeys[$i];
            else
                $rowKey = $i;
            $out[$rowKey] = array_combine($colKeys, $this->data[$i]);
        }
        return $out;
    }
    
    /**
     * Transposition the grid, turning rows into columns and vice-versa.
     * 
     * @api
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getColumn()
     */
    public function transpose()
    {
        $data = array();
        $rows = $this->columns;
        $columns = $this->rows;
        $rowKeys = $this->columnKeys;
        $columnKeys = $this->rowKeys;
        for ($i=0; $i<$this->columns; $i++)
            $data[] = $this->getColumn($i);
        $this->rowKeys = $rowKeys;
        $this->columnKeys = $columnKeys;
        $this->rows = $rows;
        $this->columns = $columns;
        $this->data = $data;
        return $this;
    }
    
    /**
     * Get an array of info about this DataGrid
     * 
     * @api
     * @return array rowCount=>string, columnCount=>string, rowKeys=>array, columnKeys=>array
     */
    public function info()
    {
        return array(
            'rowCount' => $this->rows,
            'columnCount' => $this->columns,
            'rowKeys' => $this->rowKeys,
            'columnKeys' => $this->columnKeys
        );
    }
    
    /**
     * Get a vector object which proxies to the given row.
     * 
     * @api
     * @param int|string $rowKeyOrLabel
     * @return \Smrtr_DataGridVector 
     * @uses Smrtr_DataGrid::getKey()
     */
    public function row( $rowKeyOrLabel )
    {
        $key = $this->getKey('row', $rowKeyOrLabel);
        return new Smrtr_DataGridVector(
            $this->ID, $key, false
        );
    }
    
    /**
     * Get a vector object which proxies to the given column.
     * 
     * @api
     * @param int|string $columnKeyOrLabel
     * @return \Smrtr_DataGridVector 
     * @uses Smrtr_DataGrid::getKey()
     */
    public function column( $columnKeyOrLabel )
    {
        $key = $this->getKey('column', $columnKeyOrLabel);
        return new Smrtr_DataGridVector(
            $this->ID, false, $key
        );
    }
    
    /**
     * @internal
     */
    protected function _importMatrix( array $data, $matchLabels=false )
    {
        $vectors = array();
        $columns = 0; $rows = 0;
        foreach ($data as $row)
        {
            $j = 0;
            $vector = array();
            foreach ($row as $key => $val)
            {
                if ($matchLabels && !in_array($key, $this->columnKeys))
                    continue;
                $vector[] = (is_scalar($val) || is_null($val))
                    ? $val : null;
                $j++;
            }
            $vectors[] = $vector;
            $columns = max(array($columns, $j));
            $rows++;
        }
        $this->columns = $columns;
        $this->rows = $rows;
        foreach ($vectors as $i => $row)
            $vectors[$i] = array_pad($row, $columns, null);
        $this->data = $vectors;
    }
    
    /**
     * @internal
     */
    protected function _normalizeVector( array $ntuple, $size=null, $rigidSize=false )
    {
        $vector = array(); $count = 0;
        foreach ($ntuple as $val)
        {
            $vector[] = is_scalar($val)
                ? $val : null;
            $count++;
        }
        if (is_int($size) && $size > $count)
            $vector = array_pad($vector, $size, null);
        return $vector;
    }
    
    /**
     * @internal
     */
    protected function _normalizeKeys( array $keys, $size=null )
    {
        $vector = array(); $count = 0;
        foreach ($keys as $val)
        {
            $vector[] = (is_string($val) && strlen($val))
                ? $val : null;
            $count++;
        }
        if (is_int($size) && $size > $count)
            $vector = array_pad($vector, $size, null);
        return $vector;
    }
    
    /**
     * @internal
     */
    protected function _normalizePoint( $point )
    {
        return (is_scalar($point) || is_null($point))
            ? $point : null;
    }
    
    /**
     * Optionally pass an array of data to instanciate with
     * 
     * @api
     * @param array $data [optional] 2D array of data
     * @param boolean $associateRowLabels [optional] Defaults to false
     * @param boolean $useFirstRowAsColumnLabels [optional] Defaults to false
     * @uses Smrtr_DataGrid::appendKeys()
     * @uses Smrtr_DataGrid::_importMatrix()
     */
    public function __construct( array $data = array(), $associateRowLabels=false, $useFirstRowAsColumnLabels=false )
    {
        $this->ID = self::$IDcounter++;
        if (!empty($data)) {
            if ($useFirstRowAsColumnLabels && $row = array_shift($data))
                $this->appendKeys('column', $row);
            if (!empty($data)) {
                if ($associateRowLabels)
                    $this->appendKeys('row', array_keys($data));
                $this->_importMatrix($data);
            }
            if (!$useFirstRowAsColumnLabels)
                $this->appendKeys('column', array_fill(0, $this->columns, null));
            if (!$associateRowLabels)
                $this->appendKeys('row', array_fill(0, $this->rows, null));
        }
        self::$registry[$this->ID] = $this;
    }
    
    /**
     * @internal
     */
    public static function getByID($ID)
    {
        return array_key_exists($ID, self::$registry)
            ? self::$registry[$ID] : null;
    }
    
    
    /*
     * ================ [ JSON Import/Export ] =================================
     */
    
    /**
     * Load data from a JSON file (using file_get_contents)
     * 
     * @api 
     * @param string $fileName
     * @param boolean $rowKeysAsRowKeys [optional] Defaults to false
     * @param boolean $firstRowsKeysAsColumnKeys [optional] Defaults to false
     * @return Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::readJSON()
     */
    public function loadJSON( $fileName, $rowKeysAsRowKeys=false, $firstRowsKeysAsColumnKeys=false )
    {
        $JSON = file_get_contents($fileName);
        return $this->readJSON($JSON, $rowKeysAsRowKeys, $firstRowsKeysAsColumnKeys);
    }
    
    /**
     * Load data from a string of JSON
     * 
     * @api
     * @param string $JSON
     * @param boolean $rowKeysAsRowKeys [optional] Defaults to false
     * @param boolean $firstRowsKeysAsColumnKeys [optional] Defaults to false
     * @return \Smrtr_DataGrid $this
     * @throws Smrtr_DataGrid_Exception
     * @uses Smrtr_DataGrid::appendKeys() 
     * @uses Smrtr_DataGrid::_importMatrix() 
     */
    public function readJSON( $JSON, $rowKeysAsRowKeys=false, $firstRowsKeysAsColumnKeys=false )
    {
        $data = (array) json_decode($JSON);
        if (!count($data))
            throw new Smrtr_DataGrid_Exception("No data found");
        
        if ($firstRowsKeysAsColumnKeys)
        {
            $first = array_shift($data);
            $this->appendKeys('column', array_keys((array) $first));
            array_unshift($data, $first);
        }
        if ($rowKeysAsRowKeys)
            $this->appendKeys('row', array_keys($data));
        
        $this->_importMatrix($data, $firstRowsKeysAsColumnKeys);
        
        if (!$rowKeysAsRowKeys)
            $this->appendKeys('row', array_fill(0, $this->rows, null));
        if (!$firstRowsKeysAsColumnKeys)
            $this->appendKeys('column', array_fill(0, $this->columns, null));
        return $this;
    }
    
    /**
     * Save data to file as JSON
     * 
     * @api
     * @param string $fileName
     * @param boolean $keyRowsByRowKeys [optional] Defaults to false
     * @param boolean $keyFieldsByColumnKeys [optional] Defaults to false
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getAssociativeArray()
     */
    public function saveJSON( $fileName, $keyRowsByRowKeys=false, $keyFieldsByColumnKeys=false )
    {
        $data = $this->getAssociativeArray($keyRowsByRowKeys, $keyFieldsByColumnKeys);
        file_put_contents( $fileName, json_encode($data), LOCK_EX );
        return $this;
    }
    
    /**
     * Serve data as JSON file download
     * 
     * @api
     * @param string $fileName
     * @param boolean $keyRowsByRowKeys [optional] Defaults to false
     * @param boolean $keyFieldsByColumnKeys [optional] Defaults to false
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getAssociativeArray()
     */
    public function serveJSON( $fileName, $keyRowsByRowKeys=false, $keyFieldsByColumnKeys=false )
    {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private",false);
        header('Content-type: application/json');
        header('Content-Disposition: attachment; filename="'.$fileName.'"');
        header("Content-Transfer-Encoding: binary");
        echo json_encode($this->getAssociativeArray($keyRowsByRowKeys, $keyFieldsByColumnKeys));
        return $this;
    }
    
    /**
     * Print data as a JSON string
     *  
     * @api
     * @param boolean $keyRowsByRowKeys [optional] Defaults to false
     * @param boolean $keyFieldsByColumnKeys [optional] Defaults to false
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::getAssociativeArray(0
     */
    public function printJSON( $keyRowsByRowKeys=false, $keyFieldsByColumnKeys=false )
    {
        print json_encode($this->getAssociativeArray($keyRowsByRowKeys, $keyFieldsByColumnKeys));
        return $this;
    }
    
    
    /*
     * ================ [ CSV Import/Export ] ==================================
     */
    
    /**
     * Load data from a CSV file
     * 
     * @api
     * @param string $fileName
     * @param boolean $firstColumnAsRowKeys [optional] Defaults to false
     * @param boolean $firstRowAsColumnKeys [optional] Defaults to false
     * @param string $delimeter [optional] Defaults to comma
     * @param string $enclosure [optional] Defaults to doublequote
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::appendKeys()
     * @uses Smrtr_DataGrid::appendKey()
     * @uses Smrtr_DataGrid::_importMatrix()
     */
    public function loadCSV( $fileName, $firstColumnAsRowKeys=false, $firstRowAsColumnKeys=false, $delimeter=",", $enclosure='"' )
    {
        $fileStream = fopen( $fileName, 'r '); 
        $go = true;
        $data = array();
        while ($row = fgetCSV( $fileStream, 0, $delimeter, $enclosure ))
        {
            if ($firstRowAsColumnKeys && $go) {
                if ($firstColumnAsRowKeys)
                    $this->appendKeys('column', array_slice((array) $row, 1));
                else
                    $this->appendKeys('column', (array) $row);
                $go = false; continue;
            }
            if ($firstColumnAsRowKeys) $this->appendKey('row', (string) array_shift($row));
            $data[] = $row;
        }
        fclose($fileStream);
        
        $this->_importMatrix($data);
        
        if (!$firstColumnAsRowKeys)
            $this->appendKeys('row', array_fill(0, $this->rows, null));
        if (!$firstRowAsColumnKeys)
            $this->appendKeys('column', array_fill(0, $this->columns, null));
        return $this;
    }
    
    /**
     * Load data from a CSV string
     * 
     * @api
     * @param string $CSV
     * @param boolean $firstColumnAsRowKeys [optional] Defaults to false
     * @param boolean $firstRowAsColumnKeys [optional] Defaults to false
     * @param string $delimeter [optional] Defaults to comma
     * @param string $enclosure [optional] Defaults to doublequote
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::appendKeys()
     * @uses Smrtr_DataGrid::appendKey()
     * @uses Smrtr_DataGrid::_importMatrix()
     */
    public function readCSV( $CSV, $firstColumnAsRowKeys=false, $firstRowAsColumnKeys=false, $delimeter=",", $enclosure='"' )
    {
        $go = true;
        $data = array();
        $rows = str_getcsv($CSV, PHP_EOL, $enclosure);
        foreach ($rows as $line)
        {
            $row = str_getcsv( $line, $delimeter, $enclosure );
            if ($firstRowAsColumnKeys && $go) {
                if ($firstColumnAsRowKeys)
                    $this->appendKeys('column', array_slice($row, 1));
                else
                    $this->appendKeys('column', $row);
                $go = false; continue;
            }
            if ($firstColumnAsRowKeys)
                $this->appendKey('row', (string) array_shift($row));
            $data[] = $row;
        }
        
        $this->_importMatrix($data);
        
        if (!$firstColumnAsRowKeys)
            $this->appendKeys('row', array_fill(0, $this->rows, null));
        if (!$firstRowAsColumnKeys)
            $this->appendKeys('column', array_fill(0, $this->columns, null));
        return $this;
    }
    
    /**
     * Save data to a CSV file
     * 
     * @api
     * @param string $fileName
     * @param boolean $includeRowKeys [optional] Defaults to false
     * @param boolean $includeColumnKeys [optional] Defaults to false
     * @param string $delimeter [optional] Defaults to delimeter
     * @param string $enclosure [optional] Defaults to doublequote
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::_prepareCSV()
     */
    public function saveCSV( $fileName, $includeRowKeys=false, $includeColumnKeys=false, $delimeter=",", $enclosure='"' )
    {
        $fileStream = fopen($fileName, 'w');
        $data = $this->_prepareCSV($includeRowKeys, $includeColumnKeys);
        array_walk( $data, function(&$vals, $keys, $vars) {
            fputCSV( $vars['outstream'], $vals, $vars['delimeter'], $vars['enclosure'] );
        }, array('outstream'=>$fileStream, 'delimeter'=>$delimeter, 'enclosure'=>$enclosure) );
        fclose($fileStream);
        return $this;
    }
    
    /**
     * Serve data as a CSV file download
     * 
     * @api
     * @param string $fileName
     * @param boolean $includeRowKeys [optional] Defaults to false
     * @param boolean $includeColumnKeys [optional] Defaults to false
     * @param string $delimeter [optional] Defaults to comma
     * @param string $enclosure [optional] Defaults to doublequote
     * @param boolean $excelForceRawRender [optional] Defaults to false. Force excel to render raw contents of file (without applying any formatting).
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::_prepareCSV()
     */
    public function serveCSV( $fileName, $includeRowKeys=false, $includeColumnKeys=false, $delimeter=",", $enclosure='"', $excelForceRawRender=false )
    {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private",false);
        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; filename="'.$fileName.'"');
        header("Content-Transfer-Encoding: binary");
        if($excelForceRawRender) echo "\xef\xbb\xbf";
        $outStream = fopen("php://output", "r+");
        $data = $this->_prepareCSV($includeRowKeys, $includeColumnKeys);
        array_walk( $data, function(&$vals, $keys, $vars) {
            fputCSV( $vars['outstream'], $vals, $vars['delimeter'], $vars['enclosure'] );
        }, array('outstream'=>$outStream, 'delimeter'=>$delimeter, 'enclosure'=>$enclosure) );
        fclose($outStream);
        return $this;
    }
    
    /**
     * Print data as a CSV string
     * 
     * @api
     * @param boolean $includeRowKeys [optional] Defaults to false
     * @param boolean $includeColumnKeys [optional] Defaults to false
     * @param string $delimeter [optional] Defaults to comma
     * @param string $enclosure [optional] Defaults to doublequote
     * @return \Smrtr_DataGrid $this
     * @uses Smrtr_DataGrid::_prepareCSV()
     */
    public function printCSV( $includeRowKeys=false, $includeColumnKeys=false, $delimeter=",", $enclosure='"' )
    {
        $outStream = fopen("php://output", "r+");
        $data = $this->_prepareCSV($includeRowKeys, $includeColumnKeys);
        array_walk( $data, function(&$vals, $keys, $vars) {
            fputCSV( $vars['outstream'], $vals, $vars['delimeter'], $vars['enclosure']);
        }, array('outstream'=>$outStream, 'delimeter'=>$delimeter, 'enclosure'=>$enclosure) );
        fclose($outStream);
        return $this;
    }
    
    /**
     * @internal
     */
    protected function _prepareCSV($includeRowKeys=false, $includeColumnKeys=false)
    {
        $out = $this->data;
        if ($includeRowKeys)
            for ($i=0; $i<$this->rows; $i++)
                array_unshift($out[$i], (
                    is_string($this->rowKeys[$i])
                    ? $this->rowKeys[$i]
                    : $i
                ));
        if ($includeColumnKeys && !is_null($this->columnKeys))
        {
            $colKeys = array();
            for ($j=0; $j<$this->columns; $j++)
                $colKeys[] = is_string($this->columnKeys[$j])
                    ? $this->columnKeys[$j]
                    : $j;
            array_unshift($out, (($includeRowKeys)
                ? array_merge(array(""), $colKeys)
                : $colKeys));
        }
        return $out;
    }
    
}