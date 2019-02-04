<?php

namespace kuiper\reflection;

use kuiper\reflection\exception\InvalidTokenException;
use kuiper\reflection\exception\TokenStoppedException;

class TokenStream
{
    /**
     * @var \Iterator
     */
    private $tokens;

    /**
     * @var array|string
     */
    private $current;

    /**
     * @var bool
     */
    private $end = false;

    /**
     * @var int
     */
    private $line = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = new \ArrayIterator($tokens);
    }

    /**
     * Gets next token.
     *
     * @return array|string
     *
     * @throws TokenStoppedException
     */
    public function next()
    {
        if ($this->end || !$this->tokens->valid()) {
            $this->end = true;
            throw new TokenStoppedException();
        }
        if (isset($this->current)) {
            $this->tokens->next();
        }
        $this->current = $this->tokens->current();
        if (is_array($this->current)) {
            $this->line = $this->current[2];
        }
        // error_log($this->describe($this->current));
        return $this->current;
    }

    /**
     * Gets current token.
     *
     * @return array|string
     */
    public function current()
    {
        if (!isset($this->current)) {
            throw new \BadMethodCallException('call next first');
        }

        return $this->current;
    }

    /**
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Skips whitespace or comment.
     *
     * @throws InvalidTokenException
     * @throws TokenStoppedException
     */
    public function skipWhitespaceAndCommentMaybe()
    {
        $this->skipWhitespaceAndComment(false);
    }

    /**
     * Skips whitespace and comment.
     * Stops when first token that is not whitespace or comment.
     *
     * @param bool $required
     *
     * @throws InvalidTokenException
     * @throws TokenStoppedException
     */
    public function skipWhitespaceAndComment(bool $required = true)
    {
        $whitespace = '';
        while (true) {
            if (is_array($this->current) && in_array($this->current[0], [T_WHITESPACE, T_COMMENT])) {
                $whitespace .= $this->current[1];
                $this->next();
            } else {
                break;
            }
        }
        if ($required && empty($whitespace)) {
            throw new InvalidTokenException('Expected whitespace');
        }
    }

    /**
     * Reads the identifiers at current position.
     * Stops when first token that not belong to identifier (not string or ns_separator).
     *
     * @return string
     *
     * @throws InvalidTokenException if there not identifier at current position
     * @throws TokenStoppedException
     */
    public function matchIdentifier(): string
    {
        $identifier = '';
        while (true) {
            if (is_array($this->current) && in_array($this->current[0], [T_STRING, T_NS_SEPARATOR])) {
                $identifier .= $this->current[1];
                $this->next();
            } else {
                break;
            }
        }

        if (empty($identifier)) {
            throw new InvalidTokenException('Expected identifier');
        }

        return $identifier;
    }

    /**
     * Reads use statement.
     *
     * @return array the import type is the first element: T_FUNCTION, T_CONST, T_STRING (class),
     *               the import list is the second element
     *
     * @throws InvalidTokenException
     * @throws TokenStoppedException
     */
    public function matchUseStatement()
    {
        $this->next();
        $this->skipWhitespaceAndComment();
        if (!is_array($this->current) || !in_array($this->current[0], [T_FUNCTION, T_CONST, T_STRING])) {
            throw new InvalidTokenException("expected class name or the keyword 'function' or 'const'");
        }
        $importType = $this->current[0];
        if (in_array($importType, [T_FUNCTION, T_CONST])) {
            $this->next();
            $this->skipWhitespaceAndComment();
        }
        $imports = $this->matchImportList(';');

        return [$importType, $imports];
    }

    /**
     * @param string $stopToken
     * @param bool   $hasSubList
     *
     * @return array
     *
     * @throws InvalidTokenException
     * @throws TokenStoppedException
     */
    private function matchImportList(string $stopToken, $hasSubList = true)
    {
        $imports = [];
        do {
            foreach ($this->matchUseList($hasSubList) as $alias => $name) {
                if (isset($imports[$alias])) {
                    throw new InvalidTokenException(sprintf(
                        "Duplicated import alias '%s' for '%s', previous '%s'",
                        $name, $alias, $imports[$alias]
                    ));
                }
                $imports[$alias] = $name;
            }
            $this->skipWhitespaceAndCommentMaybe();
            if (',' === $this->current) {
                $this->next();
                $this->skipWhitespaceAndCommentMaybe();
            } elseif ($this->current === $stopToken) {
                $this->next();
                break;
            } else {
                throw new InvalidTokenException('Expected comma or semicolon here');
            }
        } while (true);

        return $imports;
    }

    /**
     * @param bool $hasSubList
     *
     * @return array
     *
     * @throws InvalidTokenException
     * @throws TokenStoppedException
     */
    private function matchUseList(bool $hasSubList)
    {
        $imports = [];
        $name = $this->matchIdentifier();
        if ('{' === $this->current) {
            if (!$hasSubList) {
                throw new InvalidTokenException('Unexpected token');
            }
            $this->next();
            $imports = $this->matchImportList('}', false);
            $prefix = $name;
            foreach ($imports as $alias => $name) {
                $imports[$alias] = $prefix.$name;
            }
        } else {
            $this->skipWhitespaceAndCommentMaybe();
            if (is_array($this->current) && T_AS === $this->current[0]) {
                $this->next();
                $this->skipWhitespaceAndComment();
                $alias = $this->matchIdentifier();
                if (false !== strpos($alias, ReflectionFile::NAMESPACE_SEPARATOR)) {
                    throw new InvalidTokenException("import alias '{$alias}' cannot contain namespace separator");
                }
            } else {
                $alias = $this->getSimpleName($name);
            }
            $imports[$alias] = $name;
        }

        return $imports;
    }

    /**
     * match begin and end of parentheses.
     *
     * @throws TokenStoppedException
     * @throws InvalidTokenException
     */
    public function matchParentheses()
    {
        $stack = [];
        while (true) {
            $this->next();
            if (is_array($this->current) && T_CURLY_OPEN == $this->current[0]) {
                $stack[] = '{';
            } elseif ('{' === $this->current) {
                $stack[] = '{';
            } elseif ('}' === $this->current) {
                array_pop($stack);
                if (empty($stack)) {
                    break;
                }
            }
        }
        if (!empty($stack)) {
            throw new InvalidTokenException('parentheses not match');
        }
    }

    private function getSimpleName($name)
    {
        $parts = explode(ReflectionFile::NAMESPACE_SEPARATOR, $name);

        return end($parts);
    }

    /**
     * describes the token value.
     *
     * @param array|string $token
     *
     * @return string
     */
    public function describe($token)
    {
        if (is_array($token)) {
            return '['.implode(', ', [token_name($token[0]), json_encode($token[1]), $token[2]]).']';
        } else {
            return json_encode($token);
        }
    }
}
