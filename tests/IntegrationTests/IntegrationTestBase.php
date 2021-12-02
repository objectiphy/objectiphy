<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Orm\ObjectRepository;
use PHPUnit\Framework\TestCase;

class IntegrationTestBase extends TestCase
{
    protected ObjectRepository $objectRepository;
    protected \PDO $pdo;
    protected int $startTime = 0;
    protected string $testName = '';
    public static RepositoryFactory $repositoryFactory; //Static so we can re-use it for all tests, avoiding a complete cache clear for each one
    protected static bool $devMode = true; //Can temporarily set this to false to test production mode
    protected static string $cacheDirectory = '';

    protected function setUp(): void
    {
        $disabledCache = $this->getCacheSuffix();
        ini_set('memory_limit', '256M');
        $config = require(__DIR__ . '/../config.php');
        if (empty($config['DB_HOST']) || empty($config['DB_NAME']) || empty($config['DB_USER'])) {
            throw new \RuntimeException('Please populate the database credentials either in environment variables (recommended) or directly in /src/test/config.php (if you must).');
        }
        $this->pdo = new \PDO('mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'], $config['DB_USER'], $config['DB_PASSWORD']);
        $this->createFixtures();
        $start = microtime(true);
        if (!isset(static::$repositoryFactory)) {
            static::$cacheDirectory = __DIR__ . '/../../../../../var/cache/dev/objectiphy';
            if (!file_exists(static::$cacheDirectory)) {
                mkdir(static::$cacheDirectory, 0777, true);
            }
            static::$repositoryFactory = new RepositoryFactory($this->pdo, realpath(static::$cacheDirectory), static::$devMode);
        }
        $repositoryFactory = static::$repositoryFactory;
        $repositoryFactory->setConfigOptions(['commonProperty' => 'loginId']);
        $this->objectRepository = $repositoryFactory->createRepository(TestPolicy::class);
        //We might get back a second-hand repo, as the factory hangs onto them - so clear the entity cache as it will not be valid now the fixtures have been run
        $this->objectRepository->clearCache();
        //Also reset to default configuration
        $this->objectRepository->resetConfiguration();
        $setupTime = round(microtime(true) - $start, 3);
        echo "Objectiphy setup time: $setupTime seconds.\n";
        $this->startTime = microtime(true);
        if ($disabledCache) { //Have to re-do this as it will have been forgotten
            $this->disableCache();
        }
    }

    protected function tearDown(): void
    {
        echo ($this->testName ?: get_class($this)) . " tests executed in ". round(microtime(true) - $this->startTime, 2) ." seconds\n";
    }

    protected function createFixtures()
    {
        $sql = file_get_contents(__DIR__ . '/../Fixtures/objectiphy_test.sql');
        $this->pdo->beginTransaction();
        $stm = $this->pdo->prepare($sql);
        $stm->execute();
        $queryCount = 0;
        do {
            $queryCount++;
            if ($stm->errorCode() && $stm->errorCode() != '0') {
                if ($pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new \RuntimeException('Could not create database fixture: ' . print_r($stm->errorInfo(), true));
            }
        } while ($stm->nextRowset());

        $semiColonCount = substr_count ($sql, ';');
        if ($semiColonCount > $queryCount) {
            throw new \RuntimeException(sprintf('There are more semi-colons in the SQL file (%1$d) than there were queries executed (%2$d). This probably means not all queries were executed. Please check the fixture SQL.', $semiColonCount, $queryCount));
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    protected function disableCache()
    {
        if (!empty($this->objectRepository)) {
            $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        }
    }

    protected function getCacheSuffix(): string
    {
        $suffix = '';
        if (!empty($this->objectRepository)) {
            $suffix = $this->objectRepository->getConfiguration()->disableEntityCache ? ' No Cache' : '';
        }

        return $suffix;
    }
}
