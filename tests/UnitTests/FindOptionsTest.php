<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use PHPUnit\Framework\TestCase;

class FindOptionsTest extends TestCase
{
    public function testStaticCreate()
    {
        $mappingCollection = $this->getMockBuilder(MappingCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mappingCollection->expects($this->once())->method('getEntityClassName')->willReturn('hi');

        $findOptions = FindOptions::create($mappingCollection, [
            'multiple' => false,
            'count' => true,
            'indexBy' => 'code',
            'orderBy' => [
                'id' => 'ASC',
                'code'
            ]
        ]);
        $this->assertEquals(false, $findOptions->multiple);
        $this->assertEquals(false, $findOptions->latest);
        $this->assertEquals(true, $findOptions->count);
        $this->assertEquals('code', $findOptions->indexBy);
        $this->assertEquals('ASC', $findOptions->orderBy['id']);
        $this->assertEquals('hi', $findOptions->getClassName());
        $this->assertEquals(['code'], $findOptions->getPropertyPaths());

        $findOptions2 = FindOptions::create($mappingCollection, [
            'madeUpProperty' => 'blue',
            'scalarProperty' => 'id2',
            'indexBy' => 'code2'
        ]);
        $this->assertEquals(false, property_exists($findOptions2, 'madeUpProperty'));
        $this->assertEquals(['code2', 'id2'], $findOptions2->getPropertyPaths());
    }
}
