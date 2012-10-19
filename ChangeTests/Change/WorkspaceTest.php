<?php

namespace ChangeTests\Change;

use Change\Workspace;

/**
 */
class WorkspaceTest extends \PHPUnit_Framework_TestCase
{
	public function testConstruct()
	{
		$application  = \Change\Application::getInstance();
		$workspace = new Workspace($application);
		return $workspace;
	}

	/**
	 * @depends testConstruct
	 */
	public function testAppPath(Workspace $workspace)
	{
		$this->assertEquals(PROJECT_HOME . '/App/Dir1/Dir2/Dir3/File.php', $workspace->appPath('Dir1', 'Dir2', 'Dir3', 'File.php'));
	}
	
	/**
	 * @depends testConstruct
	 */
	public function testCompilationPath(Workspace $workspace)
	{
		$this->assertEquals(PROJECT_HOME . '/Compilation/Dir1/Dir2/Dir3/File.php', $workspace->compilationPath('Dir1', 'Dir2', 'Dir3', 'File.php'));
	}
	
	/**
	 * @depends testConstruct
	 */
	public function testProjectPath(Workspace $workspace)
	{
		$this->assertEquals(PROJECT_HOME . '/Dir1/Dir2/Dir3/File.php', $workspace->projectPath('Dir1', 'Dir2', 'Dir3', 'File.php'));
	}
	
	/**
	 * @depends testConstruct
	 */
	public function testProjectModulesPath(Workspace $workspace)
	{
		$this->assertEquals(PROJECT_HOME . '/App/Modules/Dir1/Dir2/Dir3/File.php', $workspace->projectModulesPath('Dir1', 'Dir2', 'Dir3', 'File.php'));
	}
	
	/**
	 * @depends testConstruct
	 */
	public function testPluginsModulesPath(Workspace $workspace)
	{
		$this->assertEquals(PROJECT_HOME . '/Plugins/Modules/Dir1/Dir2/Dir3/File.php', $workspace->pluginsModulesPath('Dir1', 'Dir2', 'Dir3', 'File.php'));
	}
	
	/**
	 * @depends testConstruct
	 */
	public function testGetBootstrapConfigurationPath(Workspace $workspace)
	{
		$this->assertEquals(PROJECT_HOME . '/change.json', $workspace->getBootstrapConfigurationPath());
	}
	
	public function testGetProjectConfigurationPaths()
	{
		$application  = \Change\Application::getInstance();
		$mockWorkspace = $this->getMock('\Change\Workspace', array('appBase'), array($application));
		$mockWorkspace->expects($this->any())->method('appBase')->will($this->returnValue(__DIR__ . '/TestAssets'));
		$configPaths = $mockWorkspace->getProjectConfigurationPaths();
		$this->assertCount(2, $configPaths);
		$this->assertEquals(__DIR__ . '/TestAssets/Config/project.xml', $configPaths[0]);
		$this->assertEquals(__DIR__ . '/TestAssets/Config/project.default.xml', $configPaths[1]);
	}
}