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

namespace Neunerlei\ContainerDelegate\Instantiator;


use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringClassInterface;
use Neunerlei\ContainerDelegate\CircularDependencyException;
use Neunerlei\ContainerDelegate\Instantiator\Definition\AutoWiringClass;
use Psr\Container\ContainerInterface;

class Instantiator implements InstantiatorInterface {
	use FileWriterTrait;
	
	/**
	 * The name of the factory generator class that should be used.
	 * @api
	 * @var string
	 */
	public static $factoryGeneratorClass = FactoryGenerator::class;
	
	/**
	 * The list of resolved singleton instances that have already been created
	 * @var array
	 */
	protected $singletons = [];
	
	/**
	 * The instance of the container this instantiator is linked with
	 * @var \Neunerlei\ContainerDelegate\Container
	 */
	protected $container;
	
	/**
	 * Contains the instance of the factory generator or null if it was not instantiated yet
	 * @var FactoryGeneratorInterface|null
	 */
	protected $factoryGenerator;
	
	/**
	 * The directory path of the service definition cache.
	 * This is null if there was no cache directory registered
	 * @var string|null
	 */
	protected $cacheDirectoryPath;
	
	/**
	 * The filepath of the service definition cache
	 * @var string
	 */
	protected $cacheFilePath;
	
	/**
	 * The list of factories that are used to create object instances
	 * @var \Closure[]
	 */
	protected $factories = [];
	
	/**
	 * The list of auto wiring definitions that are registered
	 * @var AutoWiringClassInterface[]
	 */
	protected $definitions = [];
	
	/**
	 * Contains the path trough all instantiated classes to detect circular dependencies
	 * @var array|null
	 */
	protected $path;
	
	/**
	 * Instantiator constructor.
	 *
	 * @param string|null                       $cacheDirectoryPath  The absolute path to the cache directory or null.
	 *                                                               If NULL is given we will not compile the factories
	 *                                                               into a php file, this should only be used in
	 *                                                               development!
	 * @param \Psr\Container\ContainerInterface $container           The instance of the parent container
	 */
	public function __construct(?string $cacheDirectoryPath, ContainerInterface $container) {
		$this->container = $container;
		
		// Check if we can access the compiled factories
		if (!empty($cacheDirectoryPath)) {
			$this->cacheDirectoryPath = rtrim($cacheDirectoryPath, "\\/") . DIRECTORY_SEPARATOR;
			$this->cacheFilePath = $this->cacheDirectoryPath . "containerServiceDefinition.php";
			$this->validateFilePermissions($this->cacheFilePath);
			if (file_exists($this->cacheFilePath)) $this->factories = require $this->cacheFilePath;
		}
		
		// Validate the factories
		if (!is_array($this->factories)) $this->factories = [];
	}
	
	/**
	 * Dump the factories into a file when this object is destroyed
	 */
	public function __destruct() {
		// Ignore if caching is disabled
		if (empty($this->cacheFilePath)) return;
		
		// Check if we have to dump the factories into a file
		if (empty($this->factoryGenerator)) return;
		
		// Dump the factories to the file
		$this->factoryGenerator->dumpFactories($this->cacheFilePath);
	}
	
	/**
	 * @inheritDoc
	 */
	public function setAutoWiringDefinition(AutoWiringClassInterface $definition): InstantiatorInterface {
		$this->definitions[$definition->getName()] = $definition;
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getAutoWiringDefinition(string $className): AutoWiringClassInterface {
		if (!isset($this->definitions[$className]))
			$this->definitions[$className] = new AutoWiringClass($className);
		return $this->definitions[$className];
	}
	
	/**
	 * @inheritDoc
	 */
	public function hasFactoryFor(string $id): bool {
		return isset($this->factories[$id]) &&
			isset($this->factories[$id]["factory"]) &&
			is_callable($this->factories[$id]["factory"]);
	}
	
	/**
	 * @inheritDoc
	 */
	public function getOrMakeInstanceForClass(string $className, bool $useSingletonStorage) {
		
		// Check if we can handle a singleton
		if ($useSingletonStorage && isset($this->singletons[$className]))
			return $this->singletons[$className];
		
		// Validate the path
		$isNewPath = FALSE;
		if (!is_array($this->path)) {
			$isNewPath = TRUE;
			$this->path = [];
		} else if (in_array($className, $this->path))
			throw new CircularDependencyException("Error while resolving the instance of class: " . reset($this->path) . " because there is a circular dependency: " .
				implode(" -> ", $this->path));
		$this->path[] = $className;
		
		// Check if we have a factory or create one
		$i = NULL;
		if (!isset($this->factories[$className])) {
			// Check if the instantiator is requested
			if ($className === get_called_class() || $className === Instantiator::class || $className === InstantiatorInterface::class)
				$i = $this;
			
			// Make sure we have a factory
			else
				$this->getFactoryGenerator()->ensureRequiredFactoriesFor($this->getAutoWiringDefinition($className));
		}
		
		// Check if we got an instance
		if (is_null($i)) {
			
			// Instantiate the class using the factory
			$i = call_user_func($this->factories[$className]["factory"], $this->container);
			
			// Store singleton
			if ($useSingletonStorage && $this->factories[$className]["isSingleton"])
				$this->singletons[$className] = $i;
			
		}
		
		// Revert the path
		array_pop($this->path);
		if ($isNewPath) $this->path = NULL;
		
		// Done
		return $i;
	}
	
	/**
	 * Lazy loading helper to create the factory generator instance only if its required
	 * @return FactoryGeneratorInterface
	 */
	protected function getFactoryGenerator(): FactoryGeneratorInterface {
		if (!isset($this->factoryGenerator)) $this->factoryGenerator =
			new static::$factoryGeneratorClass($this->factories, $this->cacheDirectoryPath);
		return $this->factoryGenerator;
	}
}