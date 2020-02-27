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
 * Last modified: 2020.02.27 at 12:27
 */

Namespace Neunerlei\ContainerDelegate\Tests\Assets;


use Neunerlei\ContainerAutoWiringDeclaration\InjectableInterface;
use Neunerlei\ContainerAutoWiringDeclaration\SingletonInterface;

interface DummyClassInterface {

}

interface DummyClassAInterface {

}

class DummyClassA implements DummyClassInterface, DummyClassAInterface {

}

class DummyClassB implements DummyClassInterface {

}

class DummyClassC implements DummyClassInterface {
	public $a;
	public $b;
	
	public function __construct(DummyClassA $a, DummyClassB $b) {
		$this->a = $a;
		$this->b = $b;
	}
}

class DummyClassD implements SingletonInterface {
	public $e;
	
	public function __construct(DummyClassE $e) {
		$this->e = $e;
	}
}

class DummyClassE {
	public const BAZ = "baz";
	
}

class DummyClassF {
	public $a;
	public $d;
	public $bar;
	public $baz;
	public $empty;
	
	public const BAR = "bar";
	
	public function __construct(DummyClassAInterface $a, DummyClassD $d, string $bar = self::BAR, $baz = DummyClassE::BAZ, $empty = NULL) {
		$this->a = $a;
		$this->d = $d;
		
		$this->bar = $bar;
		$this->baz = $baz;
		$this->empty = $empty;
	}
}

class DummyInjectableClass implements InjectableInterface {
	public $c;
	public $d;
	
	public function __construct(DummyClassC $c) {
		$this->c = $c;
	}
	
	public function injectDummyClass(DummyClassD $d) {
		$this->d = $d;
	}
}

class DummyStaticInjectableClass implements InjectableInterface {
	public $d;
	
	public function injectDummyClass(DummyClassD $d) {
		$this->d = $d;
	}
	
	public static function injectStaticDummyClass(DummyClassD $d) {
		throw new \Exception("A static inject method was executed!");
	}
}

abstract class AbstractDummyClass {
	public $d;
	
	public function injectDummyClass(DummyClassD $d) {
		$this->d = $d;
	}
}

class DummyNonInterfaceLazyClass {
	public $lazyClassA;
	
	public function __construct(DummyClassA $lazyClassA) {
		$this->lazyClassA = $lazyClassA;
	}
}

interface DummyLazyClassInterface {
	public function getInjectableClass();
}

class DummyLazyClass implements DummyLazyClassInterface {
	public $injectableClass;
	
	public function __construct(DummyInjectableClass $injectableClass) { $this->injectableClass = $injectableClass; }
	
	public function getInjectableClass() {
		return $this->injectableClass;
	}
}

class DummyLazyParentClass {
	public $lazyClass;
	
	public function __construct(DummyLazyClassInterface $lazyClass) {
		$this->lazyClass = $lazyClass;
	}
	
	public function getLazyClass(): DummyLazyClassInterface {
		return $this->lazyClass;
	}
}

class DummyCircularA {
	public $b;
	
	public function __construct(DummyCircularB $b) {
		$this->b = $b;
	}
}

class DummyCircularB {
	public $a;
	
	public function __construct(DummyCircularA $a) {
		$this->a = $a;
	}
	
}
