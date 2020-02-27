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
use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringMethodInterface;
use ReflectionMethod;

class AutoWiringMethod implements AutoWiringMethodInterface {
	
	/**
	 * The class that is used when new auto-wiring parameter instances are created
	 * @var string
	 */
	public static $autoWiringParameterClass = AutoWiringParameter::class;
	
	/**
	 * @var \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringClassInterface
	 */
	protected $class;
	
	/**
	 * @var \ReflectionMethod
	 */
	protected $ref;
	
	/**
	 * The list of resolved parameters
	 * @var array|null
	 */
	protected $parameters;
	
	/**
	 * AutoWiringMethod constructor.
	 *
	 * @param \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringClassInterface $class
	 * @param \ReflectionMethod                                                             $ref
	 */
	public function __construct(AutoWiringClassInterface $class, ReflectionMethod $ref) {
		$this->class = $class;
		$this->ref = $ref;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getClass(): AutoWiringClassInterface {
		return $this->class;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->ref->getName();
	}
	
	/**
	 * @inheritDoc
	 */
	public function getParameters(): array {
		if (isset($this->parameters)) return $this->parameters;
		$params = [];
		foreach ($this->ref->getParameters() as $parameter)
			$params[$parameter->getName()] = new static::$autoWiringParameterClass($this, $parameter);
		return $this->parameters = $params;
	}
}