<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Generic\Setup;

/**
 * @name \Rbs\Generic\Setup\Install
 */
class Install extends \Change\Plugins\InstallBase
{
	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application $application
	 * @param \Change\Configuration\EditableConfiguration $configuration
	 * @throws \RuntimeException
	 */
	public function executeApplication($plugin, $application, $configuration)
	{
		$configuration->addPersistentEntry('Change/Events/ListenerAggregateClasses/Rbs_Generic',
			'\Rbs\Generic\Events\SharedListeners');
		$configuration->addPersistentEntry('Change/Events/CollectionManager/Rbs_Generic', '\Rbs\Generic\Collection\Listeners');
		$configuration->addPersistentEntry('Change/Events/DocumentManager/Rbs_Generic',
			'\Rbs\Generic\Events\DocumentManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/Http/Admin/Rbs_Generic', '\Rbs\Generic\Events\Http\Admin\Listeners');
		$configuration->addPersistentEntry('Change/Events/Http/Rest/Rbs_Generic', '\Rbs\Generic\Events\Http\Rest\Listeners');
		$configuration->addPersistentEntry('Change/Events/Http/Web/Rbs_Generic', '\Rbs\Generic\Events\Http\Web\Listeners');
		$configuration->addPersistentEntry('Change/Events/Http/Ajax/Rbs_Generic', '\Rbs\Generic\Events\Http\Ajax\Listeners');

		$configuration->addPersistentEntry('Change/Events/Commands/Rbs_Generic', '\Rbs\Generic\Events\Commands\Listeners');
		$configuration->addPersistentEntry('Change/Events/Db/Rbs_Generic', '\Rbs\Generic\Events\Db\Listeners');
		$configuration->addPersistentEntry('Change/Events/WorkflowManager/Rbs_Generic',
			'\Rbs\Generic\Events\WorkflowManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/BlockManager/Rbs_Generic',
			'\Rbs\Generic\Events\BlockManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/PageManager/Rbs_Generic',
			'\Rbs\Generic\Events\PageManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/ProfileManager/Rbs_Generic',
			'\Rbs\Generic\Events\ProfileManager\Listeners');
		$configuration->addPersistentEntry('Change/Events/AuthenticationManager/Rbs_Generic',
			'\Rbs\Generic\Events\AuthenticationManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/ThemeManager/Rbs_Generic',
			'\Rbs\Generic\Events\ThemeManager\Listeners');
		$configuration->addPersistentEntry('Change/Events/RichTextManager/Rbs_Generic',
			'\Rbs\Generic\Events\RichTextManager\Listeners');
		$configuration->addPersistentEntry('Change/Events/JobManager/Rbs_Generic', '\Rbs\Generic\Events\JobManager\Listeners');
		$configuration->addPersistentEntry('Change/Events/OAuthManager/Rbs_Generic',
			'\Rbs\Generic\Events\OAuthManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/ModelManager/Rbs_Generic',
			'\Rbs\Generic\Events\ModelManager\Listeners');

		$configuration->addPersistentEntry('Change/Events/PathRuleManager/Rbs_Generic',
			'Rbs\Generic\Events\PathRuleManager\Listeners');
		$configuration->addPersistentEntry('Rbs/Mail/Events/MailManager/Rbs_Generic', 'Rbs\Generic\Events\MailManager\Listeners');

		$configuration->addPersistentEntry('Rbs/Admin/Events/AdminManager/Rbs_Generic',
			'Rbs\Generic\Events\AdminManager\Listeners');

		$configuration->addPersistentEntry('Rbs/Commerce/Events/CatalogManager/Rbs_Generic',
			'\Rbs\Generic\Events\CatalogManager\Listeners');
	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
	public function finalize($plugin)
	{
		$plugin->setConfigurationEntry('locked', true);
	}
}