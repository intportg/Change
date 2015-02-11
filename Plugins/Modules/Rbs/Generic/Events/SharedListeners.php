<?php
/**
 * Copyright (C) 2014 Ready Business System, Eric Hauswald
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Events;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\SharedListenerAggregateInterface;

/**
 * @name \Rbs\Generic\Events\SharedListeners
 */
class SharedListeners implements SharedListenerAggregateInterface
{
	/**
	 * @var \Rbs\Generic\GenericServices
	 */
	protected $genericServices;

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
				if ($this->genericServices === null) {

					$this->genericServices = new \Rbs\Generic\GenericServices($event->getApplication(), $event->getApplicationServices());
				}
				$event->getServices()->set('genericServices', $this->genericServices);
			}
			return true;
		}, 9998);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				$website = $event->getDocument();
				if ($website instanceof \Rbs\Website\Documents\Website)
				{
					(new \Rbs\Website\Events\WebsiteResolver())->changed($event->getApplication());
				}
			}
		};
		$eventNames = array('documents.created', 'documents.updated');
		$events->attach('Rbs_Website_Website', $eventNames, $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Website\Events\PageResolver())->resolve($event);
			}
		};
		$events->attach('Documents', 'http.web.displayPage', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Workflow\Tasks\PublicationProcess\Start())->execute($event);
			}
		};
		$events->attach('Documents', array('documents.created', 'documents.localized.created'), $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Workflow\Tasks\CorrectionPublicationProcess\Start())->execute($event);
			}
		};
		$events->attach('Documents', 'correction.created', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Workflow\Http\Rest\Actions\ExecuteTask())->addTasks($event);
			}
		};
		$events->attach('Documents', 'updateRestResult', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Events\Event)
			{
				(new \Rbs\Seo\Std\ModelConfigurationGenerator())->onPluginSetupSuccess($event);
			}
		};
		$events->attach('Plugin', 'setupSuccess', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Website\Events\PathRuleBuilder())->updatePathRules($event);
			}
		};
		$events->attach('Documents', ['documents.updated'], $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Seo\Std\DocumentSeoGenerator())->onDocumentCreated($event);
			}
		};
		$events->attach('Documents', 'documents.created', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Seo\Http\Rest\UpdateDocumentLinks())->addLinks($event);
			}
		};
		$events->attach('Documents', 'updateRestResult', $callback, 5);

		/** @var $toIndex \Rbs\Elasticsearch\Index\ToIndexDocuments */
		$toIndex = null;

		$callback = function ($event) use (&$toIndex)
		{
			if (!$toIndex)
			{
				$toIndex = new \Rbs\Elasticsearch\Index\ToIndexDocuments();
			}
			$toIndex->start($event);

		};
		$events->attach('TransactionManager', 'begin', $callback);

		$callback = function ($event) use (&$toIndex)
		{
			if ($toIndex)
			{
				$toIndex->indexDocument($event);
			}
		};
		$events->attach('Documents', ['documents.created', 'documents.localized.created', 'documents.updated',
			'documents.deleted', 'documents.localized.deleted'], $callback, 5);

		$callback = function ($event) use (&$toIndex)
		{
			if ($toIndex)
			{
				$toIndex->addJob($event);
			}
		};
		$events->attach('TransactionManager', 'commit', $callback, 10);
	}

	/**
	 * Detach all previously attached listeners
	 * @param SharedEventManagerInterface $events
	 */
	public function detachShared(SharedEventManagerInterface $events)
	{
		//TODO
	}
}