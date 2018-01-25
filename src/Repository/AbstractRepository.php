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

namespace Berlioz\DbManager\Repository;


use Berlioz\DbManager\DbManagerAwareInterface;
use Berlioz\DbManager\DbManagerAwareTrait;
use Berlioz\DbManager\Driver\DriverInterface;
use Berlioz\DbManager\Entity\EntityInterface;
use Berlioz\DbManager\Exception\DbManagerException;

abstract class AbstractRepository implements RepositoryInterface, DbManagerAwareInterface
{
    use DbManagerAwareTrait;

    /**
     * Get driver.
     *
     * @return \Berlioz\DbManager\Driver\DriverInterface|null
     * @throws \Berlioz\DbManager\Exception\DbManagerException if unable to get DbManager
     */
    protected function getDriver(): ?DriverInterface
    {
        if (is_null($this->getDbManager())) {
            throw new DbManagerException('Unable to get DbManager from repository');
        } else {
            return $this->getDbManager()->getDriver();
        }
    }

    /**
     * Give values to object.
     *
     * @param \Berlioz\DbManager\Entity\EntityInterface $obj    Source object
     * @param array                                     $values Values
     *
     * @return object
     */
    protected function giveValues(EntityInterface $obj, array $values)
    {
        foreach ($values as $key => $value) {
            b_property_set($obj, $key, $value);
        }

        return $obj;
    }
}