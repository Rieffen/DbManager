<?php
namespace Berlioz\DbManager\Tests\Driver;

use PHPUnit\Framework\TestCase;
use Berlioz\DbManager\DbManager;
use Berlioz\DbManager\Driver\SQLite;

class SQLiteTest extends TestCase
{
    /**
     * Tests connecting to an empty SQLite database in memory.
     *
     * @return DbManager
     */
    public function testCreateMemoryDB()
    {
        $dbManager = new DbManager([
            'driver' => [
                'name' => 'sqlite',
                'arguments' => [
                    ['location' => SQLite::LOCATION_MEMORY]
                ]
            ]
        ]);

        $this->assertInstanceOf(DbManager::class, $dbManager);

        return $dbManager;
    }

    /**
     * Tests connection to an existing SQLite database in a file.
     *
     * @return DbManager
     */
    public function testOpenFileDB()
    {
        $dbLocation = __DIR__ . "/../test.sq3";
        $dbManager = new DbManager([
            'driver' => [
                'name' => 'sqlite',
                'arguments' => [
                    ['location' => SQLite::LOCATION_FILE, 'path' => $dbLocation]
                ]
            ]
        ]);

        $this->assertInstanceOf(DbManager::class, $dbManager);
        
        /** @var SQLite $driver */
        $driver = $dbManager->getDriver();
        $tableNames = $driver->getTableNames();

        // Asserting that the table names are correct. The assertEquals call has extended arguments to have $canonicalize set to true,
        // avoiding errors thrown because arrays aren't in the same order
        $this->assertCount(2, $tableNames);
        $this->assertEquals(['test_table', 'sqlite_sequence'], $tableNames, "Table names aren't valid", 0.0, 10, true);

        return $dbManager;
    }
}