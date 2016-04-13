<?php
namespace ApliaContentFilter\Types;

class RegularType extends DataType
{
    public static function isSupported($dataType)
    {
        return true;
    }

    public function createColumn($filterInstance, $table, $classAttribute, $language)
    {
        if ($language) {
            \eZContentLanguage::setPrioritizedLanguages($language);
        }
        $langCond = \eZContentLanguage::sqlFilter($table, 'ezcontentobject' );
        if ($language) {
            \eZContentLanguage::clearPrioritizedLanguages();
        }
        $classAttributeId = $classAttribute->attribute('id');
        return [
            'joins' => [
                [
                    'type' => 'left',
                    'tbl' => "ezcontentobject_attribute $table",
                    'conds' => [
                        ["$table.contentobject_id", "ezcontentobject.id"],
                        ["$table.contentclassattribute_id", "$classAttributeId"],
                        ["$table.version", "ezcontentobject_name.content_version"],
                    ],
                ],
            ],
            'conds' => [$langCond],
        ];
    }

    public function createFilter($filterInstance, $column, $value, $op, $pre, $post)
    {
        $table = $column['tbl'];
        $sortType = \eZContentObjectTreeNode::sortKeyByClassAttributeID($id);
        if ($sortType =='string') {
            $fieldName = 'sort_key_string';
        } else {
            $fieldName = 'sort_key_int';
        }
        $field = ($table ? "$table." : "" ) . $fieldName;
        return $filterInstance->createFilterCond($field, $value, $op, $pre, $post);
    }
}
