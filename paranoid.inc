<?php

    require_once("notices.inc");

    if (!defined("MONITOR_SCRIPT")) {
        define("MONITOR_SCRIPT", "/tmp/monitor.sh");
    }

    if (!defined("SCRIPT_NAME")) {
        define("SCRIPT_NAME", "monitor.sh");
    }

    if (!defined("LOGS_DATABASE")) {
        define("LOGS_DATABASE", "/tmp/auth-events.sql");
    }

    // If the database LOGS_DATABASE doesn't exist, we create it
    // Tables are: logs, settings, whitelist
    function initiate_db()
    {
        $db = new SQLite3(LOGS_DATABASE);
        if ($db) {
            $sql_create_table = "CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT,date TEXT,service TEXT,status TEXT,ip TEXT, message TEXT,messageID TEXT UNIQUE)";
            $db->exec($sql_create_table);

            $sql_create_table = "CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY AUTOINCREMENT,enabled BOOLEAN NOT NULL,smtp BOOLEAN NOT NULL,telegram BOOLEAN NOT NULL,pushover BOOLEAN NOT NULL,slack BOOLEAN NOT NULL)";
            $db->exec($sql_create_table);

            $sql_create_table = "INSERT INTO settings (enabled,smtp,telegram,pushover,slack) VALUES (0,0,0,0,0)";
            $db->exec($sql_create_table);

            $sql_create_table = "CREATE TABLE IF NOT EXISTS whitelist (id INTEGER PRIMARY KEY AUTOINCREMENT,ip TEXT UNIQUE)";
            $db->exec($sql_create_table);

            $db->close();
        }
    }

    // Returns booleans representing the alerts configuration settings
    function get_settings()
    {
        $enabledBool = $smtpBool = $telegramBool = $pushoverBool = $slackBool = false;
        if (!file_exists(LOGS_DATABASE)) {
            initiate_db();
        }
        $db = new SQLite3(LOGS_DATABASE);
        if ($db) {
            $sql_select = "SELECT enabled, smtp, telegram, pushover, slack FROM settings";
            $result = $db->query($sql_select);

            while ($row = $result->fetchArray()) {
                $smtp = $row['smtp'];
                $telegram = $row['telegram'];
                $pushover = $row['pushover'];
                $slack = $row['slack'];
                $enabled = $row['enabled'];

                $smtpBool = $smtp == 1;
                $telegramBool = $telegram == 1;
                $pushoverBool = $pushover == 1;
                $slackBool = $slack == 1;
                $enabledBool = $enabled == 1;
            }

            $db->close();
        }
        return [
            'enabled' => $enabledBool,
            'smtp' => $smtpBool,
            'telegram' => $telegramBool,
            'pushover' => $pushoverBool,
            'slack' => $slackBool,
        ];
    }

    // Update the notification settings in the database
    function update_settings($enabled, $smtp, $telegram, $pushover, $slack)
    {
        if (!is_bool($smtp) || !is_bool($telegram) || !is_bool($pushover) || !is_bool($slack) || !is_bool($enabled)) {
            return;
        }

        // Forcing back the flags to False if the notification services are disabled on the firewall
        if (!config_path_enabled('notifications/smtp', 'enabled')) {
            $smtp = false;
        }
        if (!config_path_enabled('notifications/telegram', 'enabled')) {
            $telegram = false;
        }
        if (!config_path_enabled('notifications/pushover', 'enabled')) {
            $pushover = false;
        }
        if (!config_path_enabled('notifications/slack', 'enabled')) {
            $slack = false;
        }

        if (!file_exists(LOGS_DATABASE)) {
            initiate_db();
        }

        $db = new SQLite3(LOGS_DATABASE);
        if ($db) {
            $sql_update = "UPDATE settings SET enabled = :enabled, smtp = :smtp, telegram = :telegram, pushover = :pushover, slack = :slack";
            $stmt = $db->prepare($sql_update);
            $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
            $stmt->bindValue(':smtp', $smtp, SQLITE3_INTEGER);
            $stmt->bindValue(':telegram', $telegram, SQLITE3_INTEGER);
            $stmt->bindValue(':pushover', $pushover, SQLITE3_INTEGER);
            $stmt->bindValue(':slack', $slack, SQLITE3_INTEGER);
            $stmt->execute();

            $db->close();
        }

    }

    // Verify that the monitoring script is running or not
    function is_monitored()
    {
        $command = "ps -auxww |grep '" . SCRIPT_NAME . "' |grep -v grep";
        $output = [];
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            if (!empty($output) && strlen(trim($output[0])) > 1) {
                return "Running";
            } else {
                return "Not Running";
            }
        } else {
            return "Not Running";
        }
    }

    function notify_user($eventDate, $message, $ip)
    {
        // We will verify if this is not an old event
        // Only events that are less than 1 minute old will be sent
        // If the script is restarted and reads previous events, they will be ignored.
        $now = new DateTime();
        $datetime = new DateTime($eventDate);
        $diff = $datetime->diff($now);
        $daysInSeconds = isset($diff->days) ? $diff->days * 24 * 60 * 60 : 0;
        $hoursInSeconds = $diff->h * 60 * 60;
        $minutesInSeconds = $diff->i * 60;
        $seconds = $diff->s;
        $totalSeconds = $daysInSeconds + $hoursInSeconds + $minutesInSeconds + $seconds;

        if ($totalSeconds > 60) {
            return;
        }

        // Get the notifications setting from the database
        $settings = get_settings();

        $whitelistedIP = getwhitelistIP();
        if (in_array($ip, $whitelistedIP)) {
            // The IP address $ip is in the array $whitelistedIP. We will ignore this notification.
            return;
        }

        if ($settings['enabled']) {
            if ($settings['pushover']) {
                notify_via_pushover($message);
            }
            if ($settings['smtp']) {
                notify_via_smtp($message);
            }
            if ($settings['telegram']) {
                notify_via_telegram($message);
            }
            if ($settings['slack']) {
                notify_via_slack($message);
            }
        }
    }

    // Starts the shell script that is monitoring the logs
    function start_monitoring()
    {
        if (is_monitored() == "Not Running") {
            exec("chmod +x " . MONITOR_SCRIPT);
            exec("/bin/sh " . MONITOR_SCRIPT . " > /dev/null 2>&1 &");
        }
    }

    // Returns the IP address that are whitelisted from the database
    function displaywhitelistIP()
    {
        if (!file_exists(LOGS_DATABASE)) {
            initiate_db();
        }
        $db = new SQLite3(LOGS_DATABASE);
        $html = "";

        if ($db) {
            $query = 'SELECT ip FROM whitelist';
            $results = $db->query($query);
            if ($results) {
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $html .= "<tr><td>" . $row['ip'] . "</td><td><button class='btn btn-small btn-danger' type='submit' name='ipdel' value='" . $row['ip'] . "'>Delete</a></td></tr>";
                }
            }
            $db->close();
        }
        return $html;
    }

    // Returns the IP address that are whitelisted from the database
    function getwhitelistIP()
    {
        if (!file_exists(LOGS_DATABASE)) {
            initiate_db();
        }
        $db = new SQLite3(LOGS_DATABASE);
        $ip = [];

        if ($db) {
            $query = 'SELECT ip FROM whitelist';
            $results = $db->query($query);
            if ($results) {
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $ip[] = $row['ip'];
                }
            }
            $db->close();
        }
        return $ip;
    }

    // Delete an IP address that is whitelisted from the database
    function deletewhitelistIP($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $db = new SQLite3(LOGS_DATABASE);
            if ($db) {
                $query = 'DELETE from whitelist WHERE ip ="' . $ip . '"';
                $db->exec($query);
                $db->close();
            }

        }
    }

    //Add a whitelisted IP address to the database
    function addwhitelistIP($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $db = new SQLite3(LOGS_DATABASE);
            if ($db) {
                $sql_insert = "INSERT INTO whitelist (ip) VALUES (:ip)";
                $stmt = $db->prepare($sql_insert);
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                $stmt->execute();
                $db->close();
            }

        }
    }

    // Stop the shell script that is monitoring the logs
    function stop_monitoring()
    {
        $findpid = "/bin/ps -wax | /usr/bin/grep '" . SCRIPT_NAME . "' | /usr/bin/grep -v grep | /usr/bin/awk '{print $1}'";
        exec($findpid, $pidnums, $returnVar);
        if ($returnVar === 0) {
            foreach ($pidnums as $pid) {
                if (is_numeric($pid)) {
                    exec("kill -9 " . $pid);
                }
            }
        }
    }

    // Count how many log entries there are in the local database
    function countLogsentries()
    {
        $db = new SQLite3(LOGS_DATABASE);
        if ($db) {
            $query = 'SELECT count(id) AS count FROM logs';
            $results = $db->query($query);
            if ($results) {
                $row = $results->fetchArray();
                $db->close();
                return $row['count'];
            } else {
                $db->close();
                return "0";
            }

            $db->close();
        } else {
            return "0";
        }
    }

    // Display the log entries from the database
    function displayLogs()
    {
        $db = new SQLite3(LOGS_DATABASE);
        $html = "";
        if ($db) {
            $query = 'SELECT id, date, service, status, ip, message FROM logs';
            $results = $db->query($query);

            if ($results) {

                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $html .= "<tr><td>" . $row['id'] . "</td><td>" . $row['date'] . "</td><td>" . $row['service'] . "</td><td>" . $row['status'] . "</td><td>" . $row['ip'] . "</td><td>" . htmlspecialchars($row['message']) . "</td></tr>";
                }
            }

            $db->close();
            return $html;
        }
    }
