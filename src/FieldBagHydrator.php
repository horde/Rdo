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
 * Hydrator for dynamic field-bag entities.
 *
 * Sets values via ArrayAccess or public property access. No type
 * casting — values pass through as-is from the backend. Intended
 * as a migration path for legacy Rdo entities that use __get/__set
 * and ArrayAccess for field access.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class FieldBagHydrator implements Hydrator
{
    public function hydrate(array $data, TypeSchema $schema): object
    {
        $entityClass = $schema->getEntityClass();
        $entity = new $entityClass();

        foreach ($data as $column => $value) {
            $fieldName = $schema->fieldForColumn($column) ?? $column;

            if ($entity instanceof \ArrayAccess) {
                $entity[$fieldName] = $value;
            } else {
                $entity->$fieldName = $value;
            }
        }

        return $entity;
    }

    public function extract(object $entity, TypeSchema $schema): array
    {
        $data = [];

        foreach ($schema->getFields() as $field) {
            $propName = $field->name;
            $value = null;

            if ($entity instanceof \ArrayAccess && $entity->offsetExists($propName)) {
                $value = $entity[$propName];
            } elseif (isset($entity->$propName)) {
                $value = $entity->$propName;
            }

            $data[$field->columnName()] = $value;
        }

        return $data;
    }
}
