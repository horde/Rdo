<?php
/**
 * Prepare the test setup.
 */
namespace Horde\Rdo\Sql;
use \Pdo;
use \Horde_Test_Factory_Db;
use \Horde_Db_Migration_Base;
use \Horde_Test_Exception;

require_once __DIR__ . '/../Base.php';

/**
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Ralf Lang <lang@b1-systems.de>
 * @category   Horde
 * @package    Rdo
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqliteTest extends Base
{
    public static function setUpBeforeClass(): void
    {
        $factory_db = new Horde_Test_Factory_Db();

        try {
            self::$db = $factory_db->create();
            parent::setUpBeforeClass();
        } catch (Horde_Test_Exception $e) {}
    }
}
