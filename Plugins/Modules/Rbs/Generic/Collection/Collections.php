<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Collection;

use Change\I18n\I18nString;

/**
 * @name \Rbs\Generic\Collection\Collections
 */
class Collections
{
	/**
	 * @param \Change\Events\Event $event
	 */
	public function addSortDirections(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$i18n = $applicationServices->getI18nManager();
			$collection = [
				'asc' => new I18nString($i18n, 'm.rbs.generic.front.ascending', ['ucf']),
				'desc' => new I18nString($i18n, 'm.rbs.generic.front.descending', ['ucf'])
			];
			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Collection_SortDirections', $collection);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addPermissionRoles(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$i18n = $applicationServices->getI18nManager();
			$collection = [
				'*' => new I18nString($i18n, 'm.rbs.generic.admin.any_role', ['ucf']),
				'Consumer' => new I18nString($i18n, 'm.rbs.generic.admin.role_consumer', ['ucf']),
				'Creator' => new I18nString($i18n, 'm.rbs.generic.admin.role_creator', ['ucf']),
				'Editor' => new I18nString($i18n, 'm.rbs.generic.admin.role_editor', ['ucf']),
				'Publisher' => new I18nString($i18n, 'm.rbs.generic.admin.role_publisher', ['ucf']),
				'Administrator' => new I18nString($i18n, 'm.rbs.generic.admin.role_administrator', ['ucf'])
			];
			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Collection_PermissionRoles', $collection);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addPermissionPrivileges(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$i18n = $applicationServices->getI18nManager();
			$modelsNames = $applicationServices->getModelManager()->getModelsNames();
			$collection = array_combine($modelsNames, $modelsNames);
			$collection['*'] = new I18nString($i18n, 'm.rbs.generic.admin.any_privilege', ['ucf']);
			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Collection_PermissionPrivileges', $collection);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addTimeZones(\Change\Events\Event $event)
	{
		$items = [];
		foreach (\DateTimeZone::listIdentifiers() as $timeZoneName)
		{
			$now = new \DateTime('now', new \DateTimeZone($timeZoneName));
			$items[$timeZoneName] = $timeZoneName . ' (' . $now->format('P') . ')';
		}

		$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Collection_TimeZones', $items);
		$event->setParam('collection', $collection);
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addLanguages(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$items = [];
			foreach ($applicationServices->getI18nManager()->getSupportedLCIDs() as $lcid)
			{
				$items[$lcid] = \Locale::getDisplayLanguage($lcid, $applicationServices->getI18nManager()->getLCID());
			}
			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Collection_Languages', $items);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addAddressFields(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$docQuery = $applicationServices->getDocumentManager()->getNewQuery('Rbs_Geo_AddressFields');
			$qb = $docQuery->dbQueryBuilder();
			$fb = $qb->getFragmentBuilder();
			$query = $qb->addColumn($fb->alias($docQuery->getColumn('id'), 'id'))
				->addColumn($fb->alias($docQuery->getColumn('label'), 'label'))->query();

			$items = [];
			foreach ($query->getResults() as $row)
			{
				$items[$row['id']] = $row['label'];
			}
			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Collection_AddressFields', $items);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addTypologies(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$modelName = $event->getParam('modelName');
			$docQuery = $applicationServices->getDocumentManager()->getNewQuery('Rbs_Generic_Typology');
			if ($modelName)
			{
				$pb2 = $docQuery->getPredicateBuilder();
				$docQuery->andPredicates($pb2->eq('modelName', $modelName));
			}
			$qb = $docQuery->dbQueryBuilder();
			$fb = $qb->getFragmentBuilder();
			$query = $qb->addColumn($fb->alias($docQuery->getColumn('id'), 'id'))
				->addColumn($fb->alias($docQuery->getColumn('label'), 'label'))->query();

			$items = [];
			foreach ($query->getResults() as $row)
			{
				$items[$row['id']] = $row['label'];
			}

			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_Typologies', $items);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addAttributeValueTypes(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$i18n = $applicationServices->getI18nManager();
			$types = [
				\Rbs\Generic\Documents\Attribute::TYPE_BOOLEAN,
				\Rbs\Generic\Documents\Attribute::TYPE_INTEGER,
				\Rbs\Generic\Documents\Attribute::TYPE_FLOAT,
				\Rbs\Generic\Documents\Attribute::TYPE_DATETIME,
				\Rbs\Generic\Documents\Attribute::TYPE_STRING,
				\Rbs\Generic\Documents\Attribute::TYPE_RICHTEXT,
				\Rbs\Generic\Documents\Attribute::TYPE_DOCUMENTID,
				\Rbs\Generic\Documents\Attribute::TYPE_DOCUMENTIDARRAY
			];

			$items = [];
			foreach ($types as $type)
			{
				$items[$type] = new I18nString($i18n, 'm.rbs.generic.admin.attribute_type_' . strtolower($type), ['ucf']);
			}

			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_AttributeValueTypes', $items);
			$event->setParam('collection', $collection);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function addAttributeCollections(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		if ($applicationServices)
		{
			$docQuery = $applicationServices->getDocumentManager()->getNewQuery('Rbs_Collection_Collection');
			$qb = $docQuery->dbQueryBuilder();
			$fb = $qb->getFragmentBuilder();
			$query = $qb->addColumn($fb->alias($docQuery->getColumn('code'), 'code'))
				->addColumn($fb->alias($docQuery->getColumn('label'), 'label'))->query();
			$items = array();
			foreach ($query->getResults() as $row)
			{
				$items[$row['code']] = $row['label'];
			}
			$collection = new \Change\Collection\CollectionArray('Rbs_Generic_AttributeCollections', $items);
			$event->setParam('collection', $collection);
		}
	}
}