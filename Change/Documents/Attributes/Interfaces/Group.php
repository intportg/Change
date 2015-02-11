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
 * @name \Change\Documents\Attributes\Interfaces\Group
 */
interface Group
{
	/**
	 * @api
	 * @return string
	 */
	public function getName();

	/**
	 * @api
	 * @return string
	 */
	public function getTitle();

	/**
	 * @api
	 * @return \Change\Documents\Attributes\Interfaces\Attribute[]
	 */
	public function getAttributes();

	/**
	 * @api
	 * @return array
	 */
	public function toArray();
}