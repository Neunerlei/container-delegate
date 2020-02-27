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
 * Last modified: 2020.02.27 at 12:18
 */

namespace Neunerlei\ContainerDelegate\Instantiator\Definition;


use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringClassInterface;
use Neunerlei\ContainerAutoWiringDeclaration\InjectableInterface;
use Neunerlei\ContainerAutoWiringDeclaration\SingletonInterface;
use Neunerlei\ContainerDelegate\Instantiator\AutoWiringException;
use ReflectionClass;

class AutoWiringClass implements AutoWiringClassInterface {
	
	/**
	 * The class that is used when new auto-wiring method instances are created
	 * @var string
	 */
	public static $autoWiringMethodClass = AutoWiringMethod::class;
	
	/**
	 * The name of the class that is described by this object
	 * @var string
	 */
	protected $className;
	
	/**
	 * The list of constructor parameters
	 * @var \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringParameterInterface[]
	 */
	protected $constructorParams;
	
	/**
	 * The list of inject methods we should execute
	 * @var \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringMethodInterface[]
	 */
	protected $injectMethods;
	
	/**
	 * AutoWiringClass constructor.
	 *
	 * @param string $className
	 *
	 * @throws \Neunerlei\ContainerDelegate\Instantiator\AutoWiringException
	 */
	public function __construct(string $className) {
		if (!class_exists($className) && !interface_exists($className))
			throw new AutoWiringException("Failed to create a new auto wiring definition for class: $className, because the class does not exist!");
		if ((new ReflectionClass($className))->isAbstract())
			throw new AutoWiringException("Failed to create new auto wiring definition for class: $className, because it is marked as abstract!");
		$this->className = $className;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->className;
	}
	
	/**
	 * @inheritDoc
	 */
	public function isSingleton(): bool {
		return in_array(SingletonInterface::class, class_implements($this->className));
	}
	
	/**
	 * @inheritDoc
	 */
	public function getConstructorParams(): array {
		if (isset($this->constructorParams)) return $this->constructorParams;
		$constructor = (new ReflectionClass($this->getName()))->getConstructor();
		if (empty($constructor)) return $this->constructorParams = [];
		/** @noinspection PhpUndefinedMethodInspection */
		return $this->constructorParams = (new static::$autoWiringMethodClass($this, $constructor))->getParameters();
	}
	
	/**
	 * @inheritDoc
	 */
	public function getInjectMethods(): array {
		if (isset($this->injectMethods)) return $this->injectMethods;
		if (!in_array(InjectableInterface::class, class_implements($this->getName()))) return [];
		$ref = new ReflectionClass($this->getName());
		$methods = [];
		foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->isStatic()) continue;
			if (substr($method->getName(), 0, 6) === "inject")
				$methods[$method->getName()] = new static::$autoWiringMethodClass($this, $method);
		}
		
		return $methods;
	}
}