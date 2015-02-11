<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Events\DocumentManager;

/**
 * @name \Rbs\Generic\Events\DocumentManager\Listeners
 */
class Listeners implements \Zend\EventManager\ListenerAggregateInterface
{
	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the EventManager
	 * implementation will pass this to the aggregate.
	 * @param \Zend\EventManager\EventManagerInterface $events
	 * @return void
	 */
	public function attach(\Zend\EventManager\EventManagerInterface $events)
	{
		$callback = function ($event)
		{
			(new \Rbs\Generic\Events\DocumentManager\DocumentManager())->onGetTypology($event);
		};
		$events->attach('getTypology', $callback, 5);
	}

	/**
	 * Detach all previously attached listeners
	 * @param \Zend\EventManager\EventManagerInterface $events
	 * @return void
	 */
	public function detach(\Zend\EventManager\EventManagerInterface $events)
	{
		// TODO: Implement detach() method.
	}
}
