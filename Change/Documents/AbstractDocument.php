<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Documents;

use Change\Documents\Interfaces\Activable;
use Change\Documents\Interfaces\Correction;
use Change\Documents\Interfaces\Editable;
use Change\Documents\Interfaces\Localizable;
use Change\Documents\Interfaces\Publishable;
use Zend\EventManager\EventsCapableInterface;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Change\Documents\AbstractDocument
 * @api
 */
abstract class AbstractDocument implements \Serializable, EventsCapableInterface,
	\Change\Http\Rest\V1\RestfulDocumentInterface
{
	const STATE_NEW = 1;

	const STATE_INITIALIZED = 2;

	const STATE_LOADING = 3;

	const STATE_LOADED = 4;

	const STATE_SAVING = 5;

	const STATE_DELETED = 6;

	const STATE_DELETING = 7;

	/**
	 * @var integer
	 */
	private $persistentState = self::STATE_NEW;

	/**
	 * @var integer
	 */
	private $id = 0;

	/**
	 * @var string
	 */
	private $documentModelName;

	/**
	 * @var array
	 */
	protected $modifiedProperties = [];

	/**
	 * @var boolean
	 */
	protected $useCorrection = true;

	/**
	 * @var \Change\Documents\AbstractModel
	 */
	protected $documentModel;

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;

	/**
	 * @var \Change\Application
	 */
	protected $application;

	/**
	 * @var \Change\Events\EventManager
	 */
	protected $eventManager;

	/**
	 * @var \Change\Db\DbProvider
	 */
	protected $dbProvider;

	/**
	 * @param AbstractModel $model
	 */
	public function __construct(AbstractModel $model)
	{
		$this->documentModel = $model;
		$this->documentModelName = $model->getName();
	}

	/**
	 * @param \Change\Application $application
	 * @return $this
	 */
	public function setApplication(\Change\Application $application)
	{
		$this->application = $application;
		return $this;
	}

	/**
	 * @return \Change\Application
	 */
	protected function getApplication()
	{
		return $this->application;
	}

	/**
	 * @param \Change\Documents\DocumentManager $documentManager
	 * @return $this
	 */
	public function setDocumentManager(\Change\Documents\DocumentManager $documentManager)
	{
		$this->documentManager = $documentManager;
		return $this;
	}

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	protected function getDocumentManager()
	{
		return $this->documentManager;
	}

	/**
	 * @param \Change\Db\DbProvider $dbProvider
	 * @return $this
	 */
	public function setDbProvider(\Change\Db\DbProvider $dbProvider)
	{
		$this->dbProvider = $dbProvider;
		return $this;
	}

	/**
	 * @return \Change\Db\DbProvider
	 */
	protected function getDbProvider()
	{
		return $this->dbProvider;
	}

	public function cleanUp()
	{
		if (isset($this->eventManager))
		{
			foreach ($this->eventManager->getEvents() as $event)
			{
				$this->eventManager->clearListeners($event);
			}
			$this->eventManager = null;
		}
	}

	/**
	 * This class is not serializable
	 * @return null
	 */
	public function serialize()
	{
		return null;
	}

	/**
	 * @param string $serialized
	 * @return void
	 */
	public function unserialize($serialized)
	{
		return;
	}

	/**
	 * @api
	 * @return \Change\Documents\AbstractModel
	 */
	public function getDocumentModel()
	{
		return $this->documentModel;
	}

	/**
	 * @api
	 * @return string
	 */
	public function getDocumentModelName()
	{
		return $this->documentModelName;
	}

	/**
	 * @api
	 * @param boolean|null $useCorrection
	 * @return boolean
	 */
	public function useCorrection($useCorrection = null)
	{
		if (is_bool($useCorrection))
		{
			$oldValue = $this->useCorrection;
			$this->useCorrection = $useCorrection;
			return $oldValue;
		}
		return $this->useCorrection && $this->getDocumentModel()->useCorrection();
	}

	/**
	 * Retrieve the event manager
	 * @api
	 * @throws \RuntimeException
	 * @return \Change\Events\EventManager
	 */
	public function getEventManager()
	{
		if ($this->eventManager === null)
		{
			if ($this->application)
			{
				$model = $this->getDocumentModel();
				$identifiers = array_merge($model->getAncestorsNames(), [$model->getName(), 'Documents']);
				$this->eventManager = $this->application->getNewEventManager($identifiers);
				$this->eventManager->setEventClass('\Change\Documents\Events\Event');
			}
			else
			{
				throw new \RuntimeException('application not set', 999999);
			}
			$this->attachEvents($this->eventManager);
		}
		return $this->eventManager;
	}

	/**
	 * Attach specific document event
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
	}

	/**
	 * @param integer $id
	 * @param integer $persistentState
	 */
	public function initialize($id, $persistentState = null)
	{
		$this->id = intval($id);
		if ($persistentState !== null)
		{
			$this->setPersistentState($persistentState);
		}
		$this->getDocumentManager()->reference($this);
	}

	/**
	 * @param mixed $inputValue
	 * @param string $propertyType
	 * @return bool|\DateTime|float|int|null|string
	 */
	protected function convertToInternalValue($inputValue, $propertyType)
	{
		switch ($propertyType)
		{
			case Property::TYPE_DATE:
				$inputValue = is_string($inputValue) ? new \DateTime($inputValue, new \DateTimeZone('UTC')) : $inputValue;
				return ($inputValue instanceof \DateTime) ? \DateTime::createFromFormat('Y-m-d', $inputValue->format('Y-m-d'),
					new \DateTimeZone('UTC'))->setTime(0, 0) : null;

			case Property::TYPE_DATETIME:
				return is_string($inputValue) ? new \DateTime($inputValue, new \DateTimeZone('UTC')) : (($inputValue instanceof
					\DateTime) ? $inputValue : null);

			case Property::TYPE_BOOLEAN:
				return ($inputValue === null) ? $inputValue : (bool)$inputValue;

			case Property::TYPE_INTEGER:
				return ($inputValue === null) ? $inputValue : intval($inputValue);

			case Property::TYPE_FLOAT:
			case Property::TYPE_DECIMAL:
				return ($inputValue === null) ? $inputValue : floatval($inputValue);

			case Property::TYPE_DOCUMENTID :
				if (is_object($inputValue) && is_callable([$inputValue, 'getId']))
				{
					$inputValue = call_user_func([$inputValue, 'getId']);
				}
				return max(0, intval($inputValue));
			case Property::TYPE_JSON:
				return ($inputValue === null || is_string($inputValue)) ? $inputValue : json_encode($inputValue);

			case Property::TYPE_INLINE:
				return ($inputValue === null || is_string($inputValue)) ? $inputValue : serialize($inputValue);

			case Property::TYPE_DOCUMENT:
			case Property::TYPE_DOCUMENTARRAY:
				return ($inputValue === null || !($inputValue instanceof AbstractDocument)) ? 0 : $inputValue->getId();
			default:
				return $inputValue === null ? $inputValue : strval($inputValue);
		}
	}

	/**
	 * @param float $v1
	 * @param float $v2
	 * @param float $delta
	 * @return boolean
	 */
	protected function compareFloat($v1, $v2, $delta = 0.000001)
	{
		if ($v1 === $v2)
		{
			return true;
		}
		elseif ($v1 === null || $v2 === null)
		{
			return false;
		}
		return abs(floatval($v1) - floatval($v2)) <= $delta;
	}

	/**
	 * @api
	 */
	public function reset()
	{
		$this->unsetProperties();
		if ($this->persistentState === static::STATE_LOADED)
		{
			$this->persistentState = static::STATE_INITIALIZED;
		}
		elseif ($this->persistentState === static::STATE_NEW)
		{
			$this->setDefaultValues($this->documentModel);
		}
	}

	/**
	 * Set private properties to null.
	 */
	protected function unsetProperties()
	{
		$this->clearModifiedProperties();
	}

	/**
	 * @param AbstractModel $documentModel
	 */
	public function setDefaultValues(AbstractModel $documentModel)
	{
		$this->persistentState = static::STATE_NEW;
		foreach ($documentModel->getProperties() as $property)
		{
			/* @var $property \Change\Documents\Property */
			if (!$property->getLocalized() && $property->getDefaultValue() !== null)
			{
				$property->setValue($this, $property->getDefaultValue());
			}
		}
	}

	/**
	 * Persistent state list: static::STATE_*
	 * @api
	 * @return integer
	 */
	public function getPersistentState()
	{
		return $this->persistentState;
	}

	/**
	 * Persistent state list: static::STATE_*
	 * @api
	 * @param integer $newValue
	 * @return integer oldState
	 */
	public function setPersistentState($newValue)
	{
		$oldState = $this->persistentState;
		switch ($newValue)
		{
			case static::STATE_LOADED:
				$this->clearModifiedProperties();
				$this->persistentState = $newValue;
				break;
			case static::STATE_NEW:
			case static::STATE_INITIALIZED:
			case static::STATE_LOADING:
			case static::STATE_DELETING:
			case static::STATE_DELETED:
			case static::STATE_SAVING:
				$this->persistentState = $newValue;
				break;
		}
		return $oldState;
	}

	/**
	 * @api
	 * @return boolean
	 */
	public function isDeleted()
	{
		return $this->persistentState === static::STATE_DELETED;
	}

	/**
	 * @api
	 * @return boolean
	 */
	public function isNew()
	{
		return $this->persistentState === static::STATE_NEW;
	}

	/**
	 * @api
	 * @return boolean
	 */
	public function isLoaded()
	{
		return $this->persistentState === static::STATE_LOADED;
	}

	/**
	 * @api
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @api
	 * @param string $propertyName
	 */
	public function removeOldPropertyValue($propertyName)
	{
		unset($this->modifiedProperties[$propertyName]);
	}

	/**
	 * @api
	 * @return string[]
	 */
	public function getModifiedPropertyNames()
	{
		return array_keys($this->modifiedProperties);
	}

	/**
	 * @api
	 * @return boolean
	 */
	public final function hasModifiedProperties()
	{
		return count($this->getModifiedPropertyNames()) !== 0;
	}

	/**
	 * @api
	 * @param string $propertyName
	 * @return boolean
	 */
	public final function isPropertyModified($propertyName)
	{
		return in_array($propertyName, $this->getModifiedPropertyNames());
	}

	protected function clearModifiedProperties()
	{
		$this->modifiedProperties = [];
	}

	/**
	 * @param string $propertyName
	 * @param mixed $value
	 */
	protected function setOldPropertyValue($propertyName, $value)
	{
		if (!array_key_exists($propertyName, $this->modifiedProperties))
		{
			$this->modifiedProperties[$propertyName] = $value;
		}
	}

	/**
	 * @param string $propertyName
	 * @return mixed
	 */
	protected function getOldPropertyValue($propertyName)
	{
		if (array_key_exists($propertyName, $this->modifiedProperties))
		{
			return $this->modifiedProperties[$propertyName];
		}
		return null;
	}

	/**
	 * @api
	 * @param \Change\Documents\AbstractDocument $b
	 * @return boolean
	 */
	public function equals($b)
	{
		return $this === $b || (($b instanceof AbstractDocument) && $b->getId() === $this->getId());
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->getDocumentModelName() . ' ' . $this->getId();
	}

	// Tree

	/**
	 * @param string|null $treeName
	 */
	public function setTreeName($treeName)
	{
		return;
	}

	/**
	 * @api
	 * @return string|null
	 */
	public function getTreeName()
	{
		return null;
	}

	// Generic Method

	abstract public function load();

	abstract public function save();

	abstract public function update();

	abstract public function create();

	abstract public function delete();

	/**
	 * @api
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultInjection(\Change\Events\Event $event)
	{
	}

	/**
	 * @param \Change\Http\Rest\V1\Resources\DocumentResult $documentResult
	 * @return $this
	 */
	public function populateRestDocumentResult($documentResult)
	{
		$documentEvent = new \Change\Documents\Events\Event('updateRestResult', $this,
			['restResult' => $documentResult, 'urlManager' => $documentResult->getUrlManager()]);
		$this->getEventManager()->trigger($documentEvent);
		return $this;
	}

	/**
	 * @param \Change\Http\Rest\V1\Resources\DocumentLink $documentLink
	 * @param Array
	 * @return $this
	 */
	public function populateRestDocumentLink($documentLink, $extraColumn)
	{
		$documentEvent = new \Change\Documents\Events\Event('updateRestResult', $this,
			['restResult' => $documentLink, 'extraColumn' => $extraColumn, 'urlManager' => $documentLink->getUrlManager()]);
		$this->getEventManager()->trigger($documentEvent);
		return $this;
	}

	public function onDefaultCorrectionFiled(\Change\Documents\Events\Event $event)
	{
		$as = $event->getApplicationServices();
		$correction = $event->getParam('correction');
		if ($as && ($correction instanceof \Change\Documents\Correction))
		{
			$jobManager = $as->getJobManager();
			$jobManager->createNewJob('Change_Correction_Filed', [
				'correctionId' => $correction->getId(), 'documentId' => $correction->getDocumentId(),
				'LCID' => $correction->getLCID()
			]);
		}
	}

	/**
	 * @param Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		$document = $event->getDocument();
		if (!$document instanceof AbstractDocument)
		{
			return;
		}
		$documentResult = $event->getParam('restResult');
		$um = $documentResult->getUrlManager();
		if ($documentResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$selfLinks = $documentResult->getRelLink('self');
			$selfLink = array_shift($selfLinks);
			if ($selfLink instanceof \Change\Http\Rest\V1\Link)
			{
				$deleteLink = new \Change\Http\Rest\V1\Link($um, $selfLink->getPathInfo(), 'delete');
				$deleteLink->setMethod('DELETE');
				$documentResult->addAction($deleteLink);
			}

			if ($document->getTreeName())
			{
				$tn = $event->getApplicationServices()->getTreeManager()->getNodeByDocument($document);
				if ($tn)
				{
					$l = new \Change\Http\Rest\V1\ResourcesTree\TreeNodeLink($um, $tn,
						\Change\Http\Rest\V1\ResourcesTree\TreeNodeLink::MODE_LINK);
					$l->setRel('node');
					$documentResult->addLink($l);
				}
			}
			$model = $document->getDocumentModel();

			/* @var $property \Change\Documents\Property */
			foreach ($model->getProperties() as $name => $property)
			{
				if ($property->getInternal())
				{
					continue;
				}

				$c = new \Change\Http\Rest\V1\PropertyConverter($document, $property, $document->getDocumentManager(), $um);
				$documentResult->setProperty($name, $c->getRestValue());
			}

			if ($document->useCorrection())
			{
				/* @var $document \Change\Documents\Interfaces\Correction|\Change\Documents\AbstractDocument */
				$correction = $document->getCurrentCorrection();
				if ($correction)
				{
					$l = new \Change\Http\Rest\V1\Resources\DocumentActionLink($um, $document, 'correction');
					$documentResult->addAction($l);
				}
			}

			if ($document instanceof \Change\Documents\Interfaces\Publishable)
			{
				$l = new \Change\Http\Rest\V1\Resources\DocumentActionLink($um, $document, 'pathRules');
				$documentResult->addLink($l);
			}

			if ($document instanceof \Change\Documents\Interfaces\Activable)
			{
				$active = $model->getPropertyValue($document, 'active');
				if ($active)
				{
					$l = new \Change\Http\Rest\V1\Link($um, 'actions/deactivate', 'deactivate');
				}
				else
				{
					$l = new \Change\Http\Rest\V1\Link($um, 'actions/activate', 'activate');
				}
				$query = ['documentId' => $document->getId()];
				if ($document instanceof \Change\Documents\Interfaces\Localizable)
				{
					$query['LCID'] = $event->getApplicationServices()->getDocumentManager()->getLCID();
				}
				$l->setQuery($query);
				$documentResult->addAction($l);
			}

			if ($document instanceof \Change\Documents\Interfaces\Editable)
			{
				$typologyId = $this->getDocumentManager()->getTypologyIdByDocument($document);
				if ($typologyId === 0)
				{
					$documentResult->setProperty('typology$', ['id' => 0]);
				}
				elseif ($typologyId > 0)
				{
					$attributeValues = [];
					$values = $this->getDocumentManager()->getAttributeValues($document);
					$currentLCID = $this->getDocumentManager()->getLCID();
					if (is_array($values))
					{
						foreach ($values as $key => $value)
						{
							list ($LCID, $attrId) = explode('.', $key);
							if ($LCID == '_' || $LCID == $currentLCID)
							{
								$attributeValues['attr_' . $attrId] = $value;
							}
						}
					}
					$documentResult->setProperty('typology$', ['id' => $typologyId, 'values' => $attributeValues]);
				}
			}
		}
		elseif ($documentResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$documentLink = $documentResult;
			$extraColumn = $event->getParam('extraColumn');
			$dm = $event->getApplicationServices()->getDocumentManager();

			if ($documentLink->getLCID())
			{
				$dm->pushLCID($documentLink->getLCID());
			}

			$model = $document->getDocumentModel();

			$documentLink->setProperty($model->getProperty('creationDate'));
			$documentLink->setProperty($model->getProperty('modificationDate'));

			if ($document instanceof Editable)
			{
				$documentLink->setProperty($model->getProperty('label'));
				$documentLink->setProperty($model->getProperty('documentVersion'));
			}

			$deleteLink = new \Change\Http\Rest\V1\Link($um, $documentLink->getPathInfo(), 'delete');
			$deleteLink->setMethod('DELETE');
			$documentLink->addAction($deleteLink);

			if ($document instanceof Publishable)
			{
				$documentLink->setProperty($model->getProperty('publicationStatus'));
			}
			elseif ($document instanceof Activable)
			{
				$documentLink->setProperty($model->getProperty('active'));
				$active = $model->getPropertyValue($document, 'active');
				if ($active)
				{
					$l = new \Change\Http\Rest\V1\Link($um, 'actions/deactivate', 'deactivate');
				}
				else
				{
					$l = new \Change\Http\Rest\V1\Link($um, 'actions/activate', 'activate');
				}
				$query = ['documentId' => $document->getId()];
				if ($document instanceof \Change\Documents\Interfaces\Localizable)
				{
					$query['LCID'] = $event->getApplicationServices()->getDocumentManager()->getLCID();
				}
				$l->setQuery($query);
				$documentLink->addAction($l);
			}

			if ($document instanceof Localizable)
			{
				$documentLink->setProperty($model->getProperty('refLCID'));
				$documentLink->setProperty($model->getProperty('LCID'));
			}

			if ($document instanceof Correction)
			{
				/* @var $document AbstractDocument|Correction */
				if ($document->hasCorrection())
				{
					$l = new \Change\Http\Rest\V1\Resources\DocumentActionLink($um, $document, 'correction');
					$documentLink->addAction($l);
					$documentLink->setProperty('correction', true);
				}
			}

			if (is_array($extraColumn) && count($extraColumn))
			{
				foreach ($extraColumn as $propertyName)
				{
					$property = $model->getProperty($propertyName);
					if ($property && !$property->getInternal())
					{
						$documentLink->setProperty($property);
					}
				}
			}

			if ($documentLink->getLCID())
			{
				$dm->popLCID();
			}
		}
	}

	/**
	 * @param Events\Event $event
	 */
	public function onDefaultRouteParamsRestResult(\Change\Documents\Events\Event $event)
	{
		// Add route params according to route.json.
	}

	protected $ignoredPropertiesForRestEvents = ['model'];

	/**
	 * Return false on error
	 * @param \Change\Http\Event $event
	 * @return $this|boolean
	 */
	public function populateDocumentFromRestEvent(\Change\Http\Event $event)
	{
		$data = $event->getRequest()->getPost()->toArray();
		foreach ($data as $name => $value)
		{
			if (!in_array($name, $this->ignoredPropertiesForRestEvents))
			{
				$result = $this->processRestData($name, $value, $event);
				if ($result === false)
				{
					return false;
				}
			}
		}
		$documentEvent = new \Change\Documents\Events\Event('populateDocumentFromRestEvent', $this,
			['restEvent' => $event]);
		$this->getEventManager()->trigger($documentEvent);

		return $event->getResult() instanceof \Change\Http\Rest\V1\ErrorResult ? false : $this;
	}

	/**
	 * Process the incoming REST data $name and set it to $value
	 * @param string $name
	 * @param mixed $value
	 * @param \Change\Http\Event $event
	 * @return boolean
	 */
	protected function processRestData($name, $value, \Change\Http\Event $event)
	{
		$property = $this->getDocumentModel()->getProperty($name);
		if ($property && !$property->getInternal())
		{
			if ($name == 'id' && intval($value) > 0 && $this->isNew())
			{
				$value = intval($value);
				$existingDocument = $this->getDocumentManager()->getDocumentInstance($value);
				if ($existingDocument)
				{
					$errorResult = new \Change\Http\Rest\V1\ErrorResult('DOCUMENT-ALREADY-EXIST', 'document already exist', HttpResponse::STATUS_CODE_409);
					$errorResult->setData(['document-id' => $value]);
					$errorResult->addDataValue('model-name', $this->getDocumentModelName());
					$event->setResult($errorResult);
					return false;
				}
				$this->initialize($value);
			}
			elseif($name == 'LCID' && $this->isNew())
			{
				return true;
			}
			else
			{
				try
				{
					$c = new \Change\Http\Rest\V1\PropertyConverter($this, $property, $this->getDocumentManager());
					$c->setPropertyValue($value);
				}
				catch (\Exception $e)
				{
					$errorResult = new \Change\Http\Rest\V1\ErrorResult('INVALID-VALUE-TYPE', 'Invalid property value type', HttpResponse::STATUS_CODE_409);
					$errorResult->setData(['name' => $name, 'value' => $value, 'type' => $property->getType()]);
					$errorResult->addDataValue('document-type', $property->getDocumentType());
					$event->setResult($errorResult);
					return false;
				}
			}
		}
		return true;
	}
}