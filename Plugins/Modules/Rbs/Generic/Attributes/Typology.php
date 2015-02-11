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
 * @name \Rbs\Generic\Attributes\Typology
 */
class Typology implements \Change\Documents\Attributes\Interfaces\Typology
{
	/**
	 * @var integer
	 */
	protected $id;

	/**
	 * @var \Change\Documents\Attributes\Interfaces\Group[]
	 */
	protected $groups = [];

	/**
	 * @var string
	 */
	protected $modelName;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @param \Rbs\Generic\Documents\Typology|array $typology
	 */
	public function __construct($typology)
	{
		if ($typology instanceof \Rbs\Generic\Documents\Typology)
		{
			$this->fromDocument($typology);
		}
		elseif (is_array($typology))
		{
			$this->fromArray($typology);
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
	 * @return \Rbs\Generic\Attributes\Group[]
	 */
	public function getGroups()
	{
		return $this->groups;
	}

	/**
	 * @param \Change\Documents\Attributes\Interfaces\Group[] $groups
	 * @return $this
	 */
	public function setGroups($groups)
	{
		$this->groups = [];
		foreach ($groups as $group)
		{
			$this->groups[] = $this->getNewGroup($group);
		}
		return $this;
	}

	/**
	 * @param \Change\Documents\Attributes\Interfaces\Group|\Rbs\Generic\Documents\AttributeGroup|array $group
	 * @return \Rbs\Generic\Attributes\Group|null
	 */
	public function getNewGroup($group)
	{
		if ($group instanceof \Rbs\Generic\Attributes\Group)
		{
			return $group;
		}
		elseif ($group instanceof \Change\Documents\Attributes\Interfaces\Group)
		{
			return new \Rbs\Generic\Attributes\Group($group->toArray());
		}
		elseif (is_array($group))
		{
			return new \Rbs\Generic\Attributes\Group($group);
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getModelName()
	{
		return $this->modelName;
	}

	/**
	 * @param string $modelName
	 * @return $this
	 */
	public function setModelName($modelName)
	{
		$this->modelName = $modelName;
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
	 * @param string $name
	 * @return \Change\Documents\Attributes\Interfaces\Group|null
	 */
	public function getGroupByName($name)
	{
		foreach ($this->getGroups() as $group)
		{
			if ($group->getName() == $name)
			{
				return $group;
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 * @return \Change\Documents\Attributes\Interfaces\Attribute|null
	 */
	public function getAttributeByName($name)
	{
		foreach ($this->getGroups() as $group)
		{
			foreach ($group->getAttributes() as $attribute)
			{
				if ($attribute->getName() == $name)
				{
					return $group;
				}
			}
		}
		return null;
	}

	/**
	 * @param \Rbs\Generic\Documents\Typology $typology
	 */
	protected function fromDocument(\Rbs\Generic\Documents\Typology $typology)
	{
		$this->id = $typology->getId();
		$this->modelName = $typology->getModelName();
		$this->title = $typology->getCurrentLocalization()->getTitle();
		$this->groups = [];
		foreach ($typology->getGroups() as $group)
		{
			$this->groups[] = new \Rbs\Generic\Attributes\Group($group);
		}
	}


	/**
	 * @param array $typology
	 */
	protected function fromArray(array $typology)
	{
		$this->id = isset($typology['id']) ? intval($typology['id']) : null;
		$this->modelName = isset($typology['modelName']) ? $typology['modelName'] : null;
		$this->title = isset($typology['title']) ? $typology['title'] : null;
		$this->groups = [];
		if (isset($typology['groups']) && is_array($typology['groups']))
		{
			foreach ($typology['groups'] as $group)
			{
				$this->groups[] = new \Rbs\Generic\Attributes\Group($group);
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
			'modelName' => $this->getModelName(),
			'title' => $this->getTitle(),
			'groups' => []
		];
		foreach ($this->getGroups() as $group)
		{
			$result['group'][] = $group->toArray();
		}
		return $result;
	}

	/**
	 * @param array $values
	 * @param string $LCID
	 * @return array
	 */
	public function normalizeValues($values, $LCID)
	{
		$normalizedValues = [];
		foreach ($this->getGroups() as $group)
		{
			foreach ($group->getAttributes() as $attribute)
			{
				if ($attribute->getLocalized())
				{
					$key = $LCID . '.' . $attribute->getId();
				}
				else
				{
					$key = '_.' . $attribute->getId();
				}

				if (!isset($key))
				{
					continue;
				}

				if ($attribute instanceof \Rbs\Generic\Attributes\Attribute)
				{
					$value = $attribute->normalizeValue($values[$key]);
					if ($value !== null)
					{
						$normalizedValues[$key] = $value;
					}
				}
			}
		}

		foreach ($values as $key => $value)
		{
			if (!\Change\Stdlib\String::beginsWith('_.', $key) && !\Change\Stdlib\String::beginsWith($LCID . '.', $key))
			{
				$normalizedValues[$key] = $value;
			}
		}

		return $normalizedValues;
	}
}