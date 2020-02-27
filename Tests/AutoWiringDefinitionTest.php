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

namespace Neunerlei\ContainerDelegate\Tests\Assets;


use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringParameterInterface;
use Neunerlei\ContainerDelegate\Instantiator\Definition\AutoWiringClass;
use Neunerlei\ContainerDelegate\Instantiator\Definition\AutoWiringParameter;
use PHPUnit\Framework\TestCase;

class AutoWiringDefinitionTest extends TestCase {
	
	public function testSimpleClass() {
		$i = new AutoWiringClass(DummyClassA::class);
		$this->assertEquals(DummyClassA::class, $i->getName());
		$this->assertEmpty($i->getInjectMethods());
		$this->assertIsArray($i->getInjectMethods());
		$this->assertEmpty($i->getConstructorParams());
		$this->assertIsArray($i->getConstructorParams());
		$this->assertFalse($i->isSingleton());
	}
	
	public function testClassWithConstructor() {
		$i = new AutoWiringClass(DummyClassC::class);
		$this->assertEquals(DummyClassC::class, $i->getName());
		$this->assertEmpty($i->getInjectMethods());
		$this->assertNotEmpty($i->getConstructorParams());
		$this->assertFalse($i->isSingleton());
		$this->assertContainsOnlyInstancesOf(AutoWiringParameterInterface::class, $i->getConstructorParams());
		$this->assertContainsOnlyInstancesOf(AutoWiringParameter::class, $i->getConstructorParams());
		$this->assertEquals(2, count($i->getConstructorParams()));
		
		/** @var AutoWiringParameterInterface $p */
		$params = $i->getConstructorParams();
		$p = array_shift($params);
		$this->assertEquals("a", $p->getName());
		$this->assertTrue($p->hasType());
		$this->assertEquals(DummyClassA::class, $p->getType());
		$this->assertFalse($p->isLazy());
		$this->assertEquals("__construct", $p->getMethod()->getName());
		$this->assertEquals($i, $p->getMethod()->getClass());
		$this->assertFalse($p->hasDefaultValue());
		$this->assertNull($p->getDefaultValue());
		$this->assertArrayHasKey($p->getName(), $i->getConstructorParams());
		
		/** @var AutoWiringParameterInterface $p */
		$p = array_shift($params);
		$this->assertEquals("b", $p->getName());
		$this->assertTrue($p->hasType());
		$this->assertEquals(DummyClassB::class, $p->getType());
		$this->assertFalse($p->isLazy());
		$this->assertFalse($p->hasDefaultValue());
		$this->assertNull($p->getDefaultValue());
		$this->assertArrayHasKey($p->getName(), $i->getConstructorParams());
		
	}
	
	public function testClassWithComplexParams() {
		$i = new AutoWiringClass(DummyClassF::class);
		$expected = [
			[
				"name"            => "a",
				"hasType"         => TRUE,
				"type"            => DummyClassAInterface::class,
				"hasDefaultValue" => FALSE,
				"defaultValue"    => NULL,
				"isLazy"          => FALSE,
			],
			[
				"name"            => "d",
				"hasType"         => TRUE,
				"type"            => DummyClassD::class,
				"hasDefaultValue" => FALSE,
				"defaultValue"    => NULL,
				"isLazy"          => FALSE,
			],
			[
				"name"            => "bar",
				"hasType"         => FALSE,
				"type"            => NULL,
				"hasDefaultValue" => TRUE,
				"defaultValue"    => "bar",
				"isLazy"          => FALSE,
			],
			[
				"name"            => "baz",
				"hasType"         => FALSE,
				"type"            => NULL,
				"hasDefaultValue" => TRUE,
				"defaultValue"    => "baz",
				"isLazy"          => FALSE,
			],
			[
				"name"            => "empty",
				"hasType"         => FALSE,
				"type"            => NULL,
				"hasDefaultValue" => TRUE,
				"defaultValue"    => NULL,
				"isLazy"          => FALSE,
			],
		];
		foreach ($i->getConstructorParams() as $param) {
			$expectedValue = array_shift($expected);
			$this->assertEquals($expectedValue["name"], $param->getName());
			$this->assertEquals($expectedValue["hasType"], $param->hasType());
			$this->assertEquals($expectedValue["type"], $param->getType());
			$this->assertEquals($expectedValue["hasDefaultValue"], $param->hasDefaultValue());
			$this->assertEquals($expectedValue["defaultValue"], $param->getDefaultValue());
			$this->assertEquals($expectedValue["isLazy"], $param->isLazy());
		}
	}
	
	public function testLazyInjection() {
		$i = new AutoWiringClass(DummyLazyParentClass::class);
		$param = $i->getConstructorParams()["lazyClass"];
		$this->assertTrue($param->isLazy());
	}
	
	public function testWithInjectionMethods() {
		$i = new AutoWiringClass(DummyInjectableClass::class);
		$this->assertEquals(1, count($i->getInjectMethods()));
		$this->assertArrayHasKey("injectDummyClass", $i->getInjectMethods());
		$method = $i->getInjectMethods()["injectDummyClass"];
		$this->assertEquals(1, count($method->getParameters()));
		$this->assertEquals($i, $method->getClass());
		$this->assertEquals("injectDummyClass", $method->getName());
		
		/** @var AutoWiringParameterInterface $p */
		$params = $method->getParameters();
		$p = array_shift($params);
		$this->assertEquals("d", $p->getName());
		$this->assertTrue($p->hasType());
		$this->assertEquals(DummyClassD::class, $p->getType());
		$this->assertFalse($p->isLazy());
		$this->assertEquals("injectDummyClass", $p->getMethod()->getName());
		$this->assertEquals($i, $p->getMethod()->getClass());
		$this->assertFalse($p->hasDefaultValue());
		$this->assertNull($p->getDefaultValue());
		$this->assertArrayHasKey($p->getName(), $method->getParameters());
	}
}