<?php
namespace Rbs\Mail;

/**
 * @name \Rbs\Mail\MailManager
 */
class MailManager implements \Zend\EventManager\EventsCapableInterface
{
	use \Change\Events\EventsCapableTrait;

	const EVENT_MANAGER_IDENTIFIER = 'MailManager';

	/**
	 * @var \Change\Job\JobManager
	 */
	protected $jobManager;

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;

	/**
	 * @param \Change\Documents\DocumentManager $documentManager
	 * @return $this
	 */
	public function setDocumentManager($documentManager)
	{
		$this->documentManager = $documentManager;
		return $this;
	}

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	protected function getDocumentManager()
	{
		return $this->documentManager;
	}

	/**
	 * @param \Change\Job\JobManager $jobManager
	 * @return $this
	 */
	public function setJobManager($jobManager)
	{
		$this->jobManager = $jobManager;
		return $this;
	}

	/**
	 * @return \Change\Job\JobManager
	 */
	protected function getJobManager()
	{
		return $this->jobManager;
	}

	/**
	 * @return null|string|string[]
	 */
	protected function getEventManagerIdentifier()
	{
		return static::EVENT_MANAGER_IDENTIFIER;
	}

	/**
	 * @return string[]
	 */
	protected function getListenerAggregateClassNames()
	{
		return $this->getEventManagerFactory()->getConfiguredListenerClassNames('Rbs/Mail/Events/MailManager');
	}

	/**
	 * @param string $code
	 * @param \Rbs\Website\Documents\Website $website
	 * @param string $LCID
	 * @param string|array $to
	 * @param array $substitutions
	 * @param \DateTime $at
	 * @throws \RuntimeException
	 */
	public function send($code, $website, $LCID, $to, $substitutions = [], $at = null)
	{
		$mail = $this->getMailByCode($code, $website, $LCID);
		if ($mail === null)
		{
			throw new \RuntimeException('Mail not available for code, website, LCID');
		}

		$emails = $this->convertEmailsParam($to);
		if (count($emails) === 0)
		{
			throw new \RuntimeException('No dest');
		}

		$argument = ['mailId' => $mail->getId(), 'emails' => $emails, 'LCID' => $LCID, 'substitutions' => $substitutions];
		$this->getJobManager()->createNewJob('Rbs_Mail_SendMail', $argument, $at);
	}

	/**
	 * @return string[]
	 */
	public function getCodes()
	{
		$dqb = $this->getDocumentManager()->getNewQuery('Rbs_Mail_Mail');
		$qb = $dqb->dbQueryBuilder();

		$qb->addColumn('code');
		$query = $qb->query();
		return $query->getResults($query->getRowsConverter()->addStrCol('code'));
	}

	/**
	 * @param \Rbs\Mail\Documents\Mail $mail
	 * @param \Rbs\Website\Documents\Website $website
	 * @param string $LCID
	 * @param \Change\Services\ApplicationServices $applicationServices
	 * @param \Change\Application $application
	 * @return string
	 */
	public function render($mail, $website, $LCID, $applicationServices, $application)
	{
		//arguments are usefull to create Events
		$arguments = array('application' => $application);
		$arguments['services'] = new \Zend\Stdlib\Parameters(array('applicationServices' => $applicationServices));

		//use pageEvent to set blocks and make the render
		/*
		$event = new \Change\Presentation\Pages\PageEvent();
		$event->setParams($arguments);
		$event->setParam('page', $mail);
		$event->setTarget($applicationServices->getPageManager());
		*/

		$result = new \Change\Http\Web\Result\Page($mail->getCode());

		$mailTemplate = $mail->getTemplate();
		$templateLayout = $mailTemplate->getContentLayout($website->getId());

		$mailLayout = $mail->getContentLayout();
		$containers = array();
		foreach ($templateLayout->getItems() as $item)
		{
			if ($item instanceof \Change\Presentation\Layout\Container)
			{
				$container = $mailLayout->getById($item->getId());
				if ($container)
				{
					$containers[] = $container;
				}
			}
		}
		$mailLayout->setItems($containers);

		$blocks = array_merge($templateLayout->getBlocks(), $mailLayout->getBlocks());

		if (count($blocks))
		{
			$blockManager = $applicationServices->getBlockManager();

			//TODO check this with Eric
			$httpWebEvent = new \Change\Http\Web\Event();
			$httpWebEvent->setParams($arguments);
			$httpWebEvent->setUrlManager($website->getUrlManager($LCID));

			$blockInputs = array();
			foreach ($blocks as $block)
			{
				/* @var $block \Change\Presentation\Layout\Block */
				//$blockParameter = $blockManager->getParameters($block, $pageManager->getHttpWebEvent());
				$blockParameter = $blockManager->getParameters($block, $httpWebEvent);
				$blockInputs[] = array($block, $blockParameter);
			}

			$blockResults = array();
			foreach ($blockInputs as $infos)
			{
				list($blockLayout, $parameters) = $infos;

				/* @var $blockLayout \Change\Presentation\Layout\Block */
				//$blockResult = $blockManager->getResult($blockLayout, $parameters, $pageManager->getHttpWebEvent());
				$blockResult = $blockManager->getResult($blockLayout, $parameters, $httpWebEvent);
				var_dump($blockResult);
				if (isset($blockResult))
				{
					$blockResults[$blockLayout->getId()] = $blockResult;
				}
			}
			$result->setBlockResults($blockResults);
		}

		$cachePath = $application->getWorkspace()->cachePath('twig', 'mail', $mail->getCode() . '.twig');
		$cacheTime = max($mail->getModificationDate()->getTimestamp(), $mailTemplate->getModificationDate()->getTimestamp());

		if (!file_exists($cachePath) || filemtime($cachePath) <> $cacheTime)
		{
			$themeManager = $applicationServices->getThemeManager();
			$twitterBootstrapHtml = new \Change\Presentation\Layout\TwitterBootstrapHtml();
			$callableTwigBlock = function(\Change\Presentation\Layout\Block $item) use ($twitterBootstrapHtml)
			{
				return '{{ pageResult.htmlBlock(\'' . $item->getId() . '\', ' . var_export($twitterBootstrapHtml->getBlockClass($item), true). ')|raw }}';
			};
			$twigLayout = $twitterBootstrapHtml->getHtmlParts($templateLayout, $mailLayout, $callableTwigBlock);
			$twigLayout = array_merge($twigLayout, $twitterBootstrapHtml->getResourceParts($templateLayout, $mailLayout, $themeManager, $applicationServices, $application->inDevelopmentMode()));

			$htmlTemplate = str_replace(array_keys($twigLayout), array_values($twigLayout), $mailTemplate->getHtml());

			\Change\Stdlib\File::write($cachePath, $htmlTemplate);
			touch($cachePath, $cacheTime);
		}

		$templateManager = $applicationServices->getTemplateManager();
		return $templateManager->renderTemplateFile($cachePath, array('pageResult' => $result));
		//$result->setHtml($templateManager->renderTemplateFile($cachePath, array('pageResult' => $result)));
	}

	/**
	 * @param string $code
	 * @param \Rbs\Website\Documents\Website $website
	 * @param string $LCID
	 * @return \Rbs\Mail\Documents\Mail|null
	 */
	protected function getMailByCode($code, $website, $LCID)
	{
		$dqb = $this->getDocumentManager()->getNewQuery('Rbs_Mail_Mail', $LCID);
		$dqb->andPredicates($dqb->eq('code', $code), $dqb->eq('websites', $website), $dqb->isNotNull('subject'));
		return $dqb->getFirstDocument();
	}

	/**
	 * @param array $to
	 * @return array
	 */
	protected function convertEmailsParam($to)
	{
		$emails = [];
		if ($this->isValidEmailFormat($to))
		{
			$emails['to'] = [$to];
		}
		elseif (is_array($to) && array_key_exists('to', $to))
		{
			$result = $this->convertEmailsElement($to['to']);
			$emails['to'] = $result;
			if (array_key_exists('cc', $to))
			{
				$result = $this->convertEmailsElement($to['cc']);
				$emails['cc'] = $result;
			}
			if (array_key_exists('bcc', $to))
			{
				$result = $this->convertEmailsElement($to['bcc']);
				$emails['bcc'] = $result;
			}
			if (array_key_exists('reply-to', $to))
			{
				$result = $this->convertEmailsElement($to['reply-to']);
				$emails['reply-to'] = $result;
			}
		}
		elseif (is_array($to))
		{
			$emails['to'] = [];
			foreach ($to as $email)
			{
				if ($this->isValidEmailFormat($email))
				{
					$emails['to'][] = $email;
				}
			}
		}
		return $emails;
	}

	/**
	 * @param array|string $email
	 * @return boolean
	 */
	public function isValidEmailFormat($email)
	{
		//an email can be a string,
		//or an hash table with email and name like ['email' => 'mario.bros@nintendo.com', 'name' => 'Mario Bros']
		if (is_string($email))
		{
			return true;
		}
		elseif (is_array($email))
		{
			if (array_key_exists('email', $email) && is_string($email['email']) &&
				array_key_exists('name', $email) && is_string($email['name']))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $element
	 * @return array
	 */
	protected function convertEmailsElement($element)
	{
		$emails = [];
		if ($this->isValidEmailFormat($element))
		{
			$emails = $element;
		}
		elseif (is_array($element))
		{
			foreach ($element as $email)
			{
				if ($this->isValidEmailFormat($email))
				{
					$emails[] = $email;
				}
			}
		}
		return $emails;
	}
}