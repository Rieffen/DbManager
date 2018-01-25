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