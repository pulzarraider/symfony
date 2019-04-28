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

use Symfony\Component\Config\Exception\FileLoaderImportCircularReferenceException;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\GlobResource;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FileLoader extends Loader
{
    protected static $loading = [];

    protected $locator;

    private $currentDir;

    public function __construct(FileLocatorInterface $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Sets the current directory.
     *
     * @param string $dir
     */
    public function setCurrentDir($dir)
    {
        $this->currentDir = $dir;
    }

    /**
     * Returns the file locator used by this loader.
     *
     * @return FileLocatorInterface
     */
    public function getLocator()
    {
        return $this->locator;
    }

    /**
     * Imports a resource.
     *
     * @param mixed       $resource       A Resource
     * @param string|null $type           The resource type or null if unknown
     * @param int         $errorLevel     Whether to ignore import errors or not
     * @param string|null $sourceResource The original resource importing the new resource
     *
     * @return mixed
     *
     * @throws LoaderLoadException
     * @throws FileLoaderImportCircularReferenceException
     * @throws FileLocatorFileNotFoundException
     */
    public function import($resource, $type = null, $errorLevel = LoaderInterface::ERROR_LEVEL_ALL, $sourceResource = null)
    {
        if (\is_bool($errorLevel)) {
            @trigger_error('The boolean value you are using for the $errorLevel argument of the '.__CLASS__.'::import method is deprecated since Symfony 4.4 and will not be supported anymore in 5.0. Use the constants defined in the FileLoader instead.', E_USER_DEPRECATED);

            if (true === $errorLevel) {
                $errorLevel = LoaderInterface::ERROR_LEVEL_IGNORE_ALL;
            } elseif (false === $errorLevel) {
                $errorLevel = LoaderInterface::ERROR_LEVEL_ALL;
            }
        }

        if (\is_string($resource) && \strlen($resource) !== $i = strcspn($resource, '*?{[')) {
            $ret = [];
            $isSubpath = 0 !== $i && false !== strpos(substr($resource, 0, $i), '/');
            foreach ($this->glob($resource, false, $_, $errorLevel || !$isSubpath) as $path => $info) {
                if (null !== $res = $this->doImport($path, $type, $errorLevel, $sourceResource)) {
                    $ret[] = $res;
                }
                $isSubpath = true;
            }

            if ($isSubpath) {
                return isset($ret[1]) ? $ret : (isset($ret[0]) ? $ret[0] : null);
            }
        }

        return $this->doImport($resource, $type, $errorLevel, $sourceResource);
    }

    /**
     * @internal
     */
    protected function glob(string $pattern, bool $recursive, &$resource = null, int $errorLevel = LoaderInterface::ERROR_LEVEL_ALL, bool $forExclusion = false, array $excluded = [])
    {
        if (\strlen($pattern) === $i = strcspn($pattern, '*?{[')) {
            $prefix = $pattern;
            $pattern = '';
        } elseif (0 === $i || false === strpos(substr($pattern, 0, $i), '/')) {
            $prefix = '.';
            $pattern = '/'.$pattern;
        } else {
            $prefix = \dirname(substr($pattern, 0, 1 + $i));
            $pattern = substr($pattern, \strlen($prefix));
        }

        try {
            $prefix = $this->locator->locate($prefix, $this->currentDir, true);
        } catch (FileLocatorFileNotFoundException $e) {
            if ($errorLevel & LoaderInterface::ERROR_LEVEL_FILE_NOT_FOUND) {
                throw $e;
            }

            $resource = [];
            foreach ($e->getPaths() as $path) {
                $resource[] = new FileExistenceResource($path);
            }

            return;
        }
        $resource = new GlobResource($prefix, $pattern, $recursive, $forExclusion, $excluded);

        yield from $resource;
    }

    private function doImport($resource, $type = null, int $errorLevel = LoaderInterface::ERROR_LEVEL_ALL, $sourceResource = null)
    {
        try {
            $loader = $this->resolve($resource, $type);

            if ($loader instanceof self && null !== $this->currentDir) {
                $resource = $loader->getLocator()->locate($resource, $this->currentDir, false);
            }

            $resources = \is_array($resource) ? $resource : [$resource];
            for ($i = 0; $i < $resourcesCount = \count($resources); ++$i) {
                if (isset(self::$loading[$resources[$i]])) {
                    if ($i == $resourcesCount - 1) {
                        throw new FileLoaderImportCircularReferenceException(array_keys(self::$loading));
                    }
                } else {
                    $resource = $resources[$i];
                    break;
                }
            }
            self::$loading[$resource] = true;

            try {
                $ret = $loader->load($resource, $type);
            } finally {
                unset(self::$loading[$resource]);
            }

            return $ret;
        } catch (FileLoaderImportCircularReferenceException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (LoaderInterface::ERROR_LEVEL_IGNORE_ALL !== $errorLevel) {
                if (0 === ($errorLevel & LoaderInterface::ERROR_LEVEL_FILE_NOT_FOUND) && ($e instanceof FileLocatorFileNotFoundException)) {
                    // ignore exception if file was not found
                    return;
                }

                // prevent embedded imports from nesting multiple exceptions
                if ($e instanceof LoaderLoadException) {
                    throw $e;
                }

                throw new LoaderLoadException($resource, $sourceResource, null, $e, $type);
            }
        }
    }
}
