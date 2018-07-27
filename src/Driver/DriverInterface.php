<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\DbManager\Driver;

/**
 * Interface DriverInterface
 *
 * @package Berlioz\DbManager\Driver
 *
 * @method mixed errorCode() Fetch the SQLSTATE associated with the last operation on the database handle
 * @method array errorInfo() Fetch extended error information associated with the last operation on the database handle
 * @method int exec(string $statement) Execute an SQL statement and return the number of affected rows
 * @method mixed getAttribute(int $attribute) Retrieve a database connection attribute
 * @method bool inTransaction() Checks if inside a transaction
 * @method string lastInsertId(string $name = null) Returns the ID of the last inserted row or sequence value
 * @method \PDOStatement prepare(string $statement, array $driver_options = []) Prepares a statement for execution and
 *         returns a statement object
 * @method \PDOStatement query(string $statement, int $fetch_mode = \PDO::FETCH_COLUMN) Executes an SQL statement,
 *         returning a result set as a \PDOStatement object
 * @method string quote(string $string, int $parameter_type = \PDO::PARAM_STR) Quotes a string for use in a query
 * @method bool setAttribute(int $attribute, mixed $value) Set an attribute
 */
interface DriverInterface
{
    /**
     * Protect data to pass to queries.
     *
     * @param string $text       String to protect
     * @param bool   $forceQuote Force quote (default: false)
     * @param bool   $stripTags  Strip tags (default: true)
     *
     * @return string
     * @throws \Berlioz\DbManager\Exception\DatabaseException If an error occurred during protection
     */
    public function protectData($text, $forceQuote = false, $stripTags = true);
}