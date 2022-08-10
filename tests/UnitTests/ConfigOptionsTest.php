<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\NamingStrategy\PascalCamelToSnake;
use Objectiphy\Objectiphy\NamingStrategy\Unseparated;
use PHPUnit\Framework\TestCase;

class ConfigOptionsTest extends TestCase
{
    private ConfigOptions $configOptions;

    public function setUp(): void
    {
        parent::setUp();
        $this->configOptions = new ConfigOptions();
    }

    public function testConstructor()
    {
        $unseparatedStrategy = new Unseparated();
        $options = [
            'tableNamingStrategy' => $unseparatedStrategy,
            'maxDepth' => 4,
        ];
        $tempConfig = new ConfigOptions($options);
        $this->assertInstanceOf(Unseparated::class, $tempConfig->getConfigOption(ConfigOptions::TABLE_NAMING_STRATEGY));
        $this->assertInstanceOf(PascalCamelToSnake::class, $tempConfig->getConfigOption(ConfigOptions::COLUMN_NAMING_STRATEGY));
        $this->assertSame(4, $tempConfig->getConfigOption(ConfigOptions::MAX_DEPTH));
        $this->assertSame(null, $tempConfig->getConfigOption(ConfigOptions::EAGER_LOAD_TO_ONE));
        $this->assertSame(true, $tempConfig->getConfigOption(ConfigOptions::BIND_TO_ENTITIES));

        $configFile = __DIR__ . '/../../config.ini';
        $tempConfig2 = new ConfigOptions(configFile: $configFile);
        $this->assertSame('/var/cache', $tempConfig2->getConfigOption(ConfigOptions::CACHE_DIRECTORY));
        $this->assertSame('array', $tempConfig2->getConfigOption(ConfigOptions::DEFAULT_COLLECTION_CLASS));
        $this->assertSame(true, $tempConfig2->getConfigOption(ConfigOptions::BIND_TO_ENTITIES));
    }

//    public function testClone()
//    {
//
//    }
}
