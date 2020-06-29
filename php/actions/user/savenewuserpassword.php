<?php

//Edits an existing user's password, user is identified by the username.
function EditUserPassword($username, $newPassword1, $newPassword2){
	global $userData, $dbConn, $configData, $loggedInUser, $adminLogData;

	//Authorize user (is admin)
	if(IsAdmin($loggedInUser) === false){
		return "NOT_AUTHORIZED";
	}

	$newPassword1 = trim($newPassword1);
	$newPassword2 = trim($newPassword2);
	if($newPassword1 != $newPassword2){
		return "PASSWORDS_DONT_MATCH";
	}
	$password = $newPassword1;

	if(!ValidatePassword($password, $configData)){
		return "INVALID_PASSWORD_LENGTH";
	}

	//Check that the user exists
	if(!isset($userData->UserModels[$username])){
		return "USER_DOES_NOT_EXIST";
	}

	//Generate new salt, number of iterations and hashed password.
	$newUserSalt = GenerateSalt();
	$newUserPasswordIterations = GenerateUserHashIterations($configData);
	$newPasswordHash = HashPassword($password, $newUserSalt, $newUserPasswordIterations, $configData);

	$loggedInUserUsername = $loggedInUser->Username;
	$userData->UserModels[$loggedInUserUsername]->Salt = $newUserSalt;
	$userData->UserModels[$loggedInUserUsername]->PasswordHash = $newPasswordHash;
	$userData->UserModels[$loggedInUserUsername]->PasswordIterations = $newUserPasswordIterations;

	$newUserSaltClean = mysqli_real_escape_string($dbConn, $newUserSalt);
	$newPasswordHashClean = mysqli_real_escape_string($dbConn, $newPasswordHash);
	$newUserPasswordIterationsClean = mysqli_real_escape_string($dbConn, $newUserPasswordIterations);
	$usernameClean = mysqli_real_escape_string($dbConn, $username);

	$sql = "
		UPDATE user
		SET
		user_password_salt = '$newUserSaltClean',
		user_password_iterations = '$newUserPasswordIterationsClean',
		user_password_hash = '$newPasswordHashClean'
		WHERE user_username = '$usernameClean';
	";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";

    $adminLogData->AddToAdminLog("USER_PASSWORD_RESET", "Password reset for user $username", $userData->UserModels[$username]->Id, $loggedInUser->Id, "");

	return "SUCCESS";
}

function PerformAction(&$loggedInUser){
	global $_POST;
	
	if(IsAdmin($loggedInUser) !== false){
		$username = $_POST["username"];
		$password1 = $_POST["password1"];
		$password2 = $_POST["password2"];

		return EditUserPassword($username, $password1, $password2);
	}
}

?>