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
 * @name \Rbs\Generic\Events\DocumentManager\DocumentManager
 */
class DocumentManager
{
	/**
	 * @param \Change\Events\Event $event
	 */
	public function onGetTypology($event)
	{
		if ($event->getParam('typology') !== null)
		{
			return;
		}

		$document = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($event->getParam('typologyId'));
		if (!($document instanceof \Rbs\Generic\Documents\Typology))
		{
			return;
		}
		$event->setParam('typology', new \Rbs\Generic\Attributes\Typology($document));
	}
}