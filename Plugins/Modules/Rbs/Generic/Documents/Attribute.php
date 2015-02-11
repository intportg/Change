<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Documents;

/**
 * @name \Rbs\Generic\Documents\Attribute
 */
class Attribute extends \Compilation\Rbs\Generic\Documents\Attribute
{
	const TYPE_BOOLEAN = 'Boolean';
	const TYPE_INTEGER = 'Integer';
	const TYPE_FLOAT = 'Float';

	const TYPE_DATETIME = 'DateTime';

	const TYPE_STRING = 'String';

	const TYPE_RICHTEXT = 'RichText';

	const TYPE_DOCUMENTID = 'DocumentId';
	const TYPE_DOCUMENTIDARRAY = 'DocumentIdArray';

	/**
	 * @param string $name
	 * @param mixed $value
	 * @param \Change\Http\Event $event
	 * @return boolean
	 */
	protected function processRestData($name, $value, \Change\Http\Event $event)
	{
		if ($name === 'documentType' && is_array($value))
		{
			$value = $value['name'];
		}
		return parent::processRestData($name, $value, $event);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$extraColumn = $event->getParam('extraColumn');
			if (in_array('valueTypeFormatted', $extraColumn))
			{
				$types = $event->getApplicationServices()->getCollectionManager()->getCollection('Rbs_Generic_AttributeValueTypes');
				$item = $types->getItemByValue($this->getValueType());
				if ($item)
				{
					$restResult->setProperty('valueTypeFormatted', $item->getLabel());
				}
				else
				{
					$restResult->setProperty('valueTypeFormatted', $this->getValueType());
				}
			}
		}
	}
}
