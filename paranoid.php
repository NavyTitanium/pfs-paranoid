<?php

    require_once("guiconfig.inc");
    require_once("paranoid.inc");
    $pgtitle = array(gettext("Status"), gettext("Login monitor"));
    include("head.inc");

    // Start & Stop the bash script monitoring /var/log/auth.log
    if (isset($_POST['monitor'])) {
        if ($_POST['monitor'] == "START") {
            start_monitoring();
        } elseif ($_POST['monitor'] == "STOP") {
            stop_monitoring();
        }
    }

    // Save the settings (Alerts configuration) to the SQLITE3 database
    // Notifications are either globally enabled or disabled
    // Notifications options are: SMTP, Telegram, Pushover, Slack
    if (isset($_POST['configuration'])) {
        $enabled = $smtp = $slack = $telegram = $pushover = False;
        if (isset($_POST['pushover'])) {
            $pushover = True;
        }
        if (isset($_POST['telegram'])) {
            $telegram = True;
        }
        if (isset($_POST['slack'])) {
            $slack = True;
        }
        if (isset($_POST['smtp'])) {
            $smtp = True;
        }
        if (isset($_POST['enable'])) {
            $enabled = True;
        }
        update_settings($enabled, $smtp, $telegram, $pushover, $slack);
    }

    // Add an IP address to the whitelist
    if (isset($_POST['whitelist'])) {
        if (isset($_POST['ipaddress'])) {
            $ip = $_POST['ipaddress'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                addwhitelistIP($ip);
            }
        }
    }

    // Delete an IP address from the whitelist
    if (isset($_POST['ipdel'])) {
        $ip = $_POST['ipdel'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            deletewhitelistIP($ip);
        }
    }

    $tab_array = array();
    $tab_array[] = array(gettext("Auth monitoring"), true, "/packages/paranoid/paranoid.php");
    display_top_tabs($tab_array);


    $status = is_monitored();
    $status_html = "";
    $restart_html = "";

    if ($status == "Not Running") {
        $status_html = '<td class="text-danger">' . $status;
        $restart_html = '<button class="btn btn-small btn-success" type="submit" name="monitor" value="START">Start</a>';
    } elseif ($status == "Running") {
        $status_html = '<td class="text-success">' . $status;
        $restart_html = '<button class="btn btn-small btn-danger" type="submit" name="monitor"  value="STOP">Stop</a>';
    }

?>


<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Monitor process</h2></div>
    <div class="panel-body">
        <div class="content">
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="tabcont">

                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>The process <b>monitor.sh</b> has to be running to read the logs in <b>/var/log/auth.log</b>:

                                    <table>

                                        <tr>
                                            <td><strong>Status: </strong></td><?php echo $status_html; ?></td>
                            </tr>
                            <form action="paranoid.php" method="post" name="monitor">
                                <tr class="display:inline-block">
                                    <td class="display:inline-block" valign="middle"><?php echo $restart_html; ?></td>
                                </tr>
                            </form>
                        </table>
                    </td>

                </tr>
            </table>

            </table>
        </div>
    </div>
</div>


<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Alerts Configuration</h2></div>
    <div class="panel-body">
        <div class="content">
            <table style="border-collapse:collapse;table-layout:auto;width:50%">
                <form action="paranoid.php" method="post" name="configuration">
                    <tr>

                        <?php
                            $settings = get_settings();

                            if (!config_path_enabled('notifications/smtp', 'enabled') &&
                                !config_path_enabled('notifications/telegram', 'enabled') &&
                                !config_path_enabled('notifications/pushover', 'enabled') &&
                                !config_path_enabled('notifications/slack', 'enabled')) {
                                echo '<td><input type="checkbox" id="enable" name="enable"  disabled="disabled"></td>';
                                echo '<td><label for="enable"> Enable Notifications. You must configure at least one notification method first ! </label></td>';
                            } else {
                                if ($settings['enabled']) {
                                    echo '<td><input type="checkbox" id="enable" name="enable" checked></td>';
                                } else {
                                    echo '<td><input type="checkbox" id="enable" name="enable"></td>';
                                }
                                echo '<td><label for="enable"> Enable Notifications</label></td>';
                            }
                        ?>
                        <td></td>
                        <td></td>
                    </tr>

            </table>
            <table style="border-collapse:collapse;table-layout:auto;width:50%">
                <form action="paranoid.php" method="post" name="notifications">
                    <tr>
                        <td><input type="checkbox" id="smtp" name="smtp"
                                   value="smtp" <?php if (!config_path_enabled('notifications/smtp', 'enabled')) {
                                echo ' disabled="disabled" ';
                            }
                                if ($settings['smtp']) {
                                    echo ' checked';
                                } ?>>
                            <label for="smtp"> SMTP</label>
                        </td>
                        <td><input type="checkbox" id="telegram" name="telegram"
                                   value="telegram" <?php if (!config_path_enabled('notifications/telegram', 'enabled')) {
                                echo ' disabled="disabled" ';
                            }
                                if ($settings['telegram']) {
                                    echo ' checked';
                                } ?>>
                            <label for="telegram"> Telegram</label>
                        </td>
                        <td><input type="checkbox" id="pushover" name="pushover"
                                   value="pushover" <?php if (!config_path_enabled('notifications/pushover', 'enabled')) {
                                echo ' disabled="disabled" ';
                            }
                                if ($settings['pushover']) {
                                    echo ' checked';
                                } ?>>
                            <label for="pushover"> Pushover</label>
                        </td>
                        <td><input type="checkbox" id="slack" name="slack"
                                   value="slack" <?php if (!config_path_enabled('notifications/slack', 'enabled')) {
                                echo ' disabled="disabled" ';
                            }
                                if ($settings['slack']) {
                                    echo ' checked';
                                } ?>>
                            <label for="slack"> Slack</label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <button type="submit" class="btn btn-small btn-success" name="configuration"
                                    value="configuration">Save
                            </button>

                        </td>
                    </tr>

                </form>
            </table>
        </div>
    </div>
</div>


<!--Configuration -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Whitelist</h2></div>
    <div class="panel-body">
        <div class="content">

            <form action="paranoid.php" method="post" name="whitelist">


                <b>Enter an IP address to whitelist: </b>
                <input type="text" id="ipaddress" name="ipaddress">
                <button class="btn btn-small btn-success" type="submit" name="whitelist" value="whitelist"
                        onclick="validateIPaddress()">Add
                </button>

            </form>
            <table width="15%" border="1" cellpadding="0" cellspacing="0" width="15%">
                <form action="paranoid.php" method="post" name="whitelistdelete">
                    <tr bgcolor="grey" style="color:white;">
                        <th width="20%">IP</th>
                        <th width="20%">Action</th>
                    </tr>
                    <?php echo displaywhitelistIP(); ?>
                </form>
            </table>

        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Logs: <?php echo countLogsentries(); ?> entries</h2></div>
    <div class="panel-body">
        <div class="content">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" class="tablelogs">
                <tr bgcolor="#151A7B" style="color:white;">
                    <th width="20%" class="listhdrr">ID</th>
                    <th width="25%" class="listhdrr">Date <?php echo '(' . date_default_timezone_get() . ')'; ?></th>
                    <th width="5%" class="listhdrr">Service</th>
                    <th width="40%" class="listhdr">Status</th>
                    <th width="40%" class="listhdr">IP address</th>
                    <th width="40%" class="listhdr">Message</th>
                </tr>
                <?php echo displayLogs(); ?>
            </table>

        </div>
    </div>
</div>

<script type="text/javascript">
    const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;

    const comparer = (idx, asc) => (a, b) => ((v1, v2) =>
            v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2)
    )(getCellValue(asc ? a : b, idx), getCellValue(asc ? b : a, idx));

    // do the work...
    document.querySelectorAll('th').forEach(th => th.addEventListener('click', (() => {
        const table = th.closest('table');
        Array.from(table.querySelectorAll('tr:nth-child(n+2)'))
            .sort(comparer(Array.from(th.parentNode.children).indexOf(th), this.asc = !this.asc))
            .forEach(tr => table.appendChild(tr));
    })));

    function validateIPaddress() {
        iptovalidate = document.getElementById('ipaddress').value
        var ipformat = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        if (iptovalidate.match(ipformat)) {
            return true;
        } else {
            alert("You have entered an invalid IP address!");
            return false;
        }
    }
</script>
<style>
    .tablelogs table,
    .tablelogs td {
        border: 1px solid black;
    }

    th {
        cursor: pointer;
    }
</style>

<?php include("foot.inc"); ?>
