<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Events;

use Change\Documents\Events\Event as DocumentEvent;
use Zend\EventManager\SharedEventManagerInterface;

use Zend\EventManager\SharedListenerAggregateInterface;

/**
 * @name \Change\Events\DefaultSharedListenerAggregate
 */
class DefaultSharedListenerAggregate implements SharedListenerAggregateInterface
{
	/**
	 * @var \Change\Services\ApplicationServices;
	 */
	protected $applicationServices;


	protected $documentInstances = [];

	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the SharedEventManager
	 * implementation will pass this to the aggregate.
	 * @param SharedEventManagerInterface $events
	 */
	public function attachShared(SharedEventManagerInterface $events)
	{
		$events->attach('*', '*', function($event) {
			if ($event instanceof \Change\Events\Event)
			{
				if ($this->applicationServices === null) {

					$this->applicationServices = new \Change\Services\ApplicationServices($event->getApplication());
				}
				$event->getServices()->set('applicationServices', $this->applicationServices);
			}
			return true;
		}, 9999);

		$identifiers = ['Documents'];

		$events->attach($identifiers, 'setInCache', function(\Change\Events\Event $event) {
			$document = $event->getParam('document');
			if ($document instanceof \Change\Documents\AbstractDocument && $document->getId() > 0)
			{
				$this->documentInstances[$document->getId()] = $document;
			}
			$event->stopPropagation();
		}, 20000);

		$events->attach($identifiers, 'getFromCache', function(\Change\Events\Event $event) {
			$id = $event->getParam('id', 0);
			$event->setParam('document', isset($this->documentInstances[$id]) ? $this->documentInstances[$id] : null);
			$event->stopPropagation();
		}, 20000);

		$events->attach($identifiers, 'resetCache', function(\Change\Events\Event $event) {
			array_map(function (\Change\Documents\AbstractDocument $document) {$document->cleanUp();}, $this->documentInstances);
			$this->documentInstances = [];
			$event->stopPropagation();
		}, 20000);

		$callBack = function ($event)
		{
			(new \Change\Documents\Events\ValidateListener())->onValidate($event);
		};
		$events->attach($identifiers, array(DocumentEvent::EVENT_CREATE, DocumentEvent::EVENT_UPDATE), $callBack, 5);

		$callBack = function ($event)
		{
			(new \Change\Documents\Events\PublishListener())->onUpdated($event);
		};
		$events->attach($identifiers, DocumentEvent::EVENT_UPDATED, $callBack, 5);

		$callBack = function ($event)
		{
			(new \Change\Documents\Events\DeleteListener())->onDelete($event);
		};
		$events->attach($identifiers, DocumentEvent::EVENT_DELETE, $callBack, 5);

		$callBack = function ($event)
		{
			(new \Change\Documents\Events\DeleteListener())->onDeleted($event);
		};
		$events->attach($identifiers, DocumentEvent::EVENT_DELETED, $callBack, 5);

		$callBack = function ($event)
		{
			(new \Change\Documents\Events\DeleteListener())->onLocalizedDeleted($event);
		};
		$events->attach($identifiers, DocumentEvent::EVENT_LOCALIZED_DELETED, $callBack, 5);

		$callBack = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				$event->getDocument()->onDefaultUpdateRestResult($event);
			}
		};
		$events->attach($identifiers, 'updateRestResult', $callBack, 10);

		$callBack = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				$event->getDocument()->onDefaultRouteParamsRestResult($event);
			}
		};
		$events->attach($identifiers, 'updateRestResult', $callBack, 5);


		$callBack = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				$event->getDocument()->onDefaultCorrectionFiled($event);
			}
		};
		$events->attach($identifiers, 'correctionFiled', $callBack, 5);

		$callBack = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\InlineEvent)
			{
				$event->getDocument()->onDefaultGetRestValue($event);
			}
		};
		$events->attach('Inline', 'getRestValue', $callBack, 5);

		$callBack = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\InlineEvent)
			{
				$event->getDocument()->onDefaultProcessRestValue($event);
			}
		};
		$events->attach('Inline', 'processRestValue', $callBack, 5);


		$callBack = function ($event)
		{
			(new \Change\Documents\Events\DeleteListener())->onCleanUp($event);
		};
		$events->attach('JobManager', 'process_Change_Document_CleanUp', $callBack, 5);


		$callBack = function ($event)
		{
			/** @var $event \Change\Events\Event */
			$predicateJSON = $event->getParam('predicateJSON');
			if (is_array($predicateJSON) && isset($predicateJSON['op']) && ucfirst($predicateJSON['op']) === 'HasCode')
			{
				$predicateBuilder = $event->getParam('predicateBuilder');
				$documentCodeManager = $event->getApplicationServices()->getDocumentCodeManager();
				$hasTag = new \Change\Db\Query\Predicates\HasCode();
				$fragment = $hasTag->populate($predicateJSON, $predicateBuilder, $documentCodeManager);
				if ($fragment)
				{
					$event->setParam('SQLFragment', $fragment);
					$event->stopPropagation();
				}
			}
		};
		$events->attach('Db', 'SQLFragment', $callBack, 5);

		$callback = function ($event)
		{
			/** @var $event \Change\Events\Event */
			$fragment = $event->getParam('fragment');
			if ($fragment instanceof \Change\Db\Query\Predicates\HasCode)
			{
				/** @var $dbProvider \Change\Db\DbProvider */
				$dbProvider = $event->getTarget();
				$event->setParam('sql', $fragment->toSQLString($dbProvider));
				$event->stopPropagation();
			}
		};
		$events->attach('Db', 'SQLFragmentString', $callback, 5);
	}

	/**
	 * Detach all previously attached listeners
	 * @param SharedEventManagerInterface $events
	 */
	public function detachShared(SharedEventManagerInterface $events)
	{
	}
}
