<?php
namespace Imi\Model\Event\Param;

use Imi\Event\EventParam;

class InitEventParam extends EventParam
{
	/**
	 * 模型
	 * @var \Imi\Model\BaseModel
	 */
	public $model;

	/**
	 * 初始化数据
	 * @var \Imi\Util\LazyArrayObject
	 */
	public $data;
}