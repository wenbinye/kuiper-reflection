<?php

namespace kuiper\reflection;

class ClassResolverTest extends TestCase
{
    public function createResolver($file)
    {
        return new FqcnResolver(ReflectionFileFactory::createInstance()->create($file));
    }
    
    public function testResolveNotImported()
    {
        $file = $this->createResolver(__DIR__.'/fixtures/DummyClass.php');
        $this->assertEquals(
            'kuiper\\reflection\\fixtures\\DummyInterface',
            $file->resolve('DummyInterface', __NAMESPACE__.'\\fixtures')
        );
    }

    public function testResolveClassNameImported()
    {
        $file = $this->createResolver(__DIR__.'/fixtures/DummyInterface.php');
        $this->assertEquals(
            'kuiper\\reflection\\ReflectionFile',
            $file->resolve('ReflectionFile', __NAMESPACE__.'\\fixtures')
        );
    }

    public function testResolveClassNameNotExists()
    {
        $file = $this->createResolver(__DIR__.'/fixtures/DummyInterface.php');
        $this->assertEquals(
            'kuiper\reflection\fixtures\NonExistClass',
            $file->resolve('NonExistClass', __NAMESPACE__.'\\fixtures')
        );
    }

    public function testResolveClassNameMultipleNamespace()
    {
        $file = $this->createResolver(__DIR__.'/fixtures/MultipleNamespaces.php');
        $this->assertEquals(
            'NamespaceA\\ClassA',
            $file->resolve('ClassA', 'NamespaceB')
        );
        $this->assertEquals(
            'ClassC',
            $file->resolve('ClassC', '')
        );
    }
}
