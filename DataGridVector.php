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
 * @todo label() to get/set label for this row/column (on linked datagrid)
 * @todo swap(x) proxy to DataGrid->swap(this, x) check vectors are same type!!!
 */

class Smrtr_DataGridVector implements ArrayAccess
{
    protected $DataGridID = null;
    protected $type = null;
    protected $key = null;
    
    /** Exactly one key must be provided. Rowkey takes precedence if you try to provide two keys.
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
     * @return Smrtr_DataGrid 
     */
    public function grid()
    {
        return Smrtr_DataGrid::getByID($this->DataGridID);
    }
    
    /**
     * Get the data values corresponding to this vector out of linked Smrtr_DataGrid
     * @param boolean $labelled [optional] if $labelled then returned array indexed by labels, if they are found
     * @return array
     */
    public function data($labelled=false)
    {
        // call grid and return my data
        $type = $this->type();
        if ('row' == $type) 
            return $this->grid()->getRow($this->key, $labelled);
        if ('column' == $type)
            return $this->grid()->getColumn($this->key, $labelled);
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
        throw new Smrtr_DataGrid_Exception("\$offset expected string or int");
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
        throw new Smrtr_DataGrid_Exception("offset $offset not found");
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