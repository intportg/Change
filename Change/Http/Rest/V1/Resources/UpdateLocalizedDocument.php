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
 * @name \Change\Http\Rest\V1\Resources\UpdateLocalizedDocument
 */
class UpdateLocalizedDocument
{

	/**
	 * @param \Change\Http\Event $event
	 * @throws \RuntimeException
	 * @return Localizable|\Change\Documents\AbstractDocument|null
	 */
	protected function getDocument($event)
	{
		$modelName = $event->getParam('modelName');
		$model = ($modelName) ? $event->getApplicationServices()->getModelManager()->getModelByName($modelName) : null;
		if (!$model || !$model->isLocalized())
		{
			throw new \RuntimeException('Invalid Parameter: modelName', 71000);
		}

		$documentId = intval($event->getParam('documentId'));
		$document = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($documentId, $model);
		if (!$document)
		{
			return null;
		}

		if (!($document instanceof Localizable))
		{
			throw new \RuntimeException('Invalid Parameter: documentId', 71000);
		}

		return $document;
	}

	/**
	 * Use Required Event Params: documentId, modelName, LCID
	 * @param \Change\Http\Event $event
	 * @throws \RuntimeException
	 * @throws \Exception
	 */
	public function execute($event)
	{
		$LCID = $event->getParam('LCID');
		if (!$LCID || !$event->getApplicationServices()->getI18nManager()->isSupportedLCID($LCID))
		{
			throw new \RuntimeException('Invalid Parameter: LCID', 71000);
		}

		$document = $this->getDocument($event);
		if (!$document)
		{
			//Document Not Found
			return;
		}

		$document->useCorrection($event->getApplication()->getConfiguration()->getEntry('Change/Http/Rest/useCorrection'));

		$properties = $event->getRequest()->getPost()->toArray();
		if (isset($properties['LCID']) && $properties['LCID'] != $LCID)
		{
			$supported = array($LCID);
			$errorResult = new ErrorResult('INVALID-LCID', 'Invalid LCID property value', HttpResponse::STATUS_CODE_409);
			$errorResult->addDataValue('value', $properties['LCID']);
			$errorResult->addDataValue('supported-LCID', $supported);
			$event->setResult($errorResult);
			return;
		}

		$documentManager = $event->getApplicationServices()->getDocumentManager();
		$transactionManager = $event->getApplicationServices()->getTransactionManager();
		$pop = false;
		try
		{
			$documentManager->pushLCID($LCID);
			$pop = true;
			$transactionManager->begin();
			if (!$document->isNew())
			{
				$result = $document->populateDocumentFromRestEvent($event);
				if ($result)
				{
					$this->update($event, $document, $properties);
				}
			}
			else
			{
				/* @var $document Localizable */
				$supported = $document->getLCIDArray();
				$errorResult = new ErrorResult('INVALID-LCID', 'Invalid LCID property value', HttpResponse::STATUS_CODE_409);
				$errorResult->addDataValue('value', $LCID);
				$errorResult->addDataValue('supported-LCID', $supported);
				$event->setResult($errorResult);
			}
			$transactionManager->commit();
			$documentManager->popLCID();
		}
		catch (\Exception $e)
		{
			if ($pop)
			{
				$documentManager->popLCID();
			}
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
			$getDocument = new GetLocalizedDocument();
			$getDocument->execute($event);
		}
		catch (\Change\Documents\PropertiesValidationException $e)
		{
			$errors = $e->getPropertiesErrors();
			$errorResult = new ErrorResult('VALIDATION-ERROR', 'Document properties validation error', HttpResponse::STATUS_CODE_409);
			if (count($errors) > 0)
			{
				$i18nManager = $event->getApplicationServices()->getI18nManager();
				$pe = array();
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
		$data = isset($properties['typology$']) ? $properties['typology$'] : null;
		if (is_array($properties['typology$']))
		{
			$documentManager = $event->getApplicationServices()->getDocumentManager();
			$typology = null;
			if (isset($data['id']) && $data['id'] > 0)
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
						if (!array_key_exists('attr_' . $attribute->getId(), $newValues))
						{
							continue;
						}

						if (!$attribute->getLocalized())
						{
							$key = '_.' . $attribute->getId();
						}
						else
						{
							$key = $documentManager->getLCID() . '.' . $attribute->getId();
						}
						$values[$key] = $newValues['attr_' . $attribute->getId()];
					}
				}
				$documentManager->saveAttributeValues($document, $typology, $values);
			}
		}
	}
}
