<?php
/**
 * Bitrix Framework
 * @package bitrix
 * @subpackage tasks
 * @copyright 2001-2012 Bitrix
 */
namespace Bitrix\Tasks;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use CDatabase;
use CUser;

use Bitrix\Tasks\Util\Assert;

Loc::loadMessages(__FILE__);

class TaskTable extends Entity\DataManager
{
	/**
	 * @return string
	 */
	public static function getUfId()
	{
		return 'TASKS_TASK';
	}

	/**
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_tasks';
	}

	/**
	 * @return array
	 */
	public static function getMap()
	{
		/**
		 * @global CDatabase $DB
		 * @global string $DBType
		 * @global CUser $USER
		 */
		global $DB, $DBType, $USER;

		if(is_object($USER) && method_exists($USER, 'getId'))
		{
			$userId = (int) $USER->getId();
		}
		else
		{
			$userId = 0;
		}

		$fieldsMap = array(
			'ID' => array(
				'data_type' => 'integer',
				'primary' => true,
				'autocomplete' => true,
			),
			'TITLE' => array(
				'data_type' => 'string'
			),
			'DESCRIPTION' => array(
				'data_type' => 'string'
			),
			'DESCRIPTION_TR' => array(
				'data_type' => 'string',
				'expression' => array(
					self::getDbTruncTextFunction($DBType, '%s'),
					'DESCRIPTION'
				)
			),
			'DESCRIPTION_IN_BBCODE' => array(
				'data_type' => 'boolean',
				'values' => array('N', 'Y')
			),
			'PRIORITY' => array(
				'data_type' => 'string'
			),
			'STATUS' => array(
				'data_type' => 'string'
			),
			'STATUS_PSEUDO' => array(
				'data_type' => 'string',
				'expression' => array(
					"CASE
					WHEN
						%s < ".$DB->currentTimeFunction()." AND %s != '4' AND %s != '5' ".($userId ? " AND (%s != '7' OR %s != ".$userId.")" : "")."
					THEN
						'-1'
					ELSE
						%s
					END",
					'DEADLINE', 'STATUS', 'STATUS', 'STATUS', 'RESPONSIBLE_ID', 'STATUS'
				)
			),
			'RESPONSIBLE_ID' => array(
				'data_type' => 'integer'
			),
			'RESPONSIBLE' => array(
				'data_type' => 'Bitrix\Main\User',
				'reference' => array('=this.RESPONSIBLE_ID' => 'ref.ID')
			),
			'DATE_START' => array(
				'data_type' => 'datetime'
			),
			'START_DATE_PLAN' => array(
				'data_type' => 'datetime'
			),
			'END_DATE_PLAN' => array(
				'data_type' => 'datetime'
			),
			'DURATION_PLAN' => array(
				'data_type' => 'integer'
			),
			'DURATION_TYPE' => array(
				'data_type' => 'string'
			),
			'DEADLINE' => array(
				'data_type' => 'datetime'
			),
			'CREATED_BY' => array(
				'data_type' => 'integer'
			),
			'CREATED_BY_USER' => array(
				'data_type' => 'Bitrix\Main\User',
				'reference' => array(
					'=this.CREATED_BY' => 'ref.ID'
				)
			),
			'CREATED_DATE' => array(
				'data_type' => 'datetime'
			),
			'CHANGED_BY' => array(
				'data_type' => 'integer'
			),
			'CHANGED_BY_USER' => array(
				'data_type' => 'Bitrix\Main\User',
				'reference' => array('=this.CHANGED_BY' => 'ref.ID')
			),
			'CHANGED_DATE' => array(
				'data_type' => 'datetime'
			),
			'STATUS_CHANGED_BY' => array(
				'data_type' => 'integer'
			),
			'STATUS_CHANGED_BY_USER' => array(
				'data_type' => 'Bitrix\Main\User',
				'reference' => array('=this.STATUS_CHANGED_BY' => 'ref.ID')
			),
			'STATUS_CHANGED_DATE' => array(
				'data_type' => 'datetime'
			),
			'CLOSED_BY' => array(
				'data_type' => 'integer'
			),
			'CLOSED_BY_USER' => array(
				'data_type' => 'Bitrix\Main\User',
				'reference' => array('=this.CLOSED_BY' => 'ref.ID')
			),
			'CLOSED_DATE' => array(
				'data_type' => 'datetime'
			),
			'PARENT_ID' => array(
				'data_type' => 'integer'
			),
			'PARENT' => array(
				'data_type' => 'Task',
				'reference' => array('=this.PARENT_ID' => 'ref.ID')
			),
			'SITE_ID' => array(
				'data_type' => 'integer'
			),
			'SITE' => array(
				'data_type' => 'Bitrix\Main\Site',
				'reference' => array('=this.SITE_ID' => 'ref.LID')
			),
			'ADD_IN_REPORT' => array(
				'data_type' => 'boolean',
				'values' => array('N', 'Y')
			),
			'GROUP_ID' => array(
				'data_type' => 'integer'
			),
			'GROUP' => array(
				'data_type' => 'Bitrix\Socialnetwork\Workgroup',
				'reference' => array('=this.GROUP_ID' => 'ref.ID')
			),
			'MARK' => array(
				'data_type' => 'string'
			),
			'ALLOW_TIME_TRACKING' => array(
				'data_type' => 'boolean',
				'values' => array('N', 'Y')
			),
			'TIME_ESTIMATE' => array(
				'data_type' => 'integer'
			),
			'TIME_SPENT_IN_LOGS' => array(
				'data_type' => 'integer',
				'expression' => array(
					'(SELECT  SUM(SECONDS) FROM b_tasks_elapsed_time WHERE TASK_ID = %s)',
					'ID'
				)
			),
			'DURATION' => array(
				'data_type' => 'integer',
				'expression' => array(
					'ROUND((SELECT  SUM(SECONDS)/60 FROM b_tasks_elapsed_time WHERE TASK_ID = %s),0)',
					'ID'
				)
			),
			// DURATION_PLAN_MINUTES field - only for old user reports, which use it
			'DURATION_PLAN_MINUTES' => array(
				'data_type' => 'integer',
				'expression' => array(
					'%s * (CASE WHEN %s = \'days\' THEN 8 ELSE 1 END) * 60',
					'DURATION_PLAN', 'DURATION_TYPE'
				)
			),
			'DURATION_PLAN_HOURS' => array(
				'data_type' => 'integer',
				'expression' => array(
					'(%s * (CASE WHEN %s = \'days\' THEN 24 ELSE 1 END))',
					'DURATION_PLAN', 'DURATION_TYPE'
				)
			),
			'IS_OVERDUE' => array(
				'data_type' => 'boolean',
				'expression' => array(
					'CASE WHEN %s IS NOT NULL AND (%s < %s OR (%s IS NULL AND %s < '.$DB->currentTimeFunction().')) THEN 1 ELSE 0 END',
					'DEADLINE', 'DEADLINE', 'CLOSED_DATE', 'CLOSED_DATE', 'DEADLINE'
				),
				'values' => array(0, 1)
			),
			'IS_OVERDUE_PRCNT' => array(
				'data_type' => 'integer',
				'expression' => array(
					'SUM(%s)/COUNT(%s)*100',
					'IS_OVERDUE', 'ID'
				)
			),
			'IS_MARKED' => array(
				'data_type' => 'boolean',
				'expression' => array(
					'CASE WHEN %s IN(\'P\', \'N\') THEN 1 ELSE 0 END',
					'MARK'
				),
				'values' => array(0, 1)
			),
			'IS_MARKED_PRCNT' => array(
				'data_type' => 'integer',
				'expression' => array(
					'SUM(%s)/COUNT(%s)*100',
					'IS_MARKED', 'ID'
				)
			),
			'IS_EFFECTIVE' => array(
				'data_type' => 'boolean',
				'expression' => array(
					'CASE WHEN %s = \'P\' THEN 1 ELSE 0 END',
					'MARK'
				),
				'values' => array(0, 1)
			),
			'IS_EFFECTIVE_PRCNT' => array(
				'data_type' => 'integer',
				'expression' => array(
					'SUM(%s)/COUNT(%s)*100',
					'IS_EFFECTIVE', 'ID'
				)
			),
			'IS_RUNNING' => array(
				'data_type' => 'boolean',
				'expression' => array(
					'CASE WHEN %s IN (3,4) THEN 1 ELSE 0 END',
					'STATUS'
			),
				'values' => array(0, 1)
			),
			'ZOMBIE' => array(
				'data_type' => 'boolean',
				'values' => array('N', 'Y')
			),
		);

		return $fieldsMap;
	}

	/**
	 * Dont rely on this function, it may become deprecated. This function DOES NOT check rights.
	 * @param integer task id
	 * @param mixed[] parameters
	 * @return \Bitrix\Main\DB\ArrayResult
	 * @access private
	 */
	public static function getChildrenTasksData($taskId, $parameters = array())
	{
		$taskId = Assert::expectIntegerPositive($taskId, '$taskId');

		if(!is_array($parameters))
			$parameters = array();

		// a shame, but no tree struct here, so have to make "recursive" calls...
		$queue = array($taskId);
		$meetings = array();
		$result = array();

		while(!empty($queue))
		{
			$nextId = array_shift($queue);
			if(isset($meetings[$nextId]))
			{
				throw new \Bitrix\Tasks\Exception('Task subtree seems to be loopy');
			}
			$meetings[$nextId] = true;

			$nextParams = array_merge_recursive($parameters, array(
				'filter' => array(
					'=PARENT_ID' => $nextId
				),
				'select' => array(
					'ID'
				)
			));

			$res = static::getList($nextParams);
			while($item = $res->fetch())
			{
				if(intval($item['ID']))
				{
					array_unshift($queue, $item['ID']);

					$result[$item['ID']] = $item;
				}
			}
		}

		return new \Bitrix\Main\DB\ArrayResult($result);
	}

	/**
	 * Allows to add various runtime mixins to static::getList(), which depend on some external data and, therefore, cannot be placed at static::getMap()
	 * 
	 * @param string[] $mixinCodes Mixin codes to create
	 * 
	 * 		<li> 
	 * 
	 * @param string[] $param External parameters
	 * 
	 * 		<li> USER_ID integer Current user id. If not set, takes the current user`s id
	 * 
	 * @return string
	 */
	public static function getRuntimeFieldMixins($mixinCodes, $parameters = array())
	{
		$mixinCodes = Assert::expectArrayOfUniqueStringNotNull($mixinCodes, '$mixinCodes');

		if(!is_array($parameters))
		{
			$parameters = array();
		}
		if(!array_key_exists('USER_ID', $parameters))
		{
			// get current USER_ID
			global $USER;

			$parameters['USER_ID'] = 0;
			if(is_object($USER) && method_exists($USER, 'getId'))
			{
				$parameters['USER_ID'] = (int) $USER->getId();
			}
		}
		if(!array_key_exists('REF_FIELD', $parameters))
		{
			$parameters['REF_FIELD'] = false;
		}
		$rf = $parameters['REF_FIELD'];

		$result = array();

		foreach($mixinCodes as $mixinCode)
		{
			if($mixinCode == 'FAVORITE' || $mixinCode == 'IN_FAVORITE')
			{
				$parameters['USER_ID'] = Assert::expectIntegerPositive($parameters['USER_ID'], '$parameters[USER_ID]');

				$result['FAVORITE'] = array(
					'data_type' => 'Bitrix\Tasks\Task\Favorite',
					'reference' => array(
						'=this'.($rf ? '.'.$rf : '').'.ID' => 'ref.TASK_ID',
						'=ref.USER_ID' => array('?', $parameters['USER_ID'])
					)
				);
			}

			if($mixinCode == 'IN_FAVORITE')
			{
				$result['IN_FAVORITE'] = array(
					'data_type' => 'boolean',
					'expression' => array(
						'CASE WHEN %s IS NOT NULL THEN 1 ELSE 0 END',
						'FAVORITE.TASK_ID'
					)
				);
			}

			if($mixinCode == 'CHECK_RIGHTS')
			{
				$parameters['USER_ID'] = Assert::expectIntegerPositive($parameters['USER_ID'], '$parameters[USER_ID]');

				$field = static::getRuntimeFieldMixinsCheckRights($parameters);
				if($field !== false)
				{
					$result['CHECK_RIGHTS'] = $field;
				}
			}
		}

		return $result;
	}

	protected static function getRuntimeFieldMixinsCheckRights($parameters)
	{
		$result = false;

		$userId =	(int) $parameters['USER_ID'];
		$rf = 		$parameters['REF_FIELD'];

		if (
			!\CTasksTools::IsAdmin($userId) // not admin
			&& !\CTasksTools::IsPortalB24Admin($userId) // and not B24portal admin
		)
		{
			list($conditions, $expression) = \CTasks::getPermissionFilterConditions($parameters, array('USE_PLACEHOLDERS' => true));

			$conditions = "(case when (".implode(' OR ', $conditions).") then '1' else '0' end)";
			array_unshift($expression, $conditions);

			$query = new \Bitrix\Main\Entity\Query('Bitrix\\Tasks\\Task');
			$query->registerRuntimeField('F', array(
				'data_type' => 'string',
				'expression' => $expression
			));
			$query->setFilter(array('=F' => '1'));
			$query->setSelect(array('TASK_ID' => 'ID'));

			$result = array(
				'data_type' => \Bitrix\Main\Entity\Base::getInstanceByQuery($query),
				'reference' => array(
					'=this'.($rf ? '.'.$rf : '').'.ID' => 'ref.TASK_ID'
				),
				'join_type' => 'inner'
			);
		}

		return $result;
	}

	/**
	 * The code was taken from CTask::GetFilter()
	 */


	/**
	 * @param string $dbtype Database type.
	 * @param string $param SQL field text.
	 * @return string
	 */
	private static function getDbTruncTextFunction($dbtype, $param)
	{
		switch (ToLower($dbtype))
		{
			case 'mysql':
				$result = "SUBSTR(".$param.", 1, 1024)";
			break;

			case 'mssql':
				$result = "SUBSTRING(".$param.", 1, 1024)";
			break;

			case 'oracle':
				$result = "TO_CHAR(SUBSTR(".$param.", 1, 1024))";
			break;

			default:
				$result = $param;
			break;
		}

		return ($result);
	}
}
