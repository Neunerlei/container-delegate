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

/**
 * Class AbstractLazyLoadingProxyObject
 *
 * The base class for the auto-generated lazy loading proxy objects
 *
 * @package Neunerlei\ContainerDelegate\Instantiator
 */
abstract class AbstractLazyLoadingProxyObject {
	protected $__realClass;
	protected $__instance;
	protected $__container;
	
	public function __construct(\Psr\Container\ContainerInterface $container) {
		$this->__container = $container;
	}
	
	public function __getRealClassName(): string {
		return $this->__realClass;
	}
	
	public function __getInstance() {
		if (empty($this->__instance)) $this->__instance = $this->__container->get($this->__realClass);
		return $this->__instance;
	}
	
	public function __call($name, $arguments) {
		return call_user_func_array([$this->__getInstance(), $name], $arguments);
	}
	
	public function __set($name, $value) {
		$this->__getInstance()->$name = $value;
	}
	
	public function __get($name) {
		return $this->__getInstance()->$name;
	}
}