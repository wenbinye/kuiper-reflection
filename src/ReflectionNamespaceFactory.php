<?php

namespace kuiper\reflection;

use Composer\Autoload\ClassLoader;

class ReflectionNamespaceFactory implements ReflectionNamespaceFactoryInterface
{
    /**
     * @var ReflectionNamespaceFactory
     */
    private static $INSTANCE;

    /**
     * @var ReflectionFileFactoryInterface
     */
    private $reflectionFileFactory;

    /**
     * @var ReflectionNamespace[]
     */
    private $namespaces = [];

    /**
     * @var array
     */
    private $namespaceDirs = [];

    /**
     * @var string[]
     */
    private $extensions = ['php'];

    /**
     * {@inheritdoc}
     */
    public static function createInstance(ReflectionFileFactoryInterface $reflectionFileFactory = null)
    {
        if (!isset(self::$INSTANCE)) {
            self::$INSTANCE = new self($reflectionFileFactory);
        }

        return self::$INSTANCE;
    }

    private function __construct(ReflectionFileFactoryInterface $factory = null)
    {
        $this->reflectionFileFactory = $factory ?: ReflectionFileFactory::createInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function create($namespace)
    {
        $namespace = $this->normalizeNamespace($namespace);
        if (isset($this->namespaces[$namespace])) {
            return $this->namespaces[$namespace];
        } else {
            $dirs = [];
            foreach ($this->namespaceDirs as $prefix => $prefixDirs) {
                if (empty($prefix) || 0 === strpos($namespace, $prefix)) {
                    foreach (array_keys($prefixDirs) as $dir) {
                        $dir .= '/'.str_replace('\\', '/', substr($namespace, strlen($prefix)));
                        $dirs[] = $dir;
                    }
                }
            }

            return $this->namespaces[$namespace]
                = new ReflectionNamespace($namespace, $dirs, $this->extensions, $this->reflectionFileFactory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache($namespace = null)
    {
        if (isset($namespace)) {
            unset($this->namespaces[$this->normalizeNamespace($namespace)]);
        } else {
            $this->namespaces = [];
        }

        return $this;
    }

    /**
     * Registers directory for the namespace.
     *
     * @param string $namespace
     * @param string $dir
     *
     * @return static
     */
    public function register($namespace, $dir)
    {
        $namespace = $this->normalizeNamespace($namespace);
        $this->namespaceDirs[$namespace][$dir] = true;

        return $this;
    }

    /**
     * Registers composer class loader.
     * Only psr4 namespace support.
     *
     * @param ClassLoader $loader
     *
     * @return static
     */
    public function registerLoader(ClassLoader $loader)
    {
        foreach ($loader->getPrefixesPsr4() as $namespace => $dirs) {
            foreach ($dirs as $dir) {
                $this->register($namespace, $dir);
            }
        }

        return $this;
    }

    /**
     * Adds new php code file extension.
     *
     * @param string $ext
     *
     * @return self
     */
    public function addExtension($ext)
    {
        $this->extensions[] = $ext;

        return $this;
    }

    /**
     * Sets php code file extension list.
     *
     * @param string[] $extensions
     *
     * @return self
     */
    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;

        return $this;
    }

    private function normalizeNamespace($namespace)
    {
        return trim($namespace, '\\').'\\';
    }
}
