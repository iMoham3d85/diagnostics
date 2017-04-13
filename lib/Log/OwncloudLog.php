<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Diagnostics\Log;

/**
 * logging utilities
 *
 * Log is saved at data/diagnostics.log (on default)
 */

use OCP\IConfig;

class OwncloudLog {
	/**
	 * @param string
	 */
	private $logFile;

	/**
	 * @param \OCP\IConfig $config
	 */
	private $config;
	
	/**
	 * @param \OCP\IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
		$this->logFile = $this->config->getSystemValue("datadirectory", \OC::$SERVERROOT.'/data').'/diagnostic.log';
	}
	
	/**
	 * write a message in the log
	 * @param string $type
	 * @param string[] $diagnostics
	 */
	public function write($type, $diagnostics) {

		// default to ISO8601
		$format = $this->config->getSystemValue('logdateformat', 'c');
		$logTimeZone = $this->config->getSystemValue( "logtimezone", 'UTC' );
		try {
			$timezone = new \DateTimeZone($logTimeZone);
		} catch (\Exception $e) {
			$timezone = new \DateTimeZone('UTC');
		}
		$time = \DateTime::createFromFormat("U.u", number_format(microtime(true), 4, ".", ""));
		if ($time === false) {
			$time = new \DateTime(null, $timezone);
		} else {
			// apply timezone if $time is created from UNIX timestamp
			$time->setTimezone($timezone);
		}
		$request = \OC::$server->getRequest();
		$reqId = $request->getId();
		$remoteAddr = $request->getRemoteAddress();
		// remove username/passwords from URLs before writing the to the log file
		$time = $time->format($format);
		$url = ($request->getRequestUri() !== '') ? $request->getRequestUri() : '--';
		$method = is_string($request->getMethod()) ? $request->getMethod() : '--';
		if(\OC::$server->getConfig()->getSystemValue('installed', false)) {
			$user = (\OC_User::getUser()) ? \OC_User::getUser() : '--';
		} else {
			$user = '--';
		}
		
		$entry = compact(
			'reqId',
			'time',
			'remoteAddr',
			'user',
			'method',
			'url',
			'type',
			'diagnostics'
		);
		$entry = json_encode($entry);
		$handle = @fopen($this->logFile, 'a');
		@chmod($this->logFile, 0640);
		if ($handle) {
			fwrite($handle, $entry."\n");
			fclose($handle);
		} else {
			// Fall back to error_log
			error_log($entry);
		}
	}

	/**
	 * clean the log
	 */
	public function clean() {
		$handle = @fopen($this->logFile, 'w');
		@chmod($this->logFile, 0640);
		if ($handle) {
			fclose($handle);
		}
	}

	/**
	 * @return string
	 */
	public function getLogFilePath() {
		return $this->logFile;
	}
}