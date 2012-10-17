<?php
namespace Change\Documents;

/**
 * @name \Change\Documents\ModelManager
 * @method \Change\Documents\ModelManager getInstance()
 */
class ModelManager
{
	/**
	 * @var string[]
	 */
	protected $publicationStatuses = array('DRAFT','CORRECTION','ACTIVE','PUBLISHED','DEACTIVATED','FILED','DEPRECATED','TRASH','WORKFLOW');
	
	/**
	 * @var \Change\Documents\AbstractModel[]
	 */
	protected $documentModels = array();
	
	/**
	 * List of Publication status:
	 * DRAFT, CORRECTION, ACTIVE, PUBLISHED, DEACTIVATED, FILED, DEPRECATED, TRASH, WORKFLOW
	 * @return string[]
	 */
	public function getPublicationStatuses()
	{
		return $this->publicationStatuses;
	}
	
	/**
	 * @param string $modelName
	 * @return \Change\Documents\AbstractModel|null
	 */
	public function getModelByName($modelName)
	{
		if (!array_key_exists($modelName, $this->documentModels))
		{
			$className = $this->getModelClassName($modelName);
			if ($className)
			{
				$this->documentModels[$modelName] = new $className();
			}
			else
			{
				$this->documentModels[$modelName] = null;
			}
		}
		return $this->documentModels[$modelName];
	}
	
	/**
	 * @param string $modelName
	 * @return NULL|string
	 */
	protected function getModelClassName($modelName)
	{
		$parts = explode('_', $modelName);
		if (count($parts) === 3)
		{
			list($vendor, $moduleName, $documentName) = $parts;
			$className = 'Compilation\\' . ucfirst($vendor) . '\\' . ucfirst($moduleName) .'\\Documents\\' . ucfirst($documentName) . 'Model';
			if (class_exists($className))
			{
				return $className;
			}
		}
		return null;
	}
}