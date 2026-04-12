<?php

declare(strict_types=1);

namespace Horde\Rdo\Test\Unit;

use Horde\Rdo\RdoException;
use Horde\Rdo\Repository;
use Horde\Rdo\TypeRegistry;
use Horde\Rdo\TypeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeRegistry::class)]
class TypeRegistryTest extends TestCase
{
    public function testRegisterAndRetrieve(): void
    {
        $registry = new TypeRegistry();
        $schema = new TypeSchema('App\Entity\Contact', 'contacts');
        $repo = $this->createStub(Repository::class);

        $registry->register($schema, $repo);

        $this->assertSame($schema, $registry->getSchema('App\Entity\Contact'));
        $this->assertSame($repo, $registry->getRepository('App\Entity\Contact'));
    }

    public function testHas(): void
    {
        $registry = new TypeRegistry();
        $schema = new TypeSchema('App\Entity\Contact', 'contacts');
        $repo = $this->createStub(Repository::class);

        $this->assertFalse($registry->has('App\Entity\Contact'));
        $registry->register($schema, $repo);
        $this->assertTrue($registry->has('App\Entity\Contact'));
    }

    public function testGetSchemaThrowsForUnregistered(): void
    {
        $registry = new TypeRegistry();

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/No TypeSchema registered/');

        $registry->getSchema('App\Entity\Unknown');
    }

    public function testGetRepositoryThrowsForUnregistered(): void
    {
        $registry = new TypeRegistry();

        $this->expectException(RdoException::class);
        $this->expectExceptionMessageMatches('/No Repository registered/');

        $registry->getRepository('App\Entity\Unknown');
    }
}
