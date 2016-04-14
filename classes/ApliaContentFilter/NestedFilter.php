<?php
namespace ApliaContentFilter;

use Exceptions\UnsupportedOperatorError;

class NestedFilter
{
    static public $ops = array(
        '=',
        '!=',
        '>',
        '>=',
        '<',
        '<=',
        'in',
        'not_in',
        'like',
        'not_like',
        'between',
        'not_between',
    );
    static public $registry = null;
    static public $modifiers = null;
    static public $customFilters = null;

    public $columns;
    public $columnIdentifiers;
    public $cond;
    public $legacyCounter = 0;

    public function __construct()
    {
        $this->columns = array();
        $this->columnIdentifiers = array();
        $this->cond = '';
    }

    public function mergeJoins($filter)
    {
        foreach ($filter->columns as $column) {
            $id = $column['id'];
            if (!isset($this->columns[$id])) {
                $this->columns[$id] = $column;
            }
        }
    }

    public function process($params)
    {
        if (!isset($params['cond'])) {
            $params = array(
                'cond' => 'and',
                'attrs' => $params,
            );
        }
        $this->cond = $this->processParam($params);
        // var_dump($this->cond);
    }

    public function processParam($param)
    {
        // var_dump($param);
        $condOp = 'and';
        $op = '=';
        $conds = array();

        if ($param instanceof NestedFilter) {
            $this->mergeJoins($param);
            return $param->cond;
        }

        if (isset($param['cond'])) {
            // A sub-query with a specific condition operator, attributes are either key => value or [key, value] array.
            // [
            //     'cond' => 'or',
            //     'attrs' => [
            //       article/name' => 'foo',
            //       ['article/name', 'bar'],
            //     ],
            // ]
            $condOp = strtolower($param['cond']);
            if ($condOp != 'and' && $condOp != 'or') {
                throw new \Exception("Unsupported join condition '$condOp'");
            }
            if (!isset($param['attrs'])) {
                throw new \Exception("Missing 'attrs' from filter array");
            }
            foreach ($param['attrs'] as $attr => $value) {
                if ($value === null) {
                    continue;
                }
                if (is_numeric($attr)) {
                    $cond = $this->processParam($value);
                } else {
                    $cond = $this->processParam(array($attr, $value));
                }
                // var_dump($cond);
                if ($cond) {
                    $conds[] = $cond;
                }
            }
        } else {
            if (isset($param['call'])) {
                // $fn is any callback structure supported by PHP
                $fn = $param['call'];
                $filter = call_user_func($fn, $this, $param);
                // Support old-style extended attribute filters
                if (is_array($filter) && isset($filter['tables']) && isset($filter['joins'])) {
                    $counter = $this->legacyCounter++;
                    $legacyId = 'eaf_' . $counter;
                    $this->columns[$legacyId] = array(
                        'id' => $legacyId,
                        'name' => 'legacy-filter/' . $counter,
                        'legacy' => $filter,
                    );
                    return '';
                }
                if (is_array($filter) && !isset($filter['cond'])) {
                    $filter = array(
                        'cond' => 'and',
                        'attrs' => $filter,
                    );
                }
                // Continue processing the return value
                return $this->processParam($filter);
            } else {
                // A statement, contains a field, a value and optionally an operator
                // e.g. ['article/name', 'baz']
                // or ['article/name', 'baz', '!=']
                // multiple fields are also supported, they will be ORed
                // e.g. [['article/name', 'folder/name'], 'baz']
                if (count($param) < 2) {
                    return;
                }
                $condOp = 'or';
                $attrs = $param[0];
                $value = $param[1];
                $op = '=';
                if (count($param) >= 3) {
                    $op = $param[2];
                } else {
                    // TODO: Support __ Django like suffix which defines the op
                    if (is_array($value)) {
                        $op = 'in';
                    }
                }
                if (!in_array($op, self::$ops)) {
                    throw new UnsupportedOperatorError("Unsupported filter operation '$op'");
                }
                $op = $op;
            }

            if (!is_array($attrs)) {
                $attrs = array($attrs);
            }

            foreach ($attrs as $attr) {
                // TODO: Support relations using __
                $attrOp = $op;
                $pre = array();
                $post = array();
                if (preg_match("|^([^:]+)((:([^:]+))+)?$|", $attr, $matches)) {
                    if (isset($matches[2])) {
                        $ops = explode(":", substr($matches[2], 1));
                        $attr = $matches[1];
                        $inPre = true;
                        foreach ($ops as $modifier) {
                            if (in_array($modifier, self::$ops)) {
                                $attrOp = $modifier;
                                $inPre = false;
                            } else if ($inPre) {
                                $pre[] = $modifier;
                            } else {
                                $post[] = $modifier;
                            }
                        }
                        if (!strlen($attrOp)) {
                            $attrOp = '=';
                        }
                    }
                }
                if (preg_match("|^([a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)$|", $attr, $matches)) {
                    $classAttributeId = $this->addAttributeColumn($attr);
                    $conds[] = $this->createClassAttributeFilter($classAttributeId, $value, $attrOp, $pre, $post);
                } else if ($this->hasCustomFilter($attr)) {
                    $columnId = $this->addCustomColumn($attr);
                    $filterConds = $this->createCustomFilter($attr, $columnId, $value, $attrOp, $pre, $post);
                    if ($filterConds) {
                        if (isset($filterConds['operator'])) {
                            $filterOp = $filterConds['operator'];
                            $statement = "\n( " . implode($filterOp == 'or' ? ' OR ' : ' AND ', $filterConds['conds']) . " )\n";
                            $conds[] = $statement;
                        } else {
                            $conds = array_merge($conds, $filterConds);
                        }
                    }
                    // TOOD: Support fields on the node
                } else {
                    throw new \Exception("Unsupported filter '$attr'");
                }
            }
        }

        $statement = null;
        if ($conds) {
            if (count($conds) == 1) {
                $statement = " \n" . $conds[0] . "\n";
            } else {
                $statement = "\n( " . implode($condOp == 'or' ? ' OR ' : ' AND ', $conds) . " )\n";
            }
        }
        return $statement;
    }

    public function getCustomFilters()
    {
        if (self::$customFilters === null) {
            $ini = \eZINI::instance('filter.ini');
            $attributes = $ini->variable('Handlers', 'Attributes');
            $filters = array();
            foreach ($attributes as $attr => $classStr) {
                $filters[$attr] = array(
                    'class' => $classStr,
                );
            }
            self::$customFilters = $filters;
        }
        return self::$customFilters;
    }

    public function hasCustomFilter($field)
    {
        $filters = self::getCustomFilters();
        return isset($filters[$field]);
    }

    public function getCustomFilter($field)
    {
        $filters = self::getCustomFilters();
        $filter = $filters[$field];
        if (!isset($filter['instance'])) {
            $filter['instance'] = new $filter['class']();
        }
        self::$customFilters[$field] = $filter;
        return $filter['instance'];
    }

    public function addCustomColumn($field)
    {
        $filterInstance = self::getCustomFilter($field);
        $col = $filterInstance->createColumn($this, $field);
        if ($col) {
            $this->columns[$col['id']] = $col;
            return $col['id'];
        }
        return null;
    }

    public function createCustomFilter($field, $id, $value, $op, $pre, $post)
    {
        $filterInstance = self::getCustomFilter($field);
        return $filterInstance->createFilter($this, $field, $id, $value, $op, $pre, $post);
    }

    public function createClassAttributeFilter($id, $value, $op, $pre, $post)
    {
        $columnIdentifier = 'cattr_' . $id;
        if (!isset($this->columns[$columnIdentifier])) {
            throw new \Exception("No such column with identifier '$columnIdentifier'");
        }
        $col = $this->columns[$columnIdentifier];
        $classAttribute = $col['classAttribute'];
        $dataType = $classAttribute->attribute('data_type_string');
        if (!isset($this->dataTypes[$dataType])) {
            throw new \Exception("No handlers found for content data-type '$dataType', cannot add attribute filter");
        }
        $classAttribute = $col['classAttribute'];

        $filter = call_user_func(array($this->dataTypes[$dataType], 'createFilter'), $this, $col, $value, $op, $pre, $post);
        return $filter;
    }

    public function createFilterCond($field, $value, $op, $pre, $post, $dbOp=null)
    {
        if (!$op) {
            $op = '=';
        }

        if ($dbOp === null) {
           $dbOp = $op;
        }
        $dbValue = $value;

        // Controls quotes around filter value, some filters do this manually
        $noQuotes = false;
        // Controls if $filterValue or $filter[2] is used, $filterValue is already escaped
        // $unEscape = false;

        $db = \eZDB::instance();
        if ($op == '!=') {
            $op = '<>';
        } else if ($op == 'like' || $op == 'not_like') {
            $dbOp = $op == 'like' ? 'LIKE' : 'NOT LIKE';
            // We escape the string ourselves, this MUST be done before wildcard replace
            $dbValue = $db->escapeString( $value );
            // $unEscape = true;
            // Since * is used as wildcard we need to transform the string to
            // use % as wildcard. The following rules apply:
            // - % -> \%
            // - * -> %
            // - \* -> *
            // - \\ -> \

            $dbValue = preg_replace( array( '#%#m',
                                              '#(?<!\\\\)\\*#m',
                                              '#(?<!\\\\)\\\\\\*#m',
                                              '#\\\\\\\\#m' ),
                                       array( '\\%',
                                              '%',
                                              '*',
                                              '\\\\' ),
                                       $dbValue );
        } else if ($op == 'in' || $op == 'not_in') {
            $dbOp = $op == 'in' ? 'IN' : 'NOT IN';
            // Turn off quotes for value, we do this ourselves
            $noQuotes = true;
            if (!is_array($value)) {
                throw new \Exception("Filter operator $op requires an array for the value");
            }
            $values = $value;
            foreach ($values as $key => $value) {
                // Non-numerics must be escaped to avoid SQL injection
                $values[$key] = is_numeric( $value ) ? $value : "'" . $db->escapeString( $value ) . "'";
            }
            $dbValue = '(' .  implode( ",", $values ) . ')';
        } else if ($op == 'between' || $op == 'not_between') {
            $dbOp = $filterType == 'between' ? 'BETWEEN' : 'NOT BETWEEN';
            // Turn off quotes for value, we do this ourselves
            $noQuotes = true;
            if (is_array($value)) {
                // Check for non-numerics to avoid SQL injection
                if ( !is_numeric( $value[0] ) ) {
                    $value[0] = "'" . $db->escapeString( $value[0] ) . "'";
                }
                if ( !is_numeric( $value[1] ) ) {
                    $value[1] = "'" . $db->escapeString( $value[1] ) . "'";
                }

                $dbValue = $value[0] . ' AND ' . $value[1];
            }
        }

        // // If $unEscape is true we get the filter value from the 2nd element instead
        // // which must have been escaped by filter type
        // $dbValue = $unEscape ? $filter[2] : $dbValue;0

        $left = $field;
        $right = ($noQuotes ? "$dbValue " : "'$dbValue' ");
        if ($pre) {
            $left = $this->applyModifiers($pre, $left);
        }
        if ($post) {
            $right = $this->applyModifiers($post, $right);
        }

        return "$left $dbOp $right";
    }

    public function applyModifiers($mods, $field)
    {
        $registry = self::getModifiers();
        foreach ($mods as $modifier) {
            if (!isset($registry[$modifier])) {
                throw new \Exception("Unsupported filter modifier '$modifier'");
            }
            $modifierCall = $registry[$modifier];
            $field = call_user_func($modifierCall, $field);
        }
        return $field;
    }

    public function addAttributeColumn($identifier)
    {
        // TODO: Support languages
        $language = false;

        if (!is_numeric($identifier) && isset($this->columnIdentifiers[$identifier])) {
            return $this->columnIdentifiers[$identifier];
        }
        if (is_numeric($identifier)) {
            $columnIdentifier = 'cattr_' . $identifier;
            if (isset($this->columns[$columnIdentifier])) {
                return $this->columns[$columnIdentifier]['classAttributeId'];
            }
        }

        $columnName = false;
        $classAttribute = null;
        if (!is_numeric($identifier)) {
            $classAttributeId = \eZContentObjectTreeNode::classAttributeIDByIdentifier($identifier);
            if ($classAttributeId === null) {
                throw new \Exception("Class attribute with identifier string '$identifier' not found");
            }
            $columnIdentifier = 'cattr_' . $classAttributeId;
            if (isset($this->columns[$columnIdentifier])) {
                return $this->columns[$columnIdentifier]['classAttributeId'];
            }
            $this->columnIdentifiers[$identifier] = $classAttributeId;
            $columnName = $identifier;
        } else {
            $classAttribute = \eZContentClassAttribute::fetch($identifier);
            if ($classAttribute === null) {
                throw new \Exception("Class attribute with ID '$identifier' not found");
            }
            $classAttributeId = $identifier;
        }
        if (!$classAttribute) {
            $classAttribute = \eZContentClassAttribute::fetch($classAttributeId);
        }
        if (!$columnName) {
            $class = \eZContentClass::fetch($classAttribute->attribute('contentclass_id'));
            $columnName = $class->attribute('identifier') . '/' . $classAttribute->attribute('identifier');
        }

        $joins = array();
        $conds = array();
        $table = 'aplia_naf_' . $classAttributeId;
        $dataType = $classAttribute->attribute('data_type_string');
        $typeColumn = $this->addDataTypeColumn($table, $classAttribute, $dataType, $language);
        if (isset($typeColumn['joins'])) {
            $joins = array_merge($joins, $typeColumn['joins']);
        }
        if (isset($typeColumn['conds'])) {
            $conds = array_merge($conds, $typeColumn['conds']);
        }

        $this->columns[$columnIdentifier] = array(
            'id' => $columnIdentifier,
            'name' => $columnName,
            'tbl' => $table,
            'joins' => $joins,
            'conds' => $conds,
            'classAttributeId' => $classAttributeId,
            'classAttribute' => $classAttribute,
        );
        return $classAttributeId;
    }

    public function addDataTypeColumn($table, $classAttribute, $dataType, $language)
    {
        if (!isset($this->dataTypes[$dataType])) {
            $registry = self::getDataTypeRegistry();
            $handler = null;
            if (isset($registry['map'][$dataType])) {
                $class = $registry['map'][$dataType];
                $this->dataTypes[$dataType] = new $class($dataType);
            } else {
                foreach ($registry['handlers'] as $class) {
                    if (call_user_func(array($class, 'isSupported'), $dataType)) {
                        $this->dataTypes[$dataType] = new $class($dataType);
                        break;
                    }
                }
            }
            if (!isset($this->dataTypes[$dataType])) {
                throw new \Exception("No handlers found for content data-type '$dataType', cannot add a filter column");
            }
        }
        $columnType = call_user_func(array($this->dataTypes[$dataType], 'createColumn'), $this, $table, $classAttribute, $language);
        return $columnType;
    }

    public static function getDataTypeRegistry()
    {
        if (self::$registry === null) {
            $ini = \eZINI::instance('filter.ini');
            self::$registry = array(
                // Reverse the order so the last appended handler is checked first
                'handlers' => array_reverse($ini->variable('Handlers', 'DataTypeHandlers')),
                // Maps data-type strings to a handler directly
                'map' => $ini->variable('Handlers', 'DataTypeMap'),
            );
        }
        return self::$registry;
    }

    public static function getModifiers()
    {
        if (self::$modifiers === null) {
            $ini = \eZINI::instance('filter.ini');
            $modifierSettings = $ini->variable('Handlers', 'Modifiers');
            $modifiers = array();
            foreach ($modifierSettings as $id => $funcStr) {
                $modifiers[$id] = explode("::", $funcStr, 2);
            }
            self::$modifiers = $modifiers;
        }
        return self::$modifiers;
    }

    public static function dateFilter($field)
    {
        return "DATE($field)";
    }
}
