<?php
namespace ChangeTests\Plugins\Modules\Simpleform\Converter;

/**
 * @name \ChangeTests\Plugins\Modules\Simpleform\Converter\EmailsTest
 */
class EmailsTest extends \ChangeTests\Change\TestAssets\TestCase
{
	public function testIsEmptyFromUI()
	{
		$i18n = $this->getApplicationServices()->getI18nManager();
		$converter = new \Rbs\Simpleform\Converter\Emails($i18n);

		$this->assertTrue($converter->isEmptyFromUI(null, array()));
		$this->assertTrue($converter->isEmptyFromUI('', array()));
		$this->assertTrue($converter->isEmptyFromUI('  	  	 ', array()));
		$this->assertFalse($converter->isEmptyFromUI('Toto', array()));
		$this->assertFalse($converter->isEmptyFromUI(' Toto', array()));
	}

	public function testParseFromUI()
	{
		$i18n = $this->getApplicationServices()->getI18nManager();
		$converter = new \Rbs\Simpleform\Converter\Emails($i18n);

		$this->assertEquals(array('no-reply@rbs.fr'), $converter->parseFromUI('no-reply@rbs.fr', array()));
		$this->assertEquals(array('no-reply@rbs.fr', 'chuck.norris@rbs.fr'), $converter->parseFromUI('		no-reply@rbs.fr , chuck.norris@rbs.fr ', array()));
		$this->assertError($converter->parseFromUI('no-reply', array()));
		$this->assertError($converter->parseFromUI('chuck.norris@rbs.fr,no-reply', array()));
	}

	protected function assertError($value)
	{
		$this->assertTrue($value instanceof \Rbs\Simpleform\Converter\Validation\Error);
	}
}