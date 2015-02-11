<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Documents\Attributes\Interfaces;

/**
 * @name \Change\Documents\Attributes\Interfaces\Typology
 */
interface Typology
{
	/**
	 * @api
	 * @return integer
	 */
	public function getId();

	/**
	 * @api
	 * @return string
	 */
	public function getModelName();

	/**
	 * @api
	 * @return string
	 */
	public function getTitle();

	/**
	 * @api
	 * @return \Change\Documents\Attributes\Interfaces\Group[]
	 */
	public function getGroups();

	/**
	 * @api
	 * @param string $name
	 * @return \Change\Documents\Attributes\Interfaces\Group|null
	 */
	public function getGroupByName($name);

	/**
	 * @api
	 * @param string $name
	 * @return \Change\Documents\Attributes\Interfaces\Attribute|null
	 */
	public function getAttributeByName($name);

	/**
	 * @api
	 * @return array
	 */
	public function toArray();

	/**
	 * @api
	 * @param array $values
	 * @param string $LCID
	 * @return array
	 */
	public function normalizeValues($values, $LCID);
}