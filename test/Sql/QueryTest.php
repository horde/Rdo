<?php

declare(strict_types=1);

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @package    Rdo
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Rdo\Test\Sql;

use Horde_Db_Migration_Base;
use Horde_Rdo_Query;
use Horde_Test_Factory_Db;
use Horde\Rdo\Test\Objects\SimpleMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Rdo_Query::class)]
class QueryTest extends TestCase
{
    protected $db;
    protected $mapper;

    public function setUp(): void
    {
        $factory_db = new Horde_Test_Factory_Db();
        $this->db = $factory_db->create();
        $this->mapper = new SimpleMapper($this->db);
        $migration = new Horde_Db_Migration_Base($this->db);

        $currentTables = $migration->tables();
        if (in_array('horde_rdo_test', $currentTables)) {
            $migration->dropTable('horde_rdo_test');
        }

        $t = $migration->createTable(
            'horde_rdo_test',
            ['autoincrementKey' => 'id']
        );
        $t->column('intprop', 'integer');
        $t->column('textprop', 'string');
        $t->end();
        $migration->migrate('up');
    }

    public function testConstructor()
    {
        $query = new Horde_Rdo_Query();
        $this->assertNull($query->mapper);
        $query = new Horde_Rdo_Query($this->mapper);
        $this->assertSame($this->mapper, $query->mapper);
    }

    public function testCreate()
    {
        $query1 = new Horde_Rdo_Query();
        $query2 = Horde_Rdo_Query::create($query1);
        $this->assertInstanceOf(Horde_Rdo_Query::class, $query2);
        $this->assertNotSame($query1, $query2);

        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $this->assertInstanceOf(Horde_Rdo_Query::class, $query);
        $this->assertEquals(
            [
                [
                    'field' => $this->mapper->tableDefinition->getPrimaryKey(),
                    'test' => '=',
                    'value' => 4,
                ],
            ],
            $query->tests
        );

        $query = Horde_Rdo_Query::create(
            ['textprop' => 'bar', 'intprop' => 2],
            $this->mapper
        );
        $this->assertInstanceOf(Horde_Rdo_Query::class, $query);
        $this->assertEquals(
            [
                ['field' => 'textprop', 'test' => '=', 'value' => 'bar'],
                ['field' => 'intprop', 'test' => '=', 'value' => 2],
            ],
            $query->tests
        );
        $this->assertEquals(
            [
                'horde_rdo_test.id',
                'horde_rdo_test.intprop',
                'horde_rdo_test.textprop',
            ],
            $query->fields
        );
        $this->assertEquals('AND', $query->conjunction);
    }

    public function testGetQuery()
    {
        $query = Horde_Rdo_Query::create(
            ['textprop' => 'bar', 'intprop' => 2],
            $this->mapper
        );
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."textprop" = ? AND horde_rdo_test."intprop" = ?',
                ['bar', 2],
            ],
            $query->getQuery()
        );
    }

    public function testDistinct()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query->distinct(true);
        $this->assertEquals(
            [
                'SELECT DISTINCT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ?',
                [4],
            ],
            $query->getQuery()
        );
    }

    public function testSetFields()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query1 = $query->setFields(['intprop']);
        $this->assertSame($query, $query1);
        $this->assertEquals(
            [
                'SELECT intprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ?',
                [4],
            ],
            $query->getQuery()
        );

        $query->setFields(['intprop'], 'prefix.');
        $this->assertEquals(
            [
                'SELECT prefix.intprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ?',
                [4],
            ],
            $query->getQuery()
        );
    }

    public function testAddFields()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query->setFields(['intprop']);
        $query->addFields(['id']);
        $this->assertEquals(
            [
                'SELECT intprop, id FROM horde_rdo_test WHERE horde_rdo_test."id" = ?',
                [4],
            ],
            $query->getQuery()
        );

        $query->addFields(['textprop'], 'prefix.');
        $this->assertEquals(
            [
                'SELECT intprop, id, prefix.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ?',
                [4],
            ],
            $query->getQuery()
        );
    }

    public function testCombineWith()
    {
        $query = Horde_Rdo_Query::create(
            ['textprop' => 'bar', 'intprop' => 2],
            $this->mapper
        );
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."textprop" = ? AND horde_rdo_test."intprop" = ?',
                ['bar', 2],
            ],
            $query->getQuery()
        );
        $query->combineWith('OR');
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."textprop" = ? OR horde_rdo_test."intprop" = ?',
                ['bar', 2],
            ],
            $query->getQuery()
        );
    }

    public function testSortBy()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query->sortBy('intprop');
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ? ORDER BY intprop',
                [4],
            ],
            $query->getQuery()
        );
        $query->sortBy('textprop');
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ? ORDER BY intprop, textprop',
                [4],
            ],
            $query->getQuery()
        );
    }

    public function testSortByProperty()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query->sortBy('intprop');
        $this->assertEquals(['intprop'], $query->sortby);

        $this->mapper->sortBy('textprop');
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $this->assertEquals(['textprop'], $query->sortby);
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ? ORDER BY textprop',
                [4],
            ],
            $query->getQuery()
        );
    }

    public function testClearSort()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query->sortBy('intprop');
        $query->clearSort();
        $query->sortBy('textprop');
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ? ORDER BY textprop',
                [4],
            ],
            $query->getQuery()
        );
    }

    public function testLimit()
    {
        $query = Horde_Rdo_Query::create(4, $this->mapper);
        $query->limit(10);
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ? LIMIT 10',
                [4],
            ],
            $query->getQuery()
        );
        $query->limit(10, 20);
        $this->assertEquals(
            [
                'SELECT horde_rdo_test.id, horde_rdo_test.intprop, horde_rdo_test.textprop FROM horde_rdo_test WHERE horde_rdo_test."id" = ? LIMIT 20, 10',
                [4],
            ],
            $query->getQuery()
        );
    }
}
