<?php

declare(strict_types=1);

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @category   Horde
 * @package    Rdo
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Rdo\Test\Sql;

use Horde_Db_Exception;
use Horde_Db_Migration_Base;
use Horde_Rdo;
use Horde_Rdo_Base;
use Horde_Rdo_Exception;
use Horde_Rdo_List;
use Horde\Rdo\Test\Objects\ManyToManyAMapper;
use Horde\Rdo\Test\Objects\ManyToManyBMapper;
use Horde\Rdo\Test\Objects\RelatedThing;
use Horde\Rdo\Test\Objects\SomeEagerBaseObjectMapper;
use Horde\Rdo\Test\Objects\SomeLazyBaseObject;
use Horde\Rdo\Test\Objects\SomeLazyBaseObjectMapper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class Base extends TestCase
{
    protected static $db;
    protected static $EagerBaseObjectMapper;
    protected static $LazyBaseObjectMapper;
    protected static $RelatedThingMapper;
    protected static $MtmaMapper;
    protected static $MtmbMapper;

    public function setUp(): void
    {
        if (!self::$db) {
            $this->markTestSkipped('No sqlite extension or no sqlite PDO driver.');
        }

        self::$LazyBaseObjectMapper = new SomeLazyBaseObjectMapper(self::$db);
        self::$EagerBaseObjectMapper = new SomeEagerBaseObjectMapper(self::$db);
        self::$MtmaMapper = new ManyToManyAMapper(self::$db);
        self::$MtmbMapper = new ManyToManyBMapper(self::$db);
    }

    protected static function _migrate_sql_rdo($db)
    {
        $migration = new Horde_Db_Migration_Base($db);

        /* Cleanup potential left-overs. */
        $currentTables = $migration->tables();
        try {
            if (in_array('test_someeagerbaseobjects', $currentTables)) {
                $migration->dropTable('test_someeagerbaseobjects');
            }
            if (in_array('test_somelazybaseobjects', $currentTables)) {
                $migration->dropTable('test_somelazybaseobjects');
            }
            if (in_array('test_relatedthings', $currentTables)) {
                $migration->dropTable('test_relatedthings');
            }
            if (in_array('test_manytomanya', $currentTables)) {
                $migration->dropTable('test_manytomanya');
            }
            if (in_array('test_manytomanyb', $currentTables)) {
                $migration->dropTable('test_manytomanyb');
            }
            if (in_array('test_manythrough', $currentTables)) {
                $migration->dropTable('test_manythrough');
            }
        } catch (Horde_Db_Exception $e) {
        }

        $t = $migration->createTable('test_someeagerbaseobjects', ['autoincrementKey' => 'baseobject_id']);
        $t->column('relatedthing_id', 'integer');
        $t->column('atextproperty', 'string');
        $t->end();

        $t = $migration->createTable('test_somelazybaseobjects', ['autoincrementKey' => 'baseobject_id']);
        $t->column('relatedthing_id', 'integer');
        $t->column('atextproperty', 'string');
        $t->end();

        $t = $migration->createTable('test_relatedthings', ['autoincrementKey' => 'relatedthing_id']);
        $t->column('relatedthing_textproperty', 'string', ['limit' => 255, 'null' => false]);
        $t->column('relatedthing_intproperty', 'integer', ['null' => false]);
        $t->end();

        $t = $migration->createTable('test_manytomanya', ['autoincrementKey' => 'a_id']);
        $t->column('a_intproperty', 'integer', ['null' => false]);
        $t->end();

        $t = $migration->createTable('test_manytomanyb', ['autoincrementKey' => 'b_id']);
        $t->column('b_intproperty', 'integer', ['null' => false]);
        $t->end();

        $t = $migration->createTable('test_manythrough');
        $t->column('a_id', 'integer');
        $t->column('b_id', 'integer');
        $t->end();

        $migration->migrate('up');
    }

    public static function setUpBeforeClass(): void
    {
        self::_migrate_sql_rdo(self::$db);
        // read sql file for statements
        $statements = [];
        $current_stmt = '';
        $fp = fopen(__DIR__ . '/../fixtures/unit_tests.sql', 'r');
        while ($line = fgets($fp, 8192)) {
            $line = rtrim(preg_replace('/^(.*)--.*$/s', '\1', $line));
            if (!$line) {
                continue;
            }

            $current_stmt .= $line;

            if (substr($line, -1) == ';') {
                // leave off the ending ;
                $statements[] = substr($current_stmt, 0, -1);
                $current_stmt = '';
            }
        }

        // run statements
        foreach ($statements as $stmt) {
            self::$db->execute($stmt);
        }
    }

    public function testListOffsetExistsReturnFalseForTooBigOffset()
    {
        $list = self::$LazyBaseObjectMapper->find();
        $this->assertFalse(isset($list[$list->count()]), "return false for index not in list");
    }

    public function testListOffsetExistsReturnFalseFor0inemptylist()
    {
        $list = self::$LazyBaseObjectMapper->find(5000);
        $this->assertFalse(isset($list[0]), "return false for first index in empty list");
    }

    public function testHasRelationManyToManyAny()
    {
        $objectA = self::$MtmaMapper->findOne(2);
        $this->assertTrue($objectA->hasRelation('manybs'));
    }

    public function testHasRelationManyToManyAnyButEmpty()
    {
        $objectA = self::$MtmaMapper->findOne(1);
        $this->assertFalse($objectA->hasRelation('manybs'));
    }

    public function testHasRelationManyToManyWrongPeer()
    {
        $objectA = self::$MtmaMapper->findOne(2);
        $objectB = self::$MtmbMapper->findOne(11);
        $this->assertFalse($objectA->hasRelation('manybs', $objectB));
    }

    public function testHasRelationManyToManyRightPeer()
    {
        $objectA = self::$MtmaMapper->findOne(2);
        $objectB = self::$MtmbMapper->findOne(12);
        $this->assertTrue($objectA->hasRelation('manybs', $objectB));
    }

    public function testHasRelationOneToOne()
    {
        $objectA1 = self::$LazyBaseObjectMapper->findOne(1);
        $objectA2 = self::$LazyBaseObjectMapper->findOne(4);
        $objectB1 = self::$LazyBaseObjectMapper->findOne(1);

        $this->assertTrue($objectA1->hasRelation('lazyRelatedThing', $objectB1));
        $this->assertFalse($objectA2->hasRelation('lazyRelatedThing', $objectB1));
        $this->assertTrue($objectA1->hasRelation('lazyRelatedThing'));
        $this->assertFalse($objectA2->hasRelation('lazyRelatedThing'));
    }

    public function testAddRelationManyToMany()
    {
        $objectA = self::$MtmaMapper->findOne(1);
        $objectB = self::$MtmbMapper->findOne(11);
        $objectA->addRelation('manybs', $objectB);
        $this->assertTrue($objectA->hasRelation('manybs', $objectB));
    }

    public function testRemoveRelationManyToManyOne()
    {
        $objectA = self::$MtmaMapper->findOne(2);
        $objectB1 = self::$MtmbMapper->findOne(12);
        $objectB2 = self::$MtmbMapper->findOne(14);
        $objectA->removeRelation('manybs', $objectB1);
        $this->assertFalse($objectA->hasRelation('manybs', $objectB1));
        $this->assertTrue($objectA->hasRelation('manybs', $objectB2));
    }

    public function testRemoveRelationManyToManyAll()
    {
        $objectA = self::$MtmaMapper->findOne(2);
        $objectB1 = self::$MtmbMapper->findOne(12);
        $objectB2 = self::$MtmbMapper->findOne(14);
        $objectA->removeRelation('manybs');
        $this->assertFalse($objectA->hasRelation('manybs', $objectB1));
        $this->assertFalse($objectA->hasRelation('manybs', $objectB2));
    }

    public function testListOffsetExistsReturnTrueForFirst()
    {
        $list = self::$LazyBaseObjectMapper->find();
        $this->assertTrue(isset($list[0]), "return true for first index in list");
    }

    public function testListOffsetExistsReturnTrueForLast()
    {
        $list = self::$LazyBaseObjectMapper->find();
        $this->assertTrue(isset($list[$list->count() - 1]), "return true for last index in list");
    }

    public function testListOffsetGetReturnNullForTooBig()
    {
        $list = self::$LazyBaseObjectMapper->find();
        $this->assertNull($list[$list->count()], "return Null for one after last index in list");
    }

    public function testListOffsetGetReturnObjectForLast()
    {
        $list = self::$LazyBaseObjectMapper->find();
        $this->assertInstanceOf(SomeLazyBaseObject::class, $list[$list->count() - 1]);
    }

    public function testListOffsetGetReturnObjectForFirst()
    {
        $list = self::$LazyBaseObjectMapper->find();
        $this->assertInstanceOf(SomeLazyBaseObject::class, $list[0]);
    }

    public function testListOffsetSetThrowException()
    {
        $this->expectException(Horde_Rdo_Exception::class);

        $list = self::$LazyBaseObjectMapper->find();
        $list[0] = $list[0];
    }

    public function testListOffsetUnsetThrowException()
    {
        $this->expectException(Horde_Rdo_Exception::class);

        $list = self::$LazyBaseObjectMapper->find();
        unset($list[0]);
    }

    public function testFindReturnsHordeRdoList()
    {
        $result = self::$LazyBaseObjectMapper->find();
        $this->assertInstanceOf(Horde_Rdo_List::class, $result);
    }

    public function testFindOneReturnsEntity()
    {
        $result = self::$LazyBaseObjectMapper->findOne();
        $this->assertInstanceOf(Horde_Rdo_Base::class, $result);
    }

    public function testFindOneWithScalarReturnsEntityWithKeyValue()
    {
        $result = self::$LazyBaseObjectMapper->findOne(2);
        $this->assertEquals(2, $result->baseobject_id);
    }

    public function testToOneRelationRetrievesEntityWhenKeyIsFound()
    {
        $entity = self::$LazyBaseObjectMapper->findOne(1);
        $this->assertInstanceOf(RelatedThing::class, $entity->lazyRelatedThing);
    }

    public function testToOneRelationRetrievesCorrectEntityWhenKeyIsFound()
    {
        $result = self::$LazyBaseObjectMapper->findOne(1);
        $this->assertEquals(100, $result->lazyRelatedThing->relatedthing_intproperty);
    }

    public function testLazyToOneRelationThrowsExceptionWhenKeyIsNotFound()
    {
        $this->expectException(Horde_Rdo_Exception::class);

        $entity = self::$LazyBaseObjectMapper->findOne(3);
        $entity->lazyRelatedThing;
    }

    public function testLazyToOneRelationReturnsNullWhenKeyIsEmpty()
    {
        $entity = self::$LazyBaseObjectMapper->findOne(4);
        $this->assertNull($entity->lazyRelatedThing);
    }

    public function testObjectWithEagerToOneRelationIsNotLoadedWhenRelatedObjectDoesntExist()
    {
        $entity = self::$EagerBaseObjectMapper->findOne(3);
        $this->assertNull($entity);
    }

    public function testObjectWithEagerToOneRelationIsNotLoadedWhenlWhenKeyIsNull()
    {
        $entity = self::$EagerBaseObjectMapper->findOne(4);
        $this->assertNull($entity);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db) {
            $migration = new Horde_Db_Migration_Base(self::$db);
            $migration->dropTable('test_someeagerbaseobjects');
            $migration->dropTable('test_somelazybaseobjects');
            $migration->dropTable('test_relatedthings');
            $migration->dropTable('test_manytomanya');
            $migration->dropTable('test_manytomanyb');
            $migration->dropTable('test_manythrough');
            self::$db->disconnect();
            self::$db = null;
        }
    }
}
