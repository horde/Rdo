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
 * Converts between raw data arrays and entity objects.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
interface Hydrator
{
    /**
     * Create an entity object from a raw data array.
     *
     * @param array      $data   Backend data (column names as keys)
     * @param TypeSchema $schema Entity type schema
     * @return object The hydrated entity
     */
    public function hydrate(array $data, TypeSchema $schema): object;

    /**
     * Extract raw data from an entity object.
     *
     * @param object     $entity The entity to extract from
     * @param TypeSchema $schema Entity type schema
     * @return array Column-keyed data array for persistence
     */
    public function extract(object $entity, TypeSchema $schema): array;
}
