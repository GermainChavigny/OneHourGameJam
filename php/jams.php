<?php

function GetNextJamDateAndTime(&$jamData){
	AddActionLog("GetNextJamDateAndTime");
	StartTimer("GetNextJamDateAndTime");

	$nextJamStartTime = null;

	$now = time();
	foreach($jamData->JamModels as $i => $jamModel){
		$nextJamTime = strtotime($jamModel->StartTime . " UTC");

		if($nextJamTime > $now){
			$nextJamStartTime = $nextJamTime;
		}
	}

	StopTimer("GetNextJamDateAndTime");
	return $nextJamStartTime;
}

function ProcessJamStates(&$jamData, &$themeData, &$configData, &$adminLogData){
	AddActionLog("ProcessJamStates");
	StartTimer("ProcessJamStates");

	foreach($jamData->JamModels as $i => $jamModel){
		if($jamModel->Deleted == 1){
			if($jamModel->State != "DELETED"){
				$jamData->UpdateJamStateInDatabase($jamModel->Id, "DELETED");
			}
			continue;
		}
		
		//Hide theme of not-yet-started jams
		$now = new DateTime("UTC");
		$jamStartTime = new DateTime($jamModel->StartTime . " UTC");
		$jamDurationInMinutes = intval($configData->ConfigModels["JAM_DURATION"]->Value);
		$jamEndTime = clone $jamStartTime;
		$jamEndTime->add(new DateInterval("PT".$jamDurationInMinutes."M"));

		if($now > $jamEndTime){
			//Past Jam (jam's over)
			if($jamModel->State != "COMPLETED"){
				$jamData->UpdateJamStateInDatabase($jamModel->Id, "COMPLETED");
			}
		}else if($now > $jamStartTime){
			//Present Jam (started, hasn't finished yet)
			if($jamModel->State != "ACTIVE"){
				$jamData->UpdateJamStateInDatabase($jamModel->Id, "ACTIVE");
				PruneThemes($themeData, $jamData, $configData, $adminLogData);
			}
		}else{
			//Future Jam (not yet started)
			if($jamModel->State != "SCHEDULED"){
				$jamData->UpdateJamStateInDatabase($jamModel->Id, "SCHEDULED");
			}
		}
	}
	
	StopTimer("ProcessJamStates");
}

function ParseJamColors($colorString){
	AddActionLog("ParseJamColors");
	StartTimer("ParseJamColors");

	$jamColors = explode("|", $colorString);
	if(count($jamColors) == 0){
		StopTimer("ParseJamColors");
		return Array("FFFFFF");
	}

	StopTimer("ParseJamColors");
	return $jamColors;
}

function RenderJam(&$configData, &$userData, &$gameData, &$jamModel, &$jamData, &$satisfactionData, &$loggedInUser, $nonDeletedJamCounter, $renderDepth){
	AddActionLog("RenderJam");
	StartTimer("RenderJam");

	$render = Array();

	$render["jam_id"] = $jamModel->Id;
	$render["username"] = $jamModel->Username;
	$render["jam_number"] = $jamModel->JamNumber;
	$render["theme_id"] = $jamModel->ThemeId;
	$render["theme"] = $jamModel->Theme;
	$render["start_time"] = $jamModel->StartTime;
	$render["state"] = $jamModel->State;

	if($jamModel->Deleted == 1){
		$render["jam_deleted"] = 1;
	}

	$render["theme_visible"] = $jamModel->Theme; //Theme is visible to admins
	$render["jam_number_ordinal"] = ordinal(intval($jamModel->JamNumber));
	$render["date"] = date("d M Y", strtotime($jamModel->StartTime));
	$render["time"] = date("H:i", strtotime($jamModel->StartTime));

	//Jam Colors
	$render["colors"] = Array();
	foreach($jamModel->Colors as $num => $color){
		$render["colors"][] = Array("number" => $num, "color" => "#".$color, "color_hex" => $color);
	}
	$render["colors_input_string"] = implode("-", $jamModel->Colors);

	$render["minutes_to_jam"] = floor((strtotime($jamModel->StartTime ." UTC") - time()) / 60);

	//Games in jam
	$render["entries"] = Array();
	$render["entries_count"] = 0;
	foreach($gameData->GameModels as $j => $gameModel){
		if($gameModel->JamId == $render["jam_id"]){
			if(($renderDepth & RENDER_DEPTH_GAMES) > 0){
				$render["entries"][] = RenderGame($userData, $gameModel, $jamData, $renderDepth & ~RENDER_DEPTH_JAMS);
			}

			if(!$gameModel->Deleted){
				//Has logged in user participated in this jam?
				if($loggedInUser !== false){
					if($loggedInUser->Id == $gameModel->AuthorUserId){
						$render["user_participated_in_jam"] = 1;
					}
				}

				//Count non-deleted entries in jam
				$render["entries_count"] += 1;
			}
		}
	}
	$render["entries"] = array_reverse($render["entries"]);

	//Hide theme of not-yet-started jams
	$now = new DateTime();
	$datetime = new DateTime($render["start_time"] . " UTC");
	$timeUntilJam = date_diff($datetime, $now);

	$render["first_jam"] = $nonDeletedJamCounter == 1;
	$render["entries_visible"] = $nonDeletedJamCounter <= 2;

	if($datetime > $now){
		$render["theme"] = "Not yet announced";
		$render["jam_started"] = false;
		if($timeUntilJam->days > 0){
			$render["time_left"] = $timeUntilJam->format("%a days %H:%I:%S");
		}else if($timeUntilJam->h > 0){
			$render["time_left"] = $timeUntilJam->format("%H:%I:%S");
		}else  if($timeUntilJam->i > 0){
			$render["time_left"] = $timeUntilJam->format("%I:%S");
		}else if($timeUntilJam->s > 0){
			$render["time_left"] = $timeUntilJam->format("%S seconds");
		}else{
			$render["time_left"] = "Now!";
		}
	}else{
		$render["jam_started"] = true;
	}
	
	$render["satisfaction"] = "No Data";
	if(isset($satisfactionData->SatisfactionModels["JAM_".$render["jam_number"]])){
		$arrayId = "JAM_".$render["jam_number"];

		$satisfactionSum = 0;
		$satisfactionCount = 0;
		foreach($satisfactionData->SatisfactionModels[$arrayId]->Scores as $score => $votes){
			$satisfactionSum += $score * $votes;
			$satisfactionCount += $votes;
		}
		$satisfactionAverage = $satisfactionSum / $satisfactionCount;

		$render["satisfaction_average_score"] = $satisfactionAverage;
		$render["satisfaction_submitted_scores"] = $satisfactionCount;
		$render["enough_scores_to_show_satisfaction"] = $satisfactionCount >= $configData->ConfigModels["SATISFACTION_RATINGS_TO_SHOW_SCORE"]->Value;
		$render["score-5"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[-5];
		$render["score-4"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[-4];
		$render["score-3"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[-3];
		$render["score-2"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[-2];
		$render["score-1"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[-1];
		$render["score0"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[0];
		$render["score1"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[1];
		$render["score2"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[2];
		$render["score3"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[3];
		$render["score4"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[4];
		$render["score5"] = $satisfactionData->SatisfactionModels[$arrayId]->Scores[5];
	}

	StopTimer("RenderJam");
	return $render;
}

function RenderSubmitJam(&$configData, &$userData, &$gameData, &$jamModel, &$jamData, &$satisfactionData, &$loggedInUser, $renderDepth){
	AddActionLog("RenderSubmitJam");

	return RenderJam($configData, $userData, $gameData, $jamModel, $jamData, $satisfactionData, $loggedInUser, 0, $renderDepth);
}

function RenderJams(&$configData, &$userData, &$gameData, &$jamData, &$satisfactionData, &$loggedInUser, $renderDepth, $loadAll){
	AddActionLog("RenderJams");
	StartTimer("RenderJams");

	$render = Array("LIST" => Array());
	$suggestedNextGameJamTime = GetSuggestedNextJamDateTime($configData);
	$render["next_jam_timer_code"] = gmdate("Y-m-d", $suggestedNextGameJamTime)."T".gmdate("H:i", $suggestedNextGameJamTime).":00Z";

    $nonDeletedJamCounter = 0;
	$latestStartedJamFound = false;
	$currentJam = GetCurrentJamNumberAndID();

	$jamsToLoad = $configData->ConfigModels["JAMS_TO_LOAD"]->Value;

	$allJamsLoaded = true;
	$render["current_jam"] = $currentJam["NUMBER"] !== 0;

	foreach($jamData->JamModels as $i => $jamModel){
		if($jamModel->Deleted != 1){
			$nonDeletedJamCounter += 1;
		}
		if($loadAll || $nonDeletedJamCounter <= $jamsToLoad)
		{
			if(($renderDepth & RENDER_DEPTH_JAMS) > 0){
				$jamRender = RenderJam($configData, $userData, $gameData, $jamModel, $jamData, $satisfactionData, $loggedInUser, $nonDeletedJamCounter, $renderDepth);

				$now = time();
				$datetime = strtotime($jamRender["start_time"] . " UTC");
				if($datetime > $now){
					$render["next_jam_timer_code"] = gmdate("Y-m-d", $datetime)."T".gmdate("H:i", $datetime).":00Z";
				}else{
					if(!isset($jamRender["jam_deleted"])){
						if($latestStartedJamFound == false){
							$jamRender["is_latest_started_jam"] = 1;
							$latestStartedJamFound = true;
						}
					}
				}
	
				$render["LIST"][] = $jamRender;
			}
			if($currentJam["ID"] == $jamModel->Id){
				$render["current_jam"] = RenderJam($configData, $userData, $gameData, $jamModel, $jamData, $satisfactionData, $loggedInUser, $nonDeletedJamCounter, $renderDepth);
			}
		}else{
			$allJamsLoaded = false;
			continue;
		}
    }

	$render["all_jams_loaded"] = $allJamsLoaded;
	$render["all_jams_count"] = $nonDeletedJamCounter;

	StopTimer("RenderJams");
	return $render;
}



//Checks if a jam is scheduled. If not and a jam is coming up, one is scheduled automatically.
function CheckNextJamSchedule(&$configData, &$jamData, &$ThemeData, $nextScheduledJamTime, $nextSuggestedJamTime, &$adminLogData){
	AddActionLog("CheckNextJamSchedule");
	StartTimer("CheckNextJamSchedule"); 

	//print "<br>CHECK JAM SCHEDULING";

	if($configData->ConfigModels["JAM_AUTO_SCHEDULER_ENABLED"]->Value == 0){
		//print "<br>AUTO SCHEDULER DISABLED";
		StopTimer("CheckNextJamSchedule");
		return;
	}

	//print "<br>AUTO SCHEDULER ENABLED";
	$autoScheduleThreshold = $configData->ConfigModels["JAM_AUTO_SCHEDULER_MINUTES_BEFORE_JAM"]->Value * 60;

	$now = time();
	$timeToNextScheduledJam = $nextScheduledJamTime - $now;
	$timeToNextSuggestedJam = $nextSuggestedJamTime - $now;

	$isTimeToScheduleJam = $timeToNextSuggestedJam > 0 && $timeToNextSuggestedJam <= $autoScheduleThreshold;
	$isAJamAlreadyScheduled = $timeToNextScheduledJam > 0;
	$isAJamScheduledInAuthSchedulerThresholdTime = $isAJamAlreadyScheduled && $timeToNextScheduledJam <= $autoScheduleThreshold;

	//print "<br>nextScheduledJamTime = ".gmdate("Y-m-d H:i:s", $nextScheduledJamTime);
	//print "<br>nextSuggestedJamTime = ".gmdate("Y-m-d H:i:s", $nextSuggestedJamTime);
	//print "<br>now = ".gmdate("Y-m-d H:i:s", $now);
	//print "<br>timeToNextScheduledJam = $timeToNextScheduledJam";
	//print "<br>timeToNextSuggestedJam = $timeToNextSuggestedJam";
	//print "<br>autoScheduleThreshold = $autoScheduleThreshold";
	//print "<br>isTimeToScheduleJam = ".($isTimeToScheduleJam ? "YES" : "NO");
	//print "<br>isAJamAlreadyScheduled = ".($isAJamAlreadyScheduled ? "YES" : "NO");
	//print "<br>isAJamScheduledInAuthSchedulerThresholdTime = ".($isAJamScheduledInAuthSchedulerThresholdTime ? "YES" : "NO");

	$colors = "e38484|e3b684|dee384|ade384|84e38d|84e3be|84d6e3|84a4e3|9684e3|c784e3";

	if($isTimeToScheduleJam){
		//print "<br>IT IS TIME TO SCHEDULE A JAM";

		if($isAJamScheduledInAuthSchedulerThresholdTime){
			//A future jam is already scheduled
			//print "<br>A JAM IS ALREADY SCHEDULED";
			return;
		}

		$selectedThemeId = -1;
		$selectedTheme = "";

		$selectedThemeId = SelectRandomThemeByVoteDifference($ThemeData, $configData);
		if($selectedThemeId == -1){
			$selectedThemeId = SelectRandomThemeByPopularity($ThemeData, $configData);
		}
		if($selectedThemeId == -1){
			$selectedThemeId = SelectRandomTheme($ThemeData);
		}
		if($selectedThemeId == -1){
			//Failed to find a theme
			$selectedTheme = "Any theme";
		}else{
			$selectedTheme = $ThemeData->ThemeModels[$selectedThemeId]->Theme;
		}

		//print "<br>A THEME WAS SELECTED";

		$currentJam = GetCurrentJamNumberAndID();
		$jamNumber = intval($currentJam["NUMBER"] + 1);
		//print "<br>A JAM NUMBER WAS SELECTED: ".$jamNumber;

		$jamData->AddJamToDatabase("127.0.0.1", "AUTO", -1, "AUTOMATIC", $jamNumber, $selectedThemeId, $selectedTheme, "".gmdate("Y-m-d H:i", $nextSuggestedJamTime), $colors, $adminLogData);
	}
	
	StopTimer("CheckNextJamSchedule");
}

//Selects a random theme (or "" if none can be selected) by calculating the difference between positive and negative votes and
//selecting a proportional random theme by this difference
function SelectRandomThemeByVoteDifference(&$ThemeData, &$configData){
	AddActionLog("SelectRandomThemeByVoteDifference");
	StartTimer("SelectRandomThemeByVoteDifference");

	$minimumVotes = $configData->ConfigModels["THEME_MIN_VOTES_TO_SCORE"]->Value;

	$selectedThemeId = -1;

	$availableThemes = Array();
	$totalVotesDifference = 0;
	foreach($ThemeData->ThemeModels as $id => $themeModel){
		$themeOption = Array();

		if($themeModel->Banned){
			continue;
		}

		$votesFor = $themeModel->VotesFor;
		$votesNeutral = $themeModel->VotesNeutral;
		$votesAgainst = $themeModel->VotesAgainst;
		$votesDifference = $votesFor - $votesAgainst;

		$votesTotal = $votesFor + $votesNeutral + $votesAgainst;
		$votesOpinionatedTotal = $votesFor + $votesAgainst;

		if($votesOpinionatedTotal <= 0){
			continue;
		}

		$votesPopularity = $votesFor / ($votesOpinionatedTotal);

		if($votesTotal <= 0 || $votesTotal <= $minimumVotes){
			continue;
		}

		$themeOption["theme_id"] = $themeModel->Id;
		$themeOption["votes_for"] = $votesFor;
		$themeOption["votes_difference"] = $votesDifference;
		$themeOption["popularity"] = $votesPopularity;
		$totalVotesDifference += max(0, $votesDifference);

		$availableThemes[] = $themeOption;
	}

	if($totalVotesDifference > 0 && count($availableThemes) > 0){
		$selectedVote = rand(0, $totalVotesDifference);

		$runningVoteNumber = $selectedVote;
		foreach($availableThemes as $i => $availableTheme){
			$runningVoteNumber -= $availableTheme["votes_difference"];
			if($runningVoteNumber <= 0){
				$selectedThemeId = $availableTheme["theme_id"];
				break;
			}
		}
	}

	StopTimer("SelectRandomThemeByVoteDifference");
	return $selectedThemeId;
}

//Selects a random theme (or "" if none can be selected) proportionally based on its popularity.
function SelectRandomThemeByPopularity(&$ThemeData, &$configData){
	AddActionLog("SelectRandomThemeByPopularity");
	StartTimer("SelectRandomThemeByPopularity");

	$minimumVotes = $configData->ConfigModels["THEME_MIN_VOTES_TO_SCORE"]->Value;

	$selectedThemeId = -1;

	$availableThemes = Array();
	$totalPopularity = 0;
	foreach($ThemeData->ThemeModels as $id => $themeModel){
		$themeOption = Array();

		if($themeModel->Banned){
			continue;
		}

		$votesFor = $themeModel->VotesFor;
		$votesNeutral = $themeModel->VotesNeutral;
		$votesAgainst = $themeModel->VotesAgainst;
		$votesDifference = $votesFor - $votesAgainst;

		$votesTotal = $votesFor + $votesNeutral + $votesAgainst;
		$votesOpinionatedTotal = $votesFor + $votesAgainst;

		if($votesOpinionatedTotal <= 0){
			continue;
		}

		$votesPopularity = $votesFor / ($votesOpinionatedTotal);

		if($votesTotal <= 0 || $votesTotal <= $minimumVotes){
			continue;
		}

		$themeOption["theme_id"] = $themeModel->Id;
		$themeOption["votes_for"] = $votesFor;
		$themeOption["votes_difference"] = $votesDifference;
		$themeOption["popularity"] = $votesPopularity;
		$totalPopularity += max(0, $votesPopularity);

		$availableThemes[] = $themeOption;
	}

	if($totalPopularity > 0 && count($availableThemes) > 0){
		$selectedPopularity = (rand(0, 100000) / 100000) * $totalPopularity;

		$runningPopularity = $selectedPopularity;
		foreach($availableThemes as $i => $availableTheme){
			$runningPopularity -= $availableTheme["popularity"];
			if($runningPopularity <= 0){
				$selectedThemeId = $availableTheme["theme_id"];
				break;
			}
		}
	}

	StopTimer("SelectRandomThemeByPopularity");
	return $selectedThemeId;
}

//Selects a random theme with equal probability for all themes, not caring for number of votes
function SelectRandomTheme(&$ThemeData){
	AddActionLog("SelectRandomTheme");
	StartTimer("SelectRandomTheme");

	$selectedThemeId = -1;

	$availableThemes = Array();
	foreach($ThemeData->ThemeModels as $id => $themeModel){
		$themeOption = Array();

		if($themeModel->Banned){
			continue;
		}

		$themeOption["theme_id"] = $themeModel->Id;

		$availableThemes[] = $themeOption;
	}

	if(count($availableThemes) > 0){
		$selectedIndex = rand(0, count($availableThemes));
		$selectedThemeId = $availableThemes[$selectedIndex]["theme_id"];
	}

	StopTimer("SelectRandomTheme");
	return $selectedThemeId;
}

// Returns a jam given its number.
// The dictionary of jams must have been previously loaded.
function GetJamByNumber(&$jamData, $jamNumber) {
	AddActionLog("GetJamByNumber");
	StartTimer("GetJamByNumber");

	foreach ($jamData->JamModels as $jamModel) {
		if ($jamModel->JamNumber == $jamNumber && $jamModel->Deleted != 1) {
			StopTimer("GetJamByNumber");
			return $jamModel;
		}
	}

	StopTimer("GetJamByNumber");
	return null;
}



?>