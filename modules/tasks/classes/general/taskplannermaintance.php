<?php
/**
 * Bitrix Framework
 * @package bitrix
 * @subpackage tasks
 * @copyright 2001-2013 Bitrix
 */


class CTaskPlannerMaintance
{
	const PLANNER_COUNTER_CODE = 'planner_tasks';
	const PLANNER_OPTION_CURRENT_TASKS = 'current_tasks_list';

	private static $arTaskStatusOpened = array(4,5,7);

	private static $SITE_ID = SITE_ID;
	private static $USER_ID = null;

	public static function OnPlannerInit($params)
	{
		global $APPLICATION, $USER, $CACHE_MANAGER;

		self::$SITE_ID = $params['SITE_ID'];
		self::$USER_ID = $USER->GetID();

		$arTasks = array();

		if (self::$USER_ID > 0)
		{
			$CACHE_MANAGER->RegisterTag('tasks_user_'.self::$USER_ID);
			$CACHE_MANAGER->RegisterTag('tasks_user_fields');

			$arTaskIDs = self::getCurrentTasksList();
			$tasksCount = self::getTasksCount($arTaskIDs);
		}
		else
		{
			$arTaskIDs  = array();
			$tasksCount = 0;
		}

		if ($params['FULL'])
		{
			if (self::$USER_ID > 0)
			{
				if (is_array($arTaskIDs) && ( ! empty($arTaskIDs)))
					$arTasks = self::getTasks($arTaskIDs);
			}
		}
		else
		{
			$APPLICATION->IncludeComponent(
				"bitrix:tasks.iframe.popup",
				".default",
				array(
					"ON_TASK_ADDED"   => "BX.DoNothing",
					"ON_TASK_CHANGED" => "BX.DoNothing",
					"ON_TASK_DELETED" => "BX.DoNothing",
				),
				null,
				array("HIDE_ICONS" => "Y")
			);
		}

		CJSCore::RegisterExt('tasks_planner_handler', array(
			'js'   => '/bitrix/js/tasks/core_planner_handler.js',
			'css'  => '/bitrix/js/tasks/css/tasks.css',
			'lang' => BX_ROOT.'/modules/tasks/lang/'.LANGUAGE_ID.'/core_planner_handler.php',
			'rel'  => array('popup', 'tooltip')
		));

		if (self::$USER_ID > 0)
		{
			$oTimer  = CTaskTimerManager::getInstance(self::$USER_ID);
			$arTimer = $oTimer->getLastTimer();

			$arTaskOnTimer = false;
			if (($arTimer !== false) && ($arTimer['TASK_ID'] > 0))
			{
				// Timered task can be in day plan, try to found it
				if (in_array($arTimer['TASK_ID'], $arTaskIDs))
				{
					foreach ($arTasks as &$arTaskData)
					{
						if ($arTaskData['ID'] == $arTimer['TASK_ID'])
						{
							$arTaskOnTimer = $arTaskData;
							break;
						}
					}
					unset($arTaskData);
				}

				// If task not found, select it
				if ($arTaskOnTimer === false)
				{
					$arTmp = self::getTasks(array($arTimer['TASK_ID']));
					if (isset($arTmp[0]))
						$arTaskOnTimer = $arTmp[0];
				}
			}
		}
		else
		{
			$arTimer       = false;
			$arTaskOnTimer = false;
		}

		$arResult = array(
			'DATA' => array(
				'TASKS_ENABLED' => true,
				'TASKS'         => $arTasks,
				'TASKS_COUNT'   => $tasksCount,
				'TASKS_TIMER'   => $arTimer,
				'TASK_ON_TIMER' => $arTaskOnTimer,
				'MANDATORY_UFS' => (CTasksRarelyTools::isMandatoryUserFieldExists() ? 'Y' : 'N')
			),
			'STYLES' => array('/bitrix/js/tasks/css/tasks.css'),
			'SCRIPTS' => array('CJSTask', 'taskQuickPopups', 'tasks_planner_handler', '/bitrix/js/tasks/task-iframe-popup.js')
		);

		return ($arResult);
	}


	public static function OnPlannerAction($action, $params)
	{
		$res = array();
		$lastTaskId = 0;
		switch($action)
		{
			case 'task':

				$lastTaskId = self::plannerActions(array(
					'name' => $_REQUEST['name'],
					'add' => $_REQUEST['add'],
					'remove' => $_REQUEST['remove'],
				), $params['SITE_ID']);

			break;

			case 'timeman_close':
				$res = self::getTimemanCloseDayData(array(
					'SITE_ID' => $params['SITE_ID']
				));
			break;
		}

		if($lastTaskId > 0)
		{
			$res['TASK_LAST_ID'] = $lastTaskId;
		}

		return $res;
	}


	protected static function getTimemanCloseDayData($arParams)
	{
		if(CModule::IncludeModule('timeman'))
		{
			global $USER;

			$arTasks       = array();
			$userId        = $USER->getId();
			$runningTaskId = null;
			$taskRunTime   = null;

			// key features of that info:
			// [REPORT_REQ] => 'A' means that day will be closed right now. other variants - just form show.
			// [INFO][DATE_START] => 1385459336 - unix timestamp of day start
			// [INFO][TIME_START] => 46136 - short timestamp of day start
			// [DURATION]
			// [TIME_LEAKS]
			$arTimemanInfo = CTimeMan::GetRunTimeInfo(true);

			if ( ! ($userId > 0) )
			{
				foreach($arTimemanInfo['PLANNER']['DATA']['TASKS'] as $arTask)
				{
					$arTask['TIME'] = 0;
					$arTasks[] = $arTask;
				}

				return (array('TASKS' => $arTasks));
			}

			$unixTsDateStart = (int) $arTimemanInfo['INFO']['DATE_START'];

			$oTimer  = CTaskTimerManager::getInstance($userId);
			$arTimer = $oTimer->getLastTimer();

			if ($arTimer && ($arTimer['TIMER_STARTED_AT'] > 0))
			{
				$runningTaskId = $arTimer['TASK_ID'];

				if ($arTimer['TIMER_STARTED_AT'] >= $unixTsDateStart)
					$taskRunTime = max(0, time() - (int) $arTimer['TIMER_STARTED_AT']);
				else
					$taskRunTime = max(0, time() - $unixTsDateStart);
			}

			$bitrixTimestampDateStart = $unixTsDateStart + CTasksTools::getTimeZoneOffset();
			$dateStartAsString        = ConvertTimeStamp($bitrixTimestampDateStart, 'FULL');

			foreach($arTimemanInfo['PLANNER']['DATA']['TASKS'] as $arTask)
			{
				$rsElapsedTime = CTaskElapsedTime::getList(
					array('ID' => 'ASC'),
					array(
						'TASK_ID'        => $arTask['ID'],
						'USER_ID'        => $userId,
						'>=CREATED_DATE' => $dateStartAsString
					),
					array('skipJoinUsers' => true)
				);

				$arTask['TIME'] = 0;

				while ($arElapsedTime = $rsElapsedTime->fetch())
					$arTask['TIME'] += max(0, $arElapsedTime['SECONDS']);

				if ($runningTaskId && ($arTask['ID'] == $runningTaskId))
					$arTask['TIME'] += $taskRunTime;

				$arTasks[] = $arTask;
			}

			return array('TASKS' => $arTasks);
		}
	}

	public static function plannerActions($arActions, $site_id = SITE_ID)
	{
		global $USER, $CACHE_MANAGER;

		self::$SITE_ID = $site_id;
		self::$USER_ID = $USER->GetID();

		$lastTaskId = 0;

		$arTasks = self::getCurrentTasksList();

		if (!is_array($arTasks))
			$arTasks = array();

		if (strlen($arActions['name']) > 0)
		{
			$obt = new CTasks();
			$ID = $obt->Add(array(
				'RESPONSIBLE_ID' => self::$USER_ID,
				'TITLE' => $arActions['name'],
				'TAGS' => array(),
				'STATUS' => 2,
				'SITE_ID' => self::$SITE_ID
			));

			if ($ID)
			{
				if (!is_array($arActions['add']))
				{
					$arActions['add'] = array($ID);
				}
				else
				{
					$arActions['add'][] = $ID;
				}
			}
		}

		if (is_array($arActions['add']))
		{
			$task_id = $lastTaskId;

			foreach ($arActions['add'] as $task_id)
			{
				$arTasks[] = intval($task_id);
			}

			$lastTaskId = $task_id;
		}

		$arTasks = array_unique($arTasks);

		if (is_array($arActions['remove']))
		{
			$arActions['remove'] = array_unique($arActions['remove']);

			foreach ($arActions['remove'] as $task_id)
			{
				$task_id = intval($task_id);

				if (($key = array_search($task_id, $arTasks)) !== false)
				{
					unset($arTasks[$key]);
				}
			}
		}

		$CACHE_MANAGER->ClearByTag('tasks_user_'.self::$USER_ID);

		self::setCurrentTasksList($arTasks);

		return $lastTaskId;
	}

	private static function getTasks($arIDs = array(), $bOpened = false)
	{
		$res = null;

		if  (!is_array($arIDs) && strlen($arIDs) > 0)
			$arIDs = unserialize($arIDs);

		$arIDs = array_values($arIDs);

		$USER_ID = self::$USER_ID;

		$res = array();
		if (count($arIDs) > 0)
		{
			$arFilter = array('ID' => $arIDs);

			if ($bOpened)
			{
				$arFilter['!STATUS'] = self::$arTaskStatusOpened;
			}

			$dbRes = CTasks::GetList(
				array(),
				$arFilter,
				array(
					'ID', 'RESPONSIBLE_ID', 'PRIORITY', 'STATUS', 'TITLE',
					'TASK_CONTROL', 'TIME_SPENT_IN_LOGS', 'TIME_ESTIMATE',
					'ALLOW_TIME_TRACKING'
				)
			);
			while ($arRes = $dbRes->Fetch())
			{
				$arRes['ACCOMPLICES'] = $arRes['AUDITORS'] = array();
				$rsMembers = CTaskMembers::GetList(
					array(),
					array('TASK_ID' => $arRes['ID'])
				);

				while ($arMember = $rsMembers->Fetch())
				{
					if ($arMember['TYPE'] == 'A')
						$arRes['ACCOMPLICES'][] = $arMember['USER_ID'];
					elseif ($arMember['TYPE'] == 'U')
						$arRes['AUDITORS'][] = $arMember['USER_ID'];
				}

				// Permit only for responsible user, accomplices or auditors
				$isPermited = ( ($arRes['RESPONSIBLE_ID'] == $USER_ID)
					|| in_array($USER_ID, $arRes['ACCOMPLICES'])
					|| in_array($USER_ID, $arRes['AUDITORS'])
				);

				if (!$isPermited)
					continue;

				$res[] = array(
					'ID'                  => $arRes['ID'],
					'PRIORITY'            => $arRes['PRIORITY'],
					'STATUS'              => $arRes['STATUS'],
					'TITLE'               => $arRes['TITLE'],
					'TASK_CONTROL'        => $arRes['TASK_CONTROL'],
					'ALLOW_TIME_TRACKING' => $arRes['ALLOW_TIME_TRACKING'],
					'TIME_SPENT_IN_LOGS'  => $arRes['TIME_SPENT_IN_LOGS'],
					'TIME_ESTIMATE'       => $arRes['TIME_ESTIMATE'],
					'URL'                 => str_replace(
						array('#USER_ID#', '#TASK_ID#'),
						array($USER_ID, $arRes['ID']),
						COption::GetOptionString('intranet', 'path_task_user_entry', '/company/personal/user/#USER_ID#/tasks/task/view/#TASK_ID#/')
					)
				);
			}
		}

		return $res;
	}

	private static function getTasksCount($arTasks)
	{
		$cnt = 0;
		if (is_array($arTasks) && count($arTasks) > 0)
		{
			$dbRes = CTasks::GetCount(array(
				'ID' => $arTasks,
				'RESPONSIBLE_ID' => self::$USER_ID,
				'!STATUS' => self::$arTaskStatusOpened
			));
			if ($arRes = $dbRes->Fetch())
			{
				$cnt = $arRes['CNT'];
			}
		}

		return $cnt;
	}

	public static function getCurrentTasksList()
	{
		$list = CUserOptions::GetOption('tasks', self::PLANNER_OPTION_CURRENT_TASKS, null);
		// current user hasn't already used tasks list or has list in timeman
		if($list === null)
		{
			if(CModule::IncludeModule('timeman'))
			{
				$TMUSER = CTimeManUser::instance();
				$arInfo = $TMUSER->GetCurrentInfo();
				if(is_array($arInfo['TASKS']))
				{
					$list = $arInfo['TASKS'];
				}
			}
			else
			{
				$list = array();
			}

			if ($list !== null)
				self::setCurrentTasksList($list);
		}

		if(!is_array($list))
		{
			$list = array();
		}

		return $list;
	}


	private static function setCurrentTasksList($list)
	{
		CUserOptions::SetOption('tasks', self::PLANNER_OPTION_CURRENT_TASKS, $list);
	}

	public static function OnAfterTMDayStart()
	{
		global $CACHE_MANAGER, $USER;

		$list = self::getCurrentTasksList();
		if(count($list) > 0)
		{
			$arFilter = array(
				'ID' => $list,
				'!STATUS' => self::$arTaskStatusOpened,
			);

			$newList = array();
			$dbRes = CTasks::GetList(array(), $arFilter, array('ID'));
			while($arRes = $dbRes->Fetch())
			{
				$newList[] = $arRes['ID'];
			}

			self::setCurrentTasksList($newList);
			$CACHE_MANAGER->ClearByTag('tasks_user_'.$USER->GetID());
		}
	}

	public static function runRestMethod($executiveUserId, $methodName, $args, $navigation)
	{
		CTaskAssert::assert($methodName === 'getcurrenttaskslist');

		// Check and parse params
		$argsParsed = CTaskRestService::_parseRestParams('ctaskplannermaintance', $methodName, $args);

		$arTasksIds = call_user_func_array(array('self', 'getcurrenttaskslist'), $argsParsed);

		return (array($arTasksIds, null));
	}


	public static function getManifest()
	{
		return(array(
			'Manifest version' => '1',
			'Warning' => 'don\'t rely on format of this manifest, it can be changed without any notification',
			'REST: shortname alias to class'  => 'planner',
			'REST: available methods' => array(
				'getcurrenttaskslist' => array(
					'alias'                => 'getlist',
					'mandatoryParamsCount' =>  0,
					'params'               =>  array()
				)
			)
		));
	}
}
