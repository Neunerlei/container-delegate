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
 * Last modified: 2020.02.27 at 12:29
 */

return [
	"@source"                                              => 'eJydjrEKwzAMRH/FGA/JL7R0CMnSpXToqMWYSzE4ClgyJYT8e+2hkLkc3OkJJG63DxRGTohE48rqY6UJCW+vIHpBVIgGEbScyrJsY/Iig73YuXDQuHL3lHy6Po13VuTZBxgXfrve7MSkLpqbYXwM0b8Vuvanqnp/bZ6hJbNxsdJhjy9unVNr',
	"Neunerlei\ContainerDelegate\Tests\Assets\DummyClassA" => [
		"factory"     => function (Psr\Container\ContainerInterface $container) {
			$i = new \Neunerlei\ContainerDelegate\Tests\Assets\DummyClassA();
			return $i;
		},
		"isSingleton" => FALSE,
	],
];