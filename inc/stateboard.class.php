<?php
/*

Copyright (C) 2010-2016 by the FusionInventory Development Team.
Copyright (C) 2016 Teclib'

This file is part of Armadito Plugin for GLPI.

Armadito Plugin for GLPI is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Armadito Plugin for GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with Armadito Plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.

**/

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginArmaditoStateBoard extends PluginArmaditoBoard
{

    function __construct()
    {
    }

    function displayBoard()
    {
        echo "<table align='center'>";
        echo "<tr height='420'>";
        $this->showUpdateStatusChart();
        $this->showMostCriticalUpdatesChart();
        $this->showLastUpdatesChart();
        echo "</tr>";
        echo "</table>";
    }

    function showUpdateStatusChart()
    {
        $params = array(
            "svgname" => 'updatestatus',
            "title"   => __('AV Update(s) statuses', 'armadito'),
            "width"   => 370,
            "height"  => 400,
            "data"    => $this->getUpdateStatusData()
        );

        $chart = new PluginArmaditoChart("DonutChart", $params);

        echo "<td width='380'>";
        $chart->showChart();
        echo "</td>";
    }

    function showMostCriticalUpdatesChart()
    {
        $colortbox = new PluginArmaditoColorToolbox();

        $params = array(
            "svgname" => 'most_critical_updates',
            "title"   => __('Most critical updates', 'armadito'),
            "palette" => $colortbox->getPalette(12),
            "width"   => 370,
            "height"  => 400,
            "data"    => $this->getMostCriticalUpdatesData()
        );

        $bchart = new PluginArmaditoChart("HorizontalBarChart", $params);

        echo "<td width='400'>";
        $bchart->showChart();
        echo "</td>";
    }

    function showLastUpdatesChart()
    {
        $colortbox = new PluginArmaditoColorToolbox();

        $params = array(
            "svgname"       => 'lastupdates',
            "title"         => __('AV Update(s) of last hours', 'armadito'),
            "palette"       => $colortbox->getPalette(12),
            "width"         => 370,
            "height"        => 400,
            "data"          => PluginArmaditoLastUpdateStat::getLastHours(12),
            "staggerLabels" => true
        );

        $bchart = new PluginArmaditoChart("VerticalBarChart", $params);

        echo "<td width='400'>";
        $bchart->showChart();
        echo "</td>";
    }

    function getUpdateStatusData()
    {
        $statuses = PluginArmaditoState::getAvailableStatuses();

        $data = array();
        foreach ($statuses as $name => $color) {
            $n_status = $this->countUpdateStatus($name);
            $data[]   = array(
                'key' => __($name, 'armadito'),
                'value' => $n_status,
                'color' => $color
            );
        }

        return $data;
    }

    function countUpdateStatus($status)
    {
        return countElementsInTableForMyEntities(
            'glpi_plugin_armadito_states',
            [
                'update_status' => $status,
            ]
        );
    }

    function getMostCriticalUpdatesData()
    {
        global $DB;

        $ret = $this->getLastUpdatesFromDB();

        $arrayTop = new PluginArmaditoArrayTopChart(10);
        $arrayTop->init();

        if ($DB->numrows($ret) > 0)
        {
             while ($state = $DB->fetch_assoc($ret))
             {
                $lastupdate_ts = PluginArmaditoToolbox::MySQLDateTime_to_Timestamp($state['last_update']);
                $now_ts        = time();
                $duration      = PluginArmaditoToolbox::computeDuration($lastupdate_ts, $now_ts);

                $value = PluginArmaditoToolbox::DurationToSeconds($duration);
                $label = 'Agent '.$state['plugin_armadito_agents_id'];

                $arrayTop->insertIfRelevant($value, $label);
             }
        }

        return $arrayTop->toChartArray("time since last update (secs)");
    }

    function getLastUpdatesFromDB()
    {
        global $DB;

        $query  = "SELECT plugin_armadito_agents_id, last_update FROM `glpi_plugin_armadito_states` WHERE `is_deleted`='0'";

        $ret   = $DB->query($query);

        if (!$ret) {
            throw new InvalidArgumentException(sprintf('Error getLastUpdates : %s', $DB->error()));
        }

        return $ret;
    }
}
?>
