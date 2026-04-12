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
 * Comparison operators for field-level criteria.
 *
 * Each case carries the SQL operator string as its backed value.
 * Non-SQL backends map from the enum case, not the string value.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
enum Operator: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_OR_EQUAL = '>=';
    case LESS_OR_EQUAL = '<=';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case IS_NULL = 'IS NULL';
    case IS_NOT_NULL = 'IS NOT NULL';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case BETWEEN = 'BETWEEN';
    case SEARCH = 'SEARCH';
}
