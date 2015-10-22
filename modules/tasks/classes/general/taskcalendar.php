<?php

IncludeModuleLangFile(__FILE__);

class CTaskCalendar
{

	public static $PriorityMapping = array(
		"low" => 0,
		"normal" => 1,
		"high" => 2
	);

	const TASKS_CALENDAR_ID = "tasks_calendar";

	function OnAfterCalendarEventAdd(&$arFields)
	{
		global $USER;

		$obTask = new CTasks();
		if (intval($arFields["PROPERTY_TASK"]) == 0) // task added from calendar
		{
			$calendarID = CUserOptions::GetOption('tasks', self::TASKS_CALENDAR_ID, false, $USER->GetID());
			if ($calendarID && $calendarID == $arFields["IBLOCK_SECTION"] && $arFields["ID"] > 0)
			{

				$arTaskFields = array(
					"CREATED_BY" => $arFields["CREATED_BY"],
					"RESPONSIBLE_ID" => $arFields["CREATED_BY"],
					"TITLE" => $arFields["NAME"],
					"DESCRIPTION" => $arFields["DETAIL_TEXT"],
					"START_DATE_PLAN" => $arFields["ACTIVE_FROM"],
					"END_DATE_PLAN" => $arFields["ACTIVE_TO"],
					"PRIORITY" => self::$PriorityMapping[$arFields["PROPERTY_IMPORTANCE"]],
					"STATUS" => 2,
					"FROM_CALENDAR" => "Y"
				);

				$taskID = $obTask->Add($arTaskFields);

				if ($taskID)
				{
					CIBlockElement::SetPropertyValues($arFields["ID"], $arFields["IBLOCK_ID"], $taskID, "TASK");
				}
			}
		}
		elseif (intval($arFields["PROPERTY_PARENT"]) > 0)
		{
			$rsTask = CTasks::GetByID(intval($arFields["PROPERTY_TASK"]));
			if ($arTask = $rsTask->Fetch())
			{
				if ($arTask["CREATED_BY"] == $arTask["RESPONSIBLE_ID"]) // first event guest
				{
					$arTaskFields = array(
						"RESPONSIBLE_ID" => $arFields["CREATED_BY"]
					);
				}
				elseif ($arFields["CREATED_BY"] != $arTask["RESPONSIBLE_ID"])
				{
					$arAccomplices = array();
					$rsAccomplices = CTaskMembers::GetList(array(), array("TASK_ID" => $arTask["ID"], "TYPE" => "A"));
					while ($accomplice = $rsAccomplices->Fetch())
					{
						$arAccomplices[] = $accomplice["USER_ID"];
					}
					if (!in_array($arFields["CREATED_BY"], $arAccomplices))
					{
						$arAccomplices[] = $arFields["CREATED_BY"];
						$arTaskFields = array(
							"ACCOMPLICES" => array_unique(array_filter($arAccomplices))
						);
					}
				}

				if ($arTaskFields)
				{
					AddMessage2Log(print_r($arTaskFields, true));
					$arTaskFields["FROM_CALENDAR"] = "Y";
					$obTask->Update($arTask["ID"], $arTaskFields);
				}

				CIBlockElement::SetPropertyValues($arFields["ID"], $arFields["IBLOCK_ID"], $arTask["ID"], "TASK");
			}
		}
	}

	function OnAfterCalendarEventUpdate(&$arFields)
	{
		global $USER;
		$calendarID = CUserOptions::GetOption('tasks', self::TASKS_CALENDAR_ID, false, $USER->GetID());
		if ($calendarID && $calendarID == $arFields["IBLOCK_SECTION"] && $arFields["RESULT"])
		{
			if (intval($arFields["PROPERTY_TASK"]) > 0)
			{
				$rsTask = CTasks::GetByID(intval($arFields["PROPERTY_TASK"]));
				if ($arTask = $rsTask->Fetch())
				{
					if ($arTask["CALENDAR_VERSION"] != $arFields["PROPERTY_VERSION"])
					{
						$arTaskFields = array(
							"TITLE" => $arFields["NAME"],
							"DESCRIPTION" => $arFields["DETAIL_TEXT"],
							"START_DATE_PLAN" => $arFields["ACTIVE_FROM"],
							"END_DATE_PLAN" => $arFields["ACTIVE_TO"],
							"PRIORITY" => self::$PriorityMapping[$arFields["PROPERTY_IMPORTANCE"]],
							"CALENDAR_VERSION" => $arFields["PROPERTY_VERSION"],
							"FROM_CALENDAR" => "Y"
						);
						$obTask = new CTasks();
						$obTask->Update($arTask["ID"], $arTaskFields);
					}
				}
			}
		}
	}

	function OnAfterCalendarEventDelete($ID)
	{
		global $USER;

		$obTask = new CTasks();
		$rsIBlock = CIBlockElement::GetByID($ID);
		if ($arIBlock = $rsIBlock->Fetch())
		{
			$rsTaskProperty = CIBlockElement::GetProperty($arIBlock["IBLOCK_ID"], $arIBlock["ID"], array("sort" => "asc"), array("CODE" => "TASK"));
			if (($arTaskProperty = $rsTaskProperty->Fetch()) && intval($arTaskProperty["VALUE"]) > 0)
			{
				$rsParentProperty = CIBlockElement::GetProperty($arIBlock["IBLOCK_ID"], $arIBlock["ID"], array("sort" => "asc"), array("CODE" => "PARENT"));
				if (($arParentProperty = $rsParentProperty->Fetch()) && intval($arParentProperty["VALUE"]) > 0)
				{
					$rsTask = CTasks::GetByID(intval($arTaskProperty["VALUE"]));
					if ($arTask = $rsTask->Fetch())
					{
						$arAccomplices = array();
						$rsAccomplices = CTaskMembers::GetList(array(), array("TASK_ID" => $arTask["ID"], "TYPE" => "A"));
						while ($accomplice = $rsAccomplices->Fetch())
						{
							$arAccomplices[] = $accomplice["USER_ID"];
						}

						$key = array_search($arIBlock["CREATED_BY"], $arAccomplices);
						if ($key !== false)
						{
							unset($arAccomplices[$key]);
							$arTaskFields = array(
								"ACCOMPLICES" => $arAccomplices
							);
						}
						elseif ($arTask["RESPONSIBLE_ID"] == $arIBlock["CREATED_BY"])
						{
							$arTaskFields = array(
								"RESPONSIBLE_ID" => $arTask["CREATED_BY"]
							);
						}
						if ($arTaskFields)
						{
							$arTaskFields["FROM_CALENDAR"] = "Y";
							$obTask->Update($arTask["ID"], $arTaskFields);
						}
					}
				}
				else
				{
					$obTask->Delete($arTaskProperty["VALUE"]);
				}
			}
		}
	}

	function InitUserCalendar($userID)
	{
		$userID = intval($userID);

		$calIblock = COption::GetOptionInt('intranet', 'iblock_calendar', 0);
		$calIblockSection = CEventCalendar::GetSectionIDByOwnerId($userID, 'USER', $calIblock);

		$obCalendar = new CEventCalendar();
		$obCalendar->Init(array(
			"ownerType" => "USER",
			"ownerId" => $userID,
			"iblockId" => $calIblock,
			"bCache" => false
		));

		$obCalendar->sectionId = $calIblockSection;
		$obCalendar->userIblockId = $calIblock;

		return $obCalendar;
	}

	function GetCalendarID(&$obCalendar)
	{
		$calendarID = CUserOptions::GetOption('tasks', self::TASKS_CALENDAR_ID, false, $obCalendar->ownerId);

		if (!$calendarID || !$obCalendar->CheckCalendar(array('calendarId' => $calendarID)))
		{
			$arParams = array(
				"arFields" => array(
					"NAME" => GetMessage("TASKS_CALENDAR_NAME"),
					"DESCRIPTION" => GetMessage("TASKS_CALENDAR_DESCRIPTION"),
					"PRIVATE_STATUS" => "private",
					"COLOR" => "#C6DAFF"
				)
			);

			$calendarID = $obCalendar->SaveCalendar($arParams);
			if ($calendarID)
			{
				CUserOptions::SetOption('tasks', self::TASKS_CALENDAR_ID, $calendarID, false, $obCalendar->ownerId);
			}
		}

		return $calendarID;
	}

	function GetEventParams(&$obCalendar, $calendarID, $arFields, $eventID = null)
	{
		$priorityMapping = array_flip(self::$PriorityMapping);

		if ($arFields["GROUP_ID"] > 0)
		{
			$pathToTask = str_replace("#GROUP_ID#", $arFields["GROUP_ID"], COption::GetOptionString("tasks", "paths_task_group_entry", "/workgroups/group/#GROUP_ID#/tasks/task/view/#TASK_ID#/", $arTask["SITE_ID"]));
		}
		else
		{
			$pathToTask = str_replace("#USER_ID#", $arFields["RESPONSIBLE_ID"], COption::GetOptionString("tasks", "paths_task_user_entry", "/company/personal/user/#USER_ID#/tasks/task/view/#TASK_ID#/", $arTask["SITE_ID"]));
		}
		$pathToTask = str_replace("#TASK_ID#", $arFields["ID"], $pathToTask);
		$arEventParams = array(
			"iblockId" => $obCalendar->iblockId,
			"ownerType" => $obCalendar->ownerType,
			"ownerId" => $obCalendar->ownerId,
			"fullUrl" => $obCalendar->fullUrl,
			"userId" => $obCalendar->ownerId,
			"pathToUserCalendar" => $obCalendar->pathToUserCalendar,
			"pathToGroupCalendar" => $obCalendar->pathToGroupCalendar,
			"userIblockId" => $obCalendar->userIblockId,
			"calendarId" => $calendarID,
			"sectionId" => $obCalendar->sectionId,
			"dateFrom" => $arFields["START_DATE_PLAN"],
			"dateTo" => $arFields["END_DATE_PLAN"] ? $arFields["END_DATE_PLAN"] : $arFields["START_DATE_PLAN"],
			"name" => $arFields["TITLE"],
			"desc" => $arFields["DESCRIPTION"],
			"prop" => array(
				"ACCESSIBILITY" => "free",
				"IMPORTANCE" => $priorityMapping[$arFields["PRIORITY"]],
				"TASK" => $arFields["ID"],
				"VERSION" => $arFields["CALENDAR_VERSION"] ? $arFields["CALENDAR_VERSION"] : 1
			),
			"notDisplayCalendar" => true
		);

		if ($eventID)
		{
			$arEventParams["id"] = $eventID;
		}
		else
		{
			$arEventParams["bNew"] = true;
		}

		return $arEventParams;
	}

	function GetExecutors(&$arFields)
	{
		$arExecutors = $arFields["ACCOMPLICES"];
		$arExecutors[] = $arFields["RESPONSIBLE_ID"];

		$arExecutors = array_unique(array_filter($arExecutors));
		$currentUserPos = array_search($arFields["CREATED_BY"], $arExecutors);
		if ($currentUserPos !== false)
		{
			unset($arExecutors[$currentUserPos]);
		}

		return $arExecutors;
	}

	function DeleteTaskEvent($ID, &$obCalendar)
	{
		$res = CECEvent::Delete(array(
			'id' => intval($ID),
			'iblockId' => $obCalendar->iblockId,
			'ownerType' => $obCalendar->ownerType,
			'ownerId' => $obCalendar->ownerId,
			'userId' => $obCalendar->userId,
			'pathToUserCalendar' => $obCalendar->pathToUserCalendar,
			'pathToGroupCalendar' => $obCalendar->pathToGroupCalendar,
			'userIblockId' => $obCalendar->userIblockId
		));

		if ($res)
		{
			$obCalendar->ClearCache($obCalendar->cachePath.'events/'.$obCalendar->iblockId.'/');
		}
	}

}