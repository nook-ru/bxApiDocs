<?
/**
 * Class implements all further interactions with "socialnetwork" module considering "task item" entity
 *
 * This class is for internal use only, not a part of public API.
 * It can be changed at any time without notification.
 * 
 * @access private
 */

namespace Bitrix\Tasks\Integration\Socialnetwork;

use Bitrix\Main\Loader;

final class Task
{
	/**
	 * See CSocNetLogFavorites::Add() and CSocNetLogFavorites::Change()
	 */
	public static function OnSonetLogFavorites(array $params)
	{
		$params['USER_ID'] = intval($params['USER_ID']);
		$params['LOG_ID'] = intval($params['LOG_ID']);

		if($params['USER_ID'] && $params['LOG_ID'] && static::includeSocialNetwork())
		{
			$res = \CSocNetLog::GetById($params['LOG_ID']);
			if(!empty($res))
			{
				$taskId = intval($res['SOURCE_ID']);
				try
				{
					$task = new \CTaskItem($taskId, $params['USER_ID']); // ensure task exists

					if($params['OPERATION'] == 'ADD')
					{
						$task->addToFavorite(array('TELL_SOCNET' => false));
					}
					else
					{
						$task->deleteFromFavorite(array('TELL_SOCNET' => false));
					}
				}
				catch(\TasksException $e)
				{
					return;
				}
			}
		}
	}

	public static function toggleFavorites(array $params)
	{
		$params['TASK_ID'] = intval($params['TASK_ID']);
		$params['USER_ID'] = intval($params['USER_ID']);

		if($params['TASK_ID'] && $params['USER_ID'] && static::includeSocialNetwork())
		{
			// get all soc net log records considering this task and user
			$res = \CSocNetLog::GetList(array(), array('SOURCE_ID' => $params['TASK_ID'], 'USER_ID' => $params['USER_ID']));
			while($item = $res->fetch())
			{
				// add them to favorite
				if($params['OPERATION'] == 'ADD')
				{
					\CSocNetLogFavorites::Add($item['USER_ID'], $item['ID'], array('TRIGGER_EVENT' => false));
				}
				else
				{
					\CSocNetLogFavorites::Change($item['USER_ID'], $item['ID'], array('TRIGGER_EVENT' => false));
				}
			}
		}
	}

	protected static function includeSocialNetwork()
	{
		return Loader::includeModule('socialnetwork');
	}
}