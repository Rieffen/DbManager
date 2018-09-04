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

use Berlioz\DbManager\Exception\ConnectionException;
use Berlioz\DbManager\Exception\DatabaseException;
use Berlioz\DbManager\Exception\TimeoutException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * MySQL DB Class.
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
class MySQL implements DriverInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    /** @var int Number of queries */
    private static $nbQueries = 0;
    /** @var array Options of database connection */
    private $options;
    /** @var \PDO PDO object */
    private $pdo = null;
    /** @var bool If a transaction started */
    private $transactionStarted = false;
    /** @var int Number of started transaction (in cascade) */
    private $iTransactionStarted = 0;

    /**
     * DB MySQL constructor.
     *
     * @option string $driver      Database driver (default: mysql)
     * @option string $unix_socket Database UNIX socket (default: null)
     * @option string $host        Database host connection
     * @option string $port        Database port (default: 3306)
     * @option string $encoding    Database encoding (default: mb_internal_encoding())
     * @option int    $timeout     Database timeout in seconds (default: 5)
     * @option string $dbname      Database default database name
     * @option string $username    Database username connection
     * @option string $password    Database password connection
     *
     * @param array $options Database connection options
     *
     * @throws \Berlioz\DbManager\Exception\ConnectionException If an error occurred during \PDO connection
     */
    public function __construct(array $options)
    {
        $this->options = ['driver'      => 'mysql',
                          'unix_socket' => null,
                          'host'        => null,
                          'port'        => 3306,
                          'encoding'    => mb_internal_encoding(),
                          'timeout'     => 5,
                          'dbname'      => null,
                          'username'    => null,
                          'password'    => null];
        $this->options = array_merge($this->options, $options);

        $this->initPDO();
    }

    /**
     * DB MySQL destructor.
     */
    public function __destruct()
    {
        // Log
        $this->log(sprintf('Disconnection from %s', $this->getDSN()));
    }

    /**
     * __sleep() PHP magic method.
     *
     * @return array
     */
    public function __sleep()
    {
        return ['options'];
    }

    /**
     * Init PDO.
     *
     * @throws \Berlioz\DbManager\Exception\ConnectionException If an error occurred during \PDO connection
     */
    private function initPDO()
    {
        try {
            // \PDO options
            $pdoOptions = [\PDO::ATTR_TIMEOUT => (int) $this->options['timeout']];

            // Creation of \PDO objects
            $this->pdo = new \PDO($this->getDSN(),
                                  (string) $this->options['username'],
                                  (string) $this->options['password'],
                                  $pdoOptions);

            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Log
            $this->log(sprintf('Connection to %s', $this->getDSN()));
        } catch (\Exception $e) {
            // Log
            $this->log(sprintf('Connection failed to %s', $this->getDSN()), LogLevel::CRITICAL);

            throw new ConnectionException(sprintf('Connection failed to %s (#%d - %s)', $this->getDSN(), $e->getCode() ?: 0, $e->getMessage()));
        }
    }

    /**
     * Log data into App logging service
     *
     * @param string $message Message
     * @param string $level
     */
    private function log(string $message, string $level = LogLevel::INFO)
    {
        if (!is_null($this->logger)) {
            $this->logger->log(sprintf('%s / %s', get_class($this), $message), $level);
        }
    }

    /**
     * Get DSN of database for \PDO connection.
     *
     * @return string DSN
     */
    private function getDSN()
    {
        $dsn = "{$this->options['driver']}:";

        if (!empty($this->options['unix_socket'])) {
            $dsn .= "unix_socket={$this->options['unix_socket']}";
        } else {
            $dsn .= "host={$this->options['host']};port={$this->options['port']}";
        }

        if (!empty($this->options['dbname'])) {
            $dsn .= ";dbname={$this->options['dbname']}";
        }

        if (!is_null($this->encodingToCharset())) {
            $dsn .= ";charset={$this->encodingToCharset()}";
        }

        return $dsn;
    }

    /**
     * Get \PDO object.
     *
     * @return \PDO
     */
    protected function getPDO()
    {
        return $this->pdo;
    }

    /**
     * Get default DB name.
     *
     * @return string
     */
    public function getDbName()
    {
        return (string) $this->options['dbname'];
    }

    /**
     * Get encoding (default: mb_internal_encoding()).
     *
     * @return string
     */
    public function getEncoding()
    {
        return !empty($this->options['encoding']) ? (string) $this->options['encoding'] : mb_internal_encoding();
    }

    /**
     * Get charset encoding string for SQL queries.
     *
     * @return string
     */
    protected function encodingToCharset()
    {
        switch (mb_strtolower($this->getEncoding())) {
            case 'cp1252':
            case 'iso-8859-1':
                return 'latin1';
            case 'iso-8859-2':
                return 'latin2';
            case 'iso-8859-5':
                return 'latin5';
            case 'iso-8859-7':
                return 'greek';
            case 'iso-8859-8':
                return 'hebrew';
            case 'iso-8859-13':
                return 'latin7';
            case 'utf-8':
                return 'utf8';
            case 'utf-16':
                return 'utf16';
            case 'utf-32':
                return 'utf32';
            default:
                return null;
        }
    }

    /**
     * __call() magic method.
     *
     * @param  string $name      Method name to call
     * @param  array  $arguments Arguments list
     *
     * @return mixed
     * @throws \Berlioz\DbManager\Exception\DatabaseException
     */
    public function __call($name, array $arguments)
    {
        try {
            $returnValue = @$this->pdo->$name(...$arguments);

            return $returnValue;
        } catch (\PDOException $e) {
            if ($e->getCode() == 'HY000') {
                throw new TimeoutException($e->getMessage(), 0, $e);
            }

            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        } finally {
            // Log
            switch ($name) {
                case 'exec':
                case 'prepare':
                case 'query':
                    $this->log(sprintf('%s "%s"', ucwords($name), $arguments[0] ?? 'N/A'), LogLevel::INFO);
            }
        }
    }

    /**
     * Begin a transaction.
     *
     * @throws \Berlioz\DbManager\Exception\DatabaseException
     */
    public function beginTransaction()
    {
        if (false === $this->transactionStarted) {
            $this->transactionStarted = true;
            $this->__call("beginTransaction", func_get_args());
        }

        $this->iTransactionStarted++;
    }

    /**
     * Commit a transaction.
     *
     * @throws \Berlioz\DbManager\Exception\DatabaseException
     */
    public function commit()
    {
        $this->iTransactionStarted--;

        if (true === $this->transactionStarted && 0 == $this->iTransactionStarted) {
            $this->transactionStarted = false;
            $this->__call("commit", func_get_args());
        }
    }

    /**
     * Rollback a transaction.
     *
     * @throws \Berlioz\DbManager\Exception\DatabaseException
     */
    public function rollBack()
    {
        if (true === $this->transactionStarted) {
            $this->transactionStarted = false;
            $this->__call("rollback", func_get_args());
        }

        $this->iTransactionStarted = 0;
    }

    /**
     * Get next auto increment of a table.
     *
     * @param  string      $table    Table name
     * @param  string|null $database Database name (if empty: defaut database name)
     *
     * @return int
     * @throws \Berlioz\DbManager\Exception\DatabaseException If DB result is an error
     */
    public function nextAutoIncrement($table, $database = null)
    {
        $query = "SHOW TABLE STATUS FROM `" . (is_null($database) ? $this->getDbName() : $database) . "` LIKE '" . escapeshellcmd($table) . "';";

        if (($result = $this->query($query)) !== false) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);

            return $row["Auto_increment"];
        } else {
            throw new DatabaseException(sprintf('Unable to get next auto increment of table "%s"', $table));
        }
    }

    /**
     * @inheritdoc
     */
    public function protectData($text, $forceQuote = false, $stripTags = true)
    {
        try {
            if (null === $text) {
                $text = 'NULL';
            } else {
                if (get_magic_quotes_gpc()) {
                    $text = stripslashes($text);
                }

                // Protect if it's not an integer
                if (!is_numeric($text) || true === $forceQuote) {
                    // Strip tags
                    if (true === $stripTags) {
                        $text = strip_tags((string) $text);
                    }

                    // Convert encoding
                    $text = $this->convertCharacterEncoding($text);

                    // Add charset on string value
                    if (($quote = $this->quote((string) $text)) !== false) {
                        $charset = $this->encodingToCharset();
                        $text = (!is_null($charset) ? '_' . $charset : '') . (string) $quote;
                    } else {
                        throw new DatabaseException(sprintf('Unable to protect data "%s" for MySQL', (string) $text));
                    }
                }
            }

            return !empty($text) || is_numeric($text) ? $text : "''";
        } catch (DatabaseException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new DatabaseException;
        }
    }

    /**
     * Convert charset encoding to pass to queries.
     *
     * @param  string $content
     *
     * @return string
     */
    private function convertCharacterEncoding($content)
    {
        if (is_string($content) && !empty($content)) {
            $encoding = mb_detect_encoding($content, mb_detect_order(), true);
            $content = @mb_convert_encoding($content, $this->getEncoding(), (false === $encoding ? 'ASCII' : $encoding));

            if ($this->getEncoding() == 'UTF-8') {
                $content = str_replace(chr(0xC2) . chr(0x80), chr(0xE2) . chr(0x82) . chr(0xAC), $content);
            }
        }

        return $content;
    }

    /**
     * Get number of queries.
     *
     * @return int
     */
    public static function getNbQueries()
    {
        return self::$nbQueries;
    }
}
