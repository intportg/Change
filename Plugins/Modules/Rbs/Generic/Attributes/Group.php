<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Attributes;

/**
 * @name \Rbs\Generic\Attributes\Group
 */
class Group implements \Change\Documents\Attributes\Interfaces\Group
{
	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var \Change\Documents\Attributes\Interfaces\Attribute[]
	 */
	protected $attributes = [];

	/**
	 * @param \Rbs\Generic\Documents\AttributeGroup|array $group
	 */
	public function __construct($group)
	{
		if ($group instanceof \Rbs\Generic\Documents\AttributeGroup)
		{
			$this->fromDocument($group);
		}
		elseif (is_array($group))
		{
			$this->fromArray($group);
		}
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 * @return $this
	 */
	public function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return \Change\Documents\Attributes\Interfaces\Attribute[]
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * @param \Change\Documents\Attributes\Interfaces\Attribute[]|\Rbs\Generic\Documents\Attribute[]|array $attributes
	 * @return $this
	 */
	public function setAttributes($attributes)
	{
		$this->attributes = [];
		foreach ($attributes as $attribute)
		{
			$item = $this->getNewAttribute($attribute);
			if ($item)
			{
				$this->attributes[] = $item;
			}
		}
		return $this;
	}

	/**
	 * @param \Change\Documents\Attributes\Interfaces\Attribute|\Rbs\Generic\Documents\Attribute|array $attribute
	 * @return \Rbs\Generic\Attributes\Attribute|null
	 */
	public function getNewAttribute($attribute)
	{
		if ($attribute instanceof \Rbs\Generic\Attributes\Attribute)
		{
			return $attribute;
		}
		elseif ($attribute instanceof \Change\Documents\Attributes\Interfaces\Attribute)
		{
			return new \Rbs\Generic\Attributes\Attribute($attribute->toArray());
		}
		elseif (is_array($attribute) || $attribute instanceof \Rbs\Generic\Documents\Attribute)
		{
			return new \Rbs\Generic\Attributes\Attribute($attribute);
		}
		return null;
	}

	/**
	 * @param \Rbs\Generic\Documents\AttributeGroup $group
	 */
	protected function fromDocument(\Rbs\Generic\Documents\AttributeGroup $group)
	{
		$this->name = $group->getName();
		$this->title = $group->getCurrentLocalization()->getTitle();
		$this->setAttributes($group->getAttributes());
	}

	/**
	 * @param array $group
	 */
	protected function fromArray(array $group)
	{
		$this->name = isset($group['name']) ? $group['name'] : null;
		$this->title = isset($group['title']) ? $group['title'] : null;
		$this->attributes = [];
		if (isset($group['attributes']) && is_array($group['attributes']))
		{
			foreach ($group['attributes'] as $attributes)
			{
				$this->attributes[] = new \Rbs\Generic\Attributes\Attribute($attributes);
			}
		}
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		$result = [
			'name' => $this->getName(),
			'title' => $this->getTitle(),
			'attributes' => []
		];
		foreach ($this->getAttributes() as $attribute)
		{
			$result['attributes'][] = $attribute->toArray();
		}
		return $result;
	}
}