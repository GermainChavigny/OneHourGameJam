<?php

function SaveConfig($key, $newValue){
	global $configData, $dictionary, $loggedInUser, $adminLogData;

	if(IsAdmin($loggedInUser) === false){
		return "NOT_AUTHORIZED";
	}

	if (!isset($configData->ConfigModels[$key])) {
		//Invalid configuration key
		return;
	}

	if ($configData->ConfigModels[$key]->Editable != true) {
		//Some configuration settings cannot be set via this interface for security reasons.
		return;
	}

	if ($newValue == $configData->ConfigModels[$key]->Value) {
		return;
	}

	$configData->UpdateConfig($key, $newValue, $loggedInUser->Id, "", $adminLogData);
	return "SUCCESS";
}

function PerformAction(&$loggedInUser){
	global $_POST;
	
	if(IsAdmin($loggedInUser) !== false){
		$overallActionResult = "NO_CHANGE";
		foreach($_POST as $key => $value){
			$actionResult = SaveConfig($key, $value);
			if($actionResult != ""){
				$overallActionResult = $actionResult;
			}
		}
		return $overallActionResult;
	}
}

?>