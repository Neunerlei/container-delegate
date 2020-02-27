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

use ReflectionClass;
use ReflectionMethod;

class LazyLoadingProxyGenerator {
	
	/**
	 * Generates the source code of a lazy loading proxy class for the given interface.
	 * The proxy class implements the given interface and is container aware
	 *
	 * @param string $interfaceName The name of the interface to build the proxy class for
	 *
	 * @return string
	 */
	public function getLazyLoadingProxyString(string $interfaceName): string {
		// Create the reflection
		$ref = new ReflectionClass($interfaceName);
		
		// Helper to generate "call" arguments
		$callArgGenerator = function (ReflectionMethod $method): string {
			$params = [];
			foreach ($method->getParameters() as $parameter)
				$params[] = ($parameter->isPassedByReference() ? "&" : "") . "$" . $parameter->getName();
			return "[" . implode(",", $params) . "]";
		};
		
		// Generate the body
		$tab = "		";
		$body = [];
		foreach ($ref->getMethods() as $method) {
			$methodName = $method->getName();
			$returnString = $method->hasReturnType() ? ($method->getReturnType()->getName() === "void" ? "" : "return") : "return";
			$body[] = $tab . $this->generateMethodSignature($method) . "{
			$returnString \$this->__call(\"$methodName\", {$callArgGenerator($method)});
		}";
		}
		
		// Generate the class wrap
		$body = implode(PHP_EOL . PHP_EOL, $body);
		$body = "	return new class(\$container) extends " . AbstractLazyLoadingProxyObject::class . " implements \\$interfaceName {
		protected \$__realClass = \\$interfaceName::class;
$body
	};";
		
		// Done
		return $body;
	}
	
	/**
	 * Helper which is used to build the parameter string of a given reflection method,
	 * to be dumped back into the source code.
	 *
	 * @param \ReflectionMethod $method
	 *
	 * @return string
	 */
	protected function generateMethodArgs(ReflectionMethod $method): string {
		$args = [];
		foreach ($method->getParameters() as $param) {
			$arg = [];
			
			// Add type definition
			if (method_exists($param, "hasType") && $param->hasType()) {
				$type = $param->getType()->getName();
				
				// Make sure the type of classes starts with a backslash...
				if (stripos($type, "\\") !== FALSE || class_exists($type))
					$type = "\\" . $type;
				
				// Check for a nullable type
				if ($param->allowsNull())
					$type = "?" . $type;
				
				$arg[] = $type;
			}
			
			// Add name of the arg
			$argName = "$" . $param->getName();
			
			// Check if this argument is used as a reference
			if ($param->isPassedByReference()) $argName = "&" . $argName;
			
			// Add name to argument
			$arg[] = $argName;
			
			// Add possible default value
			if ($param->isDefaultValueAvailable()) {
				$default = "= ";
				$default .= str_replace(PHP_EOL, " ", var_export($param->getDefaultValue(), TRUE));
				$arg[] = $default;
			}
			
			// Implode the single argument
			$args[] = implode(" ", $arg);
		}
		
		// Implode all arguments
		return implode(", ", $args);
	}
	
	/**
	 * Helper which is used to build a method signature out of the given method reflection
	 *
	 * @param \ReflectionMethod $method
	 *
	 * @return string
	 */
	protected function generateMethodSignature(ReflectionMethod $method): string {
		$args = $this->generateMethodArgs($method);
		
		// Build prefixes
		$prefixes = [];
		if ($method->isAbstract() && !$method->getDeclaringClass()->isInterface()) $prefixes[] = "abstract";
		if ($method->isFinal()) $prefixes[] = "final";
		if ($method->isPublic()) $prefixes[] = "public";
		if ($method->isProtected()) $prefixes[] = "protected";
		if ($method->isPrivate()) $prefixes[] = "private";
		if ($method->isStatic()) $prefixes[] = "static";
		$prefixes[] = ($method->returnsReference() ? "&" : "") . "function";
		
		// Build return type
		$returnType = "";
		if ($method->hasReturnType()) {
			$type = $method->getReturnType();
			$isObjectOrInterface = class_exists($type) || interface_exists($type);
			$returnType = ":" . ($isObjectOrInterface ? "\\" : "") . $type->getName();
		}
		
		// Build signature
		return implode(" ", $prefixes) . " " . $method->getName() . "(" . $args . ")" . $returnType;
	}
}