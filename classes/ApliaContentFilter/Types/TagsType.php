<?php
namespace ApliaContentFilter\Types;

class TagsType extends DataType
{
    public static function isSupported($dataType)
    {
        return $dataType == 'eztags';
    }

    public function createColumn($filterInstance, $table, $classAttribute, $language)
    {
        $classAttributeId = $classAttribute->attribute('id');
        // Tables used:
        // ${table}: eztags
        // ${table}_l: eztags_attribute_link
        // ${table}_k: eztags_keyword
        return array(
            'joins' => array(
                array(
                    'type' => 'left',
                    'tbl' => "ezcontentobject_attribute ${table}_a",
                    'conds' => array(
                        array("${table}_a.contentobject_id", "ezcontentobject.id"),
                        array("${table}_a.version", "ezcontentobject.current_version"),
                        array("${table}_a.contentclassattribute_id", "$classAttributeId"),
                    ),
                ),
                array(
                    'type' => 'left',
                    'tbl' => "eztags_attribute_link ${table}_l",
                    'conds' => array(
                        array("${table}_l.object_id", "ezcontentobject.id"),
                        array("${table}_l.objectattribute_version", "ezcontentobject.current_version"),
                        array("${table}_l.objectattribute_id", "${table}_a.id"),
                    ),
                ),
                array(
                    'type' => 'left',
                    'tbl' => "eztags ${table}",
                    'conds' => array(
                        array("${table}_l.keyword_id", "${table}.id"),
                    ),
                ),
                array(
                    'type' => 'left',
                    'tbl' => "eztags_keyword ${table}_k",
                    'conds' => array(
                        array("${table}.id", "${table}_k.keyword_id"),
                    ),
                ),
            ),
        );
    }

    public function createFilter($filterInstance, $column, $value, $op, $pre, $post)
    {
        $table = $column['tbl'];
        $fieldName = 'keyword_id';
        $field = ($table ? "${table}_k." : "" ) . $fieldName;
        return $filterInstance->createFilterCond($field, $value, $op, $pre, $post);
    }
}
