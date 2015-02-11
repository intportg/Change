<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Http\Rest\V1\Resources;

use Change\Documents\Interfaces\Localizable;
use Change\Http\Rest\V1\ErrorResult;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Change\Http\Rest\V1\Resources\UpdateDocument
 */
class UpdateDocument
{
	/**
	 * @param \Change\Http\Event $event
	 * @throws \RuntimeException
	 * @return \Change\Documents\AbstractDocument|null
	 */
	protected function getDocument($event)
	{
		$modelName = $event->getParam('modelName');
		$model = ($modelName) ? $event->getApplicationServices()->getModelManager()->getModelByName($modelName) : null;
		if (!$model)
		{
			throw new \RuntimeException('Invalid Parameter: modelName', 71000);
		}

		$documentId = intval($event->getParam('documentId'));
		$document = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($documentId, $model);
		if (!$document)
		{
			return null;
		}
		return $document;
	}

	/**
	 * Use Event Params: documentId, modelName
	 * @param \Change\Http\Event $event
	 * @throws \Exception
	 */
	public function execute($event)
	{
		$event->getApplication()->getLogging()->fatal(__METHOD__ . ' ' . __LINE__);
		$document = $this->getDocument($event);
		if (!$document)
		{
			// Document Not Found.
			return;
		}

		$document->useCorrection($event->getApplication()->getConfiguration()->getEntry('Change/Http/Rest/useCorrection'));

		if ($document instanceof Localizable)
		{
			$event->setParam('LCID', $document->getRefLCID());
			$updateLocalizedDocument = new UpdateLocalizedDocument();
			$updateLocalizedDocument->execute($event);
			return;
		}
		$properties = $event->getRequest()->getPost()->toArray();

		$transactionManager = $event->getApplicationServices()->getTransactionManager();
		try
		{
			$transactionManager->begin();
			$this->updateAttributes($event, $document, $properties);
			$result = $document->populateDocumentFromRestEvent($event);
			if ($result)
			{
				$this->update($event, $document, $properties);
			}
			$transactionManager->commit();
		}
		catch (\Exception $e)
		{
			throw $transactionManager->rollBack($e);
		}
	}

	/**
	 * @param \Change\Http\Event $event
	 * @param \Change\Documents\AbstractDocument $document
	 * @param array $properties
	 * @throws \Exception
	 */
	protected function update($event, $document, $properties)
	{
		try
		{
			$document->update();
			$document->reset();
			$getDocument = new GetDocument();
			$getDocument->execute($event);
		}
		catch (\Change\Documents\PropertiesValidationException $e)
		{
			$errors = $e->getPropertiesErrors();
			$errorResult =
				new ErrorResult('VALIDATION-ERROR', 'Document properties validation error', HttpResponse::STATUS_CODE_409);
			if (count($errors) > 0)
			{
				$i18nManager = $event->getApplicationServices()->getI18nManager();
				$pe = [];
				foreach ($errors as $propertyName => $errorsMsg)
				{
					foreach ($errorsMsg as $errorMsg)
					{
						$pe[$propertyName][] = $i18nManager->trans($errorMsg);
					}
				}
				$errorResult->addDataValue('properties-errors', $pe);
			}
			$event->setResult($errorResult);
			return;
		}
	}

	/**
	 * @param \Change\Http\Event $event
	 * @param \Change\Documents\AbstractDocument $document
	 * @param array $properties
	 * @throws \Exception
	 */
	protected function updateAttributes($event, $document, $properties)
	{
		$event->getApplication()->getLogging()->fatal(__METHOD__ . ' ' . __LINE__);
		$data = isset($properties['typology$']) ? $properties['typology$'] : null;
		if (is_array($properties['typology$']))
		{
			$documentManager = $event->getApplicationServices()->getDocumentManager();
			$typology = null;
			if (isset($data['id']) && is_int($data['id']) && $data['id'] > 0)
			{
				$typology = $documentManager->getTypology($data['id']);
			}

			if (!($typology instanceof \Change\Documents\Attributes\Interfaces\Typology))
			{
				$documentManager->saveAttributeValues($document, null, []);
			}
			else
			{
				$newValues = isset($data['values']) && is_array($data['values']) ? $data['values'] : [];
				$values = $documentManager->getAttributeValues($document);
				foreach ($typology->getGroups() as $group)
				{
					foreach ($group->getAttributes() as $attribute)
					{
						if (!isset($newValues['attr_' . $attribute->getId()]))
						{
							continue;
						}

						$values['_.' . $attribute->getId()] = $newValues['attr_' . $attribute->getId()];
					}
				}
				$documentManager->saveAttributeValues($document, $typology, $values);
			}
		}
	}
}
