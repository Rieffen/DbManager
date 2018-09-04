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

namespace Berlioz\DbManager\Entity;

/**
 * Class Entity.
 *
 * @package Berlioz\DbManager\Entity
 */
class Entity implements EntityInterface
{
    /**
     * Entity constructor.
     *
     * @param array $data Data to complete entity
     */
    public function __construct(array $data = [])
    {
        // Complete properties with data passed in function
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                b_property_set($this, $key, $value);
            }
        }
    }
}
