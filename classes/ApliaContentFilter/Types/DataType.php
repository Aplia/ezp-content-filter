<?php
namespace Aplia\Content\Filter\Types;

class DataType
{
    public $dataType;

    public function __construct($dataType)
    {
        $this->dataType = $dataType;
    }

    public function createColumn($filterInstance, $table, $classAttribute, $language)
    {
    }

    public function createFilter($filterInstance, $column, $value, $op, $pre, $post)
    {
    }
}
