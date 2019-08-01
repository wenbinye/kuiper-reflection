<?php

namespace kuiper\reflection;

use kuiper\reflection\type\ArrayType;
use kuiper\reflection\type\ClassType;
use kuiper\reflection\type\MixedType;

/**
 * @SuppressWarnings("NumberOfChildren")
 */
abstract class ReflectionType implements ReflectionTypeInterface
{
    const CLASS_NAME_REGEX = '/^\\\\?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\\)*[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';

    private static $TYPES = [
        'bool' => type\BooleanType::class,
        'false' => type\BooleanType::class,
        'true' => type\BooleanType::class,
        'boolean' => type\BooleanType::class,
        'int' => type\IntegerType::class,
        'string' => type\StringType::class,
        'integer' => type\IntegerType::class,
        'float' => type\FloatType::class,
        'double' => type\FloatType::class,
        'resource' => type\ResourceType::class,
        'callback' => type\CallableType::class,
        'callable' => type\CallableType::class,
        'void' => type\VoidType::class,
        'null' => type\NullType::class,
        'object' => type\ObjectType::class,
        'mixed' => type\MixedType::class,
        'number' => type\NumberType::class,
        'iterable' => type\IterableType::class,
    ];

    private static $SINGLETONS = [];

    /**
     * @var bool
     */
    private $allowsNull;

    /**
     * ReflectionType constructor.
     *
     * @param bool $allowsNull
     */
    public function __construct(bool $allowsNull = false)
    {
        $this->allowsNull = $allowsNull;
    }

    /**
     * @return bool
     */
    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    /**
     * Parses type string to type object.
     *
     * @param string $type
     *
     * @return ReflectionTypeInterface
     *
     * @throws \InvalidArgumentException if type is not valid
     */
    public static function forName(string $type): ReflectionTypeInterface
    {
        if (empty($type)) {
            throw new \InvalidArgumentException('Expected an type string, got empty string');
        }
        $allowsNull = false;
        if ('?' == $type[0]) {
            $type = substr($type, 1);
            $allowsNull = true;
        }

        if (preg_match('/(\[\])+$/', $type, $matches)) {
            $suffixLength = strlen($matches[0]);

            return new ArrayType(self::forName(substr($type, 0, -1 * $suffixLength)), $suffixLength / 2, $allowsNull);
        } elseif (preg_match(self::CLASS_NAME_REGEX, $type)) {
            return self::getSingletonType($type, $allowsNull);
        } else {
            throw new \InvalidArgumentException("Expected an type string, got '{$type}'");
        }
    }

    private static function getSingletonType($typeName, $allowsNull)
    {
        if (!isset(self::$SINGLETONS[$typeName][$allowsNull])) {
            if ('array' == $typeName) {
                $type = new ArrayType(new MixedType(), 1, $allowsNull);
            } elseif (isset(self::$TYPES[$typeName])) {
                $className = self::$TYPES[$typeName];
                $type = new $className($allowsNull);
            } else {
                $type = new ClassType($typeName, $allowsNull);
            }
            self::$SINGLETONS[$typeName][$allowsNull] = $type;
        }

        return self::$SINGLETONS[$typeName][$allowsNull];
    }

    protected function getDisplayString()
    {
        return $this->getName();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return ($this->allowsNull() ? '?' : '').$this->getDisplayString();
    }
}
