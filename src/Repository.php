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
 * Repository interface for entity persistence.
 *
 * The consumer-facing API for finding, saving, and deleting entities.
 * Backend-specific implementations (SQL, LDAP, etc.) handle the
 * actual data access.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
interface Repository
{
    /**
     * Find entities matching the given criteria.
     *
     * @return iterable<object>
     */
    public function find(Criterion|CriteriaBuilder $criteria): iterable;

    /**
     * Find a single entity by primary key.
     */
    public function findOne(mixed $id): ?object;

    /**
     * Count entities matching the given criteria.
     */
    public function count(Criterion|CriteriaBuilder $criteria): int;

    /**
     * Check whether an entity with the given primary key exists.
     */
    public function exists(mixed $id): bool;

    /**
     * Persist an entity (insert or update).
     */
    public function save(object $entity): void;

    /**
     * Delete an entity.
     */
    public function delete(object $entity): void;

    /**
     * Execute a raw backend query and hydrate the results.
     *
     * Escape hatch for queries that can't be expressed through Criteria.
     *
     * @param mixed $backendQuery Backend-native query (e.g., SQL string)
     * @param array $params       Bind parameters
     * @return iterable<object>
     */
    public function findRaw(mixed $backendQuery, array $params = []): iterable;
}
