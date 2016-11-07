<?php
namespace Aplia\Content\Filter;

class ObjectFilter
{
    public function createColumn($nested, $field)
    {
        if ($field == 'class_name') {
            $classNameFilter = \eZContentClassName::sqlFilter();
            // $filterSQL['from'] .= " INNER JOIN $classNameFilter[from] ON ($classNameFilter[where])";
            return array(
                'id' => 'class_name',
                'name' => $field,
                'tbl' => $classNameFilter['from'],
                'joins' => array(
                    array(
                        'type' => 'inner',
                        'tbl' => $classNameFilter['from'],
                        'condSql' => $classNameFilter['where'],
                    ),
                ),
            );
        }
    }

    public function createFilter($nested, $field, $colId, $value, $op, $pre, $post)
    {
        if ($field == 'path') {
            $dbField = 'ezcontentobject_tree.path_string';
        } else if ($field == 'published') {
            $dbField = 'ezcontentobject.published';
        } else if ($field == 'modified') {
            $dbField = 'ezcontentobject.modified';
        } else if ($field == 'modified_subnode') {
            $dbField = 'modified_subnode';
        } else if ($field == 'node_id') {
            $dbField = 'ezcontentobject_tree.node_id';
        } else if ($field == 'contentobject_id') {
            $dbField = 'ezcontentobject_tree.contentobject_id';
        } else if ($field == 'section') {
            $dbField = 'ezcontentobject.section_id';
        } else if ($field == 'state') {
            if (!in_array($op, array('=', '!=', 'in', 'not_in'))) {
                throw new \Exception("Unsupported filter operator for field '$field'");
            }
            $dbField = 'contentobject_state_id';
        } else if ($field == 'depth') {
            $dbField = 'depth';
        } else if ($field == 'class_identifier') {
            $dbField = 'ezcontentclass.identifier';
        } else if ($field == 'class_name') {
            $classNameFilter = \eZContentClassName::sqlFilter();
            $dbField = $classNameFilter['nameField'];
        } else if ($field == 'priority') {
            $dbField = 'ezcontentobject_tree.priority';
        } else if ($field == 'name') {
            $dbField = 'ezcontentobject_name.name';
        } else if ($field == 'owner') {
            $dbField = 'ezcontentobject.owner_id';
        } else if ($field == 'visibility') {
            $value = ( $value == '1' ) ? 0 : 1;
            $dbField = 'ezcontentobject_tree.is_invisible';
        } else if ($field == 'path_element') {
            $dbField = 'ezcontentobject_tree.path_string';
            if (!in_array($op, array('=', '!=', 'in', 'not_in'))) {
                throw new \Exception("Unsupported filter operator for field '$field'");
            }
            if (is_array($value)) {
                $values = $value;
            } else {
                $values = array($value);
            }
            $conds = array();
            if ($op == '=' || $op == 'in') {
                $pathOp = 'like';
            } else if ($op == '!=' || $op == 'not_in') {
                $pathOp = 'not_like';
            }
            foreach ($values as $nodeId) {
                $conds[] = $nested->createFilterCond($dbField, "*/" . $nodeId . "/*", $pathOp, $pre, $post);
            }
            return array(
                'operator' => 'or',
                'conds' => $conds,
            );
        }
        return array($nested->createFilterCond($dbField, $value, $op, $pre, $post));
    }
}
