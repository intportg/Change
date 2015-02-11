<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Documents;

use Change\Documents\Property;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Catalog\Documents\Attribute
 */
class Attribute extends \Compilation\Rbs\Catalog\Documents\Attribute
{
	const TYPE_BOOLEAN = 'Boolean';
	const TYPE_INTEGER = 'Integer';
	const TYPE_DOCUMENTID = 'DocumentId';
	const TYPE_DOCUMENTIDARRAY = 'DocumentIdArray';
	const TYPE_FLOAT = 'Float';
	const TYPE_DATETIME = 'DateTime';
	const TYPE_STRING = 'String';
	const TYPE_TEXT = 'Text';

	//Special type
	const TYPE_GROUP = 'Group';
	const TYPE_PROPERTY = 'Property';

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$cs = $event->getServices('commerceServices');
			if ($cs instanceof \Rbs\Commerce\CommerceServices)
			{
				/** @var $attribute Attribute */
				$attribute = $event->getDocument();
				$restResult->setProperty('editorDefinition', $cs->getAttributeManager()->buildEditorDefinition($attribute));
			}
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$extraColumn = $event->getParam('extraColumn');
			if (in_array('valueTypeFormatted', $extraColumn))
			{
				/* @var $attribute Attribute */
				$fv = $event->getApplicationServices()->getI18nManager()
					->trans('m.rbs.catalog.documents.attribute_type_' . strtolower($this->getValueType()), ['ucf']);

				if ($this->getValueType() == 'Group' && $this->getProductTypology())
				{
					$fv .= ' (' . $event->getApplicationServices()->getI18nManager()
							->trans('m.rbs.catalog.documents.attribute_producttypology', ['ucf']) . ')';
				}

				$restResult->setProperty('valueTypeFormatted', $fv);
			}
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @return Attribute[]|\Change\Documents\AbstractDocument|mixed|null
	 */
	public function getValue(\Rbs\Catalog\Documents\Product $product)
	{
		$vt = $this->getValueType();
		if ($vt === static::TYPE_PROPERTY)
		{
			$property = $this->getModelProperty();
			return ($property) ? $property->getValue($product) : null;
		}
		elseif ($vt === static::TYPE_GROUP)
		{
			return $this->getAttributes()->toArray();
		}
		else
		{
			$values = $product->getCurrentLocalization()->getAttributeValues();
			if (is_array($values) && count($values))
			{
				foreach ($values as $value)
				{
					if ($value['id'] != $this->getId())
					{
						continue;
					}
					$value = $value['value'];
					if ($value !== null)
					{
						if ($vt === static::TYPE_DATETIME)
						{
							return new \DateTime($value);
						}
						elseif ($vt === static::TYPE_DOCUMENTID)
						{
							if (is_numeric($value) && $value > 0)
							{
								return $value;
							}
							elseif (is_array($value) && isset($value['id']))
							{
								return $value['id'];
							}
							$value = null;
						}
						elseif ($vt === static::TYPE_DOCUMENTIDARRAY)
						{
							if (is_array($value))
							{
								$ids = [];
								foreach ($value as $id)
								{
									if (is_numeric($id) && $id > 0)
									{
										$ids[] = $id;
									}
									elseif (is_array($id) && isset($id['id']))
									{
										$ids[] = $id['id'];
									}
								}
								return count($ids) ? $ids : null;
							}
							$value = null;
						}
					}
					return $value;
				}
			}
		}
		return null;
	}

	/**
	 * @param \Change\Documents\AbstractModel $documentModel
	 */
	public function setDefaultValues(\Change\Documents\AbstractModel $documentModel)
	{
		parent::setDefaultValues($documentModel);
		$this->setAttributes(['specifications', 'listItem']);
	}

	/**
	 * @param string $visibility
	 * @return boolean
	 */
	public function isVisibleFor($visibility)
	{
		$vis = $this->getVisibility();
		if (!is_array($vis))
		{
			$vis = [];
		}
		if (!$visibility && count($vis) == 0)
		{
			return true;
		}
		elseif ($visibility && in_array($visibility, $vis))
		{
			return true;
		}
		return false;
	}

	/**
	 * @return \Change\Documents\Property|null
	 */
	public function getModelProperty()
	{
		if ($this->getValueType() === static::TYPE_PROPERTY)
		{
			if (strpos($this->getProductProperty(), '::'))
			{
				list($modelName, $propertyName) = explode('::', $this->getProductProperty());
			}
			else
			{
				$modelName = 'Rbs_Catalog_Product';
				$propertyName = $this->getProductProperty();
			}

			$model = $this->getDocumentManager()->getModelManager()->getModelByName($modelName);
			if ($model)
			{
				return $model->getProperty($propertyName);
			}
		}
		return null;
	}

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_CREATE, [$this, 'onDefaultCreate'], 10);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_UPDATE, [$this, 'onDefaultUpdate'], 10);
		$eventManager->attach('getDefaultItems', [$this, 'onDefaultGetDefaultItems'], 5);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultCreate(\Change\Documents\Events\Event $event)
	{
		if ($this->getValueType() === static::TYPE_GROUP)
		{
			$this->setAxisGroupVisibility();
		}
		elseif ($this->getAxis())
		{
			if (!$this->isAxisValidType())
			{
				$propertiesErrors = $event->getParam('propertiesErrors');
				if (!is_array($propertiesErrors))
				{
					$propertiesErrors = [];
				}
				$propertiesErrors['valueType'][] = 'Invalid value type for axis attribute';
				$event->setParam('propertiesErrors', $propertiesErrors);
			}
		}
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdate(\Change\Documents\Events\Event $event)
	{
		if ($this->getValueType() === static::TYPE_GROUP)
		{
			if ($this->isPropertyModified('attributes'))
			{
				$this->setAxisGroupVisibility();
				$arguments = ['attributeId' => $this->getId()];
				$event->getApplicationServices()->getJobManager()
					->createNewJob('Rbs_Catalog_Attribute_Refresh_Values', $arguments, null, false);
			}
		}
		elseif ($this->getAxis())
		{
			if (!$this->isAxisValidType())
			{
				$propertiesErrors = $event->getParam('propertiesErrors');
				if (!is_array($propertiesErrors))
				{
					$propertiesErrors = [];
				}
				$propertiesErrors['valueType'][] = 'Invalid value type for axis attribute';
				$event->setParam('propertiesErrors', $propertiesErrors);
			}
		}
	}

	protected function setAxisGroupVisibility()
	{
		$axisVisibility = false;
		foreach ($this->getAttributes() as $attribute)
		{
			if ($attribute->getAxis())
			{
				$axisVisibility = true;
				break;
			}
		}
		$this->setAxis($axisVisibility);
	}

	/**
	 * @return boolean
	 */
	protected function isAxisValidType()
	{
		if ($this->getValueType() === static::TYPE_PROPERTY)
		{
			$property = $this->getModelProperty();
			if ($property && !$property->getLocalized())
			{
				if (in_array($property->getType(),
					[Property::TYPE_INTEGER, Property::TYPE_DOCUMENTID, Property::TYPE_STRING, Property::TYPE_DOCUMENT]))
				{
					return true;
				}
			}
		}
		elseif (in_array($this->getValueType(),
			[static::TYPE_GROUP, static::TYPE_DOCUMENTID, static::TYPE_STRING, static::TYPE_INTEGER]))
		{
			return true;
		}
		return false;
	}

	protected function processRestData($name, $value, \Change\Http\Event $event)
	{
		if ($name === 'documentType' && is_array($value))
		{
			$value = $value['name'];
		}
		return parent::processRestData($name, $value, $event); // TODO: Change the autogenerated stub
	}

	/**
	 * @return \Change\Collection\ItemInterface[]
	 */
	public function getDefaultItems()
	{
		/** @var \Change\Collection\ItemInterface[] $result */
		$result = [];
		$em = $this->getEventManager();
		$args = $em->prepareArgs([]);
		$em->trigger('getDefaultItems', $this, $args);
		if (isset($args['defaultItems']))
		{
			$defaultItems = $args['defaultItems'];
			if (is_array($defaultItems) || $defaultItems instanceof \Traversable)
			{
				foreach ($defaultItems as $item)
				{
					if ($item instanceof \Change\Collection\ItemInterface)
					{
						$result[] = $item;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultGetDefaultItems(\Change\Documents\Events\Event $event)
	{

		if ($event->getDocument() !== $this)
		{
			return;
		}
		if ($this->getCollectionCode())
		{
			$collection = $event->getApplicationServices()->getCollectionManager()->getCollection($this->getCollectionCode());
			if ($collection)
			{
				$event->setParam('defaultItems', $collection->getItems());
			}
		}
	}
}