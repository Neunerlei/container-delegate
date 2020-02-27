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

namespace Neunerlei\ContainerDelegate\Adapter;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Neunerlei\ContainerDelegate\Container;
use Psr\Container\ContainerInterface;

/**
 * Class LeagueContainerAdapter
 *
 * Acts as an adapter for the php-league container implementation.
 * It utilizes the containers shared service capability to resolve singletons instead of the internal resolution.
 *
 * @package Neunerlei\ContainerDelegate\Adapter
 */
class LeagueContainerAdapter extends Container implements ContainerAwareInterface {
	use ContainerAwareTrait {
		setContainer as setContainerRoot;
		setLeagueContainer as setLeagueContainerRoot;
	}
	
	/**
	 * @inheritDoc
	 */
	public function setContainer(ContainerInterface $container): ContainerAwareInterface {
		$this->setContainerRoot($container);
		if ($this->parentContainer === $this) $this->parentContainer = $container;
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function setLeagueContainer(\League\Container\Container $container): ContainerAwareInterface {
		$this->setLeagueContainerRoot($container);
		if ($this->parentContainer === $this) $this->parentContainer = $container;
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function get($id, $new = FALSE) {
		
		// Convert the id into a class name that can be resolved by the instantiator
		$className = $this->resolveRealClassName($id);
		
		// Make the instance
		$definition = substr($className, 0, 17) === "@lazyLoadingProxy" ? NULL :
			$this->getInstantiator()->getAutoWiringDefinition($className);
		
		// Check if the instance implements the singleton interface
		if ($definition !== NULL && $definition->isSingleton())
			
			// Resolve the class by proxy -> This sets the initial state of the league containers shared
			return $this->getLeagueContainer()->share($className, function () use ($className) {
				
				// We always return a new instance here
				return $this->getInstantiator()->getOrMakeInstanceForClass($className, FALSE);
				
			})->setAlias($id)->resolve(TRUE);
		
		else
			// Resolve a new class
			return $this->getInstantiator()->getOrMakeInstanceForClass($className, FALSE);
		
	}
}