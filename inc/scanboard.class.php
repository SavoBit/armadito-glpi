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

class PluginArmaditoScanBoard extends PluginArmaditoBoard
{

    function __construct()
    {
    }

    function displayBoard()
    {
        echo "<table align='center'>";
        echo "<tr height='420'>";
        $this->showScanStatusChart();
        $this->showLongestScansChart();
        $this->showAverageDurationsChart();
        echo "</tr>";
        echo "</table>";
    }

    function showScanStatusChart()
    {
        $params = array(
            "svgname" => 'scanstatus',
            "title"   => "Scan(s) statuses",
            "width"   => 370,
            "height"  => 400,
            "data"    => $this->getScanStatusData()
        );

        $chart = new PluginArmaditoChart("DonutChart", $params);

        echo "<td width='380'>";
        $chart->showChart();
        echo "</td>";
    }

    function showLongestScansChart()
    {
        $colortbox = new PluginArmaditoColorToolbox();

        $params = array(
            "svgname" => 'longest_scans',
            "title"   => __('Longest scans', 'armadito'),
            "palette" => $colortbox->getPalette(12),
            "width"   => 370,
            "height"  => 400,
            "data"    => $this->getLongestScansData()
        );

        $bchart = new PluginArmaditoChart("HorizontalBarChart", $params);

        echo "<td width='400'>";
        $bchart->showChart();
        echo "</td>";
    }

    function showAverageDurationsChart()
    {
        $colortbox = new PluginArmaditoColorToolbox();

        $params = array(
            "svgname"   => 'average_scan_durations',
            "title"     => __('Average scan durations', 'armadito'),
            "palette"   => $colortbox->getPalette(12),
            "width"     => 370,
            "height"    => 400,
            "data"      => $this->getScanAverageDurationsData(),
            "showXAxis" => false
        );

        $bchart = new PluginArmaditoChart("VerticalBarChart", $params);

        echo "<td width='400'>";
        $bchart->showChart();
        echo "</td>";
    }

    function countScanStatus($status)
    {
        global $DB;
        return countElementsInTableForMyEntities(
            'glpi_plugin_armadito_jobs',
            [
                'job_status' => $DB->escape($status),
                'job_type'   => 'Scan',
                'is_deleted'  => '0',
            ]
        );
    }

    function getScanStatusData()
    {
        $statuses = PluginArmaditoJob::getAvailableStatuses();

        $data = array();
        foreach ($statuses as $name => $color) {
            $n_status = $this->countScanStatus($name);
            $data[]   = array(
                'key' => __($name, 'armadito'),
                'value' => $n_status,
                'color' => $color
            );
        }

        return $data;
    }

    function getLongestScansData()
    {
        global $DB;

        $ret = $this->getScanDurationsFromDB();

        $arrayTop = new PluginArmaditoArrayTopChart(10);
        $arrayTop->init();

        if ($DB->numrows($ret) > 0)
        {
             while ($scan = $DB->fetch_assoc($ret))
             {
                $value = PluginArmaditoToolbox::DurationToSeconds($scan['duration']);
                $label = 'Scan '.$scan['id'];

                $arrayTop->insertIfRelevant($value, $label);
             }
        }

        return $arrayTop->toChartArray("scan durations (secs)");
    }

    function getScanAverageDurationsData()
    {
        $data = array();
        $data['key'] = "Average Scan durations";
        $scanconfigs = PluginArmaditoScanConfig::getScanConfigsList();

        foreach ($scanconfigs as $id => $name)
        {
            $data['values'][] = array(
                'label' => $name,
                'value' => $this->getAverageDurationForScanConfig($id)
            );
        }

        return $data;
    }

    function getAverageDurationForScanConfig($scanconfig_id)
    {
        global $DB;

        $ret = $this->getScanDurationsFromDB($scanconfig_id);
        $average_duration = 0;

        if ($DB->numrows($ret) > 0)
        {
             $i = 0;
             while ($scan = $DB->fetch_assoc($ret))
             {
                $average_duration += PluginArmaditoToolbox::DurationToSeconds($scan['duration']);
                $i++;
             }

             if($i > 0) {
                $average_duration = $average_duration/$i;
             }
        }

        return $average_duration;
    }

    function getScanDurationsFromDB($scanconfig_id = 0)
    {
        global $DB;

        $query  = "SELECT id, duration FROM `glpi_plugin_armadito_scans` WHERE `is_deleted`='0' AND `progress`='100'";

        if($scanconfig_id > 0) {
            $query .= "AND `plugin_armadito_scanconfigs_id`='".$scanconfig_id."'";
        }

        $ret   = $DB->query($query);

        if (!$ret) {
            throw new InvalidArgumentException(sprintf('Error getLongestScansData : %s', $DB->error()));
        }

        return $ret;
    }
}
?>
