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
        switch ($op) {
            case '=':
            case 'in':
                $dbOp = 'REGEXP';
                break;
            case '!=':
            case 'not_in':
                $dbOp = 'NOT REGEXP';
                break;
            default:
                throw new UnsupportedOperatorError("Unsupported filter operation '$op' for ezselection");
        }
        $db = \eZDB::instance();
        foreach ($values as $value) {
            $dbValue = $db->escapeString(preg_quote($value));
            $conds[] = $filterInstance->createFilterCond($field, "(^$dbValue$|^$dbValue-.|.-$dbValue$|.-$dbValue-.)", '', $pre, $post, $dbOp);
        }
        return "\n(" . implode(") OR (", $conds) . ")\n";
    }
}
