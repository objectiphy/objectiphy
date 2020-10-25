<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Orm\ObjectRepository;
use PHPUnit\Framework\TestCase;

class IntegrationTestBase extends TestCase
{
    /** @var ObjectRepository */
    protected $objectRepository;
    /** @var \PDO */
    protected $pdo;
    /** @var int */
    protected $startTime;
    /** @var string */
    protected $testName;

    protected function setUp(): void
    {
        $config = require(__DIR__ . '/../config.php');
        if (empty($config['DB_HOST']) || empty($config['DB_NAME']) || empty($config['DB_USER'])) {
            throw new \RuntimeException('Please populate the database credentials either in environment variables (recommended) or directly in /src/test/config.php (if you must).');
        }
        $this->pdo = new \PDO('mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'], $config['DB_USER'], $config['DB_PASSWORD']);
        $this->createFixtures();
        $start = microtime(true);
        $configOptions = new ConfigOptions();
        $configOptions->commonProperty = 'loginId';
        $repositoryFactory = new RepositoryFactory($this->pdo, $configOptions);
        $this->objectRepository = $repositoryFactory->createRepository(TestPolicy::class);
        $setupTime = round(microtime(true) - $start, 3);
        echo "Objectiphy setup time: $setupTime seconds.\n";
        $this->startTime = microtime(true);
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
                $this->pdo->rollBack();
                throw new \RuntimeException('Could not create database fixture: ' . print_r($stm->errorInfo(), true));
            }
        } while ($stm->nextRowset());

        $semiColonCount = substr_count ($sql, ';');
        if ($semiColonCount > $queryCount) {
            throw new \RuntimeException(sprintf('There are more semi-colons in the SQL file (%1$d) than there were queries executed (%2$d). This probably means not all queries were executed. Please check the fixture SQL.', $semiColonCount, $queryCount));
        }
        $this->pdo->commit();
    }
}
