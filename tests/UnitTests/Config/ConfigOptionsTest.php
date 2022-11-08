<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\UnitTests\Config;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\NamingStrategy\PascalCamelToSnake;
use Objectiphy\Objectiphy\NamingStrategy\Unseparated;
use Objectiphy\Objectiphy\Tests\Entity\TestCollection;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Factory\TestVehicleFactory;
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

        @mkdir('/var/cache', 0777,true);
        if (!file_exists('/var/cache')) {
            throw new \RuntimeException('Please ensure /var/cache directory exists before attempting to run these tests.');
        }
        $configFile = __DIR__ . '/../../../config.ini';
        $tempConfig2 = new ConfigOptions(configFile: $configFile);
        $this->assertSame('/var/cache', $tempConfig2->getConfigOption(ConfigOptions::CACHE_DIRECTORY));
        $this->assertSame('array', $tempConfig2->getConfigOption(ConfigOptions::DEFAULT_COLLECTION_CLASS));
        $this->assertSame(true, $tempConfig2->getConfigOption(ConfigOptions::BIND_TO_ENTITIES));
    }

    public function testClone()
    {
        $tempConfig = new ConfigOptions();
        $entityConfigs = $this->configOptions->getConfigOption(ConfigOptions::ENTITY_CONFIG);
        $policyConfig = new ConfigEntity();
        $policyConfig->setConfigOption(ConfigEntity::COLLECTION_CLASS, TestCollection::class);
        $policyConfigObjectId = spl_object_id($policyConfig);
        $vehicleConfig = new ConfigEntity();
        $vehicleConfig->setConfigOption(ConfigEntity::ENTITY_FACTORY, new TestVehicleFactory());
        $vehicleConfigObjectId = spl_object_id($vehicleConfig);
        $entityConfigs[TestPolicy::class] = $policyConfig;
        $entityConfigs[TestVehicle::class] = $vehicleConfig;
        $tempConfig->setConfigOption(ConfigOptions::ENTITY_CONFIG, $entityConfigs);

        $clone = clone($tempConfig);
        $this->assertNotEquals($policyConfigObjectId, spl_object_id($clone->entityConfig[TestPolicy::class]));
        $this->assertNotEquals($vehicleConfigObjectId, spl_object_id($clone->entityConfig[TestVehicle::class]));
    }

    public function testSetCacheDirectory()
    {
        $cacheDir = '/tmp/objectiphy/unit/tests';
        if (file_exists($cacheDir)) {
            rmdir($cacheDir);
            $this->assertDirectoryDoesNotExist($cacheDir);
        }

        //Check using an indexed array gives you a nice error
        try {
            $tempConfig = new ConfigOptions([ConfigOptions::CACHE_DIRECTORY, $cacheDir]);
        } catch (ObjectiphyException $ex) {
            $this->assertStringContainsString('associative', $ex->getMessage());
        }

        $tempConfig = new ConfigOptions([ConfigOptions::CACHE_DIRECTORY => $cacheDir]);
        $this->assertDirectoryExists($cacheDir);
        $this->assertEquals($cacheDir, $tempConfig->getConfigOption(ConfigOptions::CACHE_DIRECTORY));

        chmod($cacheDir, 0444);
        try {
            $tempConfig = new ConfigOptions([ConfigOptions::CACHE_DIRECTORY => $cacheDir]);
            $this->assertEquals(false, true); //Should not reach this!
        } catch (ObjectiphyException $ex) {
            $this->assertStringContainsString('is not writable', $ex->getMessage());
        }

        try {
            $tempConfig = new ConfigOptions([ConfigOptions::CACHE_DIRECTORY => $cacheDir . '/tmp']);
            $this->assertEquals(false, true); //Should not reach this!
        } catch (ObjectiphyException $ex) {
            $this->assertStringContainsString('does not exist', $ex->getMessage());
        }
        
        chmod($cacheDir, 0755);
        rmdir($cacheDir);
    }
}
