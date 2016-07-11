<?php

/**
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

/**
 * Class managing Armadito devices' Enrollment
 **/
class PluginArmaditoEnrollment {
     protected $agentid;
     protected $jobj;

     function __construct($jobj) {

         $this->jobj = $jobj;

         PluginArmaditoToolbox::logIfExtradebug(
            'pluginArmadito-Enrollment',
            'New PluginArmaditoEnrollment object.'
         );
     }

    /**
    * Get agentId
    *
    * @return agentid
    **/
     function getAgentid(){
        return $this->agentid;
     }

    /**
    * Set agentId
    *
    * @return nothing
    **/
     function setAgentid($agentid_){
         $this->agentid = PluginArmaditoToolbox::validateInt($agentid_);
     }

    /**
    * Run Enrollment new Armadito device
    *
    * @return agentid
    **/
     function enroll(){

         global $DB;

         $query = "INSERT INTO `glpi_plugin_armadito_arma-ditos`(`entities_id`, `computers_id`, `plugin_fusioninventory_agents_id`, `agent_version`, `antivirus_name`, `antivirus_version`, `antivirus_state`, `last_contact`, `last_alert`) VALUES (?,?,?,?,?,?,?,?,?)";

         $stmt = $DB->prepare($query);

         if($stmt){
            $stmt->bind_param('iiissssss', $entities_id, $computers_id, $fusion_id, $agent_version, $antivirus_name, $antivirus_version, $antivirus_state, $last_contact, $last_alert);
            $entities_id = 0;
            $computers_id = 0;
            $fusion_id = 0;
            $agent_version = "";
            $antivirus_name = "";
            $antivirus_version = "";
            $antivitus_state = "";
            $last_contact = '2016-04-30 10:09:00';
            $last_alert = '2016-04-30 10:09:00';

            if(!$stmt->execute()){
               $error =  '"error" : "enrollment insert execution failed: (' . $stmt->errno . ') ' . $stmt->error.'"';
               PluginArmaditoToolbox::logE($error);
               $stmt->close();
               return $error;
            }
         }
         else {
               $error =  '"error" : "enrollment insert preparation failed."';
               PluginArmaditoToolbox::logE($error);
               return $error;
         }

         $stmt->close();

         $result = $DB->query("SELECT LAST_INSERT_ID()");
         if($result){
            $data = $DB->fetch_array($result);
            $this->setAgentid($data[0]);
         }
         else {
            $error =  '"error" : "enrollment get agent_id failed."';
            PluginArmaditoToolbox::logE($error);
            return $error;
         }

         PluginArmaditoToolbox::logIfExtradebug(
            'pluginArmadito-Enrollment',
            'Enroll new Device with id '.$this->agentid
         );

         $response = '"success": "new device successfully enrolled", "agentid": "'.$this->agentid.'"';
         return $response;
     }
}
?>