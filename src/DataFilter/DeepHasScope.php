<?php

namespace Zofe\Rapyd\DataFilter;

use LogicException;

trait DeepHasScope
{
    public function scopeHasRel($query, $value, $relation, $operator = 'LIKE', $value_pattern = '%%%s%%')
    {
        if (null === $value) {
            return $query;
        }
        $relations = explode('.', $relation);
        if ('LIKE' == mb_strtoupper(trim($operator))) {
            $value = sprintf($value_pattern, $value);
        }
        if (\count($relations) < 2) {
            throw new LogicException('Relation param must contain at least 2 elements: "relation.field"' . $relation);
        }

        return $this->recurseRelation($query, $value, $relations, $operator);
    }

    protected function recurseRelation($query, $value, $relations, $operator)
    {
        $field = end($relations);
        if (1 == \count($relations)) {
            return $query->where($field, $operator, $value);
        }

        $rel = array_shift($relations);

        return $query->whereHas($rel, function ($q) use ($value, $relations, $operator) {
            return $this->recurseRelation($q, $value, $relations, $operator);
        });
    }
}
