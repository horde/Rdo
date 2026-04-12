<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

/**
 * Wraps a Criterion with query modifiers: ordering, pagination, and
 * relationship eager-loading hints.
 *
 * Immutable — each modifier method returns a new instance.
 *
 * Usage:
 *   $query = CriteriaBuilder::from(Field::equals('status', 'active'))
 *       ->orderBy('name')
 *       ->limit(20)
 *       ->offset(40)
 *       ->with('company', 'groups');
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class CriteriaBuilder
{
    /** @var array{string, Direction}[] */
    private array $orderBy;

    /** @var string[] */
    private array $with;

    private function __construct(
        private Criterion $criterion,
        array $orderBy = [],
        private ?int $limit = null,
        private ?int $offset = null,
        array $with = [],
    ) {
        $this->orderBy = $orderBy;
        $this->with = $with;
    }

    public static function from(Criterion $criterion): self
    {
        return new self($criterion);
    }

    public function orderBy(string $field, Direction $direction = Direction::ASC): self
    {
        $new = clone $this;
        $new->orderBy[] = [$field, $direction];
        return $new;
    }

    public function limit(int $limit): self
    {
        $new = clone $this;
        $new->limit = $limit;
        return $new;
    }

    public function offset(int $offset): self
    {
        $new = clone $this;
        $new->offset = $offset;
        return $new;
    }

    public function with(string ...$relations): self
    {
        $new = clone $this;
        $new->with = array_values(array_unique(array_merge($this->with, $relations)));
        return $new;
    }

    public function criterion(): Criterion
    {
        return $this->criterion;
    }

    /**
     * @return array{string, Direction}[]
     */
    public function orderByList(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @return string[]
     */
    public function eagerRelations(): array
    {
        return $this->with;
    }
}
