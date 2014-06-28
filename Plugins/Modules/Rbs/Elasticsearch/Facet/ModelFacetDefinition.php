<?php
/**
 * Copyright (C) 2014 Ready Business System
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Elasticsearch\Facet;

/**
 * @name \Rbs\Elasticsearch\Facet\ModelFacetDefinition
 */
class ModelFacetDefinition implements FacetDefinitionInterface
{
	/**
	 * @var string
	 */
	protected $title = 'm.rbs.elasticsearch.fo.facet-model-title';

	/**
	 * @var \Zend\Stdlib\Parameters
	 */
	protected $parameters;

	/**
	 * @var \Change\I18n\I18nManager
	 */
	protected $i18nManager;

	function __construct()
	{
		$this->getParameters()->set(static::PARAM_MULTIPLE_CHOICE, true);
	}

	/**
	 * @param \Change\I18n\I18nManager $i18nManager
	 * @return $this
	 */
	public function setI18nManager($i18nManager)
	{
		$this->i18nManager = $i18nManager;
		return $this;
	}

	/**
	 * @return \Change\I18n\I18nManager
	 */
	protected function getI18nManager()
	{
		return $this->i18nManager;
	}


	/**
	 * @param string $title
	 * @return $this
	 */
	public function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->getI18nManager()->trans($this->title);
	}

	/**
	 * @return string
	 */
	public function getFieldName()
	{
		return 'model';
	}

	/**
	 * @return \Zend\Stdlib\Parameters
	 */
	public function getParameters()
	{
		if ($this->parameters === null)
		{
			$this->parameters = new \Zend\Stdlib\Parameters();
		}
		return $this->parameters;
	}

	/**
	 * @return boolean
	 */
	public function hasChildren()
	{
		return false;
	}

	/**
	 * @return FacetDefinitionInterface[]
	 */
	public function getChildren()
	{
		return [];
	}

	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @param array $documentData
	 * @return array
	 */
	public function addIndexData($document, array $documentData)
	{
		return $documentData;
	}

	/**
	 * Part of index mapping
	 * @return array
	 */
	public function getMapping()
	{
		return [];
	}

	/**
	 * @param array $facetFilters
	 * @param array $context
	 * @return \Elastica\Filter\Terms|null
	 */
	public function getFilterQuery(array $facetFilters, array $context = [])
	{
		$filterName = $this->getFieldName();
		if (isset($facetFilters[$filterName]))
		{
			$facetFilter = is_array($facetFilters[$filterName]) ? $facetFilters[$filterName] : [$facetFilters[$filterName]];
			$terms = [];
			foreach ($facetFilter as $key)
			{
				$key = strval($key);
				if (!empty($key))
				{
					$terms[] = $key;
				}
			}

			if (count($terms))
			{
				$filterQuery = new \Elastica\Filter\Terms('model', $terms);
				return $filterQuery;
			}
		}
		return null;
	}

	/**
	 * @param array $context
	 * @return \Elastica\Aggregation\AbstractAggregation
	 */
	public function getAggregation(array $context = [])
	{
		$aggregation = new \Elastica\Aggregation\Terms('model');
		$aggregation->setField('model');
		return $aggregation;
	}

	/**
	 * @param $aggregations
	 * @return \Rbs\Elasticsearch\Facet\AggregationValues
	 */
	public function formatAggregation(array $aggregations)
	{
		$av = new \Rbs\Elasticsearch\Facet\AggregationValues($this);
		$mappingName = 'model';
		if (isset($aggregations[$mappingName]['buckets']))
		{
			foreach ($aggregations[$mappingName]['buckets'] as $bucket)
			{
				$v = new \Rbs\Elasticsearch\Facet\AggregationValue($bucket['key'], $bucket['doc_count']);
				$av->addValue($v);
			}
		}
		return $av;
	}

	/**
	 * @deprecated
	 * @return boolean
	 */
	public function getShowEmptyItem()
	{
		return false;
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getFacetType()
	{
		return static::TYPE_TERM;
	}
}