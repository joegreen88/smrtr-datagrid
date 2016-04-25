<?php 
namespace Smrtr;
class DataGridException extends \Exception {}
require_once('DataGridVector.php');

/**
 * 2D-array wrapper with methods for import/export. PHP5.3 +
 * 
 * This class is a one-stop-shop for transporting tabular data between formats.
 * We currently provide methods for CSV and JSON.
 * Handles custom keys (a.k.a. labels) on rows and columns.
 * 
 * New in version 1.3.1:
 *  - Serializable
 *  - Added class alias (Smrtr_DataGrid) for backwards compatibility
 * 
 * New in version 1.3.0:
 *  - Namespaced
 *  - Methods hasValue(), rowHasValue(), columnHasValue()
 * 
 * @author Joe Green
 * @package Smrtr
 * @version 1.3.2
 */

class DataGrid implements \Serializable
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
     * Restrict data values to scalar types? Turned on by default.
     * @var bool
     */
    protected $scalarValues = true;
    
    /**
     * A map of operators to matching-functions
     * @var array
     */
    private static $selectors;
    
    /**
     * An array cache of selector-operator characters
     * @var array
     */
    private static $selectorChars;
    
    /**
     * On loadArray(), associate row labels by using array keys
     * @var int
     */
    const ASSOC_ROW_KEYS = 1;
    
    /**
     * On loadArray(), associate row labels by using the first column
     * @var int
     */
    const ASSOC_ROW_FIRST = 0;
    
    /**
     * On loadArray(), associate column labels by using array keys
     * @var int
     */
    const ASSOC_COLUMN_KEYS = 0;
    
    /**
     * On loadArray(), associate column labels by using the first row
     * @var int
     */
    const ASSOC_COLUMN_FIRST = 1;
    
    /**
     * Maximum length of operator string
     * @var int 
     */
    const maxOperatorLength = 2;
    
    /**
     * Maximum length of value string 
     * @var int
     */
    const maxValueLength = 1000;
    
    /**
     * Constant for left associativity of operators
     * @var int 
     */
    const leftAssociative = 0;
    
    /**
     * Constant for right associativity of operators
     * @var int 
     */
    const rightAssociative = 1;
    
    /**
     * Supported operators ["operator" => [precendence, associativity]]
     * @var array
     */
    private static $setOperators = array(
        "+" => array(   10,     self::leftAssociative    ),     // intersection
        "-" => array(   10,     self::leftAssociative    ),     // difference
        "," => array(   0,      self::leftAssociative    )      // union
    );
    
    /*
     * =============================================================
     * Serializable Interface
     * =============================================================
     * serialize
     * unserialize
     * ________________________________________________________________
     */
    
    /**
     * @api
     * @return string
     */
    public function serialize()
    {
        $arr = array(
            'data' => $this->data,
            'rowKeys' => $this->rowKeys,
            'columnKeys' => $this->columnKeys,
            'rows' => $this->rows,
            'columns' => $this->columns,
            'scalarValues' => $this->scalarValues
        );
        return serialize($arr);
    }
    
    /**
     * @api
     * @param string $serialized
     * @return DataGrid
     */
    public function unserialize( $serialized )
    {
        $arr = unserialize($serialized);
        foreach (array('data', 'rowKeys', 'columnKeys', 'rows', 'columns', 'scalarValues') as $key)
            $this->$key = $arr[$key];
        $this->ID = self::$IDcounter++;
        self::$registry[$this->ID] = $this;
    }
    
    /*
     * ================================================================
     * Search Functionality (* = API)
     * ================================================================
     * isSetOperator
     * isAssociative
     * compareSetOperatorPrecedence
     * selectors
     * selectorChars
     * infixToRPN
     * extractSearchTokens
     * extractSearchExpression
     * extractSearchField
     * extractSearchOperation
     * extractSearchValue
     * evaluateSearchVector
     * search*
     * searchRows*
     * searchColumns*
     * ________________________________________________________________
     */
    
    /**
     * @internal
     */
    private static function isSetOperator($token)
    {
        return array_key_exists($token, self::$setOperators);
    }
    
    /**
     * @internal
     */
    private static function isAssociative($token, $type)
    {
        if (!self::isSetOperator($token)) throw new DataGridException("Invalid token: $token");
        if ($type === self::$setOperators[$token][1]) return true;
        return false;
    }
    
    /**
     * @internal
     */
    private static function compareSetOperatorPrecedence($tokenA, $tokenB)
    {
        if (!self::isSetOperator($tokenA) || !self::isSetOperator($tokenB))
            throw new DataGridException("Invalid tokens: $tokenA & $tokenB");
        return (self::$setOperators[$tokenA][0] - self::$setOperators[$tokenB][0]);
    }
    
    /**
     * @internal
     */
    private static function selectors()
    {
        if (is_null(self::$selectors))
            self::$selectors = array(
                '='  => function($val1, $val2) { return ($val1 == $val2); },
                '!=' => function($val1, $val2) { return ($val1 != $val2); },
                '>'  => function($val1, $val2) {
                    $val1 = (int) str_replace(array(' ', ',', '.'), '', $val1);
                    $val2 = (int) str_replace(array(' ', ',', '.'), '', $val2);
                    return ($val1 > $val2); 
                },
                '<'  => function($val1, $val2) {
                    $val1 = (int) str_replace(array(' ', ',', '.'), '', $val1);
                    $val2 = (int) str_replace(array(' ', ',', '.'), '', $val2);
                    return ($val1 < $val2); 
                },
                '>=' => function($val1, $val2) {
                    $val1 = (int) str_replace(array(' ', ',', '.'), '', $val1);
                    $val2 = (int) str_replace(array(' ', ',', '.'), '', $val2);
                    return ($val1 >= $val2); 
                },
                '<=' => function($val1, $val2) {
                    $val1 = (int) str_replace(array(' ', ',', '.'), '', $val1);
                    $val2 = (int) str_replace(array(' ', ',', '.'), '', $val2);
                    return ($val1 <= $val2); 
                },
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
        return self::$selectors;
    }
    
    /**
     * @internal
     */
    private function selectorChars()
    {
        if (is_null(self::$selectorChars))
        {
            self::$selectorChars = array();
            $operators = array_keys(self::selectors());
            foreach ($operators as $operator)
            {
                for ($n=0; $n < strlen($operator); $n++)
                    if (! in_array($operator[$n], self::$selectorChars))
                        self::$selectorChars[] = $operator[$n];
            }
        }
        return self::$selectorChars;
    }
    
    /**
     * @internal
     */
    private static function infixToRPN( array $tokens )
    {
        $out = array();
        $stack = array();
        foreach ($tokens as $token)
        {
            if (self::isSetOperator($token))
            {
                while (count($stack) && self::isSetOperator($stack[0]))
                {
                    if (
                        (
                            self::isAssociative($token, self::leftAssociative)
                            && self::compareSetOperatorPrecedence($token, $stack[0]) <= 0
                        )
                        || (
                            self::isAssociative($token, self::rightAssociative)
                            && self::compareSetOperatorPrecedence($token, $stack[0]) < 0
                        )
                    ) {
                        array_push($out, array_shift($stack));
                        continue;
                    }
                    break;
                }
                array_unshift($stack, $token);
            }
            elseif ('(' == $token)
                array_unshift($stack, $token);
            elseif (')' == $token)
            {
                while (count($stack) && $stack[0] != '(')
                    array_push($out, array_shift($stack));
                array_shift($stack);
            }
            else
                array_push($out, $token);
        }
        while (count($stack))
            array_push($out, array_shift($stack));
        return $out;
    }
    
    /**
     * @internal
     */
    private static function extractSearchTokens( $str )
    {
        $out = array(); $curDepth = 0;
        $substr = ''; $resetSubstr = false;
        $char = ''; $openingQuote = '';
        $str = trim($str);
        for ($i=0; $i<strlen($str); $i++)
        {
            $char = $str[$i];
            if ($char == '"' || $char == "'")
            {
                if ($i == 0 || $str[$i-1] != '\\')
                {
                    if ($openingQuote && $char == $openingQuote)
                    {
                        $openingQuote = '';
                    }
                    else
                    {
                        $openingQuote = $char;
                    }
                }
            }
            elseif (!$openingQuote)
            {
                if ($char == '(')
                {
                    $substr = trim($substr);
                    if (strlen($substr))
                        array_push($out, $substr);
                    array_push($out, '(');
                    $resetSubstr = true;
                    $curDepth++;
                }
                elseif ($char == ')')
                {
                    $curDepth--;
                    if ($curDepth < 0)
                        throw new DataGridException("Unmatched closing bracket detected");
                    $substr = trim($substr);
                    if (strlen($substr))
                        array_push($out, $substr);
                    array_push($out, ')');
                    $resetSubstr = true;
                }
                elseif (array_key_exists($char, self::$setOperators))
                {
                    $substr = trim($substr);
                    if (strlen($substr))
                        array_push($out, $substr);
                    array_push($out, $char);
                    $resetSubstr = true;
                }
            }
            $substr = $resetSubstr ? '' : $substr.$char;
            $resetSubstr = false;
        }
        if ($curDepth > 0)
            throw new DataGridException("Unmatched opening bracket detected");
        $substr = trim($substr);
        if (strlen($substr))
            array_push($out, $substr);
        return $out;
    }
    
    /**
     * @internal
     */
    private function extractSearchExpression( $str )
    {
        if (!is_string($str) || !strlen($str))
            throw new DataGridException("Non-empty string expected");
        $fields = (array) $this->extractSearchField($str);
        $operation = $this->extractSearchOperation($str, self::selectorChars());
        $values = (array) $this->extractSearchValue($str);
        $expression = array($fields, $operation, $values);
        return $expression;
    }
    
    /**
     * @internal
     */
    private function extractSearchField( &$str )
    {
        $field ='';
        if (preg_match('/^"(.*?[^\\\])"(.*)/', $str, $matches)) // quoted (complex) string
        {
            $fields = array();
            $fields[] = str_replace('\"', '"', $matches[1]);
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
    private function extractSearchOperation( &$str, array $operators)
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
    private function extractSearchValue(&$str)
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
                if ($c == '|')
                {
                    if ($lastChar != '\\') // non-quoted, non-escaped pipe terminates the value
                        break;
                    else // intentionally escaped pipe (remove the escape char)
                        $value = rtrim($value, '\\');
                }
            }
            $value.= $c;
            $lastChar = $c;
        } while(++$n < self::maxValueLength);
        if (strlen("$value"))
            $str = substr($str, $n);
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
    
    /** 
     * @internal
     */
    private function evaluateSearchVector( $v, $key, $label, $rowOrColumn, $selector )
    {
        if (is_bool($v)) return $v;
        if ('row' == $rowOrColumn) $rowOrColumnInverse = 'column';
        elseif ('column' == $rowOrColumn) $rowOrColumnInverse = 'row';
        else throw new DataGridException("'row' or 'column' expected");
        $selector = $this->extractSearchExpression($selector);
        $selectorMaps = self::selectors();
        list($fields, $operator, $values) = $selector;
        if (empty($operator) || !array_key_exists($operator, $selectorMaps))
            throw new DataGridException("Invalid selector provided");
        $matchingFunction = $selectorMaps[$operator];
        $match = false;
        foreach ($fields as $field)
        {
            foreach ($values as $value)
            {
                if ('/' == $field) $val1 = $key;
                elseif ('//' == $field) $val1 = $label;
                elseif (preg_match('#/(\d+)#', $field, $matches)) $val1 = $v[$this->getKey($rowOrColumnInverse, (int)$matches[1])];
                else $val1 = $v[$this->getKey($rowOrColumnInverse, $field)];
                if ($matchingFunction($val1, $value))
                {
                    $match = true;
                    break 2;
                }
            }
        }
        return $match;
    }
    
    /**
     * Perform a search query on the grid.
     * Returns results as a new DataGrid without modifying $this.
     * 
     * @link{http://datagrid.smrtr.co.uk/tutorial/searching}
     * @param string $str Query string. 
     * @param string $rowOrColumn 'row' or 'column'.
     * @return \Smrtr\DataGrid
     * @throws DataGridException 
     */
    public function search( $str, $rowOrColumn )
    {
        if (!is_string($str)) throw new DataGridException("String expected");
        if (!strlen($str)) return $this;
        if (!in_array($rowOrColumn, array('row','column')))
            throw new DataGridException("'row' or 'column' expected");
        $tokens = self::infixToRPN(self::extractSearchTokens($str));
        $Grid = new DataGrid();
        if ($rowOrColumn == 'row') {
            $count = $this->rows;
            $labels = $this->getRowLabels();
            $Grid->appendKeys('column', $this->columnKeys, true);
        }
        else {
            $count = $this->columns;
            $labels = $this->getColumnLabels();
            $Grid->appendKeys('row', $this->rowKeys, true);
        }
        for ($i=0; $i<$count; $i++)
        {
            # BEGIN LOOPING ROWS AND COLUMNS
            $Tokens = $tokens;
            $isMatch = true;
            $v = $this->{'get'.ucfirst($rowOrColumn)}($i);
            if (count($Tokens) > 1) {
                $stack = array();
                foreach ($Tokens as $Token)
                {
                    if (!self::isSetOperator($Token)) array_unshift($stack, $Token);
                    else {
                        $t2 = array_shift($stack);
                        $t1 = array_shift($stack);
                        $t1 = is_bool($t1) ? $t1 : $this->evaluateSearchVector($v, $i, $labels[$i], $rowOrColumn, $t1);
                        $t2 = is_bool($t2) ? $t2 : $this->evaluateSearchVector($v, $i, $labels[$i], $rowOrColumn, $t2);
                        if ('+' == $Token) {
                            # INTERSECTION, AND
                            array_unshift($stack, $t1 && $t2);
                        }
                        elseif ('-' == $Token) {
                            # DIFFERENCE, NOT
                            array_unshift($stack, $t1 && !$t2);
                        }
                        elseif (',' == $Token) {
                            # UNION, OR
                            array_unshift($stack, $t1 || $t2);
                        }
                    }
                }
                $isMatch = array_shift($stack);
            }
            elseif (count($Tokens) == 1)
                $isMatch = $this->evaluateSearchVector($v, $i, $labels[$i], $rowOrColumn, array_shift($Tokens));
            if ($isMatch) {
                $Grid->{'append'.ucfirst($rowOrColumn)}($v, $labels[$i]);
            }
            # END LOOPING ROWS OR COLUMNS
        }
        return $Grid;
    }
    
    /**
     * Perform a search query on the grid's rows.
     * Returns results as a new DataGrid without modifying $this.
     * 
     * @param string $s query string
     * @return \DataGrid
     * @throws DataGridException
     */
    public function searchRows( $s )
    {
        return $this->search($s, 'row');
    }
    
    /**
     * Perform a search query on the grid's columns.
     * Returns results as a new DataGrid without modifying $this.
     * 
     * @param string $s query string
     * @return \DataGrid
     * @throws DataGridException
     */
    public function searchColumns( $s )
    {
        return $this->search($s, 'column');
    }
        
    
    /*
     * ================================================================
     * Keys & Labels (* = API)
     * ================================================================
     * appendKey 
     * appendKeys
     * updateKey | updateLabel *
     * prependKey
     * deleteLastKey
     * emptyKey | emptyLabel *
     * swapKeys | swapLabels *
     * moveKey | moveLabels *
     * padKeys
     * getKey *
     * getKeys *
     * getLabel *
     * getLabels *
     * hasKey *
     * hasLabel *
     * ________________________________________________________________
     */
    
    /**
     * @internal
     */
    public function appendKey( $rowOrColumn, $label=null, $increaseCount=false )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (is_string($label))
        {
            if (!strlen($label))
                $label = null;
            if (in_array($label, $this->{$rowOrColumn.'Keys'}))
                throw new DataGridException($rowOrColumn."Key '$label' already exists");
        }
        elseif (!is_null($label))
            throw new DataGridException("non-empty string \$label or null expected");
        array_push($this->{$rowOrColumn.'Keys'}, $label);
        if ($increaseCount)
            $this->{$rowOrColumn.'s'}++;
        return $this;
    }
    
    /**
     * @internal
     */
    public function appendKeys( $rowOrColumn, array $labels, $increaseCount=false )
    {
        foreach ($labels as $label)
            $this->appendKey($rowOrColumn, $label, $increaseCount);
    }
    
    /**
     * Update the label for an existing key
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @param string|null $label
     * @return \DataGrid $this
     * @throws DataGridException 
     */
    public function updateKey( $rowOrColumn, $key, $label=null )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (!is_int($key) || !array_key_exists($key, $this->{$rowOrColumn.'Keys'}))
            throw new DataGridException("key not found");
        if (is_string($label) && strlen($label))
        {
            if (in_array($label, $this->{$rowOrColumn.'Keys'}))
                throw new DataGridException($rowOrColumn."Key '$label' already exists");
        }
        elseif (!is_null($label))
            throw new DataGridException("non-empty string \$label or null expected");
        $this->{$rowOrColumn.'Keys'}[$key] = $label;
        return $this;
    }
    
    /**
     * Update the label for an existing key
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @param string|null $label
     * @return \DataGrid $this
     * @throws DataGridException 
     */
    public function updateLabel( $rowOrColumn, $key, $label=null )
    {
        return $this->updateKey($rowOrColumn, $key, $label);
    }
    
    /**
     * Get row labels, or optionally update row labels
     * 
     * @api
     * @param array|false $labels [optional]
     * @return \DataGrid $this
     * @throws DataGridException
     */
    public function rowLabels( $labels=false )
    {
        if (false === $labels)
            return $this->rowKeys;
        elseif (is_array($labels))
        {
            if ($this->rows == 0)
                throw new DataGridException("Cannot assign labels to empty DataGrid");
            $rowKeys = $this->_normalizeKeys($labels, $this->rows);
            $this->rowKeys = $rowKeys;
            return $this;
        }
        throw new DataGridException("\$labels Array or false|void expected");
    }
    
    /**
     * Get column labels, or optionally update column labels
     * 
     * @api
     * @param array|false $labels [optional]
     * @return \DataGrid $this
     * @throws DataGridException 
     */
    public function columnLabels( $labels=false )
    {
        if (false === $labels)
            return $this->columnKeys;
        elseif (is_array($labels))
        {
            if ($this->columns == 0)
                throw new DataGridException("Cannot assign labels to empty DataGrid");
            $columnKeys = $this->_normalizeKeys($labels, $this->columns);
            $this->columnKeys = $columnKeys;
            return $this;
        }
        throw new DataGridException("\$labels Array or false|void expected");
    }
    
    /**
     * @internal
     */
    public function prependKey( $rowOrColumn, $label=null )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (is_string($label) && strlen($label))
        {
            if (in_array($label, $this->{$rowOrColumn.'Keys'}))
                throw new DataGridException($rowOrColumn."Key '$label' already exists");
        }
        elseif (!is_null($label))
            throw new DataGridException("non-empty string \$label or null expected");
        array_unshift($this->{$rowOrColumn.'Keys'}, $label);
        return $this;
    }
    
    /**
     * @internal
     */
    public function deleteLastKey( $rowOrColumn )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        $key = $this->{$rowOrColumn.'s'} - 1;
        unset($this->{$rowOrColumn.'Keys'}[$key]);
        return $this;
    }
    
    /**
     * Remove label identified by key/label
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int|string $keyOrLabel
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     */
    public function emptyKey( $rowOrColumn, $keyOrLabel )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
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
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     * @uses DataGrid::getLabel()
     * @uses DataGrid::emptyKey()
     * @uses DataGrid::updateKey()
     */
    public function swapKeys( $rowOrColumn, $keyOrLabel1, $keyOrLabel2, $stickyData=true )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
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
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     * @uses DataGrid::getLabel()
     * @uses DataGrid::emptyKey()
     * @uses DataGrid::updateKey()
     */
    public function moveKey( $rowOrColumn, $from_KeyOrLabel, $to_KeyOrLabel, $stickyData=true )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if ($stickyData)
            $this->{'move'.ucfirst($rowOrColumn)}($rowOrColumn, $to_KeyOrLabel, $from_KeyOrLabel, false);
            
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
    public function padKeys( $rowOrColumn, $length )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (!is_int($length) || $length < 0)
            throw new DataGridException("positive int \$length expected");
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
     * @throws DataGridException 
     */
    public function getKey( $rowOrColumn, $keyOrLabel )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (is_string($keyOrLabel))
        {
            $offset = array_search($keyOrLabel, $this->{$rowOrColumn.'Keys'});
            if (false !== $offset)
                return $offset;
            throw new DataGridException("Label '$keyOrLabel' not found");
        }
        elseif (is_int($keyOrLabel))
        {
            if (array_key_exists($keyOrLabel, $this->{$rowOrColumn.'Keys'}))
                return $keyOrLabel;
            throw new DataGridException("$rowOrColumn Key $keyOrLabel not found");
        }
        else
            throw new DataGridException("\$keyOrLabel can be int or string only");
    }
    
    /**
     * Get row key from a key or label
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return int
     * @throws DataGridException 
     */
    public function getRowKey( $keyOrLabel )
    {
        return $this->getKey('row', $keyOrLabel);
    }
    
    /**
     * Get column key from a key or label
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return int
     * @throws DataGridException 
     */
    public function getColumnKey( $keyOrLabel )
    {
        return $this->getKey('column', $keyOrLabel);
    }
    
    /**
     * Get keys array for rows or columns
     * 
     * @param string $rowOrColumn 'row' or 'column'
     * @return array 
     */
    public function getKeys( $rowOrColumn )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        return array_keys($this->{$rowOrColumn.'Keys'});
    }
    
    /**
     * Get row keys array
     * 
     * @return array 
     */
    public function getRowKeys()
    {
        return $this->getKeys('row');
    }
    
    /**
     * Get column keys array
     * 
     * @return array 
     */
    public function getColumnKeys()
    {
        return $this->getKeys('column');
    }
    
    /**
     * Get label from a key
     * 
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @return string
     * @throws DataGridException 
     */
    public function getLabel( $rowOrColumn, $key )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (!is_int($key))
            throw new DataGridException("int \$key expected");
        if (array_key_exists($key, $this->{$rowOrColumn.'Keys'}))
            return $this->{$rowOrColumn.'Keys'}[$key];
        return false;
    }
    
    /**
     * Get row label from a key
     * 
     * @api
     * @param int $key
     * @return string
     * @throws DataGridException 
     */
    public function getRowLabel( $key )
    {
        return $this->getLabel('row', $key);
    }
    
    /**
     * Get column label from a key
     * 
     * @api
     * @param int $key
     * @return string
     * @throws DataGridException 
     */
    public function getColumnLabel( $key )
    {
        return $this->getLabel('column', $key);
    }
    
    /**
     * Get labels array for rows or columns, indexed by keys
     * 
     * @param string $rowOrColumn 'row' or 'column'
     * @return array
     */
    public function getLabels( $rowOrColumn )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        return $this->{$rowOrColumn.'Keys'};
    }
    
    /**
     * Get row labels array, indexed by keys
     * 
     * @param string $rowOrColumn 'row' or 'column'
     * @return array
     */
    public function getRowLabels()
    {
        return $this->getLabels('row');
    }
    
    /**
     * Get column labels array, indexed by keys
     * 
     * @param string $rowOrColumn 'row' or 'column'
     * @return array
     */
    public function getColumnLabels()
    {
        return $this->getLabels('column');
    }
    
    /**
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param int $key
     * @return boolean
     * @throws DataGridException 
     */
    public function hasKey( $rowOrColumn, $key )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (!is_int($key))
            throw new DataGridException("int \$key expected");
        return array_key_exists($key, $this->{$rowOrColumn.'Keys'});
    }
    
    /**
     * @api
     * @param int $key
     * @return boolean
     * @throws DataGridException 
     */
    public function hasRowKey( $key )
    {
        return $this->hasKey('row', $key);
    }
    
    /**
     * @api
     * @param int $key
     * @return boolean
     * @throws DataGridException 
     */
    public function hasColumnKey( $key )
    {
        return $this->hasKey('column', $key);
    }
    
    /**
     * @api
     * @param string $rowOrColumn 'row' or 'column'
     * @param string $label
     * @return boolean
     * @throws DataGridException 
     */
    public function hasLabel( $rowOrColumn, $label )
    {
        if (!in_array($rowOrColumn, array('column', 'row')))
            throw new DataGridException("'column' or 'row' expected");
        if (!is_string($label))
            throw new DataGridException("string \$label expected");
        return in_array($label, $this->{$rowOrColumn.'Keys'});
    }
    
    /**
     * @api
     * @param string $label
     * @return boolean
     * @throws DataGridException 
     */
    public function hasRowLabel( $label )
    {
        return $this->hasLabel('row', $label);
    }
    
    /**
     * @api
     * @param string $label
     * @return boolean
     * @throws DataGridException 
     */
    public function hasColumnLabel( $label )
    {
        return $this->hasLabel('column', $label);
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
     * filterRows
     * mergeRows
     * diffRows
     * intersectRows
     * deleteEmptyRows
     * ________________________________________________________________
     */
    
    /**
     * Append a row to the end of the grid
     * 
     * @api
     * @param array $row
     * @param string|null $label [optional] string label for the appended row
     * @param boolean $internal @internal
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::appendKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function appendRow($row, $label=null, $internal=false)
    {
        if ($row instanceof DataGridVector)
            $row = $row->data();
        if (!is_array($row))
            throw new DataGridException("array expected");
        
        $rowVector = $this->_normalizeVector($row, $this->columns);
        if (count($rowVector) > $this->columns)
        {
            $lim = count($rowVector) - $this->columns;
            for ($i=0; $i<$lim; $i++)
                $this->appendColumn(array(), null);
        }
        if (!$internal) $this->appendKey('row', $label);
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
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function updateRow($keyOrLabel, $row)
    {
        if ($row instanceof DataGridVector)
            $row = $row->data();
        if (!is_array($row))
            throw new DataGridException("array expected");
        
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
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::prependKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function prependRow($row, $label=null)
    {
        if ($row instanceof DataGridVector)
            $row = $row->data();
        if (!is_array($row))
            throw new DataGridException("array expected");
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
     * @param boolean $associative Optional
     * @return array
     * @uses DataGrid::getKey()
     */
    public function getRow( $keyOrLabel, $associative=false )
    {
        $key = $this->getKey('row', $keyOrLabel);
        if ($associative) {
            return array_combine($this->getColumnLabels(), $this->data[$key]);
        }
        return $this->data[$key];
    }
    
    /**
     * Get the DISTINCT values from a row
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return array
     * @uses DataGrid::getRow()
     */
    public function getRowDistinct( $keyOrLabel )
    {
        $row = $this->getRow($keyOrLabel);
        $out = array();
        foreach ($row as $val) {
            if (!in_array($val, $out) && null !== $val) {
                $out[] = $val;
            }
        }
        return $out;
    }
    
    /**
     * Get an array with counts of occurences of each value in a row
     * 
     * @param int|string $keyOrLabel
     * @param boolean $verbose (optional) return array with reoccurring values appearing multiple times
     * @return array [ value => count, ... ] or [ [ value, count ], ... ] if $verbose is on
     */
    public function getRowCounts( $keyOrLabel, $verbose=false )
    {
        $counts = array();
        $keys = array();
        $i = $this->getKey('row', $keyOrLabel);
        for ($j=0; $j<$this->columns; $j++)
        {
            $val = $this->data[$i][$j];
            if (array_key_exists($val, $counts)) {
                $counts[$val]++;
                if ($verbose)
                    $keys[$val][] = $j;
            }
            else {
                $counts[$val] = 1;
                if ($verbose)
                    $keys[$val] = array($j);
            }
        }
        if ($verbose) {
            $tmp = array();
            foreach ($keys as $val => $Keys) {
                foreach ($Keys as $Key)
                    $tmp[$Key] = array($val, $counts[$val]);
            }
            ksort($tmp);
            return $tmp;
        }
        return $counts;
    }
    
    /**
     * Fill a row with null values
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function emptyRow( $keyOrLabel )
    {
        $key = $this->getKey('row', $keyOrLabel);
        $this->data[$key] = $this->_normalizeVector(array(), $this->columns);
        return $this;
    }
    
    /**
     * Delete a row from the grid
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \DataGrid $this
     * @uses DataGrid::moveRow()
     * @uses DataGrid::deleteLastKey()
     */
    public function deleteRow( $keyOrLabel )
    {
        $lastRowKey = $this->rows - 1;
        $this->moveRow($keyOrLabel, $lastRowKey, true);
        $this->deleteLastKey('row');
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
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::updateKey()
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
     * @return \DataGrid $this
     * @uses DataGrid::swapKeys() if $stickyLabels
     * @uses DataGrid::getKey()
     * @uses DataGrid::getRow()
     * @uses DataGrid::updateRow()
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
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::moveKey() if $stickyLabels
     * @uses DataGrid::getRow()
     * @uses DataGrid::updateRow()
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
            throw new DataGridException("positive int \$length expected");
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
     * @uses DataGrid::getRow()
     * @uses DataGrid::deleteRow() 
     */
    public function takeRow( $keyOrLabel )
    {
        $return = $this->getRow($keyOrLabel);
        $this->deleteRow($keyOrLabel);
        return $return;
    }
    
    /**
     * Loop through rows and execute a callback function on each row. f(key, label, row)
     * Row provided to callback as array by default (faster), or optionally as DataGridVector object.
     * 
     * The label parameter was added to the callback in version 1.1
     * 
     * @api
     * @param callable $callback
     * @param boolean $returnVectorObject 
     * @return \DataGrid $this
     * @throws DataGridException 
     * @uses DataGrid::row()
     * @uses DataGrid::getRow()
     */
    public function eachRow( $callback, $returnVectorObject=false )
    {
        if (!is_callable($callback))
            throw new DataGridException("\$callback provided is not callable");
        foreach ($this->rowKeys as $key => $label)
        {
            $row = $returnVectorObject ? $this->row($key) : $this->getRow($key);
            $callback($key, $label, $row);
        }
        return $this;
    }
    
    /**
     * Order rows by a particular column (ascending or descending)
     * 
     * @api
     * @param int|string $byColumnKeyOrLabel
     * @param string $order 'asc' or 'desc'. Defaults to 'asc'
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     * @uses DataGrid::getLabel()
     * @uses DataGrid::rowLabels()
     */
    public function orderRows( $byColumnKeyOrLabel, $order='asc', $stickyLabels=true )
    {
        switch ($order)
        {
            case 'asc': $sortFunction = 'ksort'; break;
            case 'desc': $sortFunction = 'krsort'; break;
            default: throw new DataGridException("\$order of 'asc' or 'desc' expected"); break;
        }
        $searchKey = $this->getKey('column', $byColumnKeyOrLabel);
        $stack = array(); $keyStack = array();
        $this->eachRow( function($i, $label, $row) use(&$stack, &$keyStack, $searchKey)
        {
            $val = $row[$searchKey];
            if (!array_key_exists($val, $stack))
            {
                $stack[$val] = array();
                $keyStack[$val] = array();
            }
            $stack[$val][] = $row;
            $keyStack[$val][] = $label;
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
     * The $returnVectorObject parameter was added in version 1.1
     * 
     * @api
     * @param callable $callback Called on each row: $filter($key, $label, $row)
     * @param boolean $returnVectorObject 
     * @return DataGrid new DataGrid with results
     * @throws DataGridException
     * @uses DataGrid::getRow()
     * @uses DataGrid::appendRow()
     */
    public function filterRows( $callback, $returnVectorObject=false )
    {
        if (!is_callable($callback))
            throw new DataGridException("\$filter provided is not callable");
        $Grid = new DataGrid;
        $Grid->appendKeys('column', $this->columnKeys, true);
        foreach ($this->rowKeys as $key => $label)
        {
            $row = $returnVectorObject ? $this->row($key) : $this->getRow($key);
            $result = (boolean) $callback($key, $label, $row);
            if ($result)
                $Grid->appendRow(($returnVectorObject ? $row->data() : $row), $label);
        }
        return $Grid;
    }
    
    /**
     * Merge another grid's rows into this grid.
     * We merge by appending rows with null or unique labels
     * 
     * @api
     * @param DataGrid $Grid Grid to merge with this
     * @return \DataGrid $this
     */
    public function mergeRows(DataGrid $Grid)
    {
        $columnLabelsDone = false;
        foreach ($Grid->getLabels('row') as $key => $label)
        {
            if (! is_null($label) && $this->hasLabel('row', $label))
                continue;
            if (!$columnLabelsDone)
            {
                $GridColumnCount = $Grid->info('columnCount');
                $thisColumnCount = $this->columns;
            }
            $this->appendRow($Grid->getRow($key), $label);
            if (!$columnLabelsDone)
            {
                if ($GridColumnCount > $thisColumnCount)
                {
                    $diff = $GridColumnCount - $thisColumnCount;
                    for ($i=$diff; $i>0; $i--)
                    {
                        $columnKey = $GridColumnCount-$i;
                        $columnLabel = $Grid->getLabel('column', $columnKey);
                        $this->updateKey('column', $columnKey, $columnLabel);
                    }
                }
                $columnLabelsDone = true;
            }
        }
        return $this;
    }
    
    /**
     * Remove another grid's rows from this grid.
     * We remove rows with matching labels
     * 
     * @api
     * @param DataGrid $Grid Grid to reference against
     * @return \DataGrid $this
     */
    public function diffRows(DataGrid $Grid)
    {
        $subtractor = 0;
        foreach ($this->getLabels('row') as $key => $label)
        {
            if ($Grid->hasLabel('row', $label))
                $this->deleteRow($key-$subtractor++);
        }
        return $this;
    }
    
    /**
     * Intersection of this grid's rows with the rows of another grid
     * We intersect by removing rows with labels unique to this grid
     * 
     * @api
     * @param DataGrid $Grid Grid to reference against
     * @return \DataGrid $this
     */
    public function intersectRows(DataGrid $Grid)
    {
        $subtractor = 0;
        foreach ($this->getLabels('row') as $key => $label)
        {
            if (is_null($label) || !$Grid->hasLabel('row', $label))
                $this->deleteRow($key-$subtractor++);
        }
        return $this;
    }
    
    /**
     * Deletes empty rows (rows with all null values) and returns resulting grid.
     * To overwrite this object just call like so: $grid = $grid->deleteEmptyRows();
     * 
     * @api
     * @return \DataGrid new DataGrid with results
     */
    public function deleteEmptyRows()
    {
        return $this->filterRows(function($key, $label, $row) {
            return array_reduce($row, function(&$keep, $value){
                $keep = is_null($value) ? $keep : true;
                return $keep;
            }, false);
        });
    }
    
    /**
     * Check if a value exists in a row.
     * @param scalar|null $value
     * @param int|string $rowKeyOrLabel
     * @return boolean
     */
    public function rowHasValue( $value, $rowKeyOrLabel )
    {
        return $this->hasValue($value, 'row', $rowKeyOrLabel);
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
     * filterColumns
     * mergeColumns
     * diffColumns
     * intersectColumns
     * columnHasValue
     * ________________________________________________________________
     */
    
    /**
     * Append a column to the end of the grid
     * 
     * @api
     * @param array $column
     * @param string|null $label [optional] string label for the appended column
     * @param boolean $internal @internal
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::appendKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function appendColumn( $column, $label=null, $internal=false )
    {
        if ($column instanceof DataGridVector)
            $column = $column->data();
        if (!is_array($column))
            throw new DataGridException("array expected");
        
        $colVector = $this->_normalizeVector($column, $this->rows);
        if (count($colVector) > $this->rows)
        {
            $lim = count($colVector) - $this->rows;
            for ($i=0; $i<$lim; $i++)
                $this->appendRow(array(), null);
        }
        if (!$internal) $this->appendKey('column', $label);
        foreach (array_keys($this->rowKeys) as $i)
        {
            if (!array_key_exists($i, $this->data))
                $this->data[$i] = array();
            array_push($this->data[$i], array_shift($colVector));
        }
        $this->columns++;
        return $this;
    }
    
    /**
     * Set an array to the grid by overwriting an existing column
     * 
     * @api
     * @param int|string $keyOrLabel
     * @param array $column
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function updateColumn($keyOrLabel, $column)
    {
        if ($column instanceof DataGridVector)
            $column = $column->data();
        if (!is_array($column))
            throw new DataGridException("array expected");
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
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::prependKey()
     * @uses DataGrid::_normalizeVector()
     */
    public function prependColumn( $column, $label=false )
    {
        if ($column instanceof DataGridVector)
            $column = $column->data();
        if (!is_array($column))
            throw new DataGridException("array expected");
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
     * @param boolean $associative Optional
     * @return array
     * @uses DataGrid::getKey()
     */
    public function getColumn( $keyOrLabel, $associative=false )
    {
        $key = $this->getKey('column', $keyOrLabel);
        $column = array();
        foreach ($this->data as $i => $row)
            $column[$i] = $row[$key];
        if ($associative) {
            return array_combine($this->getRowLabels(), $column);
        }
        return $column;
    }
    
    /**
     * Get the DISTINCT values from a column
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return array
     * @uses DataGrid::getColumn()
     */
    public function getColumnDistinct( $keyOrLabel )
    {
        $col = $this->getColumn($keyOrLabel);
        $out = array();
        foreach ($col as $val) {
            if (!in_array($val, $out) && null !== $val) {
                $out[] = $val;
            }
        }
        return $out;
    }
    
    /**
     * Get an array with counts of occurrences of each value in a column
     * 
     * @param int|string $keyOrLabel
     * @param boolean $verbose (optional) return array with reoccurring values appearing multiple times
     * @return array [ value => count, ... ] or [ [ value, count ], ... ] if $verbose is on
     */
    public function getColumnCounts( $keyOrLabel, $verbose=false )
    {
        $counts = array();
        $keys = array();
        $i = $this->getKey('column', $keyOrLabel);
        for ($j=0; $j<$this->rows; $j++)
        {
            $val = $this->getValue($j, $i);
            if (array_key_exists($val, $counts)) {
                $counts[$val]++;
                if ($verbose)
                    $keys[$val][] = $j;
            }
            else {
                $counts[$val] = 1;
                if ($verbose)
                    $keys[$val] = array($j);
            }
        }
        if ($verbose) {
            $tmp = array();
            foreach ($keys as $val => $Keys) {
                foreach ($Keys as $Key)
                    $tmp[$Key] = array($val, $counts[$val]);
            }
            ksort($tmp);
            return $tmp;
        }
        return $counts;
    }
    
    /**
     * Fill a column with null values
     * 
     * @api
     * @param int|string $keyOrLabel
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::_normalizeVector()
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
     * @return \DataGrid $this
     * @uses DataGrid::moveColumn()
     * @uses DataGrid::deleteLastKey()
     */
    public function deleteColumn( $keyOrLabel )
    {
        $lastColKey = $this->columns - 1;
        $this->moveColumn($keyOrLabel, $lastColKey, true);
        $this->deleteLastKey('column');
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
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::updateKey()
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
     * @return \DataGrid $this
     * @uses DataGrid::swapKeys() if $stickyLabels
     * @uses DataGrid::getKey()
     * @uses DataGrid::getColumn()
     * @uses DataGrid::updateColumn()
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
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::moveKey() if $stickyLabels
     * @uses DataGrid::getColumn()
     * @uses DataGrid::updateColumn()
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
            throw new DataGridException("positive int \$length expected");
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
     * @uses DataGrid::getColumn()
     * @uses DataGrid::deleteColumn() 
     */
    public function takeColumn( $keyOrLabel )
    {
        $return = $this->getColumn($keyOrLabel);
        $this->deleteColumn($keyOrLabel);
        return $return;
    }
    
    /**
     * Loop through columns and execute a callback function on each column. f(key, columndata)
     * Column provided to callback as array by default (faster), or optionally as DataGridVector object.
     * 
     * The label parameter was added to the callback in version 1.1
     * 
     * @api
     * @param callable $callback
     * @param boolean $returnVectorObject 
     * @return \DataGrid $this
     * @throws DataGridException 
     * @uses DataGrid::column()
     * @uses DataGrid::getColumn()
     */
    public function eachColumn( $callback, $returnVectorObject=false )
    {
        if (!is_callable($callback))
            throw new DataGridException("\$callback provided is not callable");
        foreach ($this->columnKeys as $key => $label)
        {
            $column = $returnVectorObject ? $this->column($key) : $this->getColumn($key);
            $callback($key, $label, $column);
        }
        return $this;
    }
    
    /**
     * Order columns by a particular row (ascending or descending)
     * 
     * @api
     * @param int|string $byRowKeyOrLabel
     * @param string $order 'asc' or 'desc'. Defaults to 'asc'
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::getKey()
     * @uses DataGrid::getLabel()
     * @uses DataGrid::columnLabels()
     */
    public function orderColumns( $byRowKeyOrLabel, $order='asc', $stickyLabels=true )
    {
        switch ($order)
        {
            case 'asc': $sortFunction = 'ksort'; break;
            case 'desc': $sortFunction = 'krsort'; break;
            default: throw new DataGridException("\$order of 'asc' or 'desc' expected"); break;
        }        
        $searchKey = $this->getKey('row', $byRowKeyOrLabel);
        $stack = array(); $keyStack = array();
        $this->eachColumn( function($i, $label, $column) use(&$stack, &$keyStack, $searchKey)
        {
            $val = $column[$searchKey];
            if (!array_key_exists($val, $stack))
            {
                $stack[$val] = array();
                $keyStack[$val] = array();
            }
            $stack[$val][] = $column;
            $keyStack[$val][] = $label;
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
     * The $returnVectorObject parameter was added in version 1.1
     * 
     * @api
     * @param callable $callback Called on each column: $callback($key, $label, $column)
     * @param boolean $returnVectorObject
     * @return DataGrid new DataGrid with results
     * @throws DataGridException
     * @uses DataGrid::getColumn()
     * @uses DataGrid::appendColumn()
     */
    public function filterColumns( $callback, $returnVectorObject=false )
    {
        if (!is_callable($callback))
            throw new DataGridException("\$filter provided is not callable");
        $Grid = new DataGrid;
        $Grid->appendKeys('row', $this->rowKeys, true);
        foreach ($this->columnKeys as $key => $label)
        {
            $column = $returnVectorObject ? $this->column($key) : $this->getColumn($key);
            $result = (boolean) $callback($key, $label, $column);
            if ($result)
                $Grid->appendColumn(($returnVectorObject ? $column->data() : $column), $label);
        }
        return $Grid;
    }
    
    /**
     * Merge another grid's columns into this grid.
     * We merge by appending columns with null or unique labels
     * 
     * @api
     * @param DataGrid $Grid Grid to merge with this
     * @return \DataGrid $this
     */
    public function mergeColumns(DataGrid $Grid)
    {
        foreach ($Grid->getLabels('column') as $key => $label)
        {
            if (! is_null($label) && $this->hasLabel('column', $label))
                continue;
            if (!$rowLabelsDone)
            {
                $GridRowCount = $Grid->info('rowCount');
                $thisRowCount = $this->rows;
            }
            $this->appendColumn($Grid->getColumn($key), $label);
            if (!$rowLabelsDone)
            {
                if ($GridRowCount > $thisRowCount)
                {
                    $diff = $GridRowCount - $thisRowCount;
                    for ($i=$diff; $i>0; $i--)
                    {
                        $rowKey = $GridRowCount-$i;
                        $rowLabel = $Grid->getLabel('row', $rowKey);
                        $this->updateKey('row', $rowKey, $rowLabel);
                    }
                }
                $rowLabelsDone = true;
            }
        }
        return $this;
    }
    
    /**
     * Remove another grid's columns from this grid.
     * We remove columns with matching labels
     * 
     * @api
     * @param DataGrid $Grid Grid to reference against
     * @return \DataGrid $this
     */
    public function diffColumns(DataGrid $Grid)
    {
        $subtractor = 0;
        foreach ($this->getLabels('column') as $key => $label)
        {
            if ($Grid->hasLabel('column', $label))
                $this->deleteRow($key-$subtractor++);
        }
        return $this;
    }
    
    /**
     * Intersection of this grid's columns with the columns of another grid
     * We intersect by removing columns with labels unique to this grid
     * 
     * @api
     * @param DataGrid $Grid Grid to reference against
     * @return \DataGrid $this
     */
    public function intersectColumns(DataGrid $Grid)
    {
        foreach ($this->getLabels('column') as $key => $label)
        {
            if (is_null($label) || !$Grid->hasLabel('column', $label))
                $this->deleteColumn($key);
        }
        return $this;
    }
    
    /**
     * Deletes empty columns (columns with all null values) and returns resulting grid.
     * To overwrite this object just call like so: $grid = $grid->deleteEmptyColumns();
     * 
     * @api
     * @return \DataGrid new DataGrid with results
     */
    public function deleteEmptyColumns()
    {
        return $this->filterColumns(function($key, $label, $column) {
            return array_reduce($column, function(&$keep, $value){
                $keep = is_null($value) ? $keep : true;
                return $keep;
            }, false);
        });
    }
    
    /**
     * Check if a value exists in a column.
     * @param scalar|null $value
     * @param int|string $columnKeyOrLabel
     * @return boolean
     */
    public function columnHasValue( $value, $columnKeyOrLabel )
    {
        return $this->hasValue($value, 'column', $columnKeyOrLabel);
    }
    
    
    /*
     * ================================================================
     * The Grid (* = API)
     * ================================================================
     * scalarValuesOnly*
     * setValue*
     * getValue*
     * hasValue*
     * loadArray*
     * getArray*
     * getAssociativeArray*
     * transpose*
     * info*
     * row*
     * column*
     * _importMatrix
     * _normalizeVector
     * _normalizePoint
     * __construct
     * getByID
     * ________________________________________________________________
     */

    /**
     * By default the DataGrid only restricts values on the grid to scalars only.
     *
     * Call this method to turn this restriction on or off.
     *
     * @param boolean $scalarValuesOnly Set to false to turn off the restriction, set to true to turn it back on.
     *
     * @return $this
     */
    public function scalarValuesOnly($scalarValuesOnly)
    {
        $this->scalarValues = (boolean) $scalarValuesOnly;
        return $this;
    }

    /**
     * Update value at a particular point. Use
     * 
     * @api
     * @param int|string $rowKeyOrLabel
     * @param int|string $columnKeyOrLabel
     * @param mixed $value
     * @return \DataGrid $this
     * @uses DataGrid::getKey()
     * @uses DataGrid::_normalizePoint() 
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
     * @uses DataGrid::getKey()
     */
    public function getValue( $rowKeyOrLabel, $columnKeyOrLabel )
    {
        $rowKey = $this->getKey('row', $rowKeyOrLabel);
        $columnKey = $this->getKey('column', $columnKeyOrLabel);
        return $this->data[$rowKey][$columnKey];
    }
    
    /**
     * Returns true if given value exists somewhere in grid data
     * 
     * @param scalar|null $value
     * @param null|string $rowOrColumn (Optional) 'row' or 'column'. Specify a row or column in which to look for the value.
     * @param int|string $keyOrLabel (Optional) used in conjunction with $rowOrLabel to identify a row or column in which to look for the value.
     * @return boolean
     */
    public function hasValue( $value, $rowOrColumn=null, $keyOrLabel=null )
    {
        if (null !== $rowOrColumn && 'row' != $rowOrColumn && 'column' != $rowOrColumn)
            throw new DataGridException("Invalid \$rowOrColumn passed to \Smrtr\DataGrid::hasValue(). null, 'row' or 'column' expected.");
        if (null !== $rowOrColumn)
            $key = $this->getKey($rowOrColumn, $keyOrLabel);
        if (null === $rowOrColumn) {
            foreach ($this->data as $row) {
                foreach ($row as $val) {
                    if ($val === $value)
                        return true;
                }
            }
        }
        else {
            foreach ($this->{'get'.ucfirst($rowOrColumn)}($key) as $val)
                if ($val === $value)
                    return true;
        }
        return false;
    }
    
    /**
     * Import data from array (completely overwrites current grid data)
     * 
     * @api
     * @param array $data
     * @param boolean $associateRowLabels (optional) use array's row keys for grid's row labels
     * @param boolean $useFirstRowAsColumnLabels (optional) use array's first row as grid's column labels
     * @return \DataGrid $this
     * @uses DataGrid::appendKeys()
     * @uses DataGrid::_importMatrix()
     * @uses DataGrid::padKeys()
     */
    public function loadArray( $data, $associateRowLabels=false, $associateColumnLabels=false, $adjustForBlankCorner=true )
    {
        $this->rows = 0;
        $this->columns = 0;
        $this->rowKeys = array();
        $this->columnKeys = array();
        if (!empty($data)) {
            if (self::ASSOC_COLUMN_FIRST == $associateColumnLabels) {
                $row = array_shift($data);
                if ($adjustForBlankCorner && self::ASSOC_ROW_FIRST === $associateRowLabels) {
                    array_shift($row);
                }
                $this->appendKeys('column', $row);
            }
            if (self::ASSOC_ROW_FIRST === $associateRowLabels) {
                $column = array();
                foreach ($data as $i => $row) {
                    $column[] = array_shift($row);
                    $data[$i] = $row;
                }
                $this->appendKeys('row', $column);
            }
        }        
        if (!empty($data)) {
            if (self::ASSOC_ROW_KEYS == $associateRowLabels)
                $this->appendKeys('row', array_keys($data));
            if (self::ASSOC_COLUMN_KEYS === $associateColumnLabels)
                $this->appendKeys('column', array_keys(reset($data)));
            $this->_importMatrix($data);
        }
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
     * @return \DataGrid $this
     * @uses DataGrid::getColumn()
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
     * @param string|null $key Defaults to null. Optional key if looking for specific piece of info.
     * @return array rowCount=>int, columnCount=>int, rowKeys=>array, columnKeys=>array
     */
    public function info( $key=null )
    {
        $info = array(
            'rowCount' => $this->rows,
            'columnCount' => $this->columns,
            'rowKeys' => $this->rowKeys,
            'columnKeys' => $this->columnKeys
        );
        if (!is_null($key))
        {
            if (array_key_exists($key, $info))
                return $info[$key];
            throw new DataGridException("Unknown key provided to info function");
        }
        return $info;
    }
    
    /**
     * Get a vector object which proxies to the given row.
     * 
     * @api
     * @param int|string $rowKeyOrLabel
     * @return \DataGridVector 
     * @uses DataGrid::getKey()
     */
    public function row( $rowKeyOrLabel )
    {
        $key = $this->getKey('row', $rowKeyOrLabel);
        return new DataGridVector(
            $this->ID, $key, false
        );
    }
    
    /**
     * Get a vector object which proxies to the given column.
     * 
     * @api
     * @param int|string $columnKeyOrLabel
     * @return \DataGridVector 
     * @uses DataGrid::getKey()
     */
    public function column( $columnKeyOrLabel )
    {
        $key = $this->getKey('column', $columnKeyOrLabel);
        return new DataGridVector(
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
                $vector[] = $this->_normalizePoint($val);
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
            $vector[] = $this->_normalizePoint($val);
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
        if ($this->scalarValues) {
            return (is_scalar($point) or is_null($point)) ? $point : null;
        }
        return $point;
    }
    
    /**
     * Optionally pass an array of data to instanciate with
     * 
     * @api
     * @param array $data [optional] 2D array of data
     * @param boolean $associateRowLabels [optional] Defaults to false
     * @param boolean $useFirstRowAsColumnLabels [optional] Defaults to false
     * @uses DataGrid::appendKeys()
     * @uses DataGrid::_importMatrix()
     */
    public function __construct( array $data = array(), $associateRowLabels=false, $useFirstRowAsColumnLabels=false )
    {
        $this->ID = self::$IDcounter++;
        self::$registry[$this->ID] = $this;
        if (!empty($data)) {
            $this->loadArray($data, $associateRowLabels, $useFirstRowAsColumnLabels);
        }
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
     * @return DataGrid $this
     * @uses DataGrid::readJSON()
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
     * @return \DataGrid $this
     * @throws DataGridException
     * @uses DataGrid::appendKeys() 
     * @uses DataGrid::_importMatrix() 
     */
    public function readJSON( $JSON, $rowKeysAsRowKeys=false, $firstRowsKeysAsColumnKeys=false )
    {
        $data = (array) json_decode($JSON);
        if (!count($data))
            throw new DataGridException("No data found");
        
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
     * @return \DataGrid $this
     * @uses DataGrid::getAssociativeArray()
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
     * @return \DataGrid $this
     * @uses DataGrid::getAssociativeArray()
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
     * @return \DataGrid $this
     * @uses DataGrid::getAssociativeArray(0
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
     * @param string $delimiter [optional] Defaults to comma
     * @param string $enclosure [optional] Defaults to doublequote
     * @param string $escape    [optional] Defaults to backslash
     * @return \DataGrid $this
     * @uses DataGrid::appendKeys()
     * @uses DataGrid::appendKey()
     * @uses DataGrid::_importMatrix()
     */
    public function loadCSV( $fileName, $firstColumnAsRowKeys=false, $firstRowAsColumnKeys=false, $delimiter=",", $enclosure='"', $escape='\\' )
    {
        $fileStream = fopen( $fileName, 'r ');
        $data = $this->csvFileToArray($fileStream, $firstColumnAsRowKeys, $firstRowAsColumnKeys, $delimiter, $enclosure, $escape);
        fclose($fileStream);
        
        $this->_importMatrix($data);
        
        if (!$firstColumnAsRowKeys)
            $this->appendKeys('row', array_fill(0, $this->rows, null));
        if (!$firstRowAsColumnKeys)
            $this->appendKeys('column', array_fill(0, $this->columns, null));

        return $this;
    }

    /**
     * @param resource $fileStream
     * @param boolean  $firstColumnAsRowKeys [optional] Defaults to false
     * @param boolean  $firstRowAsColumnKeys [optional] Defaults to false
     * @param string   $delimiter            [optional] Defaults to comma
     * @param string   $enclosure            [optional] Defaults to doublequote
     * @param string   $escape               [optional] Defaults to backslash
     *
     * @return array
     */
    protected function csvFileToArray($fileStream, $firstColumnAsRowKeys=false, $firstRowAsColumnKeys=false, $delimiter=",", $enclosure='"', $escape='\\')
    {
        $go = true;
        $data = array();
        while ($row = fgetCSV( $fileStream, 0, $delimiter, $enclosure, $escape ))
        {
            if ($go && $firstRowAsColumnKeys) {
                if ($firstColumnAsRowKeys) {
                    $this->appendKeys('column', array_slice((array) $row, 1));
                }
                else {
                    $this->appendKeys('column', (array) $row);
                }
                $go = false;
                continue;
            }
            if ($firstColumnAsRowKeys) {
                $this->appendKey('row', (string) array_shift($row));
            }
            $data[] = $row;
        }
        return $data;
    }
    
    /**
     * Load data from a CSV string
     * 
     * @api
     * @param string $CSV
     * @param boolean $firstColumnAsRowKeys [optional] Defaults to false
     * @param boolean $firstRowAsColumnKeys [optional] Defaults to false
     * @param string $delimiter [optional] Defaults to comma
     * @param string $enclosure [optional] Defaults to doublequote
     * @param string $escape    [optional] Defaults to backslash
     * @return $this
     * @uses DataGrid::appendKeys()
     * @uses DataGrid::appendKey()
     * @uses DataGrid::_importMatrix()
     */
    public function readCSV( $CSV, $firstColumnAsRowKeys=false, $firstRowAsColumnKeys=false, $delimiter=",", $enclosure='"', $escape='\\' )
    {
        $stream = fopen('php://memory','r+');
        fwrite($stream, $CSV);
        rewind($stream);
        $data = $this->csvFileToArray($stream, $firstColumnAsRowKeys, $firstRowAsColumnKeys, $delimiter, $enclosure, $escape);
        fclose($stream);
        
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
     * @param string $newline   [optional] Defaults to "\n"
     * @return \DataGrid $this
     * @uses DataGrid::_prepareCSV()
     */
    public function saveCSV( $fileName, $includeRowKeys=false, $includeColumnKeys=false, $delimeter=",", $enclosure='"', $newline="\n" )
    {
        $fileStream = fopen($fileName, 'w');
        $data = $this->_prepareCSV($includeRowKeys, $includeColumnKeys);
        array_walk( $data, function(&$vals, $keys, $vars) use($newline) {
            $return = fputCSV( $vars['outstream'], $vals, $vars['delimeter'], $vars['enclosure'] );
            if ($return !== false && "\n" != $newline && 0 === fseek($vars['outstream'], -1, SEEK_CUR)) {
                fwrite($vars['outstream'], $newline);
            }
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
     * @param string $newline   [optional] Defaults to "\n"
     * @param boolean $excelForceRawRender [optional] Defaults to false. Force excel to render raw contents of file (without applying any formatting).
     * @return \DataGrid $this
     * @uses DataGrid::_prepareCSV()
     */
    public function serveCSV( $fileName, $includeRowKeys=false, $includeColumnKeys=false, $delimeter=",", $enclosure='"', $newline="\n", $excelForceRawRender=false )
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
        array_walk( $data, function(&$vals, $keys, $vars) use($newline) {
            $return = fputCSV( $vars['outstream'], $vals, $vars['delimeter'], $vars['enclosure'] );
            if ($return !== false && "\n" != $newline && 0 === fseek($vars['outstream'], -1, SEEK_CUR)) {
                fwrite($vars['outstream'], $newline);
            }
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
     * @return \DataGrid $this
     * @uses DataGrid::_prepareCSV()
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
                    : ''
                ));
        if ($includeColumnKeys && !is_null($this->columnKeys))
        {
            $colKeys = array();
            for ($j=0; $j<$this->columns; $j++)
                $colKeys[] = is_string($this->columnKeys[$j])
                    ? $this->columnKeys[$j]
                    : '';
            array_unshift($out, (($includeRowKeys)
                ? array_merge(array(""), $colKeys)
                : $colKeys));
        }
        return $out;
    }
    
}
// Alias for backward-compatability
if (!class_exists('Smrtr_DataGrid', false))
    class_alias('\Smrtr\DataGrid', 'Smrtr_DataGrid');