<?php
namespace Aplia\Content\Filter;

class NestedFilterSet
{
    static public $joinTypes = array(
        'inner' => 'INNER JOIN',
        'cross' => 'CROSS JOIN',
        'left' => 'LEFT OUTER JOIN',
        'right' => 'RIGHT OUTER JOIN',
    );

    static public function makeExtendedFilter($params)
    {
        /*
        [
            [
                ['news_article/tags', 'tef/tags'], [5,2], 'in',
            ],
            [
                'cond' => 'OR',
                'attrs' => [
                    'news_article/tags' => [5,2],
                    'teft_tags' => [5,2],
                ]
            ],
            ['call' => ['\Aplia\ContentFilter\NestedFilter','makeCustom']],
        ]
        */
        if ($params instanceof NestedFilter) {
            $filter = $params;
        } else {
            if ($params === null) {
                $params = array();
            }
            if (!is_array($params)) {
                throw new \Exception("Parameters must be an array");
            }

            $filter = new NestedFilter();
            $filter->process($params);
        }

        $joins = array();
        $conds = array();

        foreach ($filter->columns as $column) {
            if (!isset($column['joins']) && !isset($column['legacy']['tables'])) {
                throw new \Exception("No joins/tables defined for column " . $column['name']);
            }
            if (isset($column['joins'])) {
                foreach ($column['joins'] as $join) {
                    $joinSql = "\n" . self::$joinTypes[$join['type']];
                    $joinSql .= " " . $join['tbl'] . " ON ";
                    if (isset($join['conds'])) {
                        $joinSql .= " ( " .implode(" AND ", array_map(function ($joinCond) {
                            return $joinCond[0] . '=' . $joinCond[1];
                        }, $join['conds'])) . " ) \n";
                    } else if (isset($join['condSql'])) {
                        $joinSql .= " ( " . $join['condSql'] . " ) \n";
                    }
                    $joins[] = $joinSql;
                }
            } else if (isset($column['legacy']['tables'])) {
                $joins[] = $column['legacy']['tables'];
            }
        }
        foreach ($filter->columns as $column) {
            if (isset($column['conds'])) {
                $conds = array_merge($conds, $column['conds']);
            }
        }
        if ($filter->cond) {
            $conds[] = $filter->cond;
        }

        $tableSql = implode(" ", array_unique($joins));
        $condSql = self::buildSqlCondition($conds, 'AND');
        // Support legacy joins, will already include the AND
        foreach ($filter->columns as $column) {
            if (isset($column['legacy']['joins'])) {
                $condSql .= $column['legacy']['joins'];
            }
        }
        return array('tables' => $tableSql, 'joins' => $condSql);
    }

    public static function buildSqlCondition($conds, $op)
    {
        if ($conds) {
            return " ( " . implode(" $op ", $conds) . " ) AND \n";
        } else {
            return '';
        }
    }
}