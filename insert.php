<?php

    require_once("paranoid.inc");

    if (!isset($_GET['action'])) {
        die('Invalid action');
    }

    $action = $_GET['action'];

    if (!in_array($action, ["insert"])) {
        die('Invalid action');
    }


    if (!isset($_GET['date'])) {
        die('Invalid date');
    }

    $eventDate = extractDate($_GET['date']);

    if (!isset($_GET['service'])) {
        die('Invalid service');
    }

    $service = $_GET['service'];

    if (!isset($_GET['status'])) {
        die('Invalid status');
    }

    $status = $_GET['status'];

    if (!isset($_GET['message'])) {
        die('Invalid message');
    }

    $message = $_GET['message'];
    $messsageID = md5($message);

    function extractDate($date)
    {
        $date_string = str_replace("_", " ", $date);
        echo $date_string;
        $year = date("Y");
        $dateTime = DateTime::createFromFormat("M j H:i:s Y", $date_string . " " . $year);
        if ($dateTime === false) {
            die("Invalid date format");
        } else {
            return $dateTime->format("Y-m-d H:i:s");
        }
    }

    function extract_IP($message)
    {
        $ipv4Regex = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';
        if (preg_match($ipv4Regex, $message, $matches)) {
            return $matches[0];
        } else {
            return "";
        }
    }

    if (!file_exists(LOGS_DATABASE)) {
        initiate_db();
    }

    $db = new SQLite3(LOGS_DATABASE);

// Insert the log entry in the SQLITE3 database
    if ($db) {
        $query = $db->prepare('INSERT INTO logs (date, service, status, ip, message, messageID) VALUES (:date, :service, :status, :ip, :message, :messageID)');
        $query->bindValue(':date', $eventDate, SQLITE3_TEXT);
        $query->bindValue(':service', $service, SQLITE3_TEXT);
        $query->bindValue(':status', $status, SQLITE3_TEXT);
        $query->bindValue(':ip', extract_IP($message), SQLITE3_TEXT);
        $query->bindValue(':message', $message, SQLITE3_TEXT);
        $query->bindValue(':messageID', $messsageID, SQLITE3_TEXT);

        if ($query->execute() === false) {
            die("Error in executing the insert query or Entry already present");
        } else {
            // Send a notification
            notify_user($eventDate, $message, extract_IP($message));
        }

        $db->close();
    }
