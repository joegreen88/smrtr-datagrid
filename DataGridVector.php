<?php

namespace Smrtr;

/**
 * Used by \Smrtr\DataGrid
 * 
 * This class is a proxy to getters/setters on the grid for specific row or column.
 * This class implements Countable.
 * This class implements ArrayAccess.
 * This class implements Iterator.
 * Upon construction this is linked to a DataGrid and given a type (row, column) and a key (offset).
 * 
 * The ArrayAccess methods are overloading set/get on the linked DataGrid.
 * Examples: $grid->row(5)[7] = "foo"; $var = $grid->column(0)[2]; 
 * Unset simply sets a null value, example: unset($grid->row(1)[4]);
 * 
 * The Iterator methods use a single fresh cache of the with every foreach statement.
 * Example: foreach($grid->row(4) as $key => $value) {}
 * NOTE: PHP5.5 will allow non-scalar keys, will be able to include key AND label in the foreach key.
 * 
 * @author Joe Green
 * @package Smrtr
 * @version 1.3.0
 * @recommended PHP5.4
 */

class DataGridVector implements \Countable, \ArrayAccess, \Iterator
{
    protected $DataGridID = null;
    protected $type = null;
    protected $key = null;
    
    /** 
     * Exactly one key must be provided. Rowkey takes precedence if you try to provide two keys.
     * 
     * @param int $DataGridID Link this object to a DataGrid with $DataGridID
     * @param int|false [optional] Save the position of this vector with a rowKey
     * @param int|false [optional] Save the position of this vector with a columnKey
     */
    public function __construct( $DataGridID, $rowKey=false, $columnKey=false )
    {
        if (!is_int($DataGridID))
            throw new Exception("\$DataGridID must be of type Int");
        $this->DataGridID = $DataGridID;
        if (is_int($rowKey) || is_string($rowKey))
        {
            $this->type = 'row';
            $this->key = $rowKey;
        }
        elseif (is_int($columnKey) || is_string($columnKey))
        {
            $this->type = 'column';
            $this->key = $columnKey;
        }
    }
    
    /**
     * Get the linked DataGrid instance
     * 
     * @return DataGrid 
     */
    public function grid()
    {
        return DataGrid::getByID($this->DataGridID);
    }
    
    /**
     * Get the data values corresponding to this vector out of linked DataGrid
     * 
     * @param boolean $labelled [optional] if $labelled then returned array indexed by labels, if they are found
     * @return array
     */
    public function data($labelled=false)
    {
        // call grid and return my data
        $type = $this->type();
        return $this->grid()->{'get'.ucfirst($type)}($this->key, $labelled);
    }
    public function getValues($labelled=false) // ALIAS
    { return $this->data($labelled); }
    
    /**
     * Get an array of this vector's DISTINCT values
     * 
     * @return array
     */
    public function getDistinct()
    {
        $type = $this->type();
        $data = $this->grid()->{'get'.ucfirst($type)}($this->key, false);
        return array_keys(array_flip($data));
    }
    
    /**
     * Get/Set label for this row/column
     * 
     * @param false|string|null $label [optional] string|null to set label, false [default] to get label
     * @return \DataGridVector|string|null $this or label
     * @throws DataGrid_Exception 
     */
    public function label($label=false)
    {
        $type = $this->type();
        if (is_string($label) || is_null($label))
        {
            $this->grid()->updateKey($type, $this->key, $label);
            return $this;
        }
        elseif (!$label)
            return $this->grid()->{'get'.ucfirst($type)}($this->key, $labelled);
        throw new DataGrid_Exception("");
    }
    
    /**
     * @return string returns 'row' or 'column'
     * @throws Exception 
     */
    public function type()
    {
        if ('row' == $this->type) return 'row';
        if ('column' == $this->type) return 'column';
        throw new Exception("Type of DataGridVector is unknown");
    }
    
    /**
     * Return vector's offset in the grid, or set offset and return this
     * @return int|DataGridVector
     */
    public function position($offset=false)
    {
        if (is_int($offset)) {
            $this->key = $offset;
            return $this;
        }
        else {
            return $this->key;
        }
    }
    
    /*
     * ================ [ Countable Interface ] ==============================
     */
    
    /**
     * @ignore
     */
    public function count()
    {
        return count($this->data());
    }
    
    /*
     * ================ [ ArrayAccess Interface ] ==============================
     */
    
    /**
     * @ignore
     */
    public function offsetExists( $offset )
    {
        $type = $this->type();
        if (is_int($offset))
            return $this->grid()->hasKey($type, $offset);
        elseif (is_string($offset))
            return $this->grid()->hasLabel($type, $offset);
        throw new DataGrid_Exception("\vector offset expected string or int");
    }
    
    /**
     * @ignore
     */
    public function offsetGet( $offset )
    {
        $type = $this->type();
        if ('row' == $type)
            return $this->grid()->getValue($this->key, $offset);
        if ('column' == $type)
            return $this->grid()->getValue($offset, $this->key);
        throw new DataGrid_Exception("vector offset $offset not found");
    }
    
    /**
     * @ignore
     */
    public function offsetUnset( $offset )
    {
        $type = $this->type();
        if ('row' == $type)
            $this->grid()->setValue($this->key, $offset, null);
        if ('column' == $type)
            $this->grid()->setValue($offset, $this->key, null);
    }
    
    /**
     * @ignore
     */
    public function offsetSet( $offset, $value )
    {
        $type = $this->type();
        if ('row' == $type)
            $this->grid()->setValue($this->key, $offset, $value);
        if ('column' == $type)
            $this->grid()->setValue($offset, $this->key, $value);
    }
    
    /*
     * ================ [ Iterator Interface ] ==============================
     * 
     * @added v1.1
     */
    
    private $iteratorPosition = 0;
    private $iteratorData;
    
    /**
     * @ignore
     */
    public function rewind()
    {
        $this->iteratorData = $this->data();
        $this->iteratorPosition = 0;
    }
    
    /**
     * @ignore
     */
    public function current()
    {
        return $this->iteratorData[$this->iteratorPosition];
    }
    
    /**
     * @ignore
     */
    public function key()
    {
        return $this->iteratorPosition;
    }
    
    /**
     * @ignore
     */
    public function next()
    {
        ++$this->iteratorPosition;
    }
    
    /**
     * @ignore
     */
    public function valid()
    {
        return array_key_exists($this->iteratorPosition, $this->iteratorData);
    }
}