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

class PluginArmaditoSchedulerDetail extends PluginArmaditoEAVCommonDBTM
{
    protected $id;
    protected $agentid;
    protected $agent;
    protected $entries;
    protected $antivirus;

    static function getTypeName($nb = 0)
    {
        return __('Scheduler Details', 'armadito');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'PluginArmaditoSchedulerDetail') {
            return __('Agents schedulers', 'armadito');
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'PluginArmaditoSchedulerDetail') {
            $paAVScheduler = new self();
            $paAVScheduler->showForm($item->fields["plugin_armadito_agents_id"]);
        }

        return TRUE;
    }

    function getAgent()
    {
        return $this->agent;
    }

    function getId()
    {
        return $this->id;
    }

    function initFromJson($jobj)
    {
        $this->setAgentFromJson($jobj);
        $this->antivirus = $this->agent->getAntivirus();
        $this->entries = $jobj->task->obj->confdetails;
    }

    function run()
    {
        $this->performOptimizedInsertion();
    }

    function showForm($id, $options = array())
    {
        PluginArmaditoToolbox::validateInt($id);

        $agent_id = $this->fields["plugin_armadito_agents_id"];
        $antivirus_id = $this->fields["plugin_armadito_antiviruses_id"];

        $this->showEAVForm($agent_id, $antivirus_id);
    }

    function showErrorMessage()
    {
        echo "<div style='text-align: center;'><br><b>No id provided.</b><br></div>";
    }
}
?>
