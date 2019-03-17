<?php

namespace kuiper\reflection\filter;

use kuiper\reflection\ReflectionType;

class ArrayTypeFilterTest extends \PHPUnit\Framework\TestCase
{
    public function testValidate()
    {
        $filter = new ArrayTypeFilter(ReflectionType::forName('int[]'));
        $this->assertTrue($filter->validate([1]));
        $this->assertFalse($filter->validate(['a']));
    }
}
