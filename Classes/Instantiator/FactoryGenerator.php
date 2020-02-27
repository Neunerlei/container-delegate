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


use Closure;
use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringClassInterface;
use Psr\Container\ContainerInterface;

class FactoryGenerator implements FactoryGeneratorInterface {
	use FileWriterTrait;
	
	/**
	 * The key in the factory list that is used to store the string version of the source code.
	 */
	public const SOURCE_KEY = "@source";
	
	/**
	 * The name of the class that is used to generate the lazy loading proxy classes
	 * @var string
	 */
	public static $lazyLoadingProxyGeneratorClass = LazyLoadingProxyGenerator::class;
	
	/**
	 * The instance that is used to generate the lazy loading class source code
	 * @var \Neunerlei\ContainerDelegate\Instantiator\LazyLoadingProxyGenerator
	 */
	protected $lazyLoadingProxyGenerator;
	
	/**
	 * The path to the directory were we should store temporary factory files
	 * @var string|null
	 */
	protected $cacheDirectoryPath;
	
	/**
	 * Contains the list of all known factories of the instantiator object
	 * @var array
	 */
	protected $factories = [];
	
	/**
	 * This is true if we can use the eval method to convert the closure source to a closure
	 * @var bool
	 */
	protected $canUseEval = FALSE;
	
	/**
	 * This is true when we can use gzcompress to compress the source code of the compiled factories
	 * @var bool
	 */
	protected $canUseSourceCompression = FALSE;
	
	/**
	 * @inheritDoc
	 */
	public function __construct(array &$factories, ?string $cacheDirectoryPath) {
		$this->factories = &$factories;
		$this->canUseSourceCompression = function_exists("gzcompress");
		
		// Prepare cache directory
		if (!empty($cacheDirectoryPath)) {
			$this->validateDirectoryPermissions($cacheDirectoryPath);
			$this->cacheDirectoryPath = rtrim($cacheDirectoryPath, "\\/") . DIRECTORY_SEPARATOR;
		}
		
		// Check if we can use eval
		$useEval = FALSE;
		@eval("\$useEval = true;");
		$this->canUseEval = $useEval === TRUE;
		
		// Make sure we have a source key
		if (!isset($factories[static::SOURCE_KEY])) $factories[static::SOURCE_KEY] = "";
		
	}
	
	/**
	 * @inheritDoc
	 */
	public function ensureRequiredFactoriesFor(AutoWiringClassInterface $class): void {
		// Ignore this if we already know the factory for this type
		if (isset($this->factories[$class->getName()])) return;
		
		// Build the factory
		$factoryString = $this->getFactoryStringFor($class);
		$this->factories[$class->getName()] = [
			"factory"     => $this->convertFactoryStringToClosure($factoryString),
			"isSingleton" => $class->isSingleton(),
		];
		
		// Store the source code
		$this->addToSource($class->getName(), $factoryString);
	}
	
	/**
	 * @inheritDoc
	 */
	public function dumpFactories(string $filePath): void {
		
		// Skip if we don't cache the compiled container
		if (empty($this->cacheDirectoryPath)) return;
		if (empty($this->factories[static::SOURCE_KEY]) || !is_string($this->factories[static::SOURCE_KEY])) return;
		
		// Split up source into old and new entries
		$parts = explode("::NEW::", $this->factories[static::SOURCE_KEY]);
		
		// Load the existing sources
		$sources = [];
		if (!empty($parts[0])) {
			$source = base64_decode($parts[0]);
			if ($this->canUseSourceCompression) /** @noinspection PhpComposerExtensionStubsInspection */
				$source = gzuncompress($source);
			$sources = json_decode($source, TRUE);
		}
		
		// Append new parts
		array_shift($parts);
		foreach ($parts as $part) {
			[$key, $source] = explode("::", $part);
			$source = base64_decode($source);
			$sources[$key] = $source;
		}
		
		// Pack the sources
		$sourceString = json_encode($sources);
		if ($this->canUseSourceCompression) /** @noinspection PhpComposerExtensionStubsInspection */
			$sourceString = gzcompress($sourceString);
		$sourceString = base64_encode($sourceString);
		
		// Compile the factory list
		$lines = [];
		foreach ($sources as $key => $factorySource) {
			if (!isset($this->factories[$key])) continue;
			$lines[] = "\"$key\" => [" . PHP_EOL .
				"		\"factory\" => " . str_replace(PHP_EOL, PHP_EOL . "		", $factorySource) . "," . PHP_EOL .
				"		\"isSingleton\" => " . ($this->factories[$key]["isSingleton"] ? "TRUE" : "FALSE") . "]";
			
		}
		$lines = implode("," . PHP_EOL . "	", $lines);
		$content = "<?php
return [
	\"" . static::SOURCE_KEY . "\" => '$sourceString',
	$lines
];";
		
		// Write the contents
		$this->writeFile($filePath, $content);
	}
	
	/**
	 * Internal helper to make sure that a factory of a lazy loading proxy exists.
	 *
	 * @param string $type          The proxy identifier to call the factory with
	 * @param string $interfaceName The name of the interface a proxy should be instantiated for.
	 */
	protected function ensureLazyLoadingProxyFactory(string $type, string $interfaceName): void {
		// Ignore this if we already know the factory for this type
		if (isset($this->factories[$type])) return;
		
		// Build the factory
		$factoryString = $this->getLazyLoadingProxyGenerator()->getLazyLoadingProxyString($interfaceName);
		$factoryString = $this->wrapFactoryStringWithClosure($factoryString);
		$this->factories[$type] = [
			"factory"     => $this->convertFactoryStringToClosure($factoryString),
			"isSingleton" => FALSE,
		];
		
		// Store the source code
		$this->addToSource($type, $factoryString);
	}
	
	/**
	 * Internal helper to add a new factory source code to the list of already existing source codes.
	 * We do this to be able to extend the compiled source code if new functions are discovered.
	 * The sources injected by this method will be dumped into a file when the instantiator object is destroyed.
	 *
	 * @param string $key     The class/interface name or the lazy loading proxy identifier to store the source for
	 * @param string $content The string version of the factory function to store
	 */
	protected function addToSource(string $key, string $content): void {
		// Skip if we don't cache the compiled container
		if (empty($this->cacheDirectoryPath)) return;
		
		// We just append the new content as base64 string, the compression will be handled when the compiled file will be dumped
		if (empty($this->factories[static::SOURCE_KEY])) $this->factories[static::SOURCE_KEY] = "";
		$this->factories[static::SOURCE_KEY] .= "::NEW::" . $key . "::" . base64_encode($content);
		return;
	}
	
	/**
	 * Gathers a list of methods and their arguments to instantiate the class described in the given definition.
	 * It returns the php source code of the factory function as a string
	 *
	 * @param AutoWiringClassInterface $definition
	 *
	 * @return string
	 */
	protected function getFactoryStringFor(AutoWiringClassInterface $definition): string {
		
		// Build constructor arguments
		$constructorArgs = $this->getInjectMethodParameterList($definition->getConstructorParams());
		
		// Build injectable method calls
		$methodCalls = [];
		foreach ($definition->getInjectMethods() as $method)
			$methodCalls[$method->getName()] = $this->getInjectMethodParameterList($method->getParameters());
		
		// Generate the source code
		$source = [];
		
		// Build constructor
		$source[] = "	\$i = new \\" . $definition->getName() . "(";
		$source[] = "		" . implode(", " . PHP_EOL . "		", $constructorArgs);
		$source[] = "	);";
		
		// Build method calls
		foreach ($methodCalls as $method => $methodArgs) {
			$source[] = "	\$i->" . $method . "(";
			$source[] = "		" . implode(", " . PHP_EOL . "		", $methodArgs);
			$source[] = "	);";
		}
		
		// Combine source
		$source[] = "	return \$i;";
		$source = implode(PHP_EOL, $source);
		$source = $this->wrapFactoryStringWithClosure($source);
		
		// Done
		return $source;
	}
	
	/**
	 * Receives the list of parameters that should be passed to a method and builds the php source string
	 * for each of them. The result is an array of parameter values.
	 *
	 * @param \Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringParameterInterface[] $params
	 *
	 * @return array
	 * @throws \Neunerlei\ContainerDelegate\Instantiator\AutoWiringException
	 */
	protected function getInjectMethodParameterList(array $params): array {
		$list = [];
		foreach ($params as $param) {
			
			// Inject simple values that have no type
			if (!$param->hasType()) {
				if (!$param->hasDefaultValue())
					throw new AutoWiringException("Failed to build factory, because property: {$param->getName()} has neither a default value nor a mappable type!");
				$list[] = var_export($param->getDefaultValue(), TRUE);
				continue;
			}
			
			// Prepare the container request
			$requestType = $param->getType();
			
			// Make lazy loading proxy
			if ($param->isLazy()) {
				$requestType = "@lazyLoadingProxy." . $requestType;
				$this->ensureLazyLoadingProxyFactory($requestType, $param->getType());
				$requestType = "\"$requestType\"";
			} else {
				$requestType .= "::class";
			}
			
			// Store default lookup using the container
			$list[$param->getName()] = "\$container->get($requestType)";
		}
		
		// Done
		return $list;
	}
	
	/**
	 * Helper to wrap the given closure content into a function signature that defines the container as parameter.
	 *
	 * @param string $content The source code body of the factory function
	 *
	 * @return string
	 */
	protected function wrapFactoryStringWithClosure(string $content): string {
		return "function(" . ContainerInterface::class . " \$container) {" . PHP_EOL . $content . PHP_EOL . "}";
	}
	
	/**
	 * Helper to convert the source code of a factory into compiled php code.
	 * It will use eval as a preferred option but will automatically fall back to a file-system based approach if eval
	 * was disabled.
	 *
	 * @param string $factoryString The source code of a factory function that should be converted into a closure
	 *
	 * @return \Closure
	 */
	protected function convertFactoryStringToClosure(string $factoryString): Closure {
		// Wrap the factory string
		$factoryString = "return " . $factoryString . ";";
		
		// Try to use the short route over eval
		if ($this->canUseEval) return eval($factoryString);
		
		// Use the fallback over the file system
		$directory = empty($this->cacheDirectoryPath) ? sys_get_temp_dir() . DIRECTORY_SEPARATOR : $this->cacheDirectoryPath;
		$filename = $directory . "string2closure-" . md5($factoryString . microtime(TRUE)) . ".php";
		$this->writeFile($filename, "<?php " . $factoryString);
		$closure = require $filename;
		@unlink($filename);
		return $closure;
	}
	
	/**
	 * Lazy loading helper to only instantiate the lazy loading proxy generator if we really need it
	 * @return \Neunerlei\ContainerDelegate\Instantiator\LazyLoadingProxyGenerator
	 */
	protected function getLazyLoadingProxyGenerator(): LazyLoadingProxyGenerator {
		if (isset($this->lazyLoadingProxyGenerator)) return $this->lazyLoadingProxyGenerator;
		return $this->lazyLoadingProxyGenerator = new static::$lazyLoadingProxyGeneratorClass();
	}
}