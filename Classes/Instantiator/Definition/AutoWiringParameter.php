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


use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringMethodInterface;
use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringParameterInterface;
use Neunerlei\ContainerDelegate\Instantiator\AutoWiringException;
use ReflectionNamedType;
use ReflectionParameter;

class AutoWiringParameter implements AutoWiringParameterInterface {
	
	/**
	 * The name of the parameter
	 * @var string
	 */
	protected $name;
	
	/**
	 * True if there is a defined interface or class type for this parameter
	 * @var bool
	 */
	protected $hasType = FALSE;
	
	/**
	 * @var \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringMethodInterface
	 */
	protected $method;
	
	/**
	 * If the parameter has a type it is stored here
	 * @var string|null
	 */
	protected $type;
	
	/**
	 * True if the parameter should use a lazy loading proxy when injected
	 * @var bool
	 */
	protected $isLazy = FALSE;
	
	/**
	 * True if a default value exists for this parameter
	 * @var bool
	 */
	protected $hasDefaultValue = FALSE;
	
	/**
	 * Contains the default value for this parameter if one exists
	 * @var mixed|null
	 */
	protected $defaultValue;
	
	/**
	 * AutoWiringParameter constructor.
	 *
	 * @param \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringMethodInterface $method
	 * @param \ReflectionParameter                                                           $ref
	 *
	 * @throws \Neunerlei\ContainerDelegate\Instantiator\AutoWiringException
	 */
	public function __construct(AutoWiringMethodInterface $method, ReflectionParameter $ref) {
		$this->method = $method;
		$this->name = $ref->getName();
		
		// Check if we have a builtin type -> it gets dangerous
		$type = $ref->getType();
		if (empty($type) || $type->isBuiltin()) {
			
			// Check if a default value is given
			if ($ref->isDefaultValueAvailable()) {
				$this->hasDefaultValue = TRUE;
				$this->defaultValue = $ref->getDefaultValue();
				return;
			}
			
			// Check if the argument allows null, yes? Skip it with NULL
			if ($type->allowsNull()) {
				$this->hasDefaultValue = TRUE;
				$this->defaultValue = NULL;
				return;
			}
			
			// Die
			throw new AutoWiringException("Failed to generate auto wiring definition for class: {$ref->getDeclaringClass()->getName()}. Because the argument: {$ref->getName()} of method: {$ref->getDeclaringFunction()->getName()} could not be auto-wired!");
			
		}
		
		// We have a class type
		$type = $type instanceof ReflectionNamedType ? $type->getName() : (string)$type;
		$this->hasType = TRUE;
		
		// Check if this is a lazy parameter
		if (substr($ref->getName(), 0, 4) === "lazy" && interface_exists($type))
			$this->isLazy = TRUE;
		
		// Store the type
		$this->type = $type;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getMethod(): AutoWiringMethodInterface {
		return $this->method;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->name;
	}
	
	/**
	 * @inheritDoc
	 */
	public function hasType(): bool {
		return $this->hasType;
	}
	
	
	/**
	 * @inheritDoc
	 */
	public function getType(): ?string {
		if (!$this->hasType()) return NULL;
		return $this->type;
	}
	
	/**
	 * @inheritDoc
	 */
	public function isLazy(): bool {
		return $this->isLazy;
	}
	
	/**
	 * @inheritDoc
	 */
	public function hasDefaultValue(): bool {
		return $this->hasDefaultValue;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getDefaultValue() {
		return $this->defaultValue;
	}
}