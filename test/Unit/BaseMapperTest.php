<?php

namespace Horde\Rdo\Unit\Test;
use \Horde_Db_Adapter;
use Horde\Test\TestCase;

use Horde\Rdo\BaseMapper;

class AbstractClassBaseMapperTest extends TestCase 
{
    public function testtest(){
        $this->i = 'test';
        $this->j = 'test';
        $this->assertSame($this->i,$this->j);
    }

    public function test__construct(){
        $this->base=$this->createMock(BaseMapper::class);
        $this->a = $this->createMock(Horde_Db_Adapter::class);
        $this->assertNull($this->base->__construct($this->a));
       
    }

    public function testsetFactory(){
        $this->base=$this->createMock(BaseMapper::class);

        $test=$this->getMockBuilder('Factory')->getMock();
        $test->expects($this->once())    
             ->method('setFactory')       
             ->willReturn(TRUE);
        $this->assertTrue($this->base->setFactory($test));
    }
   

}
