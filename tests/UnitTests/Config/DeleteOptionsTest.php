<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\UnitTests\Config;

use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use PHPUnit\Framework\TestCase;

class DeleteOptionsTest extends TestCase
{
    public function testStaticCreate()
    {
        $mappingCollection = $this->getMockBuilder(MappingCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mappingCollection->expects($this->once())->method('getEntityClassName')->willReturn('hi');

        $deleteOptions = DeleteOptions::create($mappingCollection, ['disableCascade' => true]);
        $this->assertEquals(true, $deleteOptions->disableCascade);
        $deleteOptions2 = DeleteOptions::create($mappingCollection, ['madeUpProperty' => 'blue']);
        $this->assertEquals(false, property_exists($deleteOptions2, 'madeUpProperty'));
        $this->assertEquals('hi', $deleteOptions2->getClassName());
    }
}
