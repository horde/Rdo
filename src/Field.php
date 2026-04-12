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
 * Named constructors for field-level criteria.
 *
 * Usage:
 *   Field::equals('status', 'active')
 *   Field::in('role', ['admin', 'editor'])
 *   Field::between('age', 18, 65)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
final class Field
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    public static function equals(string $field, mixed $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::EQUALS, $value);
    }

    public static function notEquals(string $field, mixed $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::NOT_EQUALS, $value);
    }

    public static function greaterThan(string $field, mixed $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::GREATER_THAN, $value);
    }

    public static function lessThan(string $field, mixed $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::LESS_THAN, $value);
    }

    public static function greaterOrEqual(string $field, mixed $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::GREATER_OR_EQUAL, $value);
    }

    public static function lessOrEqual(string $field, mixed $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::LESS_OR_EQUAL, $value);
    }

    /**
     * @param mixed[] $values
     */
    public static function in(string $field, array $values): FieldCriterion
    {
        return new FieldCriterion($field, Operator::IN, $values);
    }

    /**
     * @param mixed[] $values
     */
    public static function notIn(string $field, array $values): FieldCriterion
    {
        return new FieldCriterion($field, Operator::NOT_IN, $values);
    }

    public static function isNull(string $field): FieldCriterion
    {
        return new FieldCriterion($field, Operator::IS_NULL, null);
    }

    public static function isNotNull(string $field): FieldCriterion
    {
        return new FieldCriterion($field, Operator::IS_NOT_NULL, null);
    }

    public static function contains(string $field, string $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::LIKE, '%' . $value . '%');
    }

    public static function startsWith(string $field, string $value): FieldCriterion
    {
        return new FieldCriterion($field, Operator::LIKE, $value . '%');
    }

    public static function between(string $field, mixed $low, mixed $high): FieldCriterion
    {
        return new FieldCriterion($field, Operator::BETWEEN, [$low, $high]);
    }

    public static function search(string $field, string $terms): FieldCriterion
    {
        return new FieldCriterion($field, Operator::SEARCH, $terms);
    }
}
