<?php
namespace Rbs\Elasticsearch\Commands;

use Change\Commands\Events\Event;

/**
 * @name \Rbs\Elasticsearch\Commands\Index
 */
class Index
{
	/**
	 * @param Event $event
	 */
	public function execute(Event $event)
	{
		$application = $event->getApplication();
		$applicationServices = new \Change\Application\ApplicationServices($application);
		$documentServices = new \Change\Documents\DocumentServices($applicationServices);
		$elasticsearchServices = new \Rbs\Elasticsearch\ElasticsearchServices($applicationServices, $documentServices);
		$indexManager = $elasticsearchServices->getIndexManager();

		$hasClient = false;
		$all = $event->getParam('all') == true;
		$publishable = $event->getParam('publishable') == true;
		$specificModelName = $event->getParam('model');
		if (!is_string($specificModelName) || count(explode('_', $specificModelName)) != 3)
		{
			$specificModelName = null;
		}

		if (!$all && !$publishable && !$specificModelName)
		{
			$event->addCommentMessage('No model specified.');
			return;
		}
		foreach ($indexManager->getClientsName() as $clientName)
		{
			try
			{
				$client = $indexManager->getClient($clientName);
				if ($client)
				{
					$srvStat = $client->getStatus()->getServerStatus();
					if (isset($srvStat['ok']) && $srvStat['ok'])
					{
						$hasClient = true;
						break;
					}
				}
			}
			catch (\Exception $e)
			{
				$applicationServices->getLogging()->exception($e);
			}
		}

		if ($hasClient)
		{
			if ($event->getParam('useJob'))
			{
				$jobManager = new \Change\Job\JobManager();
				$jobManager->setDocumentServices($documentServices);
			}
			else
			{
				$jobManager = null;
			}

			$documentCount = 0;
			foreach ($documentServices->getModelManager()->getModelsNames() as $modelsName)
			{
				$model = $documentServices->getModelManager()->getModelByName($modelsName);
				if ($model->isAbstract() || $model->isStateless())
				{
					continue;
				}

				if (!$all)
				{
					if ($publishable && !$model->isPublishable())
					{
						continue;
					}
					if ($specificModelName && $modelsName != $specificModelName)
					{
						continue;
					}
				}
				$event->addInfoMessage('Schedule indexation of ' . $modelsName . ' model...');

				$LCID = $documentServices->getDocumentManager()->getLCID();
				$id = 0;
				while (true)
				{
					$toIndex = array();
					$documentServices->getDocumentManager()->reset();
					$q = new \Change\Documents\Query\Query($documentServices, $model);
					$q->andPredicates($q->gt('id', $id));
					$q->addOrder('id');
					$docs = $q->getDocuments(0, 50);

					foreach ($docs as $doc)
					{
						$documentCount++;
						if ($doc instanceof \Change\Documents\Interfaces\Localizable)
						{
							foreach ($doc->getLCIDArray() as $LCID)
							{
								$toIndex[] = array('id' => $doc->getId(), 'model' => $model->getName(), 'LCID' => $LCID,
									'deleted' => false);
							}
						}
						elseif ($doc instanceof \Change\Documents\AbstractDocument)
						{
							$toIndex[] = array('id' => $doc->getId(), 'model' => $model->getName(), 'LCID' => $LCID,
								'deleted' => false);
						}
					}

					if (count($toIndex))
					{
						if ($jobManager)
						{
							$jobManager->createNewJob('Elasticsearch_Index', $toIndex);
						}
						else
						{
							$indexManager->dispatchIndexationEvents($toIndex);
						}
					}

					if ($docs->count() < 50)
					{
						break;
					}
					else
					{
						$id = max($docs->ids());
					}
				}
			}
			if ($jobManager)
			{
				$event->addInfoMessage('Indexation of ' . $documentCount . ' documents are scheduled.');
			}
			else
			{
				$event->addInfoMessage($documentCount . ' documents are indexed.');
			}
		}
		else
		{
			$event->addErrorMessage('No active client detected.');
		}
	}
}