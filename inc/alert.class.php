<?php

/*
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

include_once("toolbox.class.php");

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginArmaditoAlert extends PluginArmaditoCommonDBTM
{
    protected $obj;
    protected $agentid;
    protected $agent;
    protected $detection_time;
    protected $antivirus;
    protected $dbmanager;

    static $rightname = 'plugin_armadito_alerts';

    const INSERT_QUERY = "NewAlert";

    function getDetectionTime()
    {
        return $this->obj->detection_time;
    }

    function getAgentId()
    {
        return $this->agentid;
    }

    function getDbManager()
    {
        return $this->dbmanager;
    }

    function setDbManager($dbmanager)
    {
        $this->dbmanager = $dbmanager;
    }

    function initFromJson($jobj)
    {
        $this->setAgentFromJson($jobj);
        $this->antivirus = $this->agent->getAntivirus();
        $this->setObj($jobj->task->obj);
        $this->setScanIdOrDefault();
    }

    function setObj($obj)
    {
        $this->obj = new StdClass;
        $this->obj->threat_name = $this->setValueOrDefault($obj, "name", "string");
        $this->obj->filepath = $this->setValueOrDefault($obj, "filepath", "string");
        $this->obj->module_name = $this->setValueOrDefault($obj, "module_name", "string");
        $this->obj->action = $this->setValueOrDefault($obj, "action", "string");
        $this->obj->info   = $this->setValueOrDefault($obj, "info", "string");
        $this->obj->jobid  = $this->setValueOrDefault($obj, "job_id", "integer");

        $this->obj->detection_time = $this->setValueOrDefault($obj, "detection_time", "date");
        $this->obj->detection_time = PluginArmaditoToolbox::formatDate($this->obj->detection_time);
        $this->obj->checksum = $this->computeChecksum();
    }

    function setScanIdOrDefault()
    {
        $this->obj->scanid = 0;
        if( $this->obj->jobid > 0) {
            $scan = new PluginArmaditoScan();
            $scan->initFromDB($this->obj->jobid);
            $this->obj->scanid = $scan->getId();
        }
    }

    function computeChecksum()
    {
         $unique_string  = $this->obj->threat_name.";";
         $unique_string .= $this->obj->filepath.";";
         $unique_string .= $this->obj->detection_time.";";
         $unique_string .= $this->agentid.";";
         $unique_string .= $this->antivirus->getId().";";

         return hash("sha256", $unique_string);
    }

    function rawSearchOptions()
    {
        $search_options = new PluginArmaditoSearchoptions('Alert');

        $items['Alert Id']         = new PluginArmaditoSearchitemlink('id', $this->getTable(), 'PluginArmaditoAlert');
        $items['Agent Id']         = new PluginArmaditoSearchitemlink('id', 'glpi_plugin_armadito_agents', 'PluginArmaditoAgent');
        $items['Job Id']           = new PluginArmaditoSearchitemlink('id', 'glpi_plugin_armadito_jobs', 'PluginArmaditoJob');
        $items['Scan Id']          = new PluginArmaditoSearchitemlink('id', 'glpi_plugin_armadito_scans', 'PluginArmaditoScan');
        $items['Threat name']      = new PluginArmaditoSearchtext('threat_name', $this->getTable());
        $items['Filepath']         = new PluginArmaditoSearchtext('filepath', $this->getTable());
        $items['Antivirus']        = new PluginArmaditoSearchitemlink('fullname', 'glpi_plugin_armadito_antiviruses', 'PluginArmaditoAntivirus');
        $items['Detection Time']   = new PluginArmaditoSearchtext('detection_time', $this->getTable());
        $items['Action']           = new PluginArmaditoSearchtext('action', $this->getTable());
        $items['Info']             = new PluginArmaditoSearchtext('info', $this->getTable());
        $items['Module']           = new PluginArmaditoSearchtext('module_name', $this->getTable());

        return $search_options->get($items);
    }

    static function manageApiRequest($rawdata)
    {
        $jobj   = PluginArmaditoToolbox::parseJSON($rawdata);
        $job_id = isset($jobj->task->obj->job_id) ? $jobj->task->obj->job_id : 0;

        $alertdb = new PluginArmaditoAlert();
        $alertdb->prepareInsertQuery();
        $dbmanager = $alertdb->getDbManager();

        foreach( $jobj->task->obj->alerts as $alert_jobj )
        {
            $tmp_jobj = $jobj;
            $tmp_jobj->task->obj = $alert_jobj;
            $tmp_jobj->task->obj->job_id = $job_id;

            $alert = new PluginArmaditoAlert();
            $alert->initFromJson($tmp_jobj);
            $alert->setDbManager($dbmanager);

            if(!$alert->isAlertInDB()) {
                $alert->insertAlert();
                $alert->updateAlertStat();
            }
        }

        $alertdb->closeInsertQuery();

        if($alert)
        {
            $agent = new PluginArmaditoAgent();
            $agent->updateLastAlert($alert);
        }
    }

    function isAlertInDB()
    {
        global $DB;

        $query = "SELECT id FROM `glpi_plugin_armadito_alerts`
                WHERE `checksum`='" . $this->obj->checksum . "'";
        $ret   = $DB->query($query);

        if (!$ret) {
            throw new InvalidArgumentException(sprintf('Error isAlertInDB : %s', $DB->error()));
        }

        if ($DB->numrows($ret) > 0) {
            return true;
        }

        return false;
    }

    function prepareInsertQuery()
    {
        $this->dbmanager = new PluginArmaditoDbManager();
        $params = $this->setCommonQueryParams();

        $this->dbmanager->addQuery(self::INSERT_QUERY, "INSERT", $this->getTable(), $params);
        $this->dbmanager->prepareQuery(self::INSERT_QUERY);
        $this->dbmanager->bindQuery(self::INSERT_QUERY);
    }

    function closeInsertQuery()
    {
        $this->dbmanager->closeQuery(self::INSERT_QUERY);
    }

    function insertAlert()
    {
        $this->setCommonQueryValues(self::INSERT_QUERY);
        $this->dbmanager->executeQuery(self::INSERT_QUERY, false);

        $this->id = PluginArmaditoDbToolbox::getLastInsertedId();
        PluginArmaditoToolbox::validateInt($this->id);
        $this->logNewItem();
    }

    function setCommonQueryParams()
    {
        $params["plugin_armadito_agents_id"]["type"]       = "i";
        $params["plugin_armadito_antiviruses_id"]["type"]  = "i";
        $params["plugin_armadito_jobs_id"]["type"]         = "i";
        $params["plugin_armadito_scans_id"]["type"]        = "i";
        $params["threat_name"]["type"]                     = "s";
        $params["module_name"]["type"]                     = "s";
        $params["filepath"]["type"]                        = "s";
        $params["detection_time"]["type"]                  = "s";
        $params["checksum"]["type"]                        = "s";
        $params["action"]["type"]                          = "s";
        $params["info"]["type"]                            = "s";
        return $params;
    }

    function setCommonQueryValues( $query )
    {
        $this->dbmanager->setQueryValue($query, "plugin_armadito_agents_id", $this->agentid);
        $this->dbmanager->setQueryValue($query, "plugin_armadito_antiviruses_id", $this->antivirus->getId());
        $this->dbmanager->setQueryValue($query, "plugin_armadito_jobs_id", $this->obj->jobid);
        $this->dbmanager->setQueryValue($query, "plugin_armadito_scans_id", $this->obj->scanid);
        $this->dbmanager->setQueryValue($query, "threat_name", $this->obj->threat_name);
        $this->dbmanager->setQueryValue($query, "filepath", $this->obj->filepath);
        $this->dbmanager->setQueryValue($query, "module_name", $this->obj->module_name);
        $this->dbmanager->setQueryValue($query, "detection_time", $this->obj->detection_time);
        $this->dbmanager->setQueryValue($query, "checksum", $this->obj->checksum);
        $this->dbmanager->setQueryValue($query, "action", $this->obj->action);
        $this->dbmanager->setQueryValue($query, "info", $this->obj->info);
    }

    function updateAlertStat ()
    {
        PluginArmaditoLastAlertStat::increment($this->obj->detection_time);
    }

    static function addVirusName ($VirusNames, $data)
    {
        if(!isset($VirusNames[$data['threat_name']])) {
            $VirusNames[$data['threat_name']] = 0;
        }

        $VirusNames[$data['threat_name']]++;
        return $VirusNames;
    }

    static function getVirusNamesList( $max )
    {
        global $DB;

        $VirusNames   = array();
        $query = "SELECT threat_name FROM `glpi_plugin_armadito_alerts`";
        $ret   = $DB->query($query);

        if (!$ret) {
            throw new InvalidArgumentException(sprintf('Error getVirusNamesList : %s', $DB->error()));
        }

        if ($DB->numrows($ret) > 0) {
            while ($data = $DB->fetch_assoc($ret)) {
                $VirusNames = PluginArmaditoAlert::addVirusName($VirusNames, $data);
            }
        }

        return PluginArmaditoAlert::prepareVirusNamesListForDisplay($VirusNames, $max);
    }

    static function prepareVirusNamesListForDisplay( $VirusNames, $max )
    {
        arsort($VirusNames);

        $i = 0;
        $others = 0;
        foreach ($VirusNames as $name => $counter) {
            if($i > $max) {
                unset($VirusNames[$name]);
                $others++;
            }
            $i++;
        }

        if( $others > 0 ){
              $VirusNames["others"] = $others;
        }

        return $VirusNames;
    }

    function showForm($table_id, $options = array())
    {
        PluginArmaditoToolbox::validateInt($table_id);

        $this->initForm($table_id, $options);
        $this->showFormHeader($options);

        $rows[] = new PluginArmaditoFormRow('Id', $this->fields["id"]);
        $rows[] = new PluginArmaditoFormRow('Threat name', $this->fields["threat_name"]);
        $rows[] = new PluginArmaditoFormRow('Job Id', $this->fields["plugin_armadito_jobs_id"]);
        $rows[] = new PluginArmaditoFormRow('Scan Id', $this->fields["plugin_armadito_scans_id"]);
        $rows[] = new PluginArmaditoFormRow('File Path', $this->fields["filepath"]);
        $rows[] = new PluginArmaditoFormRow('Detection Time', $this->fields["detection_time"]);
        $rows[] = new PluginArmaditoFormRow('Action', $this->fields["action"]);
        $rows[] = new PluginArmaditoFormRow('Info', $this->fields["info"]);

        foreach( $rows as $row )
        {
            $row->write();
        }
    }
}
?>
