<?php
namespace ApliaContentFilter\Types;

class RelationType extends DataType
{
    public static function isSupported($dataType)
    {
        return $dataType == 'ezobjectrelation' || $dataType == 'ezobjectrelationlist';
    }

    public function createColumn($filterInstance, $table, $classAttribute, $language)
    {
        $classAttributeId = $classAttribute->attribute('id');
        return [
            'joins' => [
                [
                    'type' => 'left',
                    'tbl' => "ezcontentobject_link $table",
                    'conds' => [
                        ["$table.from_contentobject_id", "ezcontentobject.id"],
                        ["$table.from_contentobject_version", "ezcontentobject.current_version"],
                        ["$table.contentclassattribute_id", "$classAttributeId"],
                    ],
                ],
            ],
        ];
    }

    public function createFilter($filterInstance, $column, $value, $op, $pre, $post)
    {
        $table = $column['tbl'];
        $fieldName = 'to_contentobject_id';
        $field = ($table ? "$table." : "" ) . $fieldName;
        return $filterInstance->createFilterCond($field, $value, $op, $pre, $post);
    }
}
