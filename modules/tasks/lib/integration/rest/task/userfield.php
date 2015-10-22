<?
/**
 * Class implements all further interactions with "rest" module considering userfields for "task item" entity.
 * 
 * This class is for internal use only, not a part of public API.
 * It can be changed at any time without notification.
 * 
 * @access private
 */

namespace Bitrix\Tasks\Integration\Rest\Task;

final class UserField extends \Bitrix\Tasks\Integration\Rest\UserField
{
	public static function getTargetEntityId()
	{
		return 'TASKS_TASK';
	}
}