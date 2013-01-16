<?php

/**
 * Used by \Smrtr_DataGrid
 * 
 * This class is a proxy to getters/setters on the grid for specific row or column.
 * This class implements ArrayAccess.
 * Upon construction this is linked to a Smrtr_DataGrid and given a type (row, column) and a key (offset).
 * The ArrayAccess methods have been overloaded to get/set/unset 
 * their data to/from the linked Smrtr_DataGrid.
 * Example: $grid->row(5)[7]; $grid->column(0)[2];
 * 
 * @author Joe Green
 * @package SmrtrLib
 * @version 0.1
 * @requires PHP5.4
 */

class Smrtr_DataGridVector implements ArrayAccess
{
    protected $DataGridID = null;
    protected $type = null;
    protected $key = null;
    
    /** 
     * Exactly one key must be provided. Rowkey takes precedence if you try to provide two keys.
     * 
     * @param int $DataGridID Link this object to a Smrtr_DataGrid with $DataGridID
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
     * Get the linked Smrtr_DataGrid instance
     * 
     * @return Smrtr_DataGrid 
     */
    public function grid()
    {
        return Smrtr_DataGrid::getByID($this->DataGridID);
    }
    
    /**
     * Get the data values corresponding to this vector out of linked Smrtr_DataGrid
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
     * @return \Smrtr_DataGridVector|string|null $this or label
     * @throws Smrtr_DataGrid_Exception 
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
        throw new Smrtr_DataGrid_Exception("");
    }
    
    /**
     * @return string returns 'row' or 'column'
     * @throws Exception 
     */
    public function type()
    {
        if ('row' == $this->type) return 'row';
        if ('column' == $this->type) return 'column';
        throw new Exception("Type of Smrtr_DataGridVector is unknown");
    }
    
    /**
     * Return vector's offset in the grid, or set offset and return this
     * @return int|Smrtr_DataGridVector
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
        throw new Smrtr_DataGrid_Exception("\vector offset expected string or int");
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
        throw new Smrtr_DataGrid_Exception("vector offset $offset not found");
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
    
}