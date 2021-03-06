<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Website\Documents;

use Change\Http\Web\UrlManager;
use Zend\Uri\Http;

/**
 * @name \Rbs\Website\Documents\Website
 */
class Website extends \Compilation\Rbs\Website\Documents\Website implements \Change\Presentation\Interfaces\Website
{
	/**
	 * @return string
	 */
	public function getRelativePath()
	{
		return  $this->getPathPart();
	}

	/**
	 * @return \Change\Presentation\Interfaces\Website
	 */
	public function getWebsite()
	{
		return $this;
	}

	/**
	 * @return \Change\Presentation\Interfaces\Section[]
	 */
	public function getPublicationSections()
	{
		return $this->isNew() ? [] : [$this];
	}

	/**
	 * @param \Change\Documents\AbstractDocument $publicationSections
	 * @return $this
	 */
	public function setPublicationSections($publicationSections)
	{
		// TODO: Implement setPublicationSections() method.
		return $this;
	}

	/**
	 * @param string $url
	 * @return $this
	 */
	public function setBaseurl($url)
	{
		$currentLocalisation = $this->getCurrentLocalization();
		$url = new Http($url);
		if ($url->getScheme() === "https")
		{
			$currentLocalisation->setHttps(true);
		}
		else
		{
			$currentLocalisation->setHttps(false);
		}
		$currentLocalisation->setHostName($url->getHost());
		$currentLocalisation->setPort($url->getPort());
		$fullPath = $url->getPath();
		$index = strpos($fullPath, '.php');
		if ($index !== false)
		{
			$script = substr($fullPath, 0, $index + 4);
			$path = trim(substr($fullPath, $index + 4), '/');
			$currentLocalisation->setPathPart($path ? $path : null);
			$currentLocalisation->setScriptName($script ? $script : null);
		}
		else
		{
			$path = trim($url->getPath(), '/');
			$currentLocalisation->setPathPart($path ? $path : null);
			$currentLocalisation->setScriptName(null);
		}
		return $this;
	}

	/**
	 * @return string
	 */
	public function getBaseurl()
	{
		return $this->getUrlManager(null)->getByPathInfo('')->normalize()->toString();
	}


	protected $urlManagersByLCID = [];

	/**
	 * @param string $LCID
	 * @throws \RuntimeException
	 * @return \Change\Http\Web\UrlManager
	 */
	public function getUrlManager($LCID)
	{
		if (!$LCID)
		{
			$LCID = $this->getLCID();
		}

		if (!isset($this->urlManagersByLCID[$LCID]))
		{
			$event = new \Change\Documents\Events\Event('getUrlManager', $this, ['LCID' => $LCID]);
			$this->getEventManager()->trigger($event);

			$urlManager = $event->getParam('urlManager');
			if ($urlManager instanceof \Change\Http\Web\UrlManager)
			{
				$this->urlManagersByLCID[$LCID] = $urlManager;
			}
			else
			{
				throw new \RuntimeException('Unable to get valid urlManager');
			}
		}
		return $this->urlManagersByLCID[$LCID];
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultGetUrlManager(\Change\Documents\Events\Event $event)
	{
		if ($this !== $event->getDocument())
		{
			return;
		}
		$LCID = $event->getParam('LCID');
		try
		{
			$this->getDocumentManager()->pushLCID($LCID);

			$url = new Http();
			$url->setScheme($this->getHttps() ? "https" : "http");
			$url->setHost($this->getHostName());
			$url->setPort($this->getPort());
			$url->setPath('/');

			$urlManager = new UrlManager($url, $this->getScriptName());
			$urlManager->setDocumentManager($this->getDocumentManager());
			$urlManager->setPathRuleManager($event->getApplicationServices()->getPathRuleManager());
			$urlManager->absoluteUrl(true);
			$urlManager->setWebsite($this);
			$urlManager->setLCID($LCID);
			$urlManager->setBasePath($this->getPathPart());
			$this->getDocumentManager()->popLCID();

			$event->setParam('urlManager', $urlManager);
		}
		catch (\Exception $e)
		{
			$this->getDocumentManager()->popLCID($e);
		}
	}

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_CREATED, [$this, 'onCreated'], 5);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_UPDATE, [$this, 'onWebsiteUpdate'], 5);
		$eventManager->attach('getUrlManager', [$this, 'onDefaultGetUrlManager'], 5);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onCreated(\Change\Documents\Events\Event $event)
	{
		/* @var $website Website */
		$website = $event->getDocument();
		$tm = $event->getApplicationServices()->getTreeManager();
		$parentNode = $tm->getRootNode($website->getDocumentModel()->getTreeName());
		if ($parentNode)
		{
			$tm->insertNode($parentNode, $website);
		}

		$website->setSitemaps($this->defaultSitemaps());
		$website->save();
	}

	/**
	 * @return string
	 */
	public function getLCID()
	{
		return $this->getCurrentLCID();
	}

	/**
	 * @return string
	 */
	public function getHttps()
	{
		return $this->getCurrentLocalization()->getHttps();
	}

	/**
	 * @return string
	 */
	public function getHostName()
	{
		return $this->getCurrentLocalization()->getHostName();
	}

	/**
	 * @return integer
	 */
	public function getPort()
	{
		return $this->getCurrentLocalization()->getPort();
	}

	/**
	 * @return string
	 */
	public function getScriptName()
	{
		return $this->getCurrentLocalization()->getScriptName();
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 * @throws \Exception
	 */
	public function onWebsiteUpdate(\Change\Documents\Events\Event $event)
	{
		$jobManager = $event->getApplicationServices()->getJobManager();
		if ($this->getSitemapGeneration())
		{
			$siteMaps = [];
			foreach ($this->getSitemaps() as $siteMap)
			{
				if (!isset($siteMap['jobId']))
				{
					$LCID = $siteMap['LCID'];
					$timeInterval = $siteMap['timeInterval'];
					if ($timeInterval && $LCID && in_array($LCID, $this->getLCIDArray()))
					{
						$job = $jobManager->createNewJob('Rbs_Seo_GenerateSitemap', [
							'websiteId' => $this->getId(),
							'LCID' => $LCID,
							'randomKey' => \Change\Stdlib\String::random()
						]);
						$siteMap['jobId'] = $job->getId();
					}
					else
					{
						throw new \Exception('sitemap generation job cannot be created with websiteId: ' . $this->getId() .
						 ' LCID: ' . $LCID . ' and time interval: ' . $timeInterval, 999999);
					}
				}
				//Notify user for URL creation (if he want it, 'notify' attribute is added to sitemap)
				elseif (isset($siteMap['notify']) && isset($siteMap['url']))
				{
					$this->notifyUserOfSitemapURLCreation($siteMap, $event->getApplicationServices());
					unset($siteMap['notify']);
				}
				$siteMaps[] = $siteMap;
			}
			$this->setSitemaps($siteMaps);
		}
		else
		{
			//stop generation sitemap jobs if exist
			foreach ($this->getSitemaps() as $siteMap)
			{
				if (isset($siteMap['jobId']))
				{
					$job = $jobManager->getJob($siteMap['jobId']);
					if ($job !== null)
					{
						$jobManager->updateJobStatus($job, \Change\Job\JobInterface::STATUS_SUCCESS);
					}
				}
			}
			//back to default sitemaps
			$this->setSitemaps($this->defaultSitemaps());
		}
	}

	/**
	 * @return array
	 */
	protected function defaultSitemaps()
	{
		$siteMaps = [];
		foreach ($this->getLCIDArray() as $LCID)
		{
			$siteMaps[] = ['LCID' => $LCID, 'timeInterval' => ''];
		}
		return $siteMaps;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onGetPageByFunction(\Change\Documents\Events\Event $event)
	{
		$website = $event->getDocument();
		if ($website instanceof Website)
		{
			$functionCode = $event->getParam('functionCode');
			$q = $event->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_Website_Page');
			$spfq = $q->getModelBuilder('Rbs_Website_SectionPageFunction', 'page');
			$spfq->andPredicates($spfq->eq('functionCode', $functionCode), $spfq->eq('section', $website));
			$page = $q->getFirstDocument();
			if ($page instanceof StaticPage)
			{
				$event->setParam('page', $page);
				$event->setParam('section', $page->getSection());
			}
			elseif ($page instanceof FunctionalPage)
			{
				$page->setSection($website);
				$event->setParam('page', $page);
				$event->setParam('section', $website);
			}
		}
	}

	/**
	 * @param array $sitemap
	 * @param \Change\Services\ApplicationServices $applicationServices
	 * @throws \Exception
	 */
	protected function notifyUserOfSitemapURLCreation($sitemap, $applicationServices)
	{
		$userId = isset($sitemap['notify']) && isset($sitemap['notify']['userId']) ? $sitemap['notify']['userId'] : null;
		if ($userId)
		{
			$user = $applicationServices->getDocumentManager()->getDocumentInstance($userId);
			$LCID = isset($sitemap['LCID']) ? $sitemap['LCID'] : null;
			if ($user instanceof \Rbs\User\Documents\User && $LCID)
			{
				$authenticatedUser = new \Rbs\User\Events\AuthenticatedUser($user);
				$params = [
					'website' => $this->getLabel(),
					'LCID' => $LCID
				];

				$i18nManager = $applicationServices->getI18nManager();
				$profileManager = $applicationServices->getProfileManager();
				$userProfile = $profileManager->loadProfile($authenticatedUser, 'Change_User');
				$userLCID = $userProfile->getPropertyValue('LCID') != null ? $userProfile->getPropertyValue('LCID') : $i18nManager->getDefaultLCID();
				try
				{
					$applicationServices->getDocumentManager()->pushLCID($userLCID);
					$notification = $applicationServices->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Notification_Notification');
					/* @var $notification \Rbs\Notification\Documents\Notification */
					$notification->setUserId($user->getId());
					$notification->setCode('website_sitemap_url_creation_' . $this->getId() . '_' . $LCID);
					$notification->getCurrentLocalization()->setMessage($i18nManager->transForLCID($userLCID, 'm.rbs.website.admin.website_notification_sitemap_url_creation', ['ucf'], $params));
					$notification->setParams($params);
					$notification->save();
					$applicationServices->getDocumentManager()->popLCID();
				}
				catch (\Exception $e)
				{
					$applicationServices->getLogging()->exception($e);
					$applicationServices->getDocumentManager()->popLCID();
				}
			}
		}
	}
}