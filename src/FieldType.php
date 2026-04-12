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
 * Data types for entity field definitions.
 *
 * Used by TypeSchema and the Hydrator to cast values between
 * backend storage format and PHP types.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
enum FieldType
{
    case STRING;
    case INT;
    case FLOAT;
    case BOOL;
    case DATETIME;
    case TEXT;
    case BINARY;
    case JSON;
    case SERIALIZED;
}
