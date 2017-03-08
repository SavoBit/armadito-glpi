<?php

/*
Copyright (C) 2010-2016 by the FusionInventory Development Team
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

class PluginArmaditoAgent extends PluginArmaditoCommonDBTM
{
    protected $id;
    protected $jobj;
    protected $antivirus;
    protected $computerid;
    protected $schedulerid;

    function initFromJson($jobj)
    {
        $enrollmentkey = new PluginArmaditoEnrollmentKey();
        $enrollmentkey->initFromJson($jobj->task->obj);
        $enrollmentkey->checkKeyExpiration();
        $enrollmentkey->checkKeyUsability();
        $enrollmentkey->decrementUseCounter();

        $this->id        = PluginArmaditoToolbox::validateInt($jobj->agent_id);
        $this->jobj      = $jobj;
        $this->antivirus = new PluginArmaditoAntivirus();
        $this->antivirus->initFromJson($jobj);

        $association = new PluginArmaditoAgentAssociation($this->jobj->uuid);
        $this->computerid = $association->getComputerIdFromDB();
        $this->schedulerid = 0;
    }

    function initFromDB($agent_id)
    {
        if ($this->getFromDB($agent_id)) {
            $this->id        = $this->fields["id"];
            $this->antivirus = new PluginArmaditoAntivirus();
            $this->antivirus->initFromDB($this->fields["plugin_armadito_antiviruses_id"]);
            $this->schedulerid = $this->fields["plugin_armadito_schedulers_id"];
        } else {
            throw new InvalidArgumentException(sprintf("Unable to get Agent DB fields for agent %d", $agent_id));
        }
    }

    function updateLastAlert($alert)
    {
        $input                = array();
        $input['id']          = $alert->getAgentId();
        $input['last_alert']  = $alert->getDetectionTime();
        if ($this->update($input)) {
            return true;
        }
        return false;
    }

    function updateSchedulerId($scheduler_id)
    {
        $input                                   = array();
        $input['id']                             = $this->id;
        $input['plugin_armadito_schedulers_id']  = $scheduler_id;
        if (!$this->update($input)) {
            throw new InvalidArgumentException(sprintf('Error updateSchedulerId'));
        }

        $this->schedulerid = $scheduler_id;
    }

    function getAntivirusId()
    {
        return $this->antivirus->getId();
    }

    function getAntivirus()
    {
        return $this->antivirus;
    }

    function getId()
    {
        return $this->id;
    }

    function toJson()
    {
        return '{"agent_id": ' . $this->id . ',"scheduler_id": '. $this->schedulerid .'}';
    }

    function insertOrUpdateInDB()
    {
        $this->antivirus->run();

        if ($this->isAgentInDB($this->jobj->uuid)) {
            $this->updateAgentInDB();
        } else {
            $this->insertAgentInDB();
        }
    }

    function isAgentInDB($uuid)
    {
        global $DB;

        PluginArmaditoToolbox::validateUUID($uuid);

        $query = "SELECT id FROM `glpi_plugin_armadito_agents`
                WHERE `uuid`='" . $uuid . "'";
        $ret   = $DB->query($query);

        if (!$ret) {
            throw new InvalidArgumentException(sprintf('Error isAlreadyEnrolled : %s', $DB->error()));
        }

        if ($DB->numrows($ret) > 0) {
            $data     = $DB->fetch_assoc($ret);
            $this->id = PluginArmaditoToolbox::validateInt($data["id"]);
            return true;
        }

        return false;
    }

    function updateAgentInDB()
    {
        $dbmanager = new PluginArmaditoDbManager();

        $params = $this->setCommonQueryParams();
        $params["id"]["type"] = "i";

        $query = "UpdateAgent";
        $dbmanager->addQuery($query, "UPDATE", $this->getTable(), $params, "id");
        $dbmanager->prepareQuery($query);
        $dbmanager->bindQuery($query);

        $dbmanager = $this->setCommonQueryValues($dbmanager, $query);
        $dbmanager->setQueryValue($query, "id", $this->id);
        $dbmanager->executeQuery($query);
    }

    function insertAgentInDB()
    {
        $dbmanager = new PluginArmaditoDbManager();

        $params = $this->setCommonQueryParams();
        $params["plugin_armadito_schedulers_id"]["type"] = "i";
        $params["last_alert"]["type"] = "s";
        $params["uuid"]["type"]       = "s";

        $query = "NewAgent";
        $dbmanager->addQuery($query, "INSERT", $this->getTable(), $params);
        $dbmanager->prepareQuery($query);
        $dbmanager->bindQuery($query);

        $dbmanager = $this->setCommonQueryValues($dbmanager, $query);
        $dbmanager->setQueryValue($query, "plugin_armadito_schedulers_id", 0);
        $dbmanager->setQueryValue($query, "last_alert", '1970-01-01 00:00:00');
        $dbmanager->setQueryValue($query, "uuid", $this->jobj->uuid);
        $dbmanager->executeQuery($query);

        $this->id = PluginArmaditoDbToolbox::getLastInsertedId();
        PluginArmaditoToolbox::validateInt($this->id);
    }

    function setCommonQueryParams()
    {
        $params["entities_id"]["type"]                      = "i";
        $params["computers_id"]["type"]                     = "i";
        $params["agent_version"]["type"]                    = "s";
        $params["plugin_armadito_antiviruses_id"]["type"]   = "i";
        $params["last_contact"]["type"]                     = "s";
        return $params;
    }

    function setCommonQueryValues($dbmanager, $query)
    {
        $dbmanager->setQueryValue($query, "entities_id", 0);
        $dbmanager->setQueryValue($query, "computers_id", $this->computerid);
        $dbmanager->setQueryValue($query, "agent_version", $this->jobj->agent_version);
        $dbmanager->setQueryValue($query, "plugin_armadito_antiviruses_id", $this->antivirus->getId());
        $dbmanager->setQueryValue($query, "last_contact", date("Y-m-d H:i:s", time()));
        return $dbmanager;
    }

    static function purgeHook($agent)
    {
        try {
            $Scheduler = new PluginArmaditoScheduler();
            $Scheduler->init($agent);
            $Scheduler->setUnused();
        }
        catch(Exception $e)
        {
            PluginArmaditoLog::Error($e->getMessage());
        }
    }

    static function getTypeName($nb = 0)
    {
        return __('Agent', 'armadito');
    }

    static function getMenuName()
    {
        return __('Armadito');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $tabname = '';

        if (!$withtemplate) {
            switch ($item->getType()) {
                case 'Profile':
                    if ($item->getField('central')) {
                        $tabname = __("Armadito", 'armadito');
                    }
                    break;
                case 'Computer':
                    $tabname = array( 1 => __("Armadito AV", 'armadito'));
                    break;
                case 'Notification':
                    $tabname = array( 1 => __("Armadito Plugin", 'armadito'));
                    break;
                default :
                    break;
            }
        }

        return $tabname;
    }

    function getSearchOptions()
    {
        $search_options = new PluginArmaditoSearchoptions('Agent');
        $paConfig = new PluginArmaditoConfig();

        $items['Agent Id']         = new PluginArmaditoSearchitemlink('id', $this->getTable(), 'PluginArmaditoAgent');

        if ($paConfig->getValue('armaditoscheduler')) {
            $items['Scheduler Id']     = new PluginArmaditoSearchitemlink('id', 'glpi_plugin_armadito_schedulers', 'PluginArmaditoScheduler');
        }

        $items['Agent Version']    = new PluginArmaditoSearchtext('agent_version', $this->getTable());
        $items['Computer']         = new PluginArmaditoSearchitemlink('name', 'glpi_computers', 'Computer');
        $items['UUID']             = new PluginArmaditoSearchtext('uuid', $this->getTable());
        $items['Antivirus']        = new PluginArmaditoSearchitemlink('fullname', 'glpi_plugin_armadito_antiviruses', 'PluginArmaditoAntivirus');
        $items['Last Contact']     = new PluginArmaditoSearchtext('last_contact', $this->getTable());
        $items['Last Alert']       = new PluginArmaditoSearchtext('last_alert', $this->getTable());

        return $search_options->get($items);
    }

    function getSpecificMassiveActions($checkitem = NULL)
    {

        $actions = array();
        if (Session::haveRight("plugin_armadito_jobs", UPDATE)) {
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'newscan'] = __('Scan', 'armadito');
        }

        if (Session::haveRight("plugin_armadito_agents", UPDATE)) {
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfert'] = __('Transfer');
        }

        return $actions;
    }

    static function processMassiveActionNewScan(MassiveAction $ma, CommonDBTM $item, $key)
    {
        $agent_id = $key;

        try
        {
            if($item->getType() == "Computer") {
                $association = new PluginArmaditoAgentAssociation();
                $association->setComputerId($key);
                $agent_id = $association->getAgentIdFromDB();
            }

            $job = new PluginArmaditoJob();
            $job->initFromForm($agent_id, "Scan", $_POST);
            $job->addJob();
            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
        }
        catch(Exception $e)
        {
            PluginArmaditoLog::Error($e->getMessage());
            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
        }
    }

    static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        if($ma->getAction() == 'newscan') {
                foreach ($ids as $key) {
                    PluginArmaditoAgent::processMassiveActionNewScan($ma, $item, $key);
                }
        }
    }

    static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        if($ma->getAction() == 'newscan') {
                PluginArmaditoAgent::showNewScanForm();
                return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    static function showNewScanForm()
    {
        $configs = PluginArmaditoScanConfig::getScanConfigsList();

        if (empty($configs)) {
            PluginArmaditoScanConfig::showNoScanConfigForm();
            return;
        }

        echo "<b> Scan Parameters </b><br>";
        echo "Configuration: ";
        Dropdown::showFromArray("scanconfig_id", $configs);

        echo "<br><br><b> Job Parameters </b><br>";
        echo "Priority ";
        $array    = array();
        $array[0] = "Low";
        $array[1] = "Medium";
        $array[2] = "High";
        $array[3] = "Urgent";
        Dropdown::showFromArray("job_priority", $array);
        echo "<br><br>" . Html::submit(__('Post'), array(
            'name' => 'massiveaction'
        ));
    }

    function defineTabs($options = array())
    {
        $ong = array();
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    function showForm($table_id, $options = array())
    {
        PluginArmaditoToolbox::validateInt($table_id);

        $this->initForm($table_id, $options);
        $this->showFormHeader($options);

        $rows[] = new PluginArmaditoFormRow('Id', $this->fields["id"]);
        $rows[] = new PluginArmaditoFormRow('Version', $this->fields["agent_version"]);
        $rows[] = new PluginArmaditoFormRow('UUID', $this->fields["uuid"]);
        $rows[] = new PluginArmaditoFormRow('Last Contact', $this->fields["last_contact"]);
        $rows[] = new PluginArmaditoFormRow('Last Alert', $this->fields["last_alert"]);

        foreach( $rows as $row )
        {
            $row->write();
        }
    }
}

?>
