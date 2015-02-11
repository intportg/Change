<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Collection;

use Change\Collection\CollectionManager;
use Change\Events\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

/**
 * @name \Rbs\Generic\Collection\Listeners
 */
class Listeners implements ListenerAggregateInterface
{
	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the EventManager
	 * implementation will pass this to the aggregate.
	 * @param EventManagerInterface $events
	 * @return void
	 */
	public function attach(EventManagerInterface $events)
	{
		$callback = function (Event $event)
		{
			if (!$event->getParam('collection'))
			{
				(new \Rbs\Collection\Events\CollectionResolver())->getCollection($event);
			}
		};
		$events->attach(CollectionManager::EVENT_GET_COLLECTION, $callback, 5);

		$callback = function (Event $event)
		{
			(new \Rbs\Collection\Events\CollectionResolver())->getCodes($event);
		};
		$events->attach(CollectionManager::EVENT_GET_CODES, $callback, 5);

		$callback = function (Event $event)
		{
			if (!$event->getParam('collection'))
			{
				switch ($event->getParam('code'))
				{
					case 'Rbs_Elasticsearch_Collection_Clients':
						(new \Rbs\Elasticsearch\Collection\Collections())->addClients($event);
						break;
					case 'Rbs_Elasticsearch_Collection_Indexes':
						(new \Rbs\Elasticsearch\Collection\Collections())->addIndexes($event);
						break;
					case 'Rbs_Elasticsearch_CollectionIds':
						(new \Rbs\Elasticsearch\Collection\Collections())->addCollectionIds($event);
						break;
					case 'Rbs_Elasticsearch_Collection_AttributeIds':
						(new \Rbs\Elasticsearch\Collection\Collections())->addAttributeIds($event);
						break;
					case 'Rbs_Elasticsearch_FacetConfigurationType':
						(new \Rbs\Elasticsearch\Collection\Collections())->addFacetConfigurationType($event);
						break;
					case 'Rbs_Elasticsearch_FacetRenderingModes':
						(new \Rbs\Elasticsearch\Collection\Collections())->addFacetRenderingModes($event);
						break;

					case 'Rbs_Generic_AttributeCollections':
						(new \Rbs\Generic\Collection\Collections())->addAttributeCollections($event);
						break;
					case 'Rbs_Generic_AttributeValueTypes':
						(new \Rbs\Generic\Collection\Collections())->addAttributeValueTypes($event);
						break;
					case 'Rbs_Generic_Collection_AddressFields':
						(new \Rbs\Generic\Collection\Collections())->addAddressFields($event);
						break;
					case 'Rbs_Generic_Collection_Languages':
						(new \Rbs\Generic\Collection\Collections())->addLanguages($event);
						break;
					case 'Rbs_Generic_Collection_PermissionPrivileges':
						(new \Rbs\Generic\Collection\Collections())->addPermissionPrivileges($event);
						break;
					case 'Rbs_Generic_Collection_PermissionRoles':
						(new \Rbs\Generic\Collection\Collections())->addPermissionRoles($event);
						break;
					case 'Rbs_Generic_Collection_SortDirections':
						(new \Rbs\Generic\Collection\Collections())->addSortDirections($event);
						break;
					case 'Rbs_Generic_Collection_TimeZones':
						(new \Rbs\Generic\Collection\Collections())->addTimeZones($event);
						break;
					case 'Rbs_Generic_Typologies':
						(new \Rbs\Generic\Collection\Collections())->addTypologies($event);
						break;

					case 'Rbs_Geo_All_Countries_Codes':
						(new \Rbs\Geo\Collection\Collections())->addAllCountriesCodes($event);
						break;
					case 'Rbs_Geo_Collection_Countries':
						(new \Rbs\Geo\Collection\Collections())->addCountries($event);
						break;
					case 'Rbs_Geo_AddressField_Names':
						(new \Rbs\Geo\Collection\Collections())->addAddressFieldNames($event);
						break;

					case 'Rbs_Media_BlockAlignments':
						(new \Rbs\Media\Collection\Collections())->addBlockAlignments($event);
						break;

					case 'Rbs_Simpleform_ConfirmationModes':
						(new \Rbs\Simpleform\Collection\Collections())->addConfirmationModes($event);
						break;
					case 'Rbs_Simpleform_FieldTypes':
						(new \Rbs\Simpleform\Collection\Collections())->addFieldTypes($event);
						break;
					case 'Rbs_Simpleform_AutoCapitalizeOptions':
						(new \Rbs\Simpleform\Collection\Collections())->addAutoCapitalizeOptions($event);
						break;

					case 'Rbs_Tag_Collection_TagModules':
						(new \Rbs\Tag\Collection\Collections())->addTagModules($event);
						break;

					case 'Rbs_Theme_WebsiteIds':
						(new \Rbs\Theme\Collection\Collections())->addWebsiteIds($event);
						break;

					case 'Rbs_Website_AvailablePageFunctions':
						(new \Rbs\Admin\Collection\Collections())->addAvailablePageFunctions($event);
						break;
					case 'Rbs_Website_InterstitialAudiences':
						(new \Rbs\Website\Collection\Collections())->addInterstitialAudiences($event);
						break;
					case 'Rbs_Website_InterstitialDisplayFrequencies':
						(new \Rbs\Website\Collection\Collections())->addInterstitialDisplayFrequencies($event);
						break;
					case 'Rbs_Website_InterstitialPopinSizes':
						(new \Rbs\Website\Collection\Collections())->addInterstitialPopinSizes($event);
						break;
				}
			}
		};
		$events->attach(CollectionManager::EVENT_GET_COLLECTION, $callback, 10);

		$callback = function (Event $event)
		{
			$codes = $event->getParam('codes', []);
			$codes = array_merge($codes, [
				'Rbs_Elasticsearch_Collection_Clients',
				'Rbs_Elasticsearch_Collection_Indexes',
				'Rbs_Elasticsearch_CollectionIds',
				'Rbs_Elasticsearch_Collection_AttributeIds',
				'Rbs_Elasticsearch_FacetConfigurationType',
				'Rbs_Elasticsearch_FacetRenderingModes',

				'Rbs_Generic_AttributeCollections',
				'Rbs_Generic_AttributeValueTypes',
				'Rbs_Generic_Collection_AddressFields',
				'Rbs_Generic_Collection_Languages',
				'Rbs_Generic_Collection_PermissionRoles',
				'Rbs_Generic_Collection_PermissionPrivileges',
				'Rbs_Generic_Collection_SortDirections',
				'Rbs_Generic_Collection_TimeZones',
				'Rbs_Generic_Typologies',

				'Rbs_Geo_All_Countries_Codes',
				'Rbs_Geo_Collection_Countries',
				'Rbs_Geo_AddressField_Names',

				'Rbs_Simpleform_ConfirmationModes',
				'Rbs_Simpleform_FieldTypes',
				'Rbs_Simpleform_AutoCapitalizeOptions',

				'Rbs_Tag_Collection_TagModules',

				'Rbs_Theme_WebsiteIds',

				'Rbs_Website_AvailablePageFunctions',
				'Rbs_Website_InterstitialAudiences',
				'Rbs_Website_InterstitialDisplayFrequencies',
				'Rbs_Website_InterstitialPopinSizes',
			]);
			$event->setParam('codes', $codes);
		};
		$events->attach(CollectionManager::EVENT_GET_CODES, $callback, 1);
	}

	/**
	 * Detach all previously attached listeners
	 * @param EventManagerInterface $events
	 * @return void
	 */
	public function detach(EventManagerInterface $events)
	{
		// TODO: Implement detach() method.
	}
}