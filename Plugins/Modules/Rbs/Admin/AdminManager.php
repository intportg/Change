<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Admin;

use Assetic\AssetManager;

/**
 * @name \Rbs\Admin\AdminManager
 */
class AdminManager implements \Zend\EventManager\EventsCapableInterface
{
	use \Change\Events\EventsCapableTrait;

	/**
	 * @var \Change\I18n\I18nManager
	 */
	protected $i18nManager;

	/**
	 * @var \Change\Documents\ModelManager
	 */
	protected $modelManager;

	/**
	 * @var \Change\Plugins\PluginManager
	 */
	protected $pluginManager;

	/**
	 * @var \Assetic\AssetManager
	 */
	protected $jsAssetManager;

	/**
	 * @var \Assetic\AssetManager
	 */
	protected $cssAssetManager;

	/**
	 * @var string
	 */
	protected $cachePath;

	/**
	 * @var \Twig_ExtensionInterface[]
	 */
	protected $extensions;

	public function __construct()
	{
		$this->jsAssetManager = new AssetManager();
		$this->cssAssetManager = new AssetManager();
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
	 * @param \Change\Plugins\PluginManager $pluginManager
	 * @return $this
	 */
	public function setPluginManager($pluginManager)
	{
		$this->pluginManager = $pluginManager;
		return $this;
	}

	/**
	 * @return \Change\Plugins\PluginManager
	 */
	protected function getPluginManager()
	{
		return $this->pluginManager;
	}

	/**
	 * @param \Change\Documents\ModelManager $modelManager
	 * @return $this
	 */
	public function setModelManager($modelManager)
	{
		$this->modelManager = $modelManager;
		return $this;
	}

	/**
	 * @return \Change\Documents\ModelManager
	 */
	protected function getModelManager()
	{
		return $this->modelManager;
	}

	/**
	 * @api
	 * @param \Twig_ExtensionInterface $extension
	 * @return $this
	 */
	public function addExtension(\Twig_ExtensionInterface $extension)
	{
		$this->getExtensions();
		$this->extensions[$extension->getName()] = $extension;
		return $this;
	}

	/**
	 * @return \Twig_ExtensionInterface[]
	 */
	public function getExtensions()
	{
		if ($this->extensions === null)
		{
			$extension = new \Rbs\Admin\Presentation\Twig\Extension($this, $this->getI18nManager(), $this->getModelManager());
			$this->extensions = array($extension->getName() => $extension);
		}
		return $this->extensions;
	}

	/**
	 * @return \Assetic\AssetManager
	 */
	public function getCssAssetManager()
	{
		return $this->cssAssetManager;
	}

	/**
	 * @return null|string|string[]
	 */
	protected function getEventManagerIdentifier()
	{
		return 'Rbs_Admin';
	}

	/**
	 * @return string[]
	 */
	protected function getListenerAggregateClassNames()
	{
		return $this->getApplication()->getConfiguredListenerClassNames('Rbs/Admin/Events/AdminManager');
	}

	/**
	 * @param \Change\Events\EventManager $eventManager
	 */
	protected function attachEvents(\Change\Events\EventManager $eventManager)
	{
		$eventManager->attach('getModelTwigAttributes', array($this, 'onDefaultGetModelTwigAttributes'), 5);
		$eventManager->attach('getRoutes', array($this, 'onDefaultGetRoutes'), 5);
		$eventManager->attach('searchDocuments', array($this, 'onDefaultSearchDocuments'), 5);
	}

	/**
	 * @return array
	 */
	public function getResources()
	{
		$devMode = $this->getApplication()->inDevelopmentMode();

		$this->registerAdminResources($devMode);

		$pm = $this->getPluginManager();
		foreach ($pm->getInstalledPlugins() as $plugin)
		{
			if ($plugin->isModule())
			{
				$this->registerStandardPluginAssets($plugin, $devMode);
			}
		}

		$params = new \ArrayObject(array('header' => array(), 'body' => array()));
		$event = new Event(Event::EVENT_RESOURCES, $this, $params);
		$this->getEventManager()->trigger($event);
		return $params->getArrayCopy();
	}

	/**
	 * @param boolean $devMode
	 */
	protected function registerAdminResources($devMode)
	{
		$i18nManager = $this->getI18nManager();
		$lcid = strtolower(str_replace('_', '-', $i18nManager->getLCID()));

		$pluginPath = __DIR__ . '/Assets';
		$jsAssets = new \Assetic\Asset\AssetCollection();
		$path = $pluginPath . '/lib/moment/i18n/' . $lcid . '.js';
		if (file_exists($path))
		{
			$jsAssets->add(new \Assetic\Asset\FileAsset($path));
		}
		$path = $pluginPath . '/lib/angular/i18n/angular-locale_' . $lcid . '.js';
		if (file_exists($path))
		{
			$jsAssets->add(new \Assetic\Asset\FileAsset($path));
		}

		if (count($jsAssets->all()))
		{
			$this->getJsAssetManager()->set('i18n_' . $i18nManager->getLCID(), $jsAssets);
		}

		$jsAssets = new \Assetic\Asset\AssetCollection();
		$jsAssets->add(new \Assetic\Asset\FileAsset($pluginPath . '/js/rbschange.js'));

		$jsAssets->add(new \Assetic\Asset\GlobAsset($pluginPath . '/js/*/*.js'));
		$jsAssets->add(new \Assetic\Asset\FileAsset($pluginPath . '/menu/menu.js'));
		$jsAssets->add(new \Assetic\Asset\FileAsset($pluginPath . '/clipboard/controllers.js'));
		$jsAssets->add(new \Assetic\Asset\FileAsset($pluginPath . '/tasks/controllers.js'));
		$jsAssets->add(new \Assetic\Asset\FileAsset($pluginPath . '/notifications/controllers.js'));

		$jsAssets->add(new \Assetic\Asset\FileAsset($pluginPath . '/js/routes.js'));
		if (!$devMode)
		{
			$jsAssets->ensureFilter(new \Assetic\Filter\JSMinFilter());
		}

		$this->getJsAssetManager()->set('Rbs_Admin', $jsAssets);

		$cssAsset = new \Assetic\Asset\AssetCollection();
		$cssAsset->add(new \Assetic\Asset\GlobAsset($pluginPath . '/css/*.css'));
		$cssAsset->add(new \Assetic\Asset\FileAsset($pluginPath . '/menu/menu.css'));
		$cssAsset->add(new \Assetic\Asset\FileAsset($pluginPath . '/dashboard/dashboard.css'));

		$this->getCssAssetManager()->set('Rbs_Admin', $cssAsset);
	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param $devMode
	 */
	protected function registerStandardPluginAssets(\Change\Plugins\Plugin $plugin = null, $devMode)
	{
		$adminAssetsPath = $plugin->getAssetsPath() . '/Admin';
		if (is_dir($adminAssetsPath))
		{
			$globs = [$adminAssetsPath . '/*.js', $adminAssetsPath . '/Documents/*/*.js'];
			$jsAssets = new \Assetic\Asset\GlobAsset($globs);
			if (!$devMode)
			{
				$jsAssets->ensureFilter(new \Assetic\Filter\JSMinFilter());
			}
			$this->getJsAssetManager()->set($plugin->getName(), $jsAssets);

			$globs = [$adminAssetsPath . '/*.css'];
			$cssAsset = new \Assetic\Asset\GlobAsset($globs);
			$this->getCssAssetManager()->set($plugin->getName(), $cssAsset);
		}
	}

	/**
	 * @return array
	 */
	public function getMainMenu()
	{
		$pm = $this->getPluginManager();
		$sections = [];
		$entries = [];
		foreach ($pm->getInstalledPlugins() as $plugin)
		{
			$mainMenuPath = $plugin->getAssetsPath() . '/Admin/main-menu.json';
			if (is_readable($mainMenuPath))
			{
				$menuJson = \Zend\Json\Json::decode(file_get_contents($mainMenuPath), \Zend\Json\Json::TYPE_ARRAY);
				if (is_array($menuJson))
				{
					$sections = array_merge($sections, $this->parseSections($menuJson));
					$entries = array_merge($entries, $this->parseEntries($menuJson));
				}
			}
		}
		uasort($sections, function ($a, $b) {
			$ida = isset($a['index']) ? $a['index'] : PHP_INT_MAX;
			$idb = isset($b['index']) ? $b['index'] : PHP_INT_MAX;
			return $ida >= $idb;
		});
		$defaultSection = null;
		if (count($sections))
		{
			reset($sections);
			$defaultSection = key($sections);
		}
		foreach ($entries as $entry)
		{
			$section = isset($entry['section']) && isset($sections[$entry['section']]) ? $entry['section'] : $defaultSection;
			if ($section)
			{
				$sections[$section]['entries'][] = $entry;
			}
		}

		$result = [];
		foreach ($sections as $section)
		{
			$entries = $section['entries'];
			usort($entries, function ($a, $b) {
				return strcmp(\Change\Stdlib\String::stripAccents($a['label']), \Change\Stdlib\String::stripAccents($b['label']));
			});
			$section['entries'] = $entries;
			$result[] = $section;
		}
		return $result;
	}

	/**
	 * @return string
	 */
	public function getResourceDirectoryPath()
	{
		$webBaseDirectory = $this->getApplication()->getConfiguration()->getEntry('Change/Install/webBaseDirectory');
		return $this->getApplication()->getWorkspace()->composeAbsolutePath($webBaseDirectory, 'Assets', 'Rbs', 'Admin');
	}

	/**
	 * @return string
	 */
	public function getResourceBaseUrl()
	{
		$webBaseURLPath = $this->getApplication()->getConfiguration()->getEntry('Change/Install/webBaseURLPath');
		return $webBaseURLPath . '/Assets/Rbs/Admin';
	}

	/**
	 * @param string $resourceDirectoryPath
	 */
	public function dumpResources($resourceDirectoryPath = null)
	{
		if ($resourceDirectoryPath === null)
		{
			$resourceDirectoryPath = $this->getResourceDirectoryPath();
		}

		if ($resourceDirectoryPath)
		{
			\Change\Stdlib\File::rmdir($resourceDirectoryPath);
			\Change\Stdlib\File::mkdir($resourceDirectoryPath);
		}

		$this->prepareCssAssets($resourceDirectoryPath);
		$this->prepareScriptAssets($resourceDirectoryPath);
		$this->prepareImageAssets($resourceDirectoryPath);
	}

	/**
	 * @return string
	 */
	protected function getCachePath()
	{
		if ($this->cachePath === null)
		{
			$this->cachePath = $this->getApplication()->getWorkspace()
				->cachePath('Admin', 'Templates', 'Compiled');
			\Change\Stdlib\File::mkdir($this->cachePath);
		}
		return $this->cachePath;
	}

	/**
	 * @param string $resourceDirectoryPath
	 * @param string $resourceBaseUrl
	 * @return array
	 */
	public function prepareScriptAssets($resourceDirectoryPath = null, $resourceBaseUrl = null)
	{
		$scripts = array();
		$am = $this->getJsAssetManager();
		foreach ($am->getNames() as $name)
		{
			$asset = $am->get($name);
			if ($asset instanceof \Assetic\Asset\AssetCollection)
			{
				if (count($asset->all()) === 0)
				{
					continue;
				}
			}
			$targetPath = '/js/' . $name . '.js';
			if ($resourceDirectoryPath !== null)
			{
				$fileTargetPath = $resourceDirectoryPath . str_replace('/', DIRECTORY_SEPARATOR, $targetPath);
				\Change\Stdlib\File::write($fileTargetPath, $asset->dump());
			}

			if ($resourceBaseUrl !== null)
			{
				$scripts[] = $resourceBaseUrl . $targetPath;
			}
		}
		return $scripts;
	}

	/**
	 * @param string $resourceDirectoryPath
	 * @param string $resourceBaseUrl
	 * @return array
	 */
	public function prepareCssAssets($resourceDirectoryPath = null, $resourceBaseUrl = null)
	{
		$scripts = array();
		$am = $this->getCssAssetManager();
		foreach ($am->getNames() as $name)
		{
			$asset = $am->get($name);
			if ($asset instanceof \Assetic\Asset\AssetCollection)
			{
				if (count($asset->all()) === 0)
				{
					continue;
				}
			}
			$targetPath = '/css/' . $name . '.css';
			if ($resourceDirectoryPath !== null)
			{
				$fileTargetPath = $resourceDirectoryPath . str_replace('/', DIRECTORY_SEPARATOR, $targetPath);
				\Change\Stdlib\File::write($fileTargetPath, $asset->dump());
			}

			if ($resourceBaseUrl !== null)
			{
				$scripts[] = $resourceBaseUrl . $targetPath;
			}
		}
		return $scripts;
	}

	/**
	 * @param string $resourceDirectoryPath
	 */
	public function prepareImageAssets($resourceDirectoryPath)
	{
		if (!$resourceDirectoryPath)
		{
			return;
		}
		$pm = $this->getPluginManager();
		$plugin = $pm->getModule('Rbs', 'Admin');
		$srcPath = $plugin->getAssetsPath() . '/img';
		$targetPath = $resourceDirectoryPath . '/img';
		\Change\Stdlib\File::mkdir($targetPath);

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcPath, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $fileInfo)
		{
			/* @var $fileInfo \SplFileInfo */
			$targetPathName = str_replace($srcPath, $targetPath, $fileInfo->getPathname());
			if ($fileInfo->isFile())
			{
				copy($fileInfo->getPathname(), $targetPathName);
			}
			elseif ($fileInfo->isDir())
			{
				\Change\Stdlib\File::mkdir($targetPathName);
			}
		}
	}

	/**
	 * @param string $pathName
	 * @param array $attributes
	 * @return string
	 */
	public function renderTemplateFile($pathName, array $attributes)
	{
		$loader = new \Twig_Loader_Filesystem(dirname($pathName));
		$twig = new \Twig_Environment($loader, array('cache' => $this->getCachePath(), 'auto_reload' => true));
		$twig->addExtension(new \Change\Presentation\Templates\Twig\Extension($this->getI18nManager()));
		foreach ($this->getExtensions() as $extension)
		{
			$twig->addExtension($extension);
		}
		return $twig->render(basename($pathName), $attributes);
	}

	/**
	 * @param string $moduleName
	 * @param string $pathName
	 * @param array $attributes
	 * @return string
	 */
	public function renderModuleTemplateFile($moduleName, $pathName, array $attributes)
	{
		$overridePath = $this->getApplication()->getWorkspace()->appPath('AdminOverrides');
		$loader = new \Rbs\Admin\Presentation\Twig\Loader($overridePath, $this->getPluginManager());
		$twig = new \Twig_Environment($loader, array('cache' => $this->getCachePath(), 'auto_reload' => true));
		$twig->addExtension(new \Change\Presentation\Templates\Twig\Extension($this->getI18nManager()));
		foreach ($this->getExtensions() as $extension)
		{
			$twig->addExtension($extension);
		}
		return $twig->render('@' . $moduleName . '/' . $pathName, $attributes);
	}

	/**
	 * @api
	 * @return array
	 */
	public function getRoutes()
	{
		$args = $this->getEventManager()->prepareArgs([]);
		$this->getEventManager()->trigger('getRoutes', $this, $args);
		$routes = isset($args['routes']) && is_array($args['routes']) ? $args['routes'] : [];

		foreach ($routes as $path => $route)
		{
			if (is_array($route) && isset($route['rule']))
			{
				unset($route['auto']);
				$routes[$path] = $route;
			}
			else
			{
				unset($routes[$path]);
			}
		}
		return $routes;
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetRoutes($event)
	{
		$routes = [];
		foreach ($this->getPluginManager()->getModules() as $module)
		{
			if ($module->isAvailable())
			{
				$filePath = $this->getApplication()->getWorkspace()
					->composePath($module->getAssetsPath(), 'Admin', 'routes.json');
				if (is_readable($filePath))
				{
					$moduleRoutes = json_decode(file_get_contents($filePath), true);
					if (is_array($moduleRoutes))
					{
						$routes = array_merge($routes, $moduleRoutes);
					}
					else
					{
						$event->getApplicationServices()->getLogging()->error('invalid json file: ' . $filePath);
					}
				}
			}
		}
		$event->setParam('routes', $routes);
	}

	/**
	 * @var \Rbs\Admin\RoutesHelper
	 */
	protected $routesHelper;

	/**
	 * @return \Rbs\Admin\RoutesHelper
	 */
	public function getRoutesHelper()
	{
		if ($this->routesHelper === null)
		{
			$this->routesHelper = new \Rbs\Admin\RoutesHelper($this->getRoutes());
		}
		return $this->routesHelper;
	}

	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @param string $view edit|list|new|translate
	 * @return array
	 */
	public function getModelTwigAttributes($model, $view)
	{
		$args = $this->getEventManager()->prepareArgs([
			'model' => $model,
			'view' => $view
		]);

		$this->getEventManager()->trigger('getModelTwigAttributes', $this, $args);
		return isset($args['attributes']) && is_array($args['attributes']) ? $args['attributes'] : [];
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \RuntimeException
	 */
	public function onDefaultGetModelTwigAttributes(\Change\Events\Event $event)
	{
		/* @var $model \Change\Documents\AbstractModel */
		$model = $event->getParam('model');
		$view = $event->getParam('view');

		$attributes = [];

		$pluginManager = $event->getApplicationServices()->getPluginManager();
		$module = $pluginManager->getModule($model->getVendorName(), $model->getShortModuleName());
		if (!$module)
		{
			throw new \RuntimeException('module ' . $model->getVendorName() . '_' . $model->getShortModuleName()
				. ' is not found', 999999);
		}

		$i18nManager = $event->getApplicationServices()->getI18nManager();
		switch ($view)
		{
			case 'new':
				$attributes = [
					'model' => $model,
					'asideDirectives' => [['name' => 'rbs-aside-editor-menu']]
				];
				break;

			case 'edit':
				$attributes = [
					'model' => $model,
					'asideDirectives' => [['name' => 'rbs-aside-editor-menu']],
					'links' => []
				];
				if ($model->isLocalized())
				{
					$attributes['asideDirectives'][] = [
						'name' => 'rbs-aside-translation',
						'attributes' => [
							['name' => 'document', 'value' => 'document']
						]
					];
				}
				break;

			case 'list':
				$newDocumentLinks = [];
				$modelNames = [];
				if (!$model->isAbstract())
				{
					$key = strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(),
						'admin', $model->getShortName() . '_create']));
					$label = $i18nManager->trans($key, ['ucf']);
					if ($key !== $label)
					{
						$modelNames[] = $model->getName();
						$newDocumentLinks[] = ['modelName' => $model->getName(), 'label' => $label];
					}
				}

				foreach ($model->getDescendantsNames() as $descendantName)
				{
					$m = $event->getApplicationServices()->getModelManager()->getModelByName($descendantName);
					if ($m && !$m->isAbstract())
					{
						$key = strtolower(implode('.', ['m', $m->getVendorName(), $m->getShortModuleName(),
							'admin', $m->getShortName() . '_create']));
						$label = $i18nManager->trans($key, ['ucf']);
						if ($key !== $label)
						{
							$modelNames[] = $m->getName();
							$newDocumentLinks[] = ['modelName' => $m->getName(), 'label' => $label];
						}
					}
				}

				$attributes = [
					'asideDirectives' => [['name' => 'rbs-default-asides-for-list']],
					'newDocumentLinks' => $newDocumentLinks,
					'modelNames' => $modelNames
				];
				break;

			case 'translate':
				$attributes = [
					'model' => $model,
					'asideDirectives' => [
						['name' => 'rbs-aside-editor-menu'],
						['name' => 'rbs-aside-translation', 'attributes' => [['name' => 'document', 'value' => 'document']]]
					],
					'links' => []
				];
				break;

			default:
				break;
		}

		$event->setParam('attributes', $attributes);
	}

	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @param array $views
	 * @return array
	 * @throws \RuntimeException
	 */
	public function initializeView($model, $views)
	{
		$pluginManager = $this->getPluginManager();
		$module = $pluginManager->getModule($model->getVendorName(), $model->getShortModuleName());

		$filesGenerated = ['paths' => [], 'errors' => []];

		foreach ($views as $view => $generate)
		{
			if ($generate)
			{
				// Check if the file already exist.
				$docPath = implode(DIRECTORY_SEPARATOR,
					[$module->getAbsolutePath(), 'Assets', 'Admin', 'Documents', $model->getShortName(), $view . '.twig']
				);
				if (file_exists($docPath))
				{
					$filesGenerated['errors'][$view] = ucfirst($view) . ' view file already exists at path ' . $docPath;
					continue;
				}

				$excludedProperties = null;
				if ($view === 'new')
				{
					// refLCID selector will automatically added in the template.
					$excludedProperties = ['id', 'model', 'creationDate', 'modificationDate', 'refLCID', 'LCID',
						'authorName', 'authorId', 'documentVersion', 'publicationStatus',
						'publicationSections', 'startPublication', 'endPublication', // Already present in publication panel.
						'active', 'startActivation', 'endActivation' // Already present in activation panel.
					];
				}
				elseif ($view === 'edit')
				{
					$excludedProperties = ['id', 'model', 'creationDate', 'modificationDate', 'refLCID', 'LCID',
						'authorName', 'authorId', 'documentVersion', 'publicationStatus',
						'publicationSections', 'startPublication', 'endPublication', // Already present in publication panel.
						'active', 'startActivation', 'endActivation' // Already present in activation panel.
					];
				}
				elseif ($view === 'translate')
				{
					$excludedProperties = ['creationDate', 'modificationDate', 'LCID',
						'authorName', 'authorId', 'documentVersion', 'publicationStatus',
						'startPublication', 'endPublication', // Already present in publication panel.
						'active', 'startActivation', 'endActivation' // Already present in activation panel.
					];
				}
				$attributes = ['model' => $model];
				if ($excludedProperties)
				{
					// If the model is publishable and we are not in translation view, we exclude the title and the label from
					// properties because they will be automatically added in the template and synced with each other.
					if ($model->isPublishable() && $view !== 'translate')
					{
						$excludedProperties = array_merge($excludedProperties, ['label', 'title']);
					}
					$attributes['properties'] = $this->getFormModelProperties($model, $excludedProperties, $view === 'translate');
				}
				$loader = new \Twig_Loader_Filesystem(__DIR__);
				$twig = new \Twig_Environment($loader);
				$twig->addExtension(new \Change\Presentation\Templates\Twig\Extension($this->getI18nManager()));
				$twig->addExtension(new \Rbs\Admin\Presentation\Twig\Extension($this, $this->getI18nManager(), $this->getModelManager()));
				\Change\Stdlib\File::write($docPath, $twig->render('Assets/view/' . $view . '.twig', $attributes));

				$filesGenerated['paths'][$view] = $docPath;
			}
		}

		return $filesGenerated;
	}

	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @param array $excludedProperties
	 * @param boolean $localizedOnly
	 * @return array
	 */
	protected function getFormModelProperties($model, $excludedProperties, $localizedOnly = false)
	{
		$properties = [];

		$modelProperties = $localizedOnly ? $model->getLocalizedProperties() : $model->getProperties();

		foreach ($modelProperties as $property)
		{
			if (!in_array($property->getName(), $excludedProperties) && !$property->getInternal() && !$property->getStateless())
			{
				$properties[] = [
					'name' => $property->getName(),
					'type' => $this->getFormTypeFromModelType($property->getType()),
					'required' => $property->getRequired(),
					'modelName' => $model->getName(),
					'documentType' => $property->getDocumentType()
				];
			}
		}

		//required property first
		usort($properties, function ($a, $b)
		{
			if ($a['required'] && !$b['required'])
			{
				return -1;
			}
			else if ($a['required'] && $b['required'])
			{
				return 0;
			}
			return 1;
		});

		return $properties;
	}

	protected function getFormTypeFromModelType($modelType)
	{
		switch ($modelType)
		{
			case 'String':
				return 'text';
			case 'LongString':
				return 'long-text';
			case 'RichText':
				return 'rich-text';
			case 'Document':
				return 'picker';
			case 'DocumentArray':
				return 'picker-multiple';
			case 'Boolean':
				return 'boolean';
			case 'Integer':
				return 'integer';
			case 'Float':
				return 'float';
			case 'Date':
			case 'DateTime':
				return 'date';
			default:
				return 'unknown';
		}
	}

	/**
	 * @return \Assetic\AssetManager
	 */
	public function getJsAssetManager()
	{
		return $this->jsAssetManager;
	}

	/**
	 * @param array $menuJson
	 * @return array
	 */
	protected function parseSections($menuJson)
	{
		$result = [];
		if (isset($menuJson['sections']) && is_array($menuJson['sections']))
		{
			$i18nManager = $this->getI18nManager();
			foreach ($menuJson['sections'] as $item)
			{
				if (isset($item['code']))
				{
					if (isset($item['label']) && is_string($item['label']))
					{
						$item['label'] = $i18nManager->trans($item['label'], ['ucf']);
					}
					$result[$item['code']] = $item;
				}
			}
		}
		return $result;
	}

	/**
	 * @param array $menuJson
	 * @return array
	 */
	private function parseEntries($menuJson)
	{
		$result = [];
		if (isset($menuJson['entries']) && is_array($menuJson['entries']))
		{
			$i18nManager = $this->getI18nManager();
			foreach ($menuJson['entries'] as $item)
			{
				if (isset($item['url']))
				{
					if (isset($item['label']) && is_string($item['label']))
					{
						$item['label'] = $i18nManager->trans($item['label'], ['ucf']);
					}
					if (isset($item['keywords']) && is_string($item['keywords']))
					{
						$item['keywords'] = $i18nManager->trans($item['keywords'], ['ucf']);
					}
					$result[] = $item;
				}
			}
		}
		return $result;
	}

	/**
	 * @param array $attributes
	 * @param array $order
	 */
	public function getSortedAttributes(&$attributes, $order)
	{
		foreach ($order as $type => $newOrder)
		{
			if (array_key_exists($type, $attributes))
			{
				usort($attributes[$type], function ($a, $b) use ($newOrder)
				{
					if (in_array($a['name'], $newOrder))
					{
						if (in_array($b['name'], $newOrder))
						{
							return array_search($a['name'], $newOrder) - array_search($b['name'], $newOrder);
						}
						else
						{
							return -1;
						}
					}
					if (in_array($b['name'], $newOrder))
					{
						return 1;
					}
					return 0;
				});
			}
		}
	}

	/**
	 * @return array
	 */
	public function getGenericSettingsStructures()
	{
		$args = $this->getEventManager()->prepareArgs([]);
		$this->getEventManager()->trigger('getGenericSettingsStructures', $this, $args);
		return isset($args['structures']) && is_array($args['structures']) ? $args['structures'] : [];
	}

	/**
	 * @param string $modelName
	 * @param string $searchString
	 * @param integer $limit
	 * @return \Change\Documents\AbstractDocument[]
	 */
	public function searchDocuments($modelName, $searchString, $limit = 10)
	{
		$args = $this->getEventManager()->prepareArgs([
			'modelName' => $modelName,
			'searchString' => $searchString,
			'limit' => $limit,
			'propertyNames' => ['label']
		]);
		$this->getEventManager()->trigger('searchDocuments', $this, $args);
		return isset($args['documents']) && is_array($args['documents']) ? $args['documents'] : [];
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultSearchDocuments($event)
	{
		if (is_array($event->getParam('documents')))
		{
			return;
		}

		$model = $event->getApplicationServices()->getModelManager()->getModelByName($event->getParam('modelName'));
		if (!$model || $model->isStateless())
		{
			return;
		}
		$propertyNames = [];
		foreach ($event->getParam('propertyNames') as $propertyName)
		{
			$property = $model->getProperty($propertyName);
			if ($property && !$property->getStateless() && $property->getType() == \Change\Documents\Property::TYPE_STRING)
			{
				$propertyNames[] = $propertyName;
			}
		}

		if (!count($propertyNames))
		{
			return;
		}

		$query = $event->getApplicationServices()->getDocumentManager()->getNewQuery($model);
		$restrictions = [];
		foreach ($propertyNames as $propertyName)
		{
			$restrictions[] = $query->like($propertyName, $event->getParam('searchString'));
			$query->addOrder($propertyName);
		}
		$query->orPredicates($restrictions);
		if (!in_array('id', $propertyNames))
		{
			$query->addOrder('id');
		}
		$event->setParam('documents', $query->getDocuments(0, $event->getParam('limit'))->toArray());
	}
}
