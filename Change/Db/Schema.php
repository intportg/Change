<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Db;

/**
 * @name \Change\Db\Schema
 */
class Schema extends \Change\Db\Schema\SchemaDefinition
{
	/**
	 * @var \Change\Db\Schema\TableDefinition[]
	 */
	protected $tables;

	/**
	 * @return \Change\Db\Schema\TableDefinition[]
	 */
	public function getTables()
	{
		if ($this->tables === null)
		{
			$schemaManager = $this->getSchemaManager();
			$idDef = $schemaManager->newIntegerFieldDefinition('document_id')->setNullable(false)->setAutoNumber(true);
			$modelDef = $schemaManager->newVarCharFieldDefinition('document_model', array('length' => 80))->setDefaultValue('')->setNullable(false);

			$this->tables['change_document'] = $schemaManager->newTableDefinition('change_document')
				->addField($idDef)->addField($modelDef)
				->addKey($this->newPrimaryKey()->addField($idDef))
				->setOption('AUTONUMBER', 100000);

			$idDef = $schemaManager->newIntegerFieldDefinition('document_id')->setDefaultValue('0')->setNullable(false);

			$this->tables['change_document_metas'] = $schemaManager->newTableDefinition('change_document_metas')
			->addField($idDef)
			->addField($schemaManager->newTextFieldDefinition('metas'))
			->addField($schemaManager->newTimeStampFieldDefinition('lastupdate'))
			->addKey($this->newPrimaryKey()->addField($idDef));

			$this->tables['change_document_deleted'] = $schemaManager->newTableDefinition('change_document_deleted')
			->addField($idDef)->addField($modelDef)
			->addField($schemaManager->newTimeStampFieldDefinition('deletiondate'))
			->addField($schemaManager->newTextFieldDefinition('datas'))
			->addKey($this->newPrimaryKey()->addField($idDef));

			$correctionId = $schemaManager->newIntegerFieldDefinition('correction_id')->setNullable(false)->setAutoNumber(true);
			$lcid = $schemaManager->newVarCharFieldDefinition('lcid', array('length' => 5))->setNullable(false)->setDefaultValue('_____');
			$status = $schemaManager->newEnumFieldDefinition('status', array('VALUES' => array('DRAFT', 'VALIDATION', 'VALIDCONTENT', 'VALID', 'PUBLISHABLE', 'FILED')))->setNullable(false)->setDefaultValue('DRAFT');
			$this->tables['change_document_correction'] = $schemaManager->newTableDefinition('change_document_correction')
			->addField($correctionId)
			->addField($idDef)
			->addField($lcid)
			->addField($status)
			->addField($schemaManager->newTimeStampFieldDefinition('creationdate'))
			->addField($schemaManager->newDateFieldDefinition('publicationdate'))
			->addField($schemaManager->newLobFieldDefinition('datas'))
			->addKey($this->newPrimaryKey()->addField($correctionId))
			->addKey($this->newIndexKey()->setName('document')->addField($idDef)->addField($status)->addField($lcid))
			->setOption('AUTONUMBER', 1);

			$tokenId = $schemaManager->newIntegerFieldDefinition('token_id')->setNullable(false)->setAutoNumber(true);
			$token = $schemaManager->newVarCharFieldDefinition('token', array('length' => 64))->setNullable(false);
			$this->tables['change_oauth'] = $schemaManager->newTableDefinition('change_oauth')
				->addField($tokenId)
				->addField($token)
				->addField($schemaManager->newVarCharFieldDefinition('token_secret', array('length' => 64))->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('application_id')->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('realm', array('length' => 128))->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('device', array('length' => 255)))
				->addField($schemaManager->newEnumFieldDefinition('token_type', array('VALUES' => array('request', 'access')))->setNullable(false)->setDefaultValue('request'))
				->addField($schemaManager->newTimeStampFieldDefinition('creation_date'))
				->addField($schemaManager->newDateFieldDefinition('validity_date'))
				->addField($schemaManager->newVarCharFieldDefinition('callback', array('length' => 255))->setNullable(false)->setDefaultValue('oob'))
				->addField($schemaManager->newVarCharFieldDefinition('verifier', array('length' => 20))->setNullable(true))
				->addField($schemaManager->newBooleanFieldDefinition('authorized')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('accessor_id'))
				->addKey($this->newPrimaryKey()->addField($tokenId))
				->addKey($this->newUniqueKey()->setName('token')->addField($token))
				->setOption('AUTONUMBER', 1);

			$applicationId = $schemaManager->newIntegerFieldDefinition('application_id')->setNullable(false)->setAutoNumber(true);
			$application = $schemaManager->newVarCharFieldDefinition('application', array('length' => 255))->setNullable(false);
			$consumerKey = $schemaManager->newVarCharFieldDefinition('consumer_key', array('length' => 64))->setNullable(false);
			$this->tables['change_oauth_application'] = $schemaManager->newTableDefinition('change_oauth_application')
				->addField($applicationId)
				->addField($application)
				->addField($consumerKey)
				->addField($schemaManager->newVarCharFieldDefinition('consumer_secret', array('length' => 64))->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('timestamp_max_offset')->setNullable(false)->setDefaultValue(60))
				->addField($schemaManager->newVarCharFieldDefinition('token_access_validity', array('length' => 10))->setNullable(false)->setDefaultValue("P10Y"))
				->addField($schemaManager->newVarCharFieldDefinition('token_request_validity', array('length' => 10))->setNullable(false)->setDefaultValue("P1D"))
				->addField($schemaManager->newBooleanFieldDefinition('active')->setNullable(false)->setDefaultValue(1))
				->addKey($this->newPrimaryKey()->addField($applicationId))
				->addKey($this->newUniqueKey()->setName('application')->addField($application))
				->addKey($this->newUniqueKey()->setName('consumer_key')->addField($consumerKey));

			$this->tables['change_path_rule'] = $td = $schemaManager->newTableDefinition('change_path_rule');
			$td->addField($schemaManager->newIntegerFieldDefinition('rule_id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newIntegerFieldDefinition('website_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newVarCharFieldDefinition('lcid', array('length' => 5))->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('hash', array('length' => 40))->setNullable(false))
				->addField($schemaManager->newTextFieldDefinition('relative_path')->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('document_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('document_alias_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('section_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('http_status')->setNullable(false)->setDefaultValue(200))
				->addField($schemaManager->newTextFieldDefinition('query'))
				->addField($schemaManager->newBooleanFieldDefinition('user_edited')->setNullable(false)->setDefaultValue(0))
				->addKey($this->newPrimaryKey()
					->addField($td->getField('rule_id')))
				->addKey($this->newUniqueKey()->setName('url')
					->addField($td->getField('website_id'))
					->addField($td->getField('lcid'))
					->addField($td->getField('hash')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_plugin'] = $td = $schemaManager->newTableDefinition('change_plugin');
			$td->addField($schemaManager->newVarCharFieldDefinition('type', array('length' => 25))->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('vendor', array('length' => 25))->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('name', array('length' => 25))->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('package', array('length' => 25))->setNullable(true))
				->addField($schemaManager->newTimeStampFieldDefinition('registration_date')->setNullable(false))
				->addField($schemaManager->newBooleanFieldDefinition('configured')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newBooleanFieldDefinition('activated')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newTextFieldDefinition('config_datas'))
				->addKey($this->newPrimaryKey()
					->addField($td->getField('type'))
					->addField($td->getField('vendor'))
					->addField($td->getField('name')));

			$this->tables['change_storage'] = $td = $schemaManager->newTableDefinition('change_storage');
			$td->addField($schemaManager->newIntegerFieldDefinition('item_id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newVarCharFieldDefinition('store_name', array('length' => 50))->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('store_path', array('length' => 255))->setNullable(false))
				->addField($schemaManager->newTextFieldDefinition('infos'))
				->addKey($this->newPrimaryKey()
					->addField($td->getField('item_id')))
				->addKey($this->newUniqueKey()->setName('path')
					->addField($td->getField('store_name'))
					->addField($td->getField('store_path')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_job'] = $td = $schemaManager->newTableDefinition('change_job');
			$td->addField($schemaManager->newIntegerFieldDefinition('id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newVarCharFieldDefinition('name', array('length' => 100))->setNullable(false))
				->addField($schemaManager->newTimeStampFieldDefinition('start_date')->setNullable(false))
				->addField($schemaManager->newTextFieldDefinition('arguments')->setNullable(true))
				->addField($schemaManager->newEnumFieldDefinition('status',
					array('VALUES' => array('waiting', 'running', 'success', 'failed')))
					->setNullable(false)->setDefaultValue('waiting'))
				->addField($schemaManager->newTimeStampFieldDefinition('last_modification_date')->setNullable(true)->setDefaultValue(null))
				->addKey($this->newPrimaryKey()->addField($td->getField('id')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_permission_rule'] = $td = $schemaManager->newTableDefinition('change_permission_rule');
			$td->addField($schemaManager->newIntegerFieldDefinition('rule_id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newIntegerFieldDefinition('accessor_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newVarCharFieldDefinition('role', array('length' => 100))
					->setNullable(false)->setDefaultValue('*'))
				->addField($schemaManager->newIntegerFieldDefinition('resource_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newVarCharFieldDefinition('privilege', array('length' => 100))
					->setNullable(false)->setDefaultValue('*'))
				->addKey($this->newPrimaryKey()->addField($td->getField('rule_id')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_web_permission_rule'] = $td = $schemaManager->newTableDefinition('change_web_permission_rule');
			$td->addField($schemaManager->newIntegerFieldDefinition('rule_id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newIntegerFieldDefinition('accessor_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('section_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('website_id')->setNullable(false)->setDefaultValue(0))
				->addKey($this->newPrimaryKey()->addField($td->getField('rule_id')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_document_code'] = $td = $schemaManager->newTableDefinition('change_document_code');
			$td->addField($schemaManager->newIntegerFieldDefinition('id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newIntegerFieldDefinition('context_id')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('document_id')->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('code', array('length' => 100))->setNullable(false))
				->addKey($this->newPrimaryKey()->addField($td->getField('id')))
				->addKey($this->newIndexKey()
					->addField($td->getField('context_id'))
					->addField($td->getField('document_id'))
					->setName('document'))
				->addKey($this->newIndexKey()
					->addField($td->getField('context_id'))
					->addField($td->getField('code'))
					->setName('code'))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_document_code_context'] = $td = $schemaManager->newTableDefinition('change_document_code_context');
			$td->addField($schemaManager->newIntegerFieldDefinition('context_id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newVarCharFieldDefinition('name', array('length' => 100))->setNullable(false))
				->addKey($this->newPrimaryKey()->addField($td->getField('context_id')))
				->addKey($this->newUniqueKey()->setName('context')
					->addField($td->getField('name')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_document_filters'] = $td = $schemaManager->newTableDefinition('change_document_filters');
			$td->addField($schemaManager->newIntegerFieldDefinition('filter_id')->setNullable(false)->setAutoNumber(true))
				->addField($schemaManager->newVarCharFieldDefinition('model_name', array('length' => 80))->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('user_id')->setDefaultValue(0))
				->addField($schemaManager->newTextFieldDefinition('content')->setNullable(false))
				->addField($schemaManager->newVarCharFieldDefinition('label', array('length' => 255))->setNullable(false))
				->addKey($this->newPrimaryKey()->addField($td->getField('filter_id')))
				->setOption('AUTONUMBER', 1);

			$this->tables['change_document_attributes'] = $td = $schemaManager->newTableDefinition('change_document_attributes');
			$td->addField($schemaManager->newIntegerFieldDefinition('document_id')->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('typology_id')->setNullable(true))
				->addField($schemaManager->newTextFieldDefinition('data')->setNullable(false))
				->addKey($this->newPrimaryKey()->addField($td->getField('document_id')));

			$this->tables['change_document_attributes_index'] = $td = $schemaManager->newTableDefinition('change_document_attributes_index');
			$td->addField($schemaManager->newIntegerFieldDefinition('attribute_id')->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('document_id')->setNullable(false))
				->addField($schemaManager->newIntegerFieldDefinition('index')->setNullable(false)->setDefaultValue(0))
				->addField($schemaManager->newIntegerFieldDefinition('integer_value')->setNullable(true))
				->addField($schemaManager->newFloatFieldDefinition('float_value')->setNullable(true))
				->addField($schemaManager->newDateFieldDefinition('date_value')->setNullable(true))
				->addField($schemaManager->newCharFieldDefinition('string_value')->setNullable(true))
				->addKey($this->newPrimaryKey()->addField($td->getField('attribute_id'))->addField($td->getField('document_id'))
					->addField($td->getField('index')));
		}
		return $this->tables;
	}
}
