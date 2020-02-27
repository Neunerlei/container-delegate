<?php
/**
 * Copyright 2020 Martin Neundorfer (Neunerlei)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2020.02.27 at 12:27
 */

namespace Neunerlei\ContainerDelegate\Tests;


use FilesystemIterator;
use Neunerlei\ContainerDelegate\CircularDependencyException;
use Neunerlei\ContainerDelegate\Container;
use Neunerlei\ContainerDelegate\Instantiator\AbstractLazyLoadingProxyObject;
use Neunerlei\ContainerDelegate\Instantiator\AutoWiringException;
use Neunerlei\ContainerDelegate\Instantiator\Instantiator;
use Neunerlei\ContainerDelegate\Instantiator\InstantiatorInterface;
use Neunerlei\ContainerDelegate\Tests\Assets\AbstractDummyClass;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyCircularA;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassA;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassAInterface;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassB;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassC;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassD;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassE;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassF;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyClassInterface;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyInjectableClass;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyLazyClass;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyLazyClassInterface;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyLazyParentClass;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyNonInterfaceLazyClass;
use Neunerlei\ContainerDelegate\Tests\Assets\DummyStaticInjectableClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ContainerTest extends TestCase {
	/**
	 * @inheritDoc
	 */
	public static function setUpBeforeClass(): void {
		static::removeCacheDirectory();
		@mkdir(__DIR__ . "/testCache/", 0777, TRUE);
	}
	
	/**
	 * @inheritDoc
	 */
	public static function tearDownAfterClass(): void {
		static::removeCacheDirectory();
	}
	
	protected static function removeCacheDirectory() {
		$directory = __DIR__ . "/testCache/";
		if (!file_exists($directory)) return;
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($it as $child) {
			if (!file_exists($child->getRealPath())) continue;
			if ($child->isDir()) rmdir($child->getRealPath());
			else unlink($child->getRealPath());
		}
		clearstatcache();
		rmdir($directory);
		clearstatcache();
	}
	
	public function testAutoloader() {
		$this->assertTrue(class_exists(Container::class));
	}
	
	public function testInterface() {
		$this->assertInstanceOf(ContainerInterface::class, $this->getContainer());
	}
	
	public function testHas() {
		$this->assertFalse($this->getContainer()->has("foo"));
		/** @noinspection PhpUndefinedClassInspection */
		/** @noinspection PhpUndefinedNamespaceInspection */
		$this->assertFalse($this->getContainer()->has(Neunerlei\FooPackage\FooClass::class));
		$this->assertTrue($this->getContainer()->has(Container::class));
		$this->assertTrue($this->getContainer()->has(DummyClassA::class));
		$this->assertFalse($this->getContainer()->has(DummyClassInterface::class));
		$this->assertTrue($this->getContainer()->has(DummyClassAInterface::class));
		$this->assertTrue($this->getContainer()->has(ContainerTest::class));
	}
	
	public function testWithoutInjection() {
		$i = $this->getContainer()->get(DummyClassA::class);
		$this->assertInstanceOf(DummyClassA::class, $i);
	}
	
	public function testInterfaceAutoResolving() {
		$i = $this->getContainer()->get(DummyClassAInterface::class);
		$this->assertInstanceOf(DummyClassA::class, $i);
	}
	
	public function testInterfaceAutoResolvingFail() {
		$this->expectException(NotFoundExceptionInterface::class);
		$this->getContainer()->get(DummyClassInterface::class);
	}
	
	public function testConstructorInjection() {
		$i = $this->getContainer()->get(DummyClassC::class);
		$this->assertInstanceOf(DummyClassC::class, $i);
		$this->assertInstanceOf(DummyClassA::class, $i->a);
		$this->assertInstanceOf(DummyClassB::class, $i->b);
	}
	
	public function testComplexConstructorInjection() {
		$i = $this->getContainer()->get(DummyClassF::class);
		$this->assertInstanceOf(DummyClassF::class, $i);
		$this->assertInstanceOf(DummyClassA::class, $i->a);
		$this->assertInstanceOf(DummyClassD::class, $i->d);
		$this->assertEquals("bar", $i->bar);
		$this->assertEquals("baz", $i->baz);
		$this->assertNull($i->empty);
		
		// Test nested injection
		$d = $i->d;
		$this->assertInstanceOf(DummyClassE::class, $d->e);
	}
	
	public function testCircularInjection() {
		$this->expectException(CircularDependencyException::class);
		$this->getContainer()->get(DummyCircularA::class);
	}
	
	public function testIfStaticInjetorsAreIgnored() {
		$i = $this->getContainer()->get(DummyStaticInjectableClass::class);
		$this->assertInstanceOf(DummyStaticInjectableClass::class, $i);
		$this->assertInstanceOf(DummyClassD::class, $i->d);
	}
	
	public function testIfAbstractClassInstantiationFails() {
		$this->expectException(AutoWiringException::class);
		$this->getContainer()->get(AbstractDummyClass::class);
	}
	
	public function testLazyLoadingProxyGeneration() {
		$i = $this->getContainer()->get(DummyLazyParentClass::class);
		$this->assertInstanceOf(DummyLazyClassInterface::class, $i->lazyClass);
		$this->assertInstanceOf(AbstractLazyLoadingProxyObject::class, $i->lazyClass);
		$this->assertNotInstanceOf(DummyLazyClass::class, $i->lazyClass);
		
		// Test internal functions
		/** @var AbstractLazyLoadingProxyObject $proxy */
		$proxy = $i->lazyClass;
		$this->assertInstanceOf(DummyLazyClass::class, $proxy->__getInstance());
		$this->assertEquals(DummyLazyClassInterface::class, $proxy->__getRealClassName());
		
		// Check if we always get the same instance
		$tmp = $i->lazyClass;
		$this->assertEquals($tmp, $i->lazyClass);
		
		// Test lazy loading real object resolution
		$this->assertInstanceOf(DummyInjectableClass::class, $i->lazyClass->getInjectableClass());
		$this->assertInstanceOf(DummyClassC::class, $i->lazyClass->getInjectableClass()->c);
		$this->assertInstanceOf(DummyClassD::class, $i->lazyClass->getInjectableClass()->d);
	}
	
	public function testIfNonInterfaceLazyLoadsAreIgnored() {
		$i = $this->getContainer()->get(DummyNonInterfaceLazyClass::class);
		$this->assertInstanceOf(DummyClassA::class, $i->lazyClassA);
	}
	
	public function testSingletonResolution() {
		$c = $this->getContainer();
		$i1 = $c->get(DummyClassD::class);
		$this->assertSame($i1, $c->get(DummyClassD::class));
		
		// Check forced new instance
		$this->assertNotSame($i1, $c->get(DummyClassD::class, TRUE));
	}
	
	public function testInstantiatorResolution() {
		$c = $this->getContainer();
		$i1 = $c->get(InstantiatorInterface::class);
		$this->assertEquals($i1, $c->get(Instantiator::class));
		
		// Check if internal instantiator is returned -> Only if we test the container itself and not the delegate
		if ($c instanceof Container) {
			$ref = new \ReflectionObject($c);
			$ip = $ref->getProperty("instantiator");
			$ip->setAccessible(TRUE);
			$this->assertEquals($i1, $ip->getValue($c));
		}
	}
	
	public function testCacheGeneration() {
		$cachePath = __DIR__ . "/testCache/";
		$container = $this->getContainer($cachePath);
		$container->get(DummyClassA::class);
		$container->get(Instantiator::class)->__destruct();
		
		// Test cache writing
		$cacheFile = $cachePath . "containerServiceDefinition.php";
		$this->assertFileExists($cacheFile);
		
		$cacheContent = require $cacheFile;
		$this->assertIsArray($cacheContent);
		$checkSimpleCache = function ($cacheContent) {
			$this->assertArrayHasKey(DummyClassA::class, $cacheContent);
			$this->assertIsArray($cacheContent[DummyClassA::class]);
			$this->assertArrayHasKey("isSingleton", $cacheContent[DummyClassA::class]);
			$this->assertArrayHasKey("factory", $cacheContent[DummyClassA::class]);
			$this->assertIsBool($cacheContent[DummyClassA::class]["isSingleton"]);
			$this->assertInstanceOf(\Closure::class, $cacheContent[DummyClassA::class]["factory"]);
		};
		$checkSimpleCache($cacheContent);
		
		$i = call_user_func($cacheContent[DummyClassA::class]["factory"], $container);
		$this->assertInstanceOf(DummyClassA::class, $i);
		
		// Test cache lookup
		$container2 = $this->getContainer($cachePath);
		$instantiator = $container2->get(Instantiator::class);
		$this->assertTrue($instantiator->hasFactoryFor(DummyClassA::class));
		$this->assertFalse($instantiator->hasFactoryFor(DummyClassB::class));
		$this->assertInstanceOf(DummyClassA::class, $container2->get(DummyClassA::class));
		
		// Test lazy proxy writing
		$container2->get(DummyLazyParentClass::class);
		$this->assertTrue($instantiator->hasFactoryFor(DummyLazyParentClass::class));
		$proxyKey = "@lazyLoadingProxy." . DummyLazyClassInterface::class;
		$this->assertTrue($instantiator->hasFactoryFor($proxyKey));
		$instantiator->__destruct();
		
		$cacheContent = require $cacheFile;
		$this->assertIsArray($cacheContent);
		$this->assertArrayHasKey($proxyKey, $cacheContent);
		$this->assertIsArray($cacheContent[$proxyKey]);
		$this->assertArrayHasKey("isSingleton", $cacheContent[$proxyKey]);
		$this->assertArrayHasKey("factory", $cacheContent[$proxyKey]);
		$this->assertIsBool($cacheContent[$proxyKey]["isSingleton"]);
		$this->assertInstanceOf(\Closure::class, $cacheContent[$proxyKey]["factory"]);
		
		// We should still have the definitions from the first writing test, so check it again
		$checkSimpleCache($cacheContent);
	}
	
	protected function getContainer(?string $cachePath = NULL): ContainerInterface {
		return new Container($cachePath);
	}
	
}