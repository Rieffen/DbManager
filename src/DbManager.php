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

namespace Berlioz\DbManager;

use Berlioz\DbManager\Driver\DriverInterface;
use Berlioz\DbManager\Exception\DbManagerException;
use Berlioz\DbManager\Repository\RepositoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class DbManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    /** Default drivers into the package. */
    const DEFAULT_DRIVERS = ['mysql' => '\Berlioz\DbManager\Driver\MySQL'];
    /** @var \Berlioz\DbManager\Driver\DriverInterface|null Driver */
    private $driver;
    /** @var \Berlioz\DbManager\Repository\RepositoryInterface[] Repositories */
    private $repositories;

    /**
     * DbManager constructor.
     *
     * @param array $options Options
     *
     * @throws \InvalidArgumentException if one argument is invalid
     * @throws \ReflectionException
     */
    public function __construct(array $options = [])
    {
        // Transport
        if (!empty($options['driver'])) {
            $classArgs = [];

            if (is_array($options['driver'])) {
                if (!empty($options['driver']['name'])) {
                    if (!empty(self::DEFAULT_DRIVERS[$options['driver']['name']])) {
                        $className = self::DEFAULT_DRIVERS[$options['driver']['name']];
                    } else {
                        throw new \InvalidArgumentException(sprintf('Unknown "%s" default driver', $options['driver']['name']));
                    }
                } else {
                    if (!empty($options['driver']['class'])) {
                        $className = $options['driver']['class'];
                    } else {
                        throw new \InvalidArgumentException('Missing class name in "driver" options');
                    }
                }

                if (!empty($options['driver']['arguments'])) {
                    if (is_array($options['driver']['arguments'])) {
                        $classArgs = $options['driver']['arguments'];
                    } else {
                        throw new \InvalidArgumentException('Class arguments of "driver" options must be an array');
                    }
                }
            } else {
                if (is_string($options['driver'])) {
                    $className = $options['driver'];
                } else {
                    throw new \InvalidArgumentException('"driver" options must be an array or a valid class name');
                }
            }

            if (class_exists($className)) {
                $class = new \ReflectionClass($className);
                $object = $class->newInstanceArgs($classArgs);

                if ($object instanceof DriverInterface) {
                    $this->setDriver($object);
                } else {
                    throw new \InvalidArgumentException('Transport class must be an instance of \Berlioz\DbManager\Driver\DriverInterface interface');
                }
            } else {
                throw new \InvalidArgumentException(sprintf('Class "%s" doesn\'t exists', $className));
            }
        }
    }

    /**
     * Get driver.
     *
     * @return \Berlioz\DbManager\Driver\DriverInterface|null
     */
    public function getDriver(): ?DriverInterface
    {
        return $this->driver;
    }

    /**
     * Set driver.
     *
     * @param \Berlioz\DbManager\Driver\DriverInterface $driver
     *
     * @return \Berlioz\DbManager\DbManager
     */
    public function setDriver(DriverInterface $driver): DbManager
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Get repository.
     *
     * @param string $repositoryClassName
     *
     * @return \Berlioz\DbManager\Repository\RepositoryInterface
     * @throws \Berlioz\DbManager\Exception\DbManagerException
     */
    public function getRepository(string $repositoryClassName): RepositoryInterface
    {
        if (isset($this->repositories[$repositoryClassName])) {
            return $this->repositories[$repositoryClassName];
        } else {
            if (class_exists($repositoryClassName) && is_a($repositoryClassName, RepositoryInterface::class, true)) {
                try {
                    /** @var \Berlioz\DbManager\Repository\RepositoryInterface $repository */
                    $repository = new $repositoryClassName;

                    // LoggerAwareInterface ?
                    if ($repository instanceof LoggerAwareInterface && !is_null($this->logger)) {
                        $repository->setLogger($this->logger);
                    }

                    // DbManagerAwareInterface ?
                    if ($repository instanceof DbManagerAwareInterface) {
                        $repository->setDbManager($this);
                    }

                    return $repository;
                } catch (\Exception $e) {
                    throw new DbManagerException(sprintf('Unable to get instance of Repository of "%s"', $repositoryClassName));
                }
            } else {
                throw new DbManagerException(sprintf('Repository "%s" class not found', $repositoryClassName));
            }
        }
    }
}