<?php

declare(strict_types=1);

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @category   Horde
 * @package    Rdo
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Rdo\Test\Sql\Pdo;

use Horde\Rdo\Test\Sql\Base;
use Horde_Test_Exception;
use Horde_Test_Factory_Db;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
class SqliteTest extends Base
{
    public static function setUpBeforeClass(): void
    {
        $factory_db = new Horde_Test_Factory_Db();

        try {
            self::$db = $factory_db->create();
            parent::setUpBeforeClass();
        } catch (Horde_Test_Exception $e) {
        }
    }
}
