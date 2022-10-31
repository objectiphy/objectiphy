<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use PHPUnit\Framework\TestCase;

class SaveOptionsTest extends TestCase
{
    public function testStaticCreate()
    {
        $mappingCollection = $this->getMockBuilder(MappingCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mappingCollection->expects($this->once())->method('getEntityClassName')->willReturn('hi');

        $saveOptions = SaveOptions::create($mappingCollection, ['saveChildren' => true, 'replaceExisting' => true]);
        $this->assertEquals(true, $saveOptions->saveChildren);
        $this->assertEquals(true, $saveOptions->replaceExisting);
        $this->assertEquals(true, $saveOptions->parseDelimiters);
        
        $saveOptions2 = SaveOptions::create($mappingCollection, ['madeUpProperty' => 'blue']);
        $this->assertEquals(false, property_exists($saveOptions2, 'madeUpProperty'));
        $this->assertEquals('hi', $saveOptions2->getClassName());
    }
}
