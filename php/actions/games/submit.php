<?php

//Creates or updates a jam entry. $jam_number is a mandatory jam number to submit to.
//All other parameters are strings: $gameName and $gameURL must be non-blank
//$gameURL must be a valid URL, $screenshotURL can either be blank or a valid URL.
//If blank, a default image is used instead. description must be non-blank.
//Function also authorizes the user (must be logged in)
function SubmitEntry($jam_number, $gameName, $gameURL, $gameURLWeb, $gameURLWin, $gameURLMac, $gameURLLinux, $gameURLiOS, $gameURLAndroid, $gameURLSource, $screenshotURL, $description, $jamColorNumber){
	global $loggedInUser, $_FILES, $dbConn, $ip, $userAgent, $jamData, $gameData, $configData;

	$gameName = trim($gameName);
	$gameURL = trim($gameURL);
	$gameURLWeb = trim($gameURLWeb);
	$gameURLWin = trim($gameURLWin);
	$gameURLMac = trim($gameURLMac);
	$gameURLLinux = trim($gameURLLinux);
	$gameURLiOS = trim($gameURLiOS);
	$gameURLAndroid = trim($gameURLAndroid);
	$gameURLSource = trim($gameURLSource);
	$screenshotURL = trim($screenshotURL);
	$description = trim($description);
	$jamColorNumber = intval(trim($jamColorNumber));

	//Authorize user
	if($loggedInUser === false){
		return "NOT_LOGGED_IN";
	}

	//Validate game name
	if(strlen($gameName) < 1){
		return "MISSING_GAME_NAME";
	}

	$urlValid = FALSE;
	//Validate that at least one of the provided game URLs is valid
	$gameURL = SanitizeURL($gameURL);
	$gameURLWeb = SanitizeURL($gameURLWeb);
	$gameURLWin = SanitizeURL($gameURLWin);
	$gameURLMac = SanitizeURL($gameURLMac);
	$gameURLLinux = SanitizeURL($gameURLLinux);
	$gameURLiOS = SanitizeURL($gameURLiOS);
	$gameURLAndroid = SanitizeURL($gameURLAndroid);
	$gameURLSource = SanitizeURL($gameURLSource);

	if($gameURL || $gameURLWeb || $gameURLWin || $gameURLMac || $gameURLLinux || $gameURLiOS || $gameURLAndroid){
		$urlValid = TRUE;
	}

	//Did at least one url pass validation?
	if($urlValid == FALSE){
		return "INVALID_GAME_URL";
	}

	//Validate description
	if(strlen($description) <= 0){
		return "INVALID_DESCRIPTION";
	}

	//Check that a jam exists
	if (!is_int($jam_number)) {
		return "INVALID_JAM_NUMBER";
	}

	$jam = GetJamByNumber($jamData, $jam_number);
	if($jam == null || $jam->JamNumber == 0){
		return "NO_JAM_TO_SUBMIT_TO";
	}

	if(count($jamData->JamModels) == 0){
		return "NO_JAM_TO_SUBMIT_TO";
	}

	//Validate color
	if($jamColorNumber < 0 || count($jam->Colors) <= $jamColorNumber){
		return "INVALID_COLOR";
	}
	$color = $jam->Colors[$jamColorNumber];

	//Upload screenshot
	$jam_folder = "data/jams/jam_$jam_number";
	if(isset($_FILES["screenshotfile"]) && $_FILES["screenshotfile"] != null && $_FILES["screenshotfile"]["size"] != 0){
		$imageFileType = strtolower(pathinfo($_FILES["screenshotfile"]["name"], PATHINFO_EXTENSION));
		$target_file = $jam_folder . "/".$loggedInUser->Username."." . $imageFileType;
		$is_image = getimagesize($_FILES["screenshotfile"]["tmp_name"]) !== false;

		if(!$is_image) {
			return "SCREENSHOT_NOT_AN_IMAGE";
		}

		if($_FILES["screenshotfile"]["size"] > $configData->ConfigModels["MAX_SCREENSHOT_FILE_SIZE_IN_BYTES"]->Value) {
			return "SCREENSHOT_TOO_BIG";
		}

		if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
		&& $imageFileType != "gif" ) {
			return "SCREENSHOT_WRONG_FILE_TYPE";
		}

		if(!file_exists($jam_folder)){
			mkdir($jam_folder);
			file_put_contents($jam_folder."/.htaccess", "Order allow,deny\nAllow from all");
		}
		move_uploaded_file($_FILES["screenshotfile"]["tmp_name"], $target_file);
		$screenshotURL = $target_file;
	}

	//Default screenshot URL
	if($screenshotURL == ""){
		$screenshotURL = "logo.png";
	}

	//Create or update entry
	foreach($gameData->GameModels as $i => $gameModel){
		if($gameModel->Deleted){
			continue;
		}

		if($gameModel->JamNumber != $jam_number){
			continue;
		}

		if($gameModel->AuthorUserId != $loggedInUser->Id){
			continue;
		}

		//Updating existing entry
		$existingScreenshot = $gameModel->UrlScreenshot;
		if($screenshotURL == "logo.png"){
			if($existingScreenshot != "" && $existingScreenshot != "logo.png"){
				$screenshotURL = $existingScreenshot;
			}
		}

		$escapedGameName = mysqli_real_escape_string($dbConn, $gameName);
		$escapedGameURL = mysqli_real_escape_string($dbConn, $gameURL);
		$escapedGameURLWeb = mysqli_real_escape_string($dbConn, $gameURLWeb);
		$escapedGameURLWin = mysqli_real_escape_string($dbConn, $gameURLWin);
		$escapedGameURLMac = mysqli_real_escape_string($dbConn, $gameURLMac);
		$escapedGameURLLinux = mysqli_real_escape_string($dbConn, $gameURLLinux);
		$escapedGameURLiOS = mysqli_real_escape_string($dbConn, $gameURLiOS);
		$escapedGameURLAndroid = mysqli_real_escape_string($dbConn, $gameURLAndroid);
		$escapedGameURLSource = mysqli_real_escape_string($dbConn, $gameURLSource);
		$escapedScreenshotURL = mysqli_real_escape_string($dbConn, $screenshotURL);
		$escapedDescription = mysqli_real_escape_string($dbConn, $description);
		$escapedAuthorUserId = mysqli_real_escape_string($dbConn, $gameModel->AuthorUserId);
		$escaped_jamNumber = mysqli_real_escape_string($dbConn, $jam_number);
		$escaped_color = mysqli_real_escape_string($dbConn, $color);

		$sql = "
		UPDATE entry
		SET
			entry_title = '$escapedGameName',
			entry_url = '$escapedGameURL',
			entry_url_web = '$escapedGameURLWeb',
			entry_url_windows = '$escapedGameURLWin',
			entry_url_mac = '$escapedGameURLMac',
			entry_url_linux = '$escapedGameURLLinux',
			entry_url_ios = '$escapedGameURLiOS',
			entry_url_android = '$escapedGameURLAndroid',
			entry_url_source = '$escapedGameURLSource',
			entry_screenshot_url = '$escapedScreenshotURL',
			entry_description = '$escapedDescription',
			entry_color = '$escaped_color'
		WHERE
			entry_author_user_id = $escapedAuthorUserId
		AND entry_jam_number = $escaped_jamNumber
		AND entry_deleted = 0;

		";
		$data = mysqli_query($dbConn, $sql);
		$sql = "";

		return "SUCCESS_ENTRY_UPDATED";
	}

	$currentJamData = GetCurrentJamNumberAndID();

	if ($jam_number != $currentJamData["NUMBER"]) {
		return "CANNOT_SUBMIT_TO_PAST_JAM";
	}

	$escaped_ip = mysqli_real_escape_string($dbConn, $ip);
	$escaped_userAgent = mysqli_real_escape_string($dbConn, $userAgent);
	$escaped_jamId = mysqli_real_escape_string($dbConn, $jam->Id);
	$escaped_jamNumber = mysqli_real_escape_string($dbConn, $jam->JamNumber);
	$escaped_gameName = mysqli_real_escape_string($dbConn, $gameName);
	$escaped_description = mysqli_real_escape_string($dbConn, $description);
	$escaped_author_user_id = mysqli_real_escape_string($dbConn, $loggedInUser->Id);
	$escaped_gameURL = mysqli_real_escape_string($dbConn, $gameURL);
	$escaped_gameURLWeb = mysqli_real_escape_string($dbConn, $gameURLWeb);
	$escaped_gameURLWin = mysqli_real_escape_string($dbConn, $gameURLWin);
	$escaped_gameURLMac = mysqli_real_escape_string($dbConn, $gameURLMac);
	$escaped_gameURLLinux = mysqli_real_escape_string($dbConn, $gameURLLinux);
	$escaped_gameURLiOS = mysqli_real_escape_string($dbConn, $gameURLiOS);
	$escaped_gameURLAndroid = mysqli_real_escape_string($dbConn, $gameURLAndroid);
	$escaped_gameURLSource = mysqli_real_escape_string($dbConn, $gameURLSource);
	$escaped_ssURL = mysqli_real_escape_string($dbConn, $screenshotURL);
	$escaped_color = mysqli_real_escape_string($dbConn, $color);

	$sql = "
		INSERT INTO entry
		(entry_id,
		entry_datetime,
		entry_ip,
		entry_user_agent,
		entry_jam_id,
		entry_jam_number,
		entry_title,
		entry_description,
		entry_author_user_id,
		entry_url,
		entry_url_web,
		entry_url_windows,
		entry_url_mac,
		entry_url_linux,
		entry_url_ios,
		entry_url_android,
		entry_url_source,
		entry_screenshot_url,
		entry_color)
		VALUES
		(null,
		Now(),
		'$escaped_ip',
		'$escaped_userAgent',
		$escaped_jamId,
		$escaped_jamNumber,
		'$escaped_gameName',
		'$escaped_description',
		$escaped_author_user_id,
		'$escaped_gameURL',
		'$escaped_gameURLWeb',
		'$escaped_gameURLWin',
		'$escaped_gameURLMac',
		'$escaped_gameURLLinux',
		'$escaped_gameURLiOS',
		'$escaped_gameURLAndroid',
		'$escaped_gameURLSource',
		'$escaped_ssURL',
		'$escaped_color');
	";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";

	return "SUCCESS_ENTRY_ADDED";
}

function PerformAction(&$loggedInUser){
	global $_POST, $satisfactionData;
	
	if($loggedInUser !== false){
		$gameName = (isset($_POST["gamename"])) ? $_POST["gamename"] : "";
		$gameURL = (isset($_POST["gameurl"])) ? $_POST["gameurl"] : "";
		$gameURLWeb = (isset($_POST["gameurlweb"])) ? $_POST["gameurlweb"] : "";
		$gameURLWin = (isset($_POST["gameurlwin"])) ? $_POST["gameurlwin"] : "";
		$gameURLMac = (isset($_POST["gameurlmac"])) ? $_POST["gameurlmac"] : "";
		$gameURLLinux = (isset($_POST["gameurllinux"])) ? $_POST["gameurllinux"] : "";
		$gameURLiOS = (isset($_POST["gameurlios"])) ? $_POST["gameurlios"] : "";
		$gameURLAndroid = (isset($_POST["gameurlandroid"])) ? $_POST["gameurlandroid"] : "";
		$gameURLSource = (isset($_POST["gameurlsource"])) ? $_POST["gameurlsource"] : "";
		$screenshotURL = (isset($_POST["screenshoturl"])) ? $_POST["screenshoturl"] : "";
		$description = (isset($_POST["description"])) ? $_POST["description"] : "";
		$jamNumber = (isset($_POST["jam_number"])) ? intval($_POST["jam_number"]) : -1;
		$jamColorNumber = (isset($_POST["colorNumber"])) ? intval($_POST["colorNumber"]) : 0;

		$satisfaction = (isset($_POST["satisfaction"])) ? intval($_POST["satisfaction"]) : 0;
		if($satisfaction != 0){
			$satisfactionData->SubmitSatisfaction($loggedInUser, "JAM_$jamNumber", $satisfaction);
		}

		return SubmitEntry($jamNumber, $gameName, $gameURL, $gameURLWeb, $gameURLWin, $gameURLMac, $gameURLLinux, $gameURLiOS, $gameURLAndroid, $gameURLSource, $screenshotURL, $description, $jamColorNumber);
	}
}

?>
