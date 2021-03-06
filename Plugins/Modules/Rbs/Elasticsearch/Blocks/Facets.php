<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Elasticsearch\Blocks;

use Change\Presentation\Blocks\Event;
use Change\Presentation\Blocks\Parameters;
use Change\Presentation\Blocks\Standard\Block;

/**
 * @name \Rbs\Elasticsearch\Blocks\Result
 */
class Facets extends Block
{
	/**
	 * Event Params 'website', 'document', 'page'
	 * @api
	 * Set Block Parameters on $event
	 * @param Event $event
	 * @return Parameters
	 */
	protected function parameterize($event)
	{
		$parameters = parent::parameterize($event);
		$parameters->addParameterMeta('searchText');
		$parameters->addParameterMeta('allowedSectionIds');
		$parameters->addParameterMeta('facetFilters', null);
		$parameters->addParameterMeta('excludeProducts', null);
		$parameters->setNoCache();

		$parameters->setLayoutParameters($event->getBlockLayout());

		$genericServices = $this->getGenericServices($event);

		/** @var $website \Rbs\Website\Documents\Website */
		$website = $event->getParam('website');
		if ($genericServices == null || !($website instanceof \Rbs\Website\Documents\Website))
		{
			$this->setInvalidParameters($parameters);
			return $parameters;
		}


		$fulltextIndex = $genericServices->getIndexManager()->getFulltextIndexByWebsite($website, $website->getLCID());
		if (!$fulltextIndex)
		{
			$this->setInvalidParameters($parameters);
			return $parameters;
		}

		$parameters->setParameterValue('fulltextIndex', $fulltextIndex->getId());

		$request = $event->getHttpRequest();
		$queryFilters = $request->getQuery('facetFilters', null);
		$facetFilters = array();
		if (is_array($queryFilters)) {
			foreach ($queryFilters as $fieldName => $rawValue)
			{
				if (is_string($fieldName) && $rawValue)
				{
					$facetFilters[$fieldName] = $rawValue;
				}
			}
		}
		$parameters->setParameterValue('facetFilters', $facetFilters);

		$searchText = $request->getQuery('searchText');
		if ($searchText && is_string($searchText))
		{
			$parameters->setParameterValue('searchText', $searchText);
		}

		$uri = $event->getUrlManager()->getSelf();
		$query = $uri->getQueryAsArray();
		unset($query['facetFilters']);
		$parameters->setParameterValue('formAction', $uri->setQuery($query)->normalize()->toString());
		return $parameters;
	}

	/**
	 * @param Event $event
	 * @return \Rbs\Generic\GenericServices
	 */
	protected function getGenericServices($event)
	{
		$genericServices = $event->getServices('genericServices');
		if (!($genericServices instanceof \Rbs\Generic\GenericServices))
		{
			return null;
		}
		return $genericServices;
	}

	/**
	 * @param Parameters $parameters
	 */
	protected function setInvalidParameters($parameters)
	{
		$parameters->setParameterValue('fulltextIndex', 0);
	}

	/**
	 * Set $attributes and return a twig template file name OR set HtmlCallback on result
	 * @param Event $event
	 * @param \ArrayObject $attributes
	 * @return string|null
	 */
	protected function execute($event, $attributes)
	{
		$parameters = $event->getBlockParameters();
		$fullTextIndexId = $parameters->getParameter('fulltextIndex');

		$genericServices = $this->getGenericServices($event);
		if (!$genericServices || !$fullTextIndexId)
		{
			return null;
		}

		$applicationServices = $event->getApplicationServices();
		$documentManager = $applicationServices->getDocumentManager();

		/** @var $fullTextIndex \Rbs\Elasticsearch\Documents\FullText */
		$fullTextIndex = $documentManager->getDocumentInstance($fullTextIndexId, 'Rbs_Elasticsearch_FullText');
		$searchText = $parameters->getParameter('searchText');
		if (!$fullTextIndex || !$searchText)
		{
			return null;
		}
		$allowedSectionIds = $parameters->getParameter('allowedSectionIds');

		$indexManager = $genericServices->getIndexManager();

		$client = $indexManager->getElasticaClient($fullTextIndex->getClientName());
		if (!$client)
		{
			$applicationServices->getLogging()->warn(__METHOD__ . ': invalid client ' . $fullTextIndex->getClientName());
			return null;
		}

		$index = $client->getIndex($fullTextIndex->getName());
		if (!$index->exists())
		{
			$applicationServices->getLogging()->warn(__METHOD__ . ': index not exist ' . $fullTextIndex->getName());
			return null;
		}

		$excludeProducts = $parameters->getParameterValue('excludeProducts');
		$facetFilters = $parameters->getParameter('facetFilters');
		$queryHelper = new \Rbs\Elasticsearch\Index\QueryHelper($fullTextIndex, $indexManager, $genericServices->getFacetManager());
		$query = $queryHelper->getSearchQuery($searchText, $allowedSectionIds);
		$queryHelper->addHighlight($query);

		$facets = $fullTextIndex->getFacetsDefinition();
		$filter = null;
		if (is_array($facetFilters) && count($facetFilters))
		{
			$filter = $queryHelper->getFacetsFilter($facets, $facetFilters, []);
		}

		if ($excludeProducts)
		{
			if (!$filter)
			{
				$filter = new \Elastica\Filter\Bool();
			}
			$filter->addMustNot($facets[0]->getFiltersQuery(['model' => ['Rbs_Catalog_Product' => '1']], []));
		}

		if ($filter)
		{
			$query->setFilter($filter);
		}
		$queryHelper->addFacets($query, $facets);

		$query->setSize(0);
		$searchResult = $index->getType($fullTextIndex->getDefaultTypeName())->search($query);

		if ($searchResult)
		{
			$facetValues = $queryHelper->formatAggregations($searchResult->getAggregations(), $facets);
			if (is_array($facetFilters) && count($facetFilters))
			{
				$queryHelper->applyFacetFilters($facetValues, $facetFilters);
			}

			$facet = $facetValues[0];
			$newFacet = new \Rbs\Elasticsearch\Facet\AggregationValues($facet->getFacet());
			foreach ($facet->getValues() as $value)
			{
				if (!$excludeProducts || $value->getKey() !== 'Rbs_Catalog_Product')
				{
					$newFacet->addValue($value);
				}
			}
			$facetValues[0] = $newFacet;
			$attributes['facets'] = $facets;
			$attributes['facetValues'] = $facetValues;
		}

		return 'facets.twig';
	}
}