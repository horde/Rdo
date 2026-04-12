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
 * Central registry mapping entity classes to their schemas and repositories.
 *
 * Populated at application boot (typically by the Horde_Injector).
 * Replaces Horde_Rdo_Factory as the coordination point.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class TypeRegistry
{
    /** @var array<class-string, TypeSchema> */
    private array $schemas = [];

    /** @var array<class-string, Repository> */
    private array $repositories = [];

    public function register(TypeSchema $schema, Repository $repository): void
    {
        $class = $schema->getEntityClass();
        $this->schemas[$class] = $schema;
        $this->repositories[$class] = $repository;
    }

    /**
     * @param class-string $entityClass
     * @throws RdoException If the entity class is not registered
     */
    public function getSchema(string $entityClass): TypeSchema
    {
        if (!isset($this->schemas[$entityClass])) {
            throw new RdoException(sprintf(
                'No TypeSchema registered for entity class %s.',
                $entityClass,
            ));
        }

        return $this->schemas[$entityClass];
    }

    /**
     * @param class-string $entityClass
     * @throws RdoException If the entity class is not registered
     */
    public function getRepository(string $entityClass): Repository
    {
        if (!isset($this->repositories[$entityClass])) {
            throw new RdoException(sprintf(
                'No Repository registered for entity class %s.',
                $entityClass,
            ));
        }

        return $this->repositories[$entityClass];
    }

    /**
     * Check whether an entity class has been registered.
     *
     * @param class-string $entityClass
     */
    public function has(string $entityClass): bool
    {
        return isset($this->schemas[$entityClass]);
    }
}
