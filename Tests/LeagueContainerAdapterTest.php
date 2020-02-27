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

namespace Neunerlei\ContainerDelegate\Tests\Assets;


use League\Container\Container;
use Neunerlei\ContainerDelegate\Adapter\LeagueContainerAdapter;
use Neunerlei\ContainerDelegate\Tests\ContainerTest;
use Psr\Container\ContainerInterface;

class LeagueContainerAdapterTest extends ContainerTest {
	protected function getContainer(?string $cachePath = NULL): ContainerInterface {
		$delegate = new LeagueContainerAdapter($cachePath);
		$leagueContainer = new \League\Container\Container();
		$leagueContainer->add(ContainerInterface::class, $leagueContainer);
		$leagueContainer->add(Container::class, $leagueContainer);
		$delegate->setContainer($leagueContainer);
		$delegate->setLeagueContainer($leagueContainer);
		$leagueContainer->delegate($delegate);
		
		return $leagueContainer;
	}
}