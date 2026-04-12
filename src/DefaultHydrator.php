<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

use DateTimeImmutable;
use ReflectionClass;
use ReflectionProperty;

/**
 * Reflection-based hydrator for typed entity classes.
 *
 * Uses constructor promotion for readonly properties and direct
 * property setting otherwise. Handles FieldType casting between
 * database string representations and PHP types.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class DefaultHydrator implements Hydrator
{
    public function hydrate(array $data, TypeSchema $schema): object
    {
        $entityClass = $schema->getEntityClass();
        $ref = new ReflectionClass($entityClass);
        $constructor = $ref->getConstructor();
        $constructorParams = [];

        // Map column-keyed data to field-keyed data
        $fieldData = [];
        foreach ($data as $column => $value) {
            $fieldName = $schema->fieldForColumn($column);
            if ($fieldName !== null) {
                $fieldData[$fieldName] = $this->castFromBackend($value, $schema->getField($fieldName));
            } else {
                $fieldData[$column] = $value;
            }
        }

        // If the constructor takes parameters, fill them from field data
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $fieldData)) {
                    $constructorParams[$name] = $fieldData[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $constructorParams[$name] = $param->getDefaultValue();
                } else {
                    $constructorParams[$name] = null;
                }
            }
        }

        $entity = $ref->newInstanceArgs($constructorParams);

        // Set any remaining fields not covered by the constructor
        foreach ($fieldData as $name => $value) {
            if (array_key_exists($name, $constructorParams)) {
                continue;
            }
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                if ($prop->isReadOnly() && $prop->isInitialized($entity)) {
                    continue;
                }
                $prop->setValue($entity, $value);
            }
        }

        return $entity;
    }

    public function extract(object $entity, TypeSchema $schema): array
    {
        $ref = new ReflectionClass($entity);
        $data = [];

        foreach ($schema->getFields() as $field) {
            $propName = $field->name;
            if (!$ref->hasProperty($propName)) {
                continue;
            }

            $prop = $ref->getProperty($propName);
            if (!$prop->isInitialized($entity)) {
                continue;
            }

            $value = $prop->getValue($entity);
            $data[$field->columnName()] = $this->castToBackend($value, $field);
        }

        return $data;
    }

    private function castFromBackend(mixed $value, ?FieldDefinition $field): mixed
    {
        if ($value === null || $field === null) {
            return $value;
        }

        return match ($field->type) {
            FieldType::INT => is_numeric($value) ? (int) $value : $value,
            FieldType::FLOAT => is_numeric($value) ? (float) $value : $value,
            FieldType::BOOL => (bool) $value,
            FieldType::DATETIME => is_string($value) ? new DateTimeImmutable($value) : $value,
            FieldType::JSON => is_string($value) ? json_decode($value, true) : $value,
            FieldType::SERIALIZED => is_string($value) ? unserialize($value) : $value,
            default => $value,
        };
    }

    private function castToBackend(mixed $value, FieldDefinition $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            FieldType::BOOL => $value ? 1 : 0,
            FieldType::DATETIME => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value,
            FieldType::JSON => is_array($value) || is_object($value)
                ? json_encode($value)
                : $value,
            FieldType::SERIALIZED => serialize($value),
            default => $value,
        };
    }
}
