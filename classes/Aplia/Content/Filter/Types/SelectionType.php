<?php
namespace Aplia\Content\Filter\Types;

use Exceptions\UnsupportedOperatorError;

class SelectionType extends RegularType
{
    public static function isSupported($dataType)
    {
        return $dataType == 'ezselection';
    }

    /**
     * @param $filterInstance Aplia\Content\Filter\NestedFilter
     */
    public function createFilter($filterInstance, $column, $value, $op, $pre, $post)
    {
        $table = $column['tbl'];
        $fieldName = 'data_text';
        $field = ($table ? "$table." : "" ) . $fieldName;
        $values = is_array($value) ? $value : array($value);
        $isNegated = false;
        switch ($op) {
            case '=':
            case 'in':
                $dbOp = 'REGEXP';
                break;
            case '!=':
            case 'not_in':
                $dbOp = 'NOT REGEXP';
                $isNegated = true;
                break;
            default:
                throw new UnsupportedOperatorError("Unsupported filter operation '$op' for ezselection");
        }
        $db = \eZDB::instance();
        foreach ($values as $value) {
            $dbValue = $db->escapeString(preg_quote($value));
            if ($value === 'unset' || $value instanceof UnsetValue) {
                $conds[] = $isNegated ? "$field IS NOT NULL" : "$field IS NULL";
            } elseif ($value == 0) {
                // For the first selection item we also match entries which have an empty string
                // These are usually objects where the selection field was added later on and
                // the default choice has not been chosen.
                $conds[] = $filterInstance->createFilterCond($field, "(^$|^$dbValue$|^$dbValue-.|.-$dbValue$|.-$dbValue-.)", '', $pre, $post, $dbOp);
            } else {
                $conds[] = $filterInstance->createFilterCond($field, "(^$dbValue$|^$dbValue-.|.-$dbValue$|.-$dbValue-.)", '', $pre, $post, $dbOp);
            }
        }
        $joinOp = $op == 'not_in' ? 'AND' : 'OR';
        // langCond is set in RegularType
        $langCond = $column['extra']['langCond'];
        if ($langCond && $conds) {
            $sql = "\n($langCond AND \n(" . implode(") $joinOp (", $conds) . ") )\n";
        } else {
            $sql = "\n(" . implode(") $joinOp (", $conds) . ")\n";
        }
        return $sql;
    }
}
