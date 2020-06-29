<?php

class AdminLogModel{
	public $Id;
	public $DateTime;
	public $Ip;
	public $UserAgent;
	public $AdminUsernameOverride;
	public $AdminUserId;
	public $SubjectUserId;
	public $LogType;
	public $LogContent;
}

class AdminLogData{
    public $AdminLogModels;

    function __construct() {
        $this->AdminLogModels = $this->LoadAdminLog();
    }

    function LoadAdminLog(){
        global $dbConn;
        AddActionLog("LoadAdminLog");
        StartTimer("LoadAdminLog");

        $adminLogModels = Array();

        $sql = "select log_id, log_datetime, log_ip, log_user_agent, log_admin_username_override, log_admin_user_id, log_subject_user_id, log_type, log_content from admin_log order by log_id desc";
        $data = mysqli_query($dbConn, $sql);
        $sql = "";

        while($info = mysqli_fetch_array($data)){
            $adminLogModel = new AdminLogModel();

            $adminLogModel->Id = $info["log_id"];
            $adminLogModel->DateTime = $info["log_datetime"];
            $adminLogModel->Ip = $info["log_ip"];
            $adminLogModel->UserAgent = $info["log_user_agent"];
            $adminLogModel->AdminUsernameOverride = $info["log_admin_username_override"];
            $adminLogModel->AdminUserId = $info["log_admin_user_id"];
            $adminLogModel->SubjectUserId = $info["log_subject_user_id"];
            $adminLogModel->LogType = $info["log_type"];
            $adminLogModel->LogContent = $info["log_content"];

            $adminLogModels[] = $adminLogModel;
        }

        StopTimer("LoadAdminLog");
        return $adminLogModels;
    }

    function AddToAdminLog($logType, $logContent, $logSubjectUserId, $logAdminUserId, $logAdminUsernameOverride){
        global $dbConn, $ip, $userAgent;
        AddActionLog("AddToAdminLog");
        StartTimer("AddToAdminLog");
    
        $escapedIP = mysqli_real_escape_string($dbConn, $ip);
        $escapedUserAgent = mysqli_real_escape_string($dbConn, $userAgent);
        $escapedAdminUsernameOverride = mysqli_real_escape_string($dbConn, $logAdminUsernameOverride);
        $escapedAdminUserId = mysqli_real_escape_string($dbConn, $logAdminUserId);
        $escapedSubjectUserId = mysqli_real_escape_string($dbConn, $logSubjectUserId);
        $escapedLogType = mysqli_real_escape_string($dbConn, $logType);
        $escapedLogContent = mysqli_real_escape_string($dbConn, $logContent);
    
        $sql = "
            INSERT INTO admin_log
            (log_id, log_datetime, log_ip, log_user_agent, log_admin_username_override, log_admin_user_id, log_subject_user_id, log_type, log_content)
            VALUES
            (
                null,
                Now(),
                '$escapedIP',
                '$escapedUserAgent',
                '$escapedAdminUsernameOverride',
                $escapedAdminUserId,
                $escapedSubjectUserId,
                '$escapedLogType',
                '$escapedLogContent'
            );";
    
        $data = mysqli_query($dbConn, $sql);
        $sql = "";
        StopTimer("AddToAdminLog");
    }
    
    function GetAdminLogForAdminFormatted($adminUserId){
        global $dbConn;
        AddActionLog("GetAdminLogForAdminFormatted");
        StartTimer("GetAdminLogForAdminFormatted");
    
        $escapedAdminUserId = mysqli_real_escape_string($dbConn, $adminUserId);
        $sql = "
            SELECT *
            FROM admin_log
            WHERE log_admin_user_id = '$escapedAdminUserId';
        ";
        $data = mysqli_query($dbConn, $sql);
        $sql = "";
    
        StopTimer("GetAdminLogForAdminFormatted");
        return ArrayToHTML(MySQLDataToArray($data));
    }
    
    function GetAdminLogForSubjectFormatted($subjectUserId){
        global $dbConn;
        AddActionLog("GetAdminLogForSubjectFormatted");
        StartTimer("GetAdminLogForSubjectFormatted");
    
        $escapedSubjectUserId = mysqli_real_escape_string($dbConn, $subjectUserId);
        $sql = "
            SELECT log_id, log_datetime, log_admin_username_override, log_admin_user_id, log_subject_user_id, log_type, log_content
            FROM admin_log
            WHERE log_subject_user_id = '$escapedSubjectUserId';
        ";
        $data = mysqli_query($dbConn, $sql);
        $sql = "";
    
        StopTimer("GetAdminLogForSubjectFormatted");
        return ArrayToHTML(MySQLDataToArray($data));
    }
}

?>