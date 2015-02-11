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
 * @name \Rbs\Generic\Documents\Typology
 */
class Typology extends \Compilation\Rbs\Generic\Documents\Typology
{
	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');

		/** @var $document \Rbs\Generic\Documents\Typology */
		$document = $event->getDocument();
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$definitions = [];
			foreach ($document->getGroups() as $group)
			{
				foreach ($group->getAttributes() as $attribute)
				{
					$definitions[$attribute->getId()] = [
						'id' => $attribute->getId(),
						'label' => $attribute->getLabel(),
						'type' => $attribute->getValueType(),
						'documentType' => $attribute->getDocumentType(),
						'usePicker' => $attribute->getUsePicker(),
						'required' => $attribute->getRequiredValue(),
						'defaultValue' => $attribute->getDefaultValue(),
						'localized' => $attribute->getLocalizedValue(),
						'collectionCode' => $attribute->getCollectionCode()
					];
				}
			}
			$restResult->setProperty('attributesDefinitions', $definitions);
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$model = $event->getApplicationServices()->getModelManager()->getModelByName($document->getModelName());
			if ($model)
			{
				$i18n = $event->getApplicationServices()->getI18nManager();
				$restResult->setProperty('modelLabel', $i18n->trans($model->getLabelKey(), ['ucf']));
			}
		}
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @param \Change\Http\Event $event
	 * @return boolean
	 */
	protected function processRestData($name, $value, \Change\Http\Event $event)
	{
		if ($name === 'modelName' && is_array($value))
		{
			$value = $value['name'];
		}
		return parent::processRestData($name, $value, $event);
	}
}
