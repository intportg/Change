<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Commerce\Events\Http\Rest;

use Change\Http\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

/**
 * @name \Rbs\Commerce\Events\Http\Rest\Listeners
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
		$callback = function (\Change\Events\Event $event)
		{
			$controller = $event->getTarget();
			if ($controller instanceof \Change\Http\Rest\Controller)
			{
				$resolver = $controller->getActionResolver();
				if ($resolver instanceof \Change\Http\Rest\Resolver)
				{
					$resolver->addResolverClasses('commerce', '\Rbs\Commerce\Http\Rest\CommerceResolver');
				}
			}
		};
		$events->attach('registerServices', $callback, 1);

		$events->attach(Event::EVENT_ACTION, array($this, 'registerActions'));
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

	/**
	 * @param Event $event
	 */
	public function registerActions(Event $event)
	{
		if (!$event->getAction())
		{
			$relativePath = $event->getParam('pathInfo');
			if ($relativePath === 'rbs/price/taxInfo')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Price\Http\Rest\Actions\TaxInfo())->execute($event);
				});
			}
			else if (preg_match('#^resources/Rbs/Catalog/ProductListItem/([0-9]+)/(highlight|downplay|moveup|movedown|highlighttop|highlightbottom)$#',
				$relativePath, $matches)
			)
			{
				$event->getController()->getActionResolver()
					->setAuthorization($event, 'Consumer', null, 'Rbs_Catalog_ProductListItem');
				$event->setParam('documentId', intval($matches[1]));
				$methodName = $matches[2];
				$event->setAction(function ($event) use ($methodName)
				{
					$cr = new \Rbs\Catalog\Http\Rest\ProductListItemResult();
					call_user_func(array($cr, $methodName), $event);
				});
			}
			else if (preg_match('#^resources/Rbs/Catalog/(ProductList|SectionProductList|CrossSellingProductList|Product)/([0-9]+)/ProductListItems/?$#',
				$relativePath, $matches)
			)
			{
				$event->getController()->getActionResolver()
					->setAuthorization($event, 'Consumer', null, 'Rbs_Catalog_ProductListItem');
				$event->setParam('documentId', intval($matches[2]));
				$event->setAction(function ($event)
				{
					$cr = new \Rbs\Catalog\Http\Rest\ProductListItemResult();
					$cr->productListItemCollection($event);
				});
			}
			else if (preg_match('#^resources/Rbs/Catalog/Product/([0-9]+)/Prices/?$#', $relativePath, $matches))
			{
				$event->getController()->getActionResolver()->setAuthorization($event, 'Consumer', null, 'Rbs_Price_Price');
				$event->setParam('documentId', intval($matches[1]));
				$event->setAction(function ($event)
				{
					$cr = new \Rbs\Catalog\Http\Rest\PriceResult();
					$cr->productPriceCollection($event);
				});
			}
			else if (preg_match('#^resources/Rbs/Catalog/VariantGroup/([0-9]+)/Products/?$#', $relativePath,
				$matches)
			)
			{
				$event->setParam('documentId', intval($matches[1]));
				$event->setAction(function ($event)
				{
					(new \Rbs\Catalog\Http\Rest\VariantGroup())->getProducts($event);
				});
			}
			else if ($relativePath === 'rbs/catalog/variantstocks')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Catalog\Http\Rest\VariantStocks())->getVariantStocks($event);
				});
			}
			else if ($relativePath === 'rbs/catalog/savevariantstocks')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Catalog\Http\Rest\VariantStocks())->saveVariantStocks($event);
				});
			}
			else if ($relativePath === 'rbs/catalog/variantprices')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Catalog\Http\Rest\VariantPrices())->getVariantPrices($event);
				});
			}
			else if ($relativePath === 'rbs/catalog/savevariantprices')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Catalog\Http\Rest\VariantPrices())->saveVariantPrices($event);
				});
			}
			else if ($relativePath === 'rbs/catalog/productlistitem/delete')
			{
				$event->getController()->getActionResolver()->setAuthorization($event, 'CategoryManager');
				$event->setAction(function ($event)
				{
					$cr = new \Rbs\Catalog\Http\Rest\ProductListItemResult();
					$cr->delete($event);
				});
			}
			else if ($relativePath === 'rbs/catalog/productlistitem/addproducts')
			{
				$event->getController()->getActionResolver()->setAuthorization($event, 'CategoryManager');
				$event->setAction(function ($event)
				{
					$cr = new \Rbs\Catalog\Http\Rest\ProductListItemResult();
					$cr->addproducts($event);
				});
			}
			else if ($relativePath === 'rbs/order/lineNormalize')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Order\Http\Rest\Actions\LineNormalize())->execute($event);
				});
			}
			else if ($relativePath === 'rbs/order/productPriceInfo')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Order\Http\Rest\Actions\ProductPriceInfo())->execute($event);
				});
			}
			else if ($relativePath === 'rbs/order/orderRemainder')
			{
				$event->setAction(function ($event)
				{
					(new \Rbs\Order\Http\Rest\Actions\OrderRemainder())->execute($event);
				});
			}
			else if (preg_match('#^resources/Rbs/Order/Order/([0-9]+)/Shipments/?$#', $relativePath, $matches))
			{
				$event->getController()->getActionResolver()->setAuthorization($event, 'Consumer', null, 'Rbs_Order_Shipment');
				$event->setParam('documentId', intval($matches[1]));
				$event->setAction(function ($event)
				{
					$cr = new \Rbs\Order\Http\Rest\ShipmentResult();
					$cr->orderShipmentCollection($event);
				});
			}
			else if (preg_match('#^resources/Rbs/Payment/Transaction/([0-9]+)/(validatePayment|refusePayment)/?$#', $relativePath, $matches))
			{
				$request = $event->getRequest();
				if ($request->isPost())
				{
					$event->getController()->getActionResolver()
						->setAuthorization($event, 'Consumer', null, 'Rbs_Payment_Transaction');
					$event->setParam('documentId', intval($matches[1]));
					$event->setParam('processingInfos', $request->getPost()->toArray());
					$methodName = $matches[2];
					$event->setAction(function ($event) use ($methodName)
					{
						$cr = new \Rbs\Payment\Http\Rest\UpdateProcessingStatusForTransaction();
						call_user_func(array($cr, $methodName), $event);
					});
				}
				else
				{
					$result = $event->getController()->notAllowedError($request->getMethod(), [\Change\Http\Request::METHOD_POST]);
					$event->setResult($result);
				}
			}
		}
	}
}