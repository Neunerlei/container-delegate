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

trait FileWriterTrait {
	
	/**
	 * Checks if there is a directory at the given path and if it can be read and written by the server.
	 * The directory will be created if it does not yet exist and the parent is writable.
	 *
	 * @param string $directory The path to the directory
	 *
	 * @throws \Neunerlei\ContainerDelegate\Instantiator\ContainerFileWriterException
	 */
	protected function validateDirectoryPermissions(string $directory): void {
		if (!is_dir($directory)) {
			if (!file_exists($directory) && mkdir($directory, 0777, TRUE)) return;
			throw new ContainerFileWriterException("Could not find the given directory: $directory");
		}
		if (!is_readable($directory)) throw new ContainerFileWriterException("The given directory $directory is not readable!");
		if (!is_writable($directory)) throw new ContainerFileWriterException("The given directory $directory is not writable!");
	}
	
	/**
	 * Checks if there is a file at the given path and if it can be read and written by the server.
	 * If the file does not exist, the directory is checked for read and write permissions.
	 *
	 * @param string $filepath The path to the file to validate
	 *
	 * @throws \Neunerlei\ContainerDelegate\Instantiator\ContainerFileWriterException
	 */
	protected function validateFilePermissions(string $filepath): void {
		if (!file_exists($filepath)) {
			$this->validateDirectoryPermissions(dirname($filepath));
			return;
		}
		if (!is_readable($filepath)) throw new ContainerFileWriterException("The given file $filepath is not readable!");
		if (!is_writable($filepath)) throw new ContainerFileWriterException("The given file $filepath is not writable!");
	}
	
	/**
	 * A simple wrapper around file_put_contents, but handles non-writable or broken
	 * files with speaking exceptions.
	 *
	 * @param string $filename
	 * @param string $content
	 *
	 * @throws \Neunerlei\ContainerDelegate\Instantiator\ContainerFileWriterException
	 * @see \file_put_contents()
	 */
	protected function writeFile(string $filename, string $content, int $flags = 0) {
		// Make sure we can write the file
		if (file_exists($filename) && !is_writable($filename))
			throw new ContainerFileWriterException("Could not write file: " . $filename .
				" - Permission denied!");
		
		// Make sure the directory exists
		$dirName = dirname($filename);
		if (!file_exists($dirName)) mkdir($dirName, 0777, TRUE);
		
		// Try to write file with save guard
		$tmpFileName = $filename . ".writing." . md5(microtime(TRUE) . rand(0, 99999999)) . ".txt";
		$result = @file_put_contents($tmpFileName, $content, $flags);
		if ($result !== FALSE) $result = @rename($tmpFileName, $filename);
		if ($result !== FALSE) return;
		else @unlink($tmpFileName);
		
		// Dump the content using the normal way
		$result = @file_put_contents($filename, $content, $flags);
		if ($result === FALSE)
			throw new ContainerFileWriterException("Could not write file: " . $filename . " because: " . error_get_last()["message"]);
		
	}
}