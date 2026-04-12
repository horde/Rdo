<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

use Horde\Db\Adapter;
use Horde\Db\Query\Expression;
use Horde\Db\Query\SelectBuilder;

/**
 * SQL-backed Repository implementation.
 *
 * Connects the Criteria layer to the DBAL query builder and uses the
 * Hydrator to convert between database rows and entity objects.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
class SqlRepository implements Repository
{
    private SqlCriterionVisitor $visitor;

    public function __construct(
        private Adapter $adapter,
        private TypeSchema $schema,
        private Hydrator $hydrator,
    ) {
        $this->visitor = new SqlCriterionVisitor($schema);
    }

    public function find(Criterion|CriteriaBuilder $criteria): iterable
    {
        $builder = $this->buildSelect($criteria);
        $query = $builder->build();
        $rows = $this->adapter->selectAll($query->sql, $query->params);

        return array_map(
            fn(array $row) => $this->hydrator->hydrate($row, $this->schema),
            $rows,
        );
    }

    public function findOne(mixed $id): ?object
    {
        $pk = $this->schema->getPrimaryKey();
        if ($pk === null) {
            throw new RdoException('TypeSchema has no primary key defined.');
        }

        $criterion = Field::equals($pk->name, $id);
        $builder = $this->buildSelect(
            CriteriaBuilder::from($criterion)->limit(1),
        );

        $query = $builder->build();
        $row = $this->adapter->selectOne($query->sql, $query->params);

        if ($row === false || $row === null) {
            return null;
        }

        return $this->hydrator->hydrate($row, $this->schema);
    }

    public function count(Criterion|CriteriaBuilder $criteria): int
    {
        $criterion = $criteria instanceof CriteriaBuilder
            ? $criteria->criterion()
            : $criteria;

        $builder = new SelectBuilder($this->adapter);
        $builder = $builder
            ->columns(new Expression('COUNT(*) AS cnt'))
            ->from($this->schema->getTable());

        $builder = $this->visitor->apply($builder, $criterion);

        $query = $builder->build();
        $row = $this->adapter->selectOne($query->sql, $query->params);

        return (int) ($row['cnt'] ?? 0);
    }

    public function exists(mixed $id): bool
    {
        $pk = $this->schema->getPrimaryKey();
        if ($pk === null) {
            throw new RdoException('TypeSchema has no primary key defined.');
        }

        return $this->count(Field::equals($pk->name, $id)) > 0;
    }

    public function save(object $entity): void
    {
        $data = $this->hydrator->extract($entity, $this->schema);
        $pk = $this->schema->getPrimaryKey();
        $table = $this->schema->getTable();

        if ($this->schema->hasTimestamps()) {
            $now = gmdate('Y-m-d H:i:s');
            $data['updated_at'] = $now;
        }

        // Determine insert vs update
        $isNew = true;
        if ($pk !== null) {
            $pkColumn = $pk->columnName();
            $pkValue = $data[$pkColumn] ?? null;
            if ($pkValue !== null) {
                $isNew = !$this->exists($pkValue);
            }
        }

        if ($isNew) {
            if ($this->schema->hasTimestamps() && !isset($data['created_at'])) {
                $data['created_at'] = gmdate('Y-m-d H:i:s');
            }
            $this->adapter->insertBlob(
                $table,
                $data,
                null,
                null,
            );
        } else {
            $pkColumn = $pk->columnName();
            $pkValue = $data[$pkColumn];
            unset($data[$pkColumn]);
            $this->adapter->update(
                sprintf(
                    'UPDATE %s SET %s WHERE %s = ?',
                    $this->adapter->quoteTableName($table),
                    implode(', ', array_map(
                        fn(string $col) => $this->adapter->quoteColumnName($col) . ' = ?',
                        array_keys($data),
                    )),
                    $this->adapter->quoteColumnName($pkColumn),
                ),
                [...array_values($data), $pkValue],
            );
        }
    }

    public function delete(object $entity): void
    {
        $data = $this->hydrator->extract($entity, $this->schema);
        $pk = $this->schema->getPrimaryKey();
        $table = $this->schema->getTable();

        if ($pk === null) {
            throw new RdoException('Cannot delete entity without a primary key.');
        }

        $pkColumn = $pk->columnName();
        $pkValue = $data[$pkColumn] ?? null;

        if ($pkValue === null) {
            throw new RdoException('Cannot delete entity with null primary key.');
        }

        $this->adapter->delete(
            sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $this->adapter->quoteTableName($table),
                $this->adapter->quoteColumnName($pkColumn),
            ),
            [$pkValue],
        );
    }

    public function findRaw(mixed $backendQuery, array $params = []): iterable
    {
        $rows = $this->adapter->selectAll($backendQuery, $params);

        return array_map(
            fn(array $row) => $this->hydrator->hydrate($row, $this->schema),
            $rows,
        );
    }

    private function buildSelect(Criterion|CriteriaBuilder $criteria): SelectBuilder
    {
        $criterion = $criteria instanceof CriteriaBuilder
            ? $criteria->criterion()
            : $criteria;

        // Build column list from TypeSchema eager fields
        $columns = [];
        foreach ($this->schema->getEagerFields() as $field) {
            $columns[] = $field->columnName();
        }

        $builder = new SelectBuilder($this->adapter);
        $builder = $builder->from($this->schema->getTable());
        if (!empty($columns)) {
            $builder = $builder->columns(...$columns);
        }

        // Apply criterion
        $builder = $this->visitor->apply($builder, $criterion);

        // Apply modifiers from CriteriaBuilder
        if ($criteria instanceof CriteriaBuilder) {
            foreach ($criteria->orderByList() as [$field, $direction]) {
                $column = $this->schema->columnForField($field);
                $builder = $builder->addOrderBy($column, $direction === Direction::DESC ? 'DESC' : 'ASC');
            }

            if ($criteria->getLimit() !== null) {
                $builder = $builder->limit($criteria->getLimit());
            }

            if ($criteria->getOffset() !== null) {
                $builder = $builder->offset($criteria->getOffset());
            }
        }

        return $builder;
    }
}
