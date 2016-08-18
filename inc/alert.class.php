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
 * Class dealing with Armadito AV state
 **/
class PluginArmaditoAlert extends CommonDBTM {
     protected $jobj;

     function __construct() {
      //
     }

     function init($jobj) {
      $this->jobj = $jobj;

      PluginArmaditoToolbox::logIfExtradebug(
         'pluginArmadito-alert',
         'New PluginArmaditoAlert object.'
      );
     }

     function toJson() {
         return '{}';
     }


      static function canCreate() {
         if (isset($_SESSION["glpi_plugin_armadito_profile"])) {
            return ($_SESSION["glpi_plugin_armadito_profile"]['armadito'] == 'w');
         }
         return false;
      }

      static function canView() {

         if (isset($_SESSION["glpi_plugin_armadito_profile"])) {
            return ($_SESSION["glpi_plugin_armadito_profile"]['armadito'] == 'w'
                    || $_SESSION["glpi_plugin_armadito_profile"]['armadito'] == 'r');
         }
         return false;
      }

    /* Insert Alerts in database
    *
    * @return PluginArmaditoError obj
    **/
     function run(){

         $error = new PluginArmaditoError();
         $error->setMessage(0, 'Alerts successfully inserted.');
         return $error;
     }
}
?>
