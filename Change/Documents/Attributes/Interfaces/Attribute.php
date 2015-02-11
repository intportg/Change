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
 * @name \Change\Documents\Attributes\Interfaces\Attribute
 */
interface Attribute
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
	public function getName();

	/**
	 * @api
	 * @return string
	 */
	public function getType();

	/**
	 * @api
	 * @return string
	 */
	public function getTitle();

	/**
	 * @api
	 * @return array
	 */
	public function getDescription();

	/**
	 * @api
	 * @return boolean
	 */
	public function getLocalized();

	/**
	 * @api
	 * @return \Change\Collection\ItemInterface[]
	 */
	public function getCollectionItems();

	/**
	 * @api
	 * @return array
	 */
	public function toArray();
}