<?php
//This file contains all the globally accessible variables and constants.

//Global variables
$page = "main";
if(isset($_GET["page"])){
	$page = strtolower(trim($_GET["page"]));
}

$warnings = Array();
$loggedInUser = "";
$loginChecked = false;
$configData;
$dictionary = Array();
$userData;
$jamData;
$authors = Array();
$gameData;
$ThemeData;
$assetData;
$pollData;
$satisfactionData;
$adminVoteData;
$adminLogData;
$themesByVoteDifference = Array();
$themesByPopularity = Array();
$cookieData;
$messageData;
$themeIdeasData;

$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$nextScheduledJamTime = "";
$nextSuggestedJamDateTime = "";

require __DIR__ . '/../vendor/Mustache/Autoloader.php';
Mustache_Autoloader::register();
$mustache = new Mustache_Engine();



?>