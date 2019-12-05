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
use PDO;

/**
 * SQLite DB Class.
 *
 * @method mixed errorCode() Fetch the SQLSTATE associated with the last operation on the database handle
 * @method array errorInfo() Fetch extended error information associated with the last operation on the database handle
 * @method int exec(string $statement) Execute an SQL statement and return the number of affected rows
 * @method mixed getAttribute(int $attribute) Retrieve a database connection attribute
 * @method bool inTransaction() Checks if inside a transaction
 * @method string lastInsertId(string $name = null) Returns the ID of the last inserted row or sequence value
 * @method PDOStatement prepare(string $statement, array $driver_options = []) Prepares a statement for execution and
 *         returns a statement object
 * @method PDOStatement query(string $statement, int $fetch_mode = PDO::FETCH_COLUMN) Executes an SQL statement,
 *         returning a result set as a PDOStatement object
 * @method string quote(string $string, int $parameter_type = PDO::PARAM_STR) Quotes a string for use in a query
 * @method bool setAttribute(int $attribute, mixed $value) Set an attribute
 */
class SQLite implements DriverInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    // SQLite location type constants
    const LOCATION_FILE = "file";
    const LOCATION_MEMORY = "memory";

    /** @var string $encoding The database encoding, fetched from the database when first requested */
    protected $encoding;

    /**
     * SQLite constructor.
     *
     * @option string location  The database location type, from one of the LOCATION_* constants (default: file)
     * @option string path      The database path (default: null, required only for path locations)
     * @option string $encoding Database encoding (default: mb_internal_encoding())
     * 
     * @param array $options Database connection options
     *
     * @throws \Berlioz\DbManager\Exception\ConnectionException If an error occurred during PDO connection
     */
    public function __construct(array $options)
    {
        $this->options = ['driver' => 'sqlite',
                          'location' => self::LOCATION_FILE,
                          'path' => null];
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
     * Initializes the PDO object that this driver will use, and connects to the database.
     * 
     * @throws \Berlioz\DbManager\Exception\ConnectionException If an error occurred during PDO connection
     */
    private function initPDO()
    {
        try {
            // Creation of PDO objects
            $this->pdo = new PDO($this->getDSN());

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Log
            $this->log(sprintf('Connection to %s', $this->getDSN()));
        } catch (\Exception $e) {
            // Log
            $this->log(sprintf('Connection failed to %s', $this->getDSN()), LogLevel::CRITICAL);

            throw new ConnectionException(sprintf('Connection failed to %s (#%d - %s)', $this->getDSN(), $e->getCode() ?: 0, $e->getMessage()));
        }
    }

    /**
     * Get DSN of database for PDO connection.
     *
     * @return string DSN
     */
    private function getDSN()
    {
        $dsn = "{$this->options['driver']}:";

        switch($this->options['location']) {
            case self::LOCATION_FILE:
                $dsn .= $this->options['path'];
                break;

            case self::LOCATION_MEMORY:
                $dsn .= "memory:";
                break;
            
            default:
                throw new ConnectionException(sprintf("Database location not recognized: %s", $this->options['location']));
        }
        
        return $dsn;
    }
    
    /**
     * Get PDO object.
     *
     * @return PDO
     */
    protected function getPDO()
    {
        return $this->pdo;
    }
    
    /**
     * Log data into App logging service
     *
     * @param string $message Message
     * @param string $level
     */
    protected function log(string $message, string $level = LogLevel::INFO)
    {
        if (!is_null($this->logger)) {
            $this->logger->log(sprintf('%s / %s', get_class($this), $message), $level);
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
     * Gets the list of the current tables in the database.
     *
     * @return array The list of names of the tables that are currently stored in the database.
     */
    public function getTableNames()
    {
        try {
            $result = $this->query('SELECT `name` FROM sqlite_master WHERE type = "table";');
            
            if($result !== false) {
                $names = $result->fetchAll(PDO::FETCH_COLUMN, "name");
                return $names;
            }
        } catch(\Exception $e) {
            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getEncoding()
    {
        if(empty($this->encoding)) {
            try {
                $result = $this->query('PRAGMA encoding;');
                
                if($result !== false) {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                    $this->encoding = $row['encoding'];
                }
            } catch(\Exception $e) {
                throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->encoding;
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
}