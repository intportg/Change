<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Db\Query;

use Change\Db\Query\Expressions\Assignment;
use Change\Db\Query\Expressions\AbstractExpression;
use Change\Db\Query\Expressions\BinaryOperation;
use Change\Db\Query\Expressions\Concat;
use Change\Db\Query\Expressions\Func;
use Change\Db\Query\Expressions\Table;
use Change\Db\Query\Expressions\Identifier;
use Change\Db\Query\Expressions\Column;
use Change\Db\Query\Expressions\AllColumns;
use Change\Db\Query\Expressions\Alias;
use Change\Db\Query\Expressions\Parameter;
use Change\Db\Query\Expressions\Numeric;
use Change\Db\Query\Expressions\String;
use Change\Db\Query\Expressions\ExpressionList;
use Change\Db\Query\Expressions\SubQuery;
use Change\Db\Query\Expressions\Raw;
use Change\Db\Query\Predicates\Conjunction;
use Change\Db\Query\Predicates\Disjunction;
use Change\Db\Query\Predicates\UnaryPredicate;
use Change\Db\Query\Predicates\BinaryPredicate;
use Change\Db\Query\Predicates\Like;
use Change\Db\Query\Predicates\In;
use Change\Db\Query\Predicates\HasPermission;
use Change\Db\ScalarType;
use Change\Db\SqlMapping;

/**
 * @api
 * @name \Change\Db\Query\SQLFragmentBuilder
 */
class SQLFragmentBuilder
{
	/**
	 * @var SqlMapping
	 */
	protected $sqlMapping;

	/**
	 * @var AbstractBuilder
	 */
	protected $builder;

	/**
	 * @param SqlMapping|AbstractBuilder $sqlMappingOrBuilder
	 * @throws \InvalidArgumentException
	 */
	public function __construct($sqlMappingOrBuilder)
	{
		if ($sqlMappingOrBuilder instanceof AbstractBuilder)
		{
			$this->builder = $sqlMappingOrBuilder;
			$this->sqlMapping = $this->builder->getSqlMapping();
		}
		elseif ($sqlMappingOrBuilder instanceof SqlMapping)
		{
			$this->sqlMapping = $sqlMappingOrBuilder;
		}
		else
		{
			throw new \InvalidArgumentException('Argument 1 must be a SqlMapping or AbstractBuilder');
		}
	}

	/**
	 * @param \Change\Db\Query\AbstractBuilder $builder
	 */
	public function setBuilder(\Change\Db\Query\AbstractBuilder $builder = null)
	{
		$this->builder = $builder;
	}

	/**
	 * @param \Change\Db\SqlMapping $sqlMapping
	 */
	public function setSqlMapping(\Change\Db\SqlMapping $sqlMapping)
	{
		$this->sqlMapping = $sqlMapping;
	}

	/**
	 * @return \Change\Db\SqlMapping
	 */
	public function getSqlMapping()
	{
		return $this->sqlMapping;
	}

	/**
	 * Build a function argument after $name assumed as function arguments
	 * @api
	 * @param string $name
	 * @return \Change\Db\Query\Expressions\Func
	 */
	public function func($name)
	{
		$funcArgs = func_get_args();
		array_shift($funcArgs);
		return new Func($name, $this->normalizeValue($funcArgs));
	}

	/**
	 * @api
	 * @return \Change\Db\Query\Expressions\Func
	 */
	public function sum()
	{
		return new Func('SUM', $this->normalizeValue(func_get_args()));
	}

	/**
	 * Build a reference to a given table
	 *
	 * @api
	 * @param string $tableName
	 * @param string $dbName
	 * @return Table
	 */
	public function table($tableName, $dbName = null)
	{
		return new Table($tableName, $dbName);
	}

	/**
	 * Build an identifier string (eg: `test` on MySQL) which can be passed
	 * for instance as the second argument of the alias method.
	 *
	 * @api
	 * @param string $tableName
	 * @param string $dbName
	 * @return Identifier
	 */
	public function identifier($tableName = null, $dbName = null)
	{
		return new Identifier(func_get_args());
	}

	/**
	 * @api
	 * @param string $name
	 * @param Table | Identifier | string $tableOrIdentifier
	 * @return Column
	 */
	public function column($name, $tableOrIdentifier = null)
	{
		if (is_string($name))
		{
			$name = $this->identifier($name);
		}

		if (is_string($tableOrIdentifier))
		{
			$tableOrIdentifier = $this->identifier($tableOrIdentifier);
		}
		return new Column($name, $tableOrIdentifier);
	}

	/**
	 * @api
	 * @return AllColumns
	 */
	public function allColumns()
	{
		return new AllColumns();
	}

	/**
	 * @param AbstractExpression|string $exp1
	 * @param AbstractExpression|string $exp2
	 * @param AbstractExpression|string $_ [optional]
	 * @return Concat
	 */
	public function concat($exp1, $exp2, $_ = null)
	{
		return new Concat($this->normalizeValue(func_get_args()));
	}

	/**
	 * @api
	 * @throws \InvalidArgumentException
	 * @param AbstractExpression $lhs
	 * @param string | Identifier $rhs (if string assume identifier)
	 * @return Alias
	 */
	public function alias(AbstractExpression $lhs, $rhs)
	{
		if (is_string($rhs))
		{
			$rhs = $this->identifier($rhs);
		}
		if (!($rhs instanceof Identifier))
		{
			throw new \InvalidArgumentException('Could not convert argument 2 to an Identifier', 42012);
		}
		return new Alias($lhs, $rhs);
	}

	/**
	 * @api
	 * @throws \InvalidArgumentException
	 * @param string | AbstractExpression $lhs (if string assume identifier)
	 * @param string | AbstractExpression $rhs (if string assume string)
	 * @return Assignment
	 */
	public function assignment($lhs, $rhs)
	{
		if (is_string($lhs))
		{
			$lhs = $this->identifier($lhs);
		}
		if (!($lhs instanceof AbstractExpression))
		{
			throw new \InvalidArgumentException('Could not convert argument 1 to an Expression', 42013);
		}

		if (is_string($rhs))
		{
			$rhs = $this->string($rhs);
		}
		if (!($rhs instanceof AbstractExpression))
		{
			throw new \InvalidArgumentException('Could not convert argument 2 to an Expression', 42012);
		}

		return new Assignment($lhs, $rhs);
	}

	/**
	 * @param Parameter $parameter
	 * @return Parameter
	 */
	protected function bindParameter(Parameter $parameter)
	{
		if ($this->builder)
		{
			$this->builder->addParameter($parameter);
		}
		return $parameter;
	}

	/**
	 * @api
	 * @param string $parameterName
	 * @param string $scalarType \Change\Db\ScalarType::*
	 * @throws \InvalidArgumentException
	 * @return Parameter
	 */
	public function typedParameter($parameterName, $scalarType)
	{
		if (!is_string($parameterName))
		{
			throw new \InvalidArgumentException('Argument 1 must be a string', 42013);
		}
		return $this->bindParameter(new Parameter($parameterName, $scalarType));
	}

	/**
	 * @api
	 * Assume a string parameter
	 * @param string $parameterName
	 * @return Parameter
	 */
	public function parameter($parameterName)
	{
		return $this->typedParameter($parameterName, ScalarType::STRING);
	}

	/**
	 * @api
	 * @param string $parameterName
	 * @return Parameter
	 */
	public function integerParameter($parameterName)
	{
		return $this->typedParameter($parameterName, ScalarType::INTEGER);
	}

	/**
	 * @api
	 * @param string $parameterName
	 * @return Parameter
	 */
	public function decimalParameter($parameterName)
	{
		return $this->typedParameter($parameterName, ScalarType::DECIMAL);
	}

	/**
	 * @api
	 * @param string $parameterName
	 * @return Parameter
	 */
	public function dateTimeParameter($parameterName)
	{
		return $this->typedParameter($parameterName, ScalarType::DATETIME);
	}

	/**
	 * @api
	 * @param string $parameterName
	 * @return Parameter
	 */
	public function lobParameter($parameterName)
	{
		return $this->typedParameter($parameterName, ScalarType::LOB);
	}

	/**
	 * @api
	 * @param string $parameterName
	 * @return Parameter
	 */
	public function booleanParameter($parameterName)
	{
		return $this->typedParameter($parameterName, ScalarType::BOOLEAN);
	}

	/**
	 * @api
	 * @param integer|float $number
	 * @return Numeric
	 */
	public function number($number)
	{
		return new Numeric($number);
	}

	/**
	 * @api
	 * @param string $string
	 * @return String
	 */
	public function string($string)
	{
		return new String($string);
	}

	/**
	 * @api
	 * @param AbstractExpression|string $expression1
	 * @param AbstractExpression|string $_ [optional]
	 * @return ExpressionList
	 */
	public function expressionList($expression1, $_ = null)
	{
		return new ExpressionList($this->normalizeValue(func_get_args()));
	}

	/**
	 * @api
	 * @param SelectQuery $selectQuery
	 * @return SubQuery
	 */
	public function subQuery(SelectQuery $selectQuery)
	{
		return new SubQuery($selectQuery);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryPredicate
	 */
	public function eq($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryPredicate($lhs, $rhs, BinaryPredicate::EQUAL);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryPredicate
	 */
	public function neq($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryPredicate($lhs, $rhs, BinaryPredicate::NOTEQUAL);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryPredicate
	 */
	public function gt($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryPredicate($lhs, $rhs, BinaryPredicate::GREATERTHAN);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryPredicate
	 */
	public function gte($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryPredicate($lhs, $rhs, BinaryPredicate::GREATERTHANOREQUAL);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryPredicate
	 */
	public function lt($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryPredicate($lhs, $rhs, BinaryPredicate::LESSTHAN);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryPredicate
	 */
	public function lte($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryPredicate($lhs, $rhs, BinaryPredicate::LESSTHANOREQUAL);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @param integer $matchMode
	 * @param bool $caseSensitive
	 * @return \Change\Db\Query\Predicates\Like
	 */
	public function like($lhs, $rhs, $matchMode = Like::ANYWHERE, $caseSensitive = false)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new Like($lhs, $rhs, $matchMode, ($caseSensitive == true));
	}

	/**
	 * @api
	 * @param string|AbstractExpression $lhs
	 * @param string|array|AbstractExpression $rhs1
	 * @param string|AbstractExpression $_ [optional]
	 * @return In
	 */
	public function in($lhs, $rhs1, $_ = null)
	{
		if ($rhs1 instanceof SelectQuery)
		{
			$rhs = $this->subQuery($rhs1);
		}
		elseif ($rhs1 instanceof Subquery || $rhs1 instanceof ExpressionList)
		{
			$rhs = $rhs1;
		}
		else
		{
			$rhs = new ExpressionList();

			$items = func_get_args();
			array_shift($items);
			if (count($items))
			{
				$converter = function ($item) { return new String(strval($item)); };
				foreach ($items as $item)
				{
					if (is_array($item))
					{
						foreach ($item as $si)
						{
							$rhs->add($this->normalizeValue($si, $converter));
						}
					}
					else
					{
						$rhs->add($this->normalizeValue($item, $converter));
					}
				}
			}
		}
		return new In($this->normalizeValue($lhs), $rhs);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return In
	 */
	public function notIn($lhs, $rhs)
	{
		$pre = call_user_func_array([$this, 'in'], func_get_args());
		$pre->setNot(true);
		return $pre;
	}

	/**
	 * @param SelectQuery|SubQuery $subQuery
	 * @throws \InvalidArgumentException
	 * @return Predicates\Exists
	 */
	public function exists($subQuery)
	{
		if ($subQuery instanceof SelectQuery)
		{
			$subQuery = $this->subQuery($subQuery);
		}

		if ($subQuery instanceof SubQuery)
		{
			return new Predicates\Exists($subQuery);
		}
		throw new \InvalidArgumentException('Could not convert argument 1 to an SubQuery', 42012);
	}

	/**
	 * @param SelectQuery|SubQuery $subQuery
	 * @throws \InvalidArgumentException
	 * @return Predicates\Exists
	 */
	public function notExists($subQuery)
	{
		$exists = $this->exists($subQuery);
		$exists->setNot(true);
		return $exists;
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryOperation
	 */
	public function addition($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryOperation($lhs, $rhs, '+');
	}

	/**
	 * @api
	 * @param string | AbstractExpression $lhs
	 * @param string | AbstractExpression $rhs
	 * @return BinaryOperation
	 */
	public function subtraction($lhs, $rhs)
	{
		$lhs = $this->normalizeValue($lhs);
		$rhs = $this->normalizeValue($rhs);
		return new BinaryOperation($lhs, $rhs, '-');
	}

	/**
	 * @api
	 * @param string | AbstractExpression $expression
	 * @return UnaryPredicate
	 */
	public function isNull($expression)
	{
		$expression = $this->normalizeValue($expression);
		return new UnaryPredicate($expression, UnaryPredicate::ISNULL);
	}

	/**
	 * @api
	 * @param string | AbstractExpression $expression
	 * @return UnaryPredicate
	 */
	public function isNotNull($expression)
	{
		$expression = $this->normalizeValue($expression);
		return new UnaryPredicate($expression, UnaryPredicate::ISNOTNULL);
	}

	/**
	 * @api
	 * @param InterfaceSQLFragment $predicate1
	 * @param InterfaceSQLFragment $_ [optional]
	 * @return Conjunction
	 */
	public function logicAnd($predicate1, $_ = null)
	{
		$result = new Conjunction();
		$result->setArguments($this->normalizeValue(func_get_args()));
		return $result;
	}

	/**
	 * @api
	 * @param InterfaceSQLFragment $predicate1
	 * @param InterfaceSQLFragment $_ [optional]
	 * @return Disjunction
	 */
	public function logicOr($predicate1, $_ = null)
	{
		$result = new Disjunction();
		$result->setArguments($this->normalizeValue(func_get_args()));
		return $result;
	}

	/**
	 * @api
	 * @param AbstractExpression|\Change\User\UserInterface|integer|null $accessor
	 * @param AbstractExpression|string|null $role
	 * @param AbstractExpression|integer|null $resource
	 * @param AbstractExpression|string|null $privilege
	 * @return HasPermission
	 */
	public function hasPermission($accessor = null, $role = null, $resource = null, $privilege = null)
	{
		if ($accessor instanceof \Change\User\UserInterface)
		{
			if ($accessor->authenticated())
			{
				$list = $this->expressionList($this->number($accessor->getId()));
				foreach ($accessor->getGroups() as $group)
				{
					/* @var $group \Change\User\GroupInterface */
					$list->add($this->number($group->getId()));
				}
				$accessor = $list;
			}
			else
			{
				$accessor = null;
			}
		}
		elseif (is_numeric($accessor))
		{
			$accessor = $this->number($accessor);
		}

		if (is_string($role))
		{
			$role = $this->string($role);
		}

		if (is_numeric($resource))
		{
			$resource = $this->number($resource);
		}

		if (is_string($privilege))
		{
			$privilege = $this->string($privilege);
		}
		return new HasPermission($accessor, $role, $resource, $privilege);
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getPermissionRuleTable()
	{
		return $this->table($this->sqlMapping->getPermissionRuleTable());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getJobTable()
	{
		return $this->table($this->sqlMapping->getJobTable());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getPluginTable()
	{
		return $this->table($this->sqlMapping->getPluginTableName());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getDocumentIndexTable()
	{
		return $this->table($this->sqlMapping->getDocumentIndexTableName());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getDocumentCorrectionTable()
	{
		return $this->table($this->sqlMapping->getDocumentCorrectionTable());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getDocumentDeletedTable()
	{
		return $this->table($this->sqlMapping->getDocumentDeletedTable());
	}

	/**
	 * @api
	 * @param string $rootDocumentName
	 * @return Table
	 */
	public function getDocumentTable($rootDocumentName)
	{
		return $this->table($this->sqlMapping->getDocumentTableName($rootDocumentName));
	}

	/**
	 * @api
	 * @param string $rootDocumentName
	 * @return Table
	 */
	public function getDocumentI18nTable($rootDocumentName)
	{
		return $this->table($this->sqlMapping->getDocumentI18nTableName($rootDocumentName));
	}

	/**
	 * @api
	 * @param string $rootDocumentName
	 * @return Table
	 */
	public function getDocumentRelationTable($rootDocumentName)
	{
		return $this->table($this->sqlMapping->getDocumentRelationTableName($rootDocumentName));
	}

	/**
	 * @api
	 * @param string $propertyName
	 * @param Table | Identifier | string $tableOrIdentifier
	 * @return Column
	 */
	public function getDocumentColumn($propertyName, $tableOrIdentifier = null)
	{
		return $this->column($this->sqlMapping->getDocumentFieldName($propertyName), $tableOrIdentifier);
	}

	/**
	 * @api
	 * @param string $moduleName
	 * @return Table
	 */
	public function getTreeTable($moduleName)
	{
		return $this->table($this->sqlMapping->getTreeTableName($moduleName));
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getDocumentMetasTable()
	{
		return $this->table($this->sqlMapping->getDocumentMetasTableName());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getDocumentAttributesTable()
	{
		return $this->table($this->sqlMapping->getDocumentAttributesTableName());
	}

	/**
	 * @api
	 * @return Table
	 */
	public function getDocumentAttributesIndexTable()
	{
		return $this->table($this->sqlMapping->getDocumentAttributesIndexTableName());
	}

	/**
	 * For internal use only.
	 * @param AbstractExpression|array|string $object
	 * @param \Closure|null $converter
	 * @throws \InvalidArgumentException
	 * @return AbstractExpression|array
	 */
	public function normalizeValue($object, $converter = null)
	{
		if ($converter == null)
		{
			$converter = function ($item)
			{
				return new Raw(strval($item));
			};
		}

		if (is_array($object))
		{
			$builder = $this;
			return array_map(function ($item) use ($builder, $converter)
			{
				return $builder->normalizeValue($item, $converter);
			}, $object);
		}

		if (!($object instanceof AbstractExpression))
		{
			if (is_callable($converter))
			{
				return call_user_func($converter, $object);
			}
			else
			{
				throw new \InvalidArgumentException('Argument 2 is not valid \Closure', 42015);
			}
		}
		return $object;
	}
}