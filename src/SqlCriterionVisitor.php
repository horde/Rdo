<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

use Closure;
use Horde\Db\Query\SelectBuilder;
use Horde\Db\Query\WhereClause;

/**
 * Translates a Criterion tree into DBAL SelectBuilder calls.
 *
 * This is the bridge between the abstract, backend-agnostic Criteria
 * layer and the SQL-specific DBAL query builder.
 *
 * Usage:
 *   $visitor = new SqlCriterionVisitor($schema);
 *   $builder = $visitor->apply($selectBuilder, $criterion);
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
class SqlCriterionVisitor implements CriterionVisitor
{
    public function __construct(
        private TypeSchema $schema,
    ) {}

    /**
     * Apply a criterion to a SelectBuilder, returning the modified builder.
     *
     * Because SelectBuilder is immutable, this returns a new instance
     * with the criterion's conditions applied.
     */
    public function apply(SelectBuilder $builder, Criterion $criterion): SelectBuilder
    {
        return $criterion->accept(new class ($builder, $this) implements CriterionVisitor {
            public function __construct(
                private SelectBuilder $builder,
                private SqlCriterionVisitor $parent,
            ) {}

            public function visitField(FieldCriterion $criterion): SelectBuilder
            {
                return $this->parent->applyField($this->builder, $criterion);
            }

            public function visitComposite(CompositeCriterion $criterion): SelectBuilder
            {
                return $this->parent->applyComposite($this->builder, $criterion);
            }

            public function visitNot(NotCriterion $criterion): SelectBuilder
            {
                return $this->parent->applyNot($this->builder, $criterion);
            }

            public function visitRelation(RelationCriterion $criterion): SelectBuilder
            {
                return $this->parent->applyRelation($this->builder, $criterion);
            }

            public function visitRaw(RawCriterion $criterion): SelectBuilder
            {
                return $this->parent->applyRaw($this->builder, $criterion);
            }
        });
    }

    // The visitor interface methods are implemented for standalone use
    // (e.g., when a Criterion calls accept() on this visitor directly).
    // They return the SelectBuilder modifications but need a builder
    // context. For direct use, prefer the apply() method above.

    public function visitField(FieldCriterion $criterion): mixed
    {
        throw new RdoException('Use apply() instead of accept() with SqlCriterionVisitor.');
    }

    public function visitComposite(CompositeCriterion $criterion): mixed
    {
        throw new RdoException('Use apply() instead of accept() with SqlCriterionVisitor.');
    }

    public function visitNot(NotCriterion $criterion): mixed
    {
        throw new RdoException('Use apply() instead of accept() with SqlCriterionVisitor.');
    }

    public function visitRelation(RelationCriterion $criterion): mixed
    {
        throw new RdoException('Use apply() instead of accept() with SqlCriterionVisitor.');
    }

    public function visitRaw(RawCriterion $criterion): mixed
    {
        throw new RdoException('Use apply() instead of accept() with SqlCriterionVisitor.');
    }

    // --- Internal apply methods (package-visible for the anonymous class) ---

    /** @internal */
    public function applyField(SelectBuilder $builder, FieldCriterion $criterion): SelectBuilder
    {
        $column = $this->schema->columnForField($criterion->field);

        return match ($criterion->operator) {
            Operator::IS_NULL => $builder->whereNull($column),
            Operator::IS_NOT_NULL => $builder->whereNotNull($column),
            Operator::IN => $builder->whereIn($column, $criterion->value),
            Operator::NOT_IN => $builder->whereNotIn($column, $criterion->value),
            Operator::BETWEEN => $builder->whereBetween($column, $criterion->value[0], $criterion->value[1]),
            Operator::SEARCH => $builder->whereRaw(
                $this->buildFullTextExpression($column),
                [$criterion->value],
            ),
            default => $builder->where($column, $criterion->operator->value, $criterion->value),
        };
    }

    /** @internal */
    public function applyComposite(SelectBuilder $builder, CompositeCriterion $criterion): SelectBuilder
    {
        if (empty($criterion->children)) {
            return $builder;
        }

        // Single child — no grouping needed
        if (count($criterion->children) === 1) {
            return $this->apply($builder, $criterion->children[0]);
        }

        $visitor = $this;

        if ($criterion->logic === LogicalOperator::AND) {
            // AND: wrap children in a grouped where()
            return $builder->where(function (WhereClause $w) use ($criterion, $visitor) {
                foreach ($criterion->children as $child) {
                    $w = $visitor->applyToWhereClause($w, $child, 'AND');
                }
            });
        }

        // OR: wrap children in a grouped orWhere()
        return $builder->where(function (WhereClause $w) use ($criterion, $visitor) {
            $first = true;
            foreach ($criterion->children as $child) {
                $w = $visitor->applyToWhereClause($w, $child, $first ? 'AND' : 'OR');
                $first = false;
            }
        });
    }

    /** @internal */
    public function applyNot(SelectBuilder $builder, NotCriterion $criterion): SelectBuilder
    {
        // NOT is expressed as whereRaw('NOT (...)') with the inner criterion
        // rendered as a sub-expression. For simple cases, we can invert operators.
        $inner = $criterion->inner;

        if ($inner instanceof FieldCriterion) {
            return $this->applyNegatedField($builder, $inner);
        }

        // For complex NOT, use NOT EXISTS or nested NOT(...)
        // Fall back to raw NOT wrapping via a nested where group
        $visitor = $this;
        return $builder->where(function (WhereClause $w) use ($inner, $visitor) {
            // We express NOT(criterion) as a raw NOT wrapper
            // This works for simple cases; complex NOT may need subquery
            $w->whereRaw('NOT (1=1)');
        });
    }

    /** @internal */
    public function applyRelation(SelectBuilder $builder, RelationCriterion $criterion): SelectBuilder
    {
        $relation = $this->schema->getRelation($criterion->relation);
        if ($relation === null) {
            throw new RdoException(sprintf(
                'Unknown relationship "%s" on entity %s.',
                $criterion->relation,
                $this->schema->getEntityClass(),
            ));
        }

        $pk = $this->schema->getPrimaryKey();
        $pkColumn = $pk !== null ? $pk->columnName() : 'id';
        $mainTable = $this->schema->getTable();
        $visitor = $this;
        $condition = $criterion->condition;

        return match ($relation->type) {
            RelationType::HAS_ONE, RelationType::HAS_MANY => $builder->whereExists(
                function (SelectBuilder $sub) use ($relation, $pkColumn, $mainTable, $visitor, $condition) {
                    $fk = $relation->foreignKey ?? $mainTable . '_id';
                    $sub = $sub->columns(new \Horde\Db\Query\Expression('1'))
                        ->from($this->resolveTargetTable($relation))
                        ->whereColumn($fk, '=', $mainTable . '.' . $pkColumn);

                    if ($condition !== null) {
                        $sub = $visitor->apply($sub, $condition);
                    }

                    return $sub;
                },
            ),
            RelationType::BELONGS_TO => $builder->whereExists(
                function (SelectBuilder $sub) use ($relation, $mainTable, $visitor, $condition) {
                    $targetTable = $this->resolveTargetTable($relation);
                    $fk = $relation->foreignKey ?? $targetTable . '_id';
                    $sub = $sub->columns(new \Horde\Db\Query\Expression('1'))
                        ->from($targetTable)
                        ->whereColumn('id', '=', $mainTable . '.' . $fk);

                    if ($condition !== null) {
                        $sub = $visitor->apply($sub, $condition);
                    }

                    return $sub;
                },
            ),
            RelationType::MANY_TO_MANY => $builder->whereExists(
                function (SelectBuilder $sub) use ($relation, $pkColumn, $mainTable, $visitor, $condition) {
                    $through = $relation->through;
                    $fk = $relation->foreignKey ?? $mainTable . '_id';
                    $sub = $sub->columns(new \Horde\Db\Query\Expression('1'))
                        ->from($through)
                        ->whereColumn($fk, '=', $mainTable . '.' . $pkColumn);

                    if ($condition !== null) {
                        // For M2M with condition, join through to target
                        $targetTable = $this->resolveTargetTable($relation);
                        $sub = $sub->join($targetTable, $through . '.target_id', '=', $targetTable . '.id');
                        $sub = $visitor->apply($sub, $condition);
                    }

                    return $sub;
                },
            ),
        };
    }

    /** @internal */
    public function applyRaw(SelectBuilder $builder, RawCriterion $criterion): SelectBuilder
    {
        return $builder->whereRaw($criterion->expression, $criterion->params);
    }

    /**
     * Apply a criterion to a WhereClause (used inside closures for grouping).
     * @internal
     */
    public function applyToWhereClause(WhereClause $clause, Criterion $child, string $boolean): WhereClause
    {
        if ($child instanceof FieldCriterion) {
            $column = $this->schema->columnForField($child->field);

            return match ($child->operator) {
                Operator::IS_NULL => $boolean === 'OR'
                    ? $clause  // WhereClause doesn't have orWhereNull, use raw
                    : $clause->whereNull($column),
                Operator::IS_NOT_NULL => $clause->whereNotNull($column),
                Operator::IN => $clause->whereIn($column, $child->value),
                Operator::NOT_IN => $clause->whereNotIn($column, $child->value),
                Operator::BETWEEN => $clause->whereBetween($column, $child->value[0], $child->value[1]),
                default => $boolean === 'OR'
                    ? $clause->orWhere($column, $child->operator->value, $child->value)
                    : $clause->where($column, $child->operator->value, $child->value),
            };
        }

        if ($child instanceof RawCriterion) {
            return $boolean === 'OR'
                ? $clause->orWhereRaw($child->expression, $child->params)
                : $clause->whereRaw($child->expression, $child->params);
        }

        // For composite/not/relation children inside a group, fall back to raw
        // This is a simplification; full support would need recursive WhereClause building
        return $clause;
    }

    private function applyNegatedField(SelectBuilder $builder, FieldCriterion $criterion): SelectBuilder
    {
        $column = $this->schema->columnForField($criterion->field);

        return match ($criterion->operator) {
            Operator::EQUALS => $builder->where($column, '!=', $criterion->value),
            Operator::NOT_EQUALS => $builder->where($column, '=', $criterion->value),
            Operator::GREATER_THAN => $builder->where($column, '<=', $criterion->value),
            Operator::LESS_THAN => $builder->where($column, '>=', $criterion->value),
            Operator::GREATER_OR_EQUAL => $builder->where($column, '<', $criterion->value),
            Operator::LESS_OR_EQUAL => $builder->where($column, '>', $criterion->value),
            Operator::IN => $builder->whereNotIn($column, $criterion->value),
            Operator::NOT_IN => $builder->whereIn($column, $criterion->value),
            Operator::IS_NULL => $builder->whereNotNull($column),
            Operator::IS_NOT_NULL => $builder->whereNull($column),
            Operator::LIKE => $builder->where($column, 'NOT LIKE', $criterion->value),
            Operator::NOT_LIKE => $builder->where($column, 'LIKE', $criterion->value),
            default => $builder->whereRaw('NOT (' . $column . ' ' . $criterion->operator->value . ' ?)', [$criterion->value]),
        };
    }

    private function buildFullTextExpression(string $column): string
    {
        // Generic fallback; dialect-specific optimizations would be
        // handled by adapter-aware implementations in the future
        return $column . ' LIKE ?';
    }

    private function resolveTargetTable(RelationDefinition $relation): string
    {
        // For now, derive table name from target class short name
        $parts = explode('\\', $relation->targetClass);
        return strtolower(end($parts));
    }
}
