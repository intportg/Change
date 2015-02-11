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
 * @name \Rbs\Generic\Attributes\Attribute
 */
class Attribute implements \Change\Documents\Attributes\Interfaces\Attribute
{
	/**
	 * @var integer
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var array
	 */
	protected $description;

	/**
	 * @var boolean
	 */
	protected $localized;

	/**
	 * @var \Change\Collection\ItemInterface[]
	 */
	protected $collectionItems = [];

	/**
	 * @param \Rbs\Generic\Documents\Attribute|array $attribute
	 */
	public function __construct($attribute)
	{
		if ($attribute instanceof \Rbs\Generic\Documents\Attribute)
		{
			$this->fromDocument($attribute);
		}
		elseif (is_array($attribute))
		{
			$this->fromArray($attribute);
		}
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 * @return $this
	 */
	public function setId($id)
	{
		$this->id = $id;
		return $this;
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
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 * @return $this
	 */
	public function setType($type)
	{
		$this->type = $type;
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
	 * @return array
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @param array $description
	 * @return $this
	 */
	public function setDescription($description)
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getLocalized()
	{
		return $this->localized;
	}

	/**
	 * @param boolean $localized
	 * @return $this
	 */
	public function setLocalized($localized)
	{
		$this->localized = $localized;
		return $this;
	}

	/**
	 * @return \Change\Collection\ItemInterface[]
	 */
	public function getCollectionItems()
	{
		return $this->collectionItems;
	}

	/**
	 * @param \Change\Collection\ItemInterface[] $collectionItems
	 * @return $this
	 */
	public function setCollectionItems(array $collectionItems)
	{
		$this->collectionItems = $collectionItems;
		return $this;
	}

	/**
	 * @param \Rbs\Generic\Documents\Attribute $attribute
	 */
	protected function fromDocument(\Rbs\Generic\Documents\Attribute $attribute)
	{
		$this->id = $attribute->getId();
		$this->name = $attribute->getName();
		$this->type = $attribute->getValueType();
		$this->title = $attribute->getCurrentLocalization()->getTitle();
		$this->description = $attribute->getCurrentLocalization()->getDescription()->toArray();
		$this->localized = $attribute->getLocalizedValue();
		$this->collectionItems = [];
		if ($attribute->getCollectionCode())
		{
			// TODO
		}
	}

	/**
	 * @param array $attribute
	 */
	protected function fromArray(array $attribute)
	{
		$this->id = isset($attribute['id']) ? $attribute['id'] : null;
		$this->name = isset($attribute['name']) ? $attribute['name'] : null;
		$this->type = isset($attribute['type']) ? $attribute['type'] : null;
		$this->title = isset($attribute['title']) ? $attribute['title'] : null;
		$this->description = isset($attribute['description']) ? $attribute['description'] : null;
		$this->localized = isset($attribute['localized']) ? $attribute['localized'] : null;
		$this->collectionItems = [];
		if (isset($attribute['collectionItems']) && is_array($attribute['collectionItems']))
		{
			foreach ($attribute['collectionItems'] as $item)
			{
				if (isset($item['value']))
				{
					$label = isset($item['label']) ? $item['label'] : null;
					$title = isset($item['title']) ? $item['title'] : null;
					$this->collectionItems[] = new \Change\Collection\BaseItem($item['value'], $label, $title);
				}
			}
		}
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		$result = [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'type' => $this->getType(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'localized' => $this->getLocalized()
		];
		$result['collectionItems'] = [];
		foreach ($this->getCollectionItems() as $item)
		{
			$result['collectionItems'][$item->getValue()] = [
				'value' => $item->getValue(), 'label' => $item->getLabel(), 'title' => $item->getTitle()
			];
		}
		return $result;
	}

	/**
	 * @param mixed $value
	 * @return array
	 */
	public function normalizeValue($value)
	{
		switch ($this->getType())
		{
			case \Rbs\Generic\Documents\Attribute::TYPE_BOOLEAN:
				return $value != 0;
				break;
			case \Rbs\Generic\Documents\Attribute::TYPE_INTEGER:
			case \Rbs\Generic\Documents\Attribute::TYPE_DOCUMENTID:
			return intval($value);
				break;
			case \Rbs\Generic\Documents\Attribute::TYPE_DOCUMENTIDARRAY:
				if (is_array($value))
				{
					return $value;
				}
				break;
			case \Rbs\Generic\Documents\Attribute::TYPE_DATETIME:
				$v = $value;
				if ($v instanceof \DateTime)
				{
					return $v->format(\DateTime::ISO8601);
				}
				elseif (is_string($v))
				{
					return $v;
				}
				break;
			case \Rbs\Generic\Documents\Attribute::TYPE_FLOAT:
				return floatval($value);
				break;
			case \Rbs\Generic\Documents\Attribute::TYPE_STRING:
				return $value;
				break;
			case \Rbs\Generic\Documents\Attribute::TYPE_RICHTEXT:
				$v = $value;
				if ($v instanceof \Change\Documents\RichtextProperty)
				{
					return $v->toArray();
				}
				elseif (is_array($v))
				{
					return $v;
				}
				break;
			default:
				break;
		}
		return null;
	}
}