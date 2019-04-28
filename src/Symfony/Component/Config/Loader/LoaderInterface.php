<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * LoaderInterface is the interface implemented by all loader classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface LoaderInterface
{
    /**
     * Ignore all errors.
     */
    const ERROR_LEVEL_IGNORE_ALL = 0;

    /**
     * Basic error level.
     */
    const ERROR_LEVEL_BASIC = 1;

    /**
     * Occurs if file was not found.
     */
    const ERROR_LEVEL_FILE_NOT_FOUND = 2;

    /**
     * Throws exceptions if any error occurs.
     * The value of this constant will change in the future because it contains the sum of all error levels.
     */
    const ERROR_LEVEL_ALL = 3;

    /**
     * Loads a resource.
     *
     * @param mixed       $resource The resource
     * @param string|null $type     The resource type or null if unknown
     *
     * @throws \Exception If something went wrong
     */
    public function load($resource, $type = null);

    /**
     * Returns whether this class supports the given resource.
     *
     * @param mixed       $resource A resource
     * @param string|null $type     The resource type or null if unknown
     *
     * @return bool True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null);

    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolverInterface A LoaderResolverInterface instance
     */
    public function getResolver();

    /**
     * Sets the loader resolver.
     */
    public function setResolver(LoaderResolverInterface $resolver);
}
