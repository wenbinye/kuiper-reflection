<?php

namespace kuiper\reflection;

interface ReflectionTypeInterface
{
    /**
     * Gets type string.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Checks if null is allowed.
     *
     * @return bool
     */
    public function allowsNull(): bool;

    /**
     * @return string
     */
    public function __toString(): string;
}
