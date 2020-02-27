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

interface FactoryGeneratorInterface {
	
	/**
	 * FactoryGenerator constructor.
	 *
	 * @param array  $factories          The list of existing factory functions used in the instantiator.
	 *                                   The given list will be extended with additional factories if they are
	 *                                   discovered by ensureRequiredFactoriesFor()
	 * @param string $cacheDirectoryPath The caching directory that is used to convert source code into compiled php if
	 *                                   eval() is not enabled.
	 */
	public function __construct(array &$factories, ?string $cacheDirectoryPath);
	
	/**
	 * Receives an auto wiring class definition and makes sure that all required factories are available
	 * in the $factories array (given in the constructor).
	 *
	 * @param AutoWiringClassInterface $class
	 */
	public function ensureRequiredFactoriesFor(AutoWiringClassInterface $class): void;
	
	/**
	 * Dumps the list of compiled factory functions (given in the constructor) as a valid php file to the file system.
	 *
	 * @param string $filePath The absolute path to the file that should contain the compiled factory list
	 */
	public function dumpFactories(string $filePath): void;
	
}