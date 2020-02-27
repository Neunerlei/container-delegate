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

use Neunerlei\ContainerAutoWiringDeclaration\Definition\AutoWiringDefinitionProviderInterface;

interface InstantiatorInterface extends AutoWiringDefinitionProviderInterface {
	
	/**
	 * Checks if the instantiator currently HAS a factory for the given id.
	 * It returns false if a new factory has to be compiled.
	 *
	 * @param string $id The class name / proxy identifier to check for
	 *
	 * @return bool
	 */
	public function hasFactoryFor(string $id): bool;
	
	/**
	 * Returns either the existing instance of a class (if it is a singleton) or creates a new instance of it.
	 *
	 * @param string $className           The name of the class that should be instantiated or returned
	 * @param bool   $useSingletonStorage True if the singleton storage should be used, if this is set to FALSE
	 *                                    a new instance is created every time this method is executed.
	 *
	 * @return mixed
	 */
	public function getOrMakeInstanceForClass(string $className, bool $useSingletonStorage);
}