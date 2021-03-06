<?php
namespace Imi\Model\Annotation\Relation;

use Imi\Bean\Annotation\Base;
use Imi\Bean\Annotation\Parser;

/**
 * 自动保存
 * @Annotation
 * @Target("PROPERTY")
 * @Parser("Imi\Model\Parser\RelationParser")
 */
class AutoSave extends Base
{
	/**
	 * 只传一个参数时的参数名
	 * @var string
	 */
	protected $defaultFieldName = 'status';

	/**
	 * 是否开启
	 *
	 * @var boolean
	 */
	public $status = true;

	/**
	 * save时，删除无关联数据
	 *
	 * @var boolean
	 */
	public $orphanRemoval = false;
}