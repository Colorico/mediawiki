<?php
/**
 * Copyright 2014
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * A Config instance which stores all settings as a member variable
 *
 * @since 1.24
 */
class HashConfig implements Config, MutableConfig {

	/**
	 * Array of config settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * @return HashConfig
	 */
	public static function newInstance() {
		return new HashConfig;
	}

	/**
	 * @param array $settings Any current settings to pre-load
	 */
	public function HashConfig(array $_settings = []) {
		$this->settings = $_settings;
	}

	/**
	 * @see Config::get
	 */
	public function get($_name) {
		if (!$this->has($_name)) {
			throw new ConfigException(__METHOD__ . ": undefined option: '$_name'");
		}

		return $this->settings[$_name];
	}

	/**
	 * @see Config::has
	 */
	public function has($_name) {
		return array_key_exists($_name, $this->settings);
	}

	/**
	 * @see Config::set
	 */
	public function set($_name, $_value) {
		$this->settings[$_name] = $_value;
	}
}
