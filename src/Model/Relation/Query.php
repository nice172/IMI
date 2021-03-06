<?php
namespace Imi\Model\Relation;

use Imi\Db\Db;
use Imi\Util\Imi;
use Imi\Util\Text;
use Imi\Util\ArrayList;
use Imi\Bean\BeanFactory;
use Imi\Model\ModelManager;
use Imi\Model\Parser\RelationParser;
use Imi\Model\Relation\Struct\OneToOne;
use Imi\Model\Relation\Struct\OneToMany;
use Imi\Model\Relation\Struct\ManyToMany;


abstract class Query
{
	/**
	 * 初始化
	 *
	 * @param \Imi\Model\Model $model
	 * @param string $propertyName
	 * @param \Imi\Bean\Annotation\Base $annotation
	 * @return void
	 */
	public static function init($model, $propertyName, $annotation)
	{
		$relationParser = RelationParser::getInstance();
		$className = BeanFactory::getObjectClass($model);

		$autoSelect = $relationParser->getPropertyAnnotation($className, $propertyName, 'AutoSelect');
		if($autoSelect && !$autoSelect->status)
		{
			return;
		}

		if($annotation instanceof \Imi\Model\Annotation\Relation\OneToOne)
		{
			static::initByOneToOne($model, $propertyName, $annotation);
		}
		else if($annotation instanceof \Imi\Model\Annotation\Relation\OneToMany)
		{
			static::initByOneToMany($model, $propertyName, $annotation);
		}
		else if($annotation instanceof \Imi\Model\Annotation\Relation\ManyToMany)
		{
			static::initByManyToMany($model, $propertyName, $annotation);
		}
	}

	/**
	 * 初始化一对一关系
	 *
	 * @param \Imi\Model\Model $model
	 * @param string $propertyName
	 * @param \Imi\Model\Annotation\Relation\OneToOne $annotation
	 * @return void
	 */
	public static function initByOneToOne($model, $propertyName, $annotation)
	{
		$className = BeanFactory::getObjectClass($model);

		if(class_exists($annotation->model))
		{
			$modelClass = $annotation->model;
		}
		else
		{
			$modelClass = Imi::getClassNamespace($className) . '\\' . $annotation->model;
		}

		$struct = new OneToOne($className, $propertyName, $annotation);
		$leftField = $struct->getLeftField();
		$rightField = $struct->getRightField();

		if(null === $model->$leftField)
		{
			$rightModel = $modelClass::newInstance();
		}
		else
		{
			$rightModel = $modelClass::query()->where($rightField, '=', $model->$leftField)->select()->get();
			if(null === $rightModel)
			{
				$rightModel = $modelClass::newInstance();
			}
		}

		$model->$propertyName = $rightModel;
	}

	/**
	 * 初始化一对多关系
	 *
	 * @param \Imi\Model\Model $model
	 * @param string $propertyName
	 * @param \Imi\Model\Annotation\Relation\OneToMany $annotation
	 * @return void
	 */
	public static function initByOneToMany($model, $propertyName, $annotation)
	{
		$className = BeanFactory::getObjectClass($model);

		if(class_exists($annotation->model))
		{
			$modelClass = $annotation->model;
		}
		else
		{
			$modelClass = Imi::getClassNamespace($className) . '\\' . $annotation->model;
		}

		$struct = new OneToMany($className, $propertyName, $annotation);
		$leftField = $struct->getLeftField();
		$rightField = $struct->getRightField();

		$model->$propertyName = new ArrayList($modelClass);
		if(null !== $model->$leftField)
		{
			$list = $modelClass::query()->where($rightField, '=', $model->$leftField)->select()->getArray();
			if(null !== $list)
			{
				$model->$propertyName->append(...$list);
			}
		}

	}

	/**
	 * 初始化多对多关系
	 *
	 * @param \Imi\Model\Model $model
	 * @param string $propertyName
	 * @param \Imi\Model\Annotation\Relation\ManyToMany $annotation
	 * @return void
	 */
	public static function initByManyToMany($model, $propertyName, $annotation)
	{
		$className = BeanFactory::getObjectClass($model);

		if(class_exists($annotation->model))
		{
			$modelClass = $annotation->model;
		}
		else
		{
			$modelClass = Imi::getClassNamespace($className) . '\\' . $annotation->model;
		}

		$struct = new ManyToMany($className, $propertyName, $annotation);
		$leftField = $struct->getLeftField();
		$rightField = $struct->getRightField();
		$middleTable = ModelManager::getTable($struct->getMiddleModel());
		$rightTable = ModelManager::getTable($struct->getRightModel());

		static::parseManyToManyQueryFields($struct->getMiddleModel(), $struct->getRightModel(), $middleFields, $rightFields);
		$fields = static::mergeManyToManyFields($middleTable, $middleFields, $rightTable, $rightFields);
		
		$model->$propertyName = new ArrayList($struct->getMiddleModel());
		$model->{$annotation->rightMany} = new ArrayList($struct->getRightModel());
		
		if(null !== $model->$leftField)
		{
			$list = Db::query(ModelManager::getDbPoolName($className))
						->table($rightTable)
						// ->field($rightTable . '.*')
						->field(...$fields)
						->join($middleTable, $middleTable . '.' . $struct->getMiddleRightField(), '=', $rightTable . '.' . $rightField)
						->where($struct->getMiddleLeftField(), '=', $model->$leftField)
						->select()
						->getArray();
			if(null !== $list)
			{
				// 关联数据
				static::appendMany($model->$propertyName, $list, $middleFields, $struct->getMiddleModel());
				// $model->$propertyName->append(...$list);

				// 右侧表数据
				static::appendMany($model->{$annotation->rightMany}, $list, $rightFields, $struct->getRightModel());

			}
		}
	}

	private static function parseManyToManyQueryFields($middleModel, $rightModel, &$middleFields, &$rightFields)
	{
		$middleFields = [];
		$rightFields = [];

		$middleTable = ModelManager::getTable($middleModel);
		$rightTable = ModelManager::getTable($rightModel);

		foreach(ModelManager::getFieldNames($middleModel) as $name)
		{
			$middleFields[$middleTable . '_' . $name] = $name;
		}

		foreach(ModelManager::getFieldNames($rightModel) as $name)
		{
			$rightFields[$rightTable . '_' . $name] = $name;
		}
	}

	private static function mergeManyToManyFields($middleTable, $middleFields, $rightTable, $rightFields)
	{
		$result = [];
		foreach($middleFields as $alias => $fieldName)
		{
			$result[] = $middleTable . '.' . $fieldName . ' ' . $alias;
		}
		foreach($rightFields as $alias => $fieldName)
		{
			$result[] = $rightTable . '.' . $fieldName . ' ' . $alias;
		}
		return $result;
	}

	/**
	 * 追加到Many列表
	 *
	 * @param \Imi\Util\ArrayList $manyList
	 * @param array $dataList
	 * @param array $fields
	 * @param string $modelClass
	 * @return void
	 */
	private static function appendMany($manyList, $dataList, $fields, $modelClass)
	{
		foreach($dataList as $row)
		{
			$tmpRow = [];
			foreach($fields as $alias => $fieldName)
			{
				$tmpRow[$fieldName] = $row[$alias];
			}
			$manyList->append($modelClass::newInstance($tmpRow));
		}
	}
}