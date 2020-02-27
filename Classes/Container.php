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

namespace Neunerlei\ContainerDelegate;


use Neunerlei\ContainerDelegate\Instantiator\Instantiator;
use Neunerlei\ContainerDelegate\Instantiator\InstantiatorInterface;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface {
	/**
	 * The name of the instantiator implementation that should be used.
	 * @api
	 * @var string
	 */
	public static $instantiatorClass = Instantiator::class;
	
	/**
	 * First level cache to store already checked ids and the correct class for them
	 * @var array
	 */
	protected $idClassNameMap = [];
	
	/**
	 * The instance of the parent container that uses this container as a delegate
	 * This is used to backreference the main container when creating child objects
	 * @var ContainerInterface
	 */
	protected $parentContainer;
	
	/**
	 * The absolute path to the cache directory or null.
	 * The cache directory is used for the instantiator to compile the factories into a php file for faster loading.
	 * It this is null the instantiator will not compile the factories.
	 * @var string|null
	 */
	protected $cacheDirectoryPath;
	
	/**
	 * The instantiator instance to create the instances of services
	 * @var InstantiatorInterface
	 */
	protected $instantiator;
	
	/**
	 * Container constructor.
	 *
	 * @param string|null $cacheDirectoryPath The absolute path to the cache directory or null.
	 *                                        If NULL is given we will not compile the factories into a php file, this
	 *                                        should only be used in development!
	 */
	public function __construct(?string $cacheDirectoryPath = NULL) {
		$this->parentContainer = $this;
		$this->cacheDirectoryPath = $cacheDirectoryPath;
	}
	
	/**
	 * @inheritDoc
	 */
	public function has($id) {
		try {
			$this->resolveRealClassName($id);
			return TRUE;
		} catch (ContainerException $e) {
			return FALSE;
		}
	}
	
	/**
	 * @inheritDoc
	 */
	public function get($id, bool $new = FALSE) {
		
		// Convert the id into a class name that can be resolved by the instantiator
		$className = $this->resolveRealClassName($id);
		
		// Get or make the instance
		return $this->getInstantiator()
			->getOrMakeInstanceForClass($className, !$new);
	}
	
	/**
	 * Receives the id and tries to convert it into a resolvable class.
	 * It tries to auto-map interfaces that end with an "Interface" suffix to a class without the suffix.
	 *
	 * @param mixed $id The given id of the service to load. This should always be a class name
	 *
	 * @return string
	 * @throws \Neunerlei\ContainerDelegate\ContainerException
	 * @throws \Neunerlei\ContainerDelegate\ServiceNotFoundException
	 */
	protected function resolveRealClassName($id): string {
		// Check if the given id is valid
		if (!is_string($id))
			throw new ContainerException("The given service id has to be a string!");
		
		// Check if there is a factory for the id -> resolve to id
		if ($this->getInstantiator()->hasFactoryFor($id)) return $id;
		
		// Check if we know the id and can convert it into a class name
		if (isset($this->idClassNameMap[$id]))
			return $this->idClassNameMap[$id] === TRUE ? $id : $this->idClassNameMap[$id];
		
		// Check if we have a class for the id -> Direct resolvable
		if (class_exists($id)) {
			$this->idClassNameMap[$id] = TRUE;
			return $id;
		}
		
		// Check if there is an interface for the given id -> Try to find class implementation
		if (!interface_exists($id))
			throw new ServiceNotFoundException("There is neither a class nor an interface for the given id: $id");
		
		// Check if we CAN find a class implementation
		if (strtolower(substr($id, -9)) !== "interface")
			throw new ServiceNotFoundException("Could not resolve a class for the interface with the given id: $id");
		
		// Check if we HAVE a class implementation
		$idReal = substr($id, 0, -9);
		if (!class_exists($idReal))
			throw new ServiceNotFoundException("Could not resolve a class for the interface with the given id: $id, because the class $idReal does not exist!");
		
		// Store the id mapping and be done
		$this->idClassNameMap[$id] = $idReal;
		return $idReal;
	}
	
	/**
	 * Lazy loading helper to generate the instantiator instance when it is required.
	 * We do this as a lazy loader so child classes have the chance to update $cacheDirectoryPath or $parentContainer
	 * if required.
	 * @return \Neunerlei\ContainerDelegate\Instantiator\InstantiatorInterface
	 */
	protected function getInstantiator(): InstantiatorInterface {
		if (!isset($this->instantiator))
			$this->instantiator = new static::$instantiatorClass($this->cacheDirectoryPath, $this->parentContainer);
		return $this->instantiator;
	}
}