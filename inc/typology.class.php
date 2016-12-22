<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 typology plugin for GLPI
 Copyright (C) 2009-2016 by the typology Development Team.

 https://github.com/InfotelGLPI/typology
 -------------------------------------------------------------------------

 LICENSE
      
 This file is part of typology.

 typology is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 typology is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with typology. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Class Typology
class PluginTypologyTypology extends CommonDBTM {

   // From CommonDBTM
   var $dohistory = true;
   static $rightname                   = "plugin_typology";
   protected $usenotepad         = true;
   protected $usenotepadrights         = true;
   protected static $forward_entity_to = array('PluginTypologyTypologyCriteria');

   static $types = array('Computer');

   static $types_criteria = array(
      'Computer',
      'Monitor',
      'Software',
      'Peripheral',
      'Printer',
      'IPAddress'
//      'NetworkPort'
      /*'Phone'*/);

   public static function getTypeName($nb=0) {

      return _n('Typology', 'Typologies', $nb, 'typology');
   }

   /**
    * Display tab for each typology
    * */
   function defineTabs($options=array()) {

      $ong    = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginTypologyTypologyCriteria', $ong, $options);
      $this->addStandardTab('PluginTypologyTypology_Item', $ong, $options);
      $this->addStandardTab('Document',$ong,$options);
      $this->addStandardTab('Notepad',$ong,$options);
      $this->addStandardTab('Log',$ong,$options);
      return $ong;
   }

   /**
    * Actions done when a typo is deleted from the database
    *
    * @return nothing
    **/
   function cleanDBonPurge(){

      //Clean typology_item
      $temp1 = new PluginTypologyTypology_Item();
      $temp1->deleteByCriteria(array('plugin_typology_typologies_id' => $this->fields['id']));

      //Clean typologycriteria
      $temp2 = new PluginTypologyTypologyCriteria();
      $temp2->deleteByCriteria(array('plugin_typology_typologies_id' => $this->fields['id']));

      //Clean rule
      Rule::cleanForItemAction($this);
   }

   /**
    * For other plugins, add a type to the linkable types
    *
    * @since version 1.3.0
    *
    * @param $type string class name
    **/
   static function registerType($type) {
      if (!in_array($type, self::$types)) {
         self::$types[] = $type;
      }
   }

   /**
    * Type than could be linked to a typo
    *
    * @param $all boolean, all type, or only allowed ones
    *
    * @return array of types
    **/
   static function getTypes($all=false) {

      if ($all) {
         return self::$types;
      }

      // Only allowed types
      $types = self::$types;

      foreach ($types as $key => $type) {
         if (!($item = getItemForItemtype($type))) {
            continue;
         }

         if (!$item->canView()) {
            unset($types[$key]);
         }
      }
      return $types;
   }

   /**
    * For other plugins, add a type to the linkable types
    *
    * @since version 1.3.0
    *
    * @param $type string class name
    **/
   static function registerTypeCriteria($typeCriteria) {
      if (!in_array($typeCriteria, self::getTypesCriteria())) {
         self::$types_criteria[] = $typeCriteria;
      }
   }

   /**
    * Type than could be used as a criteria for a typo
    *
    * @param $all boolean, all type, or only allowed ones
    *
    * @return array of types
    **/
   static function getTypesCriteria() {

      // Only allowed types
      $types_criteria = self::$types_criteria;
      $devtypes = self::getComputerDeviceTypes();

      foreach ($types_criteria as $key => $type_criteria) {
         if (!($item = getItemForItemtype($type_criteria))) {
            continue;
         }

//         if (!$item->canView()) {
//            unset($types_criteria[$key]);
//         }
      }

      foreach ($devtypes as $itemtype) {
         $device        = new $itemtype();
         if ($device->can(-1,'r')) {
            $types_criteria[] = $itemtype;
         }
      }

      return $types_criteria;
   }

   /**
    * Display the typology form
    *
    * @param $ID integer ID of the item
    * @param $options array
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    *@return boolean item found
   **/
   function showForm($ID, $options=array()) {

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Name')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name", array('value' => $this->fields["name"]));
      echo "</td>";
      echo "<td rowspan=2>".__('Comments')."</td>";
      echo "<td rowspan=2>";
      echo "<textarea cols='45' rows='8' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";

      if (!$ID) {
           echo "<td>".__('Last update')."</td>";
           echo "<td>";
           echo Html::convDateTime($_SESSION["glpi_currenttime"]);

      } else {
         echo "<td>".__('Last update')."</td>";
         echo "<td>".($this->fields["date_mod"] ? Html::convDateTime($this->fields["date_mod"])
                                                : __('Never'));
      }

      echo "</td></tr>";
      echo "<input type='hidden' name='entities_id' value='".$_SESSION['glpiactive_entity']."'>";

      $this->showFormButtons($options);

      return true;
   }

   function getSearchOptions() {

      $tab = array();
      $tab['common'] = PluginTypologyTypology::getTypeName(1);

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();
      $tab[1]['massiveaction']   = false;

      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'id';
      $tab[2]['name']            = __('ID');
      $tab[2]['massiveaction']   = false;
      $tab[2]['datatype']        = 'number';
      
      $tab[14]['table']          = $this->getTable();
      $tab[14]['field']          ='date_mod';
      $tab[14]['name']           =__('Last update');
      $tab[14]['massiveaction']  = false;
      $tab[14]['datatype']       ='datetime';
      
      $tab[16]['table']          = $this->getTable();
      $tab[16]['field']          = 'comment';
      $tab[16]['name']           = __('Comments');
      $tab[16]['datatype']       = 'text';
      $tab[16]['massiveaction']  = true;

      $tab[80]['table']          = 'glpi_entities';
      $tab[80]['field']          = 'completename';
      $tab[80]['name']           = __('Entity');
      $tab[80]['massiveaction']  = false;
      $tab[80]['datatype']       = 'dropdown';

      $tab[86]['table']          = $this->getTable();
      $tab[86]['field']          = 'is_recursive';
      $tab[86]['name']           = __('Child entities');
      $tab[86]['datatype']       = 'bool';
      $tab[86]['massiveaction']  = true;

      return $tab;
   }


   static function getComputerDeviceTypes(){
      return array(/*1 => 'DeviceMotherboard', */2 => 'DeviceProcessor',   3 => 'DeviceMemory',
                   4 => 'DeviceHardDrive'/*,   5 => 'DeviceNetworkCard', 6 => 'DeviceDrive',
                   7 => 'DeviceControl',     8 => 'DeviceGraphicCard', 9 => 'DeviceSoundCard',
                   10 => 'DevicePci',        11 => 'DeviceCase',       12 => 'DevicePowerSupply'*/);
   }

   ////// CRON FUNCTIONS ///////
   //Cron action
   static function cronInfo($name){

      switch ($name) {
         case 'UpdateTypology':
            return array (
               'description' => __('Recalculate typology for the elements','typology'));   // Optional
            break;
         case 'NotValidated':
            return array (
               'description' => __('Elements not match with the typology','typology'));   // Optional
            break;
      }
      return array();
   }

   function queryUpdateTypology() {

      $query = "SELECT *
            FROM `glpi_plugin_typology_typologies_items`";

      return $query;

   }
   
   function queryNotValidated() {

      $query = "SELECT `glpi_plugin_typology_typologies_items`.*,
                        `glpi_plugin_typology_typologies`.`name`,
                        `glpi_plugin_typology_typologies`.`entities_id`
            FROM `glpi_plugin_typology_typologies_items`
            LEFT JOIN `glpi_plugin_typology_typologies`
            ON (`glpi_plugin_typology_typologies_items`.`plugin_typology_typologies_id` = `glpi_plugin_typology_typologies`.`id`)
            WHERE `glpi_plugin_typology_typologies_items`.`is_validated` = 0
            ORDER BY `glpi_plugin_typology_typologies`.`name`";

      return $query;

   }


   /**
    * Cron action on tasks : UpdateTypology
    *
    * @param $task for log, if NULL display
    *
    **/
   static function cronUpdateTypology($task=NULL) {
      global $DB;
      
      $cron_status = 0;
      $message=array();

      $typo = new self();
      $query_items = $typo->queryUpdateTypology();

      $querys = array(Alert::END=>$query_items);

      $task_infos = array();
      $task_messages = array();

      foreach ($querys as $type => $query) {
         $task_infos[$type] = array();
         foreach ($DB->request($query) as $data) {

            //update all linked item to a typology
            if (isset($data['id'])) {
               $input=PluginTypologyTypology_Item::checkValidated($data);
            }

            if($data['error'] != $input['error']){
               $typo_item = new PluginTypologyTypology_Item();
               $typo_item->getFromDB($data['id']);
               $values = array('id' =>  $data['id'],
                               'is_validated' => $input['is_validated'],
                               'error' => $input['error']);
               $typo_item->update($input);
               $typo->getFromDB($data['plugin_typology_typologies_id']);
               $entity = $typo->fields['entities_id'];
               if(!isset($message[$entity])){
                  $message=array($entity=>'');
               }
               $task_infos[$type][$entity][] = $data;
               if (!isset($task_messages[$type][$entity])) {
                  $task_messages[$type][$entity] = __('Typology of the linked elements is updated.','typology')."<br />";
               }
               $task_messages[$type][$entity] .= $message[$entity];
            }
         }
      }

      foreach ($querys as $type => $query) {

         foreach ($task_infos[$type] as $entity => $items) {
            Plugin::loadLang('typology');

            $message = $task_messages[$type][$entity];
            $cron_status = 1;
            if ($task) {
               $task->log(Dropdown::getDropdownName("glpi_entities",
                  $entity).":  $message\n");
               $task->addVolume(count($items));
            } else {
               Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                  $entity).":  $message");
            }
         }
      }

      return $cron_status;
   }
   
   /**
    * Cron action on tasks : UpdateTypology
    *
    * @param $task for log, if NULL display
    *
    **/
   static function cronNotValidated($task=NULL) {
      global $DB,$CFG_GLPI;
      
      
      if (!$CFG_GLPI["use_mailing"]) {
         return 0;
      }
      
      $cron_status = 0;
      $message=array();

      $typo = new self();
      $query_items = $typo->queryNotValidated();

      $querys = array(Alert::END=>$query_items);

      $task_infos = array();
      $task_messages = array();

      foreach ($querys as $type => $query) {
         $task_infos[$type] = array();
         foreach ($DB->request($query) as $data) {

            // Get items entity
            $item = getItemForItemtype($data['itemtype']);
            $item->getFromDB($data['items_id']);

            if (!$item->fields['is_deleted']) {
               $entity = $item->fields['entities_id'];

               $message                      = $data["name"] . ": " .
                                               $data["error"] . "<br>\n";
               $task_infos[$type][$entity][] = $data;

               if (!isset($tasks_infos[$type][$entity])) {
                  $task_messages[$type][$entity] = __('Elements not match with the typology', 'typology') . "<br />";
               }
               $task_messages[$type][$entity] .= $message;
            }
         }
      }

      foreach ($querys as $type => $query) {

         foreach ($task_infos[$type] as $entity => $items) {
            Plugin::loadLang('typology');

            $message = $task_messages[$type][$entity];
            $cron_status = 1;
            
            if (NotificationEvent::raiseEvent("AlertNotValidatedTypology",
                                              new PluginTypologyTypology(),
                                              array('entities_id'=>$entity,
                                                    'items'=>$items))) {
               $message = $task_messages[$type][$entity];
               $cron_status = 1;
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",
                                                       $entity).":  $message\n");
                  $task->addVolume(1);
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                                                                    $entity).":  $message");
               }

            } else {
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",
                     $entity).":  $message\n");
                  $task->addVolume(count($items));
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                     $entity).":  $message");
               }
            }
         }
      }

      return $cron_status;
   }

   /**
    * Get the specific massive actions
    *
    * @since version 0.84
    * @param $checkitem link item to check right   (default NULL)
    *
    * @return an array of massive actions
    **/
   /**
    * Get the specific massive actions
    * 
    * @since version 0.84
    * @param $checkitem link item to check right   (default NULL)
    * 
    * @return an array of massive actions
    **/
   public function getSpecificMassiveActions($checkitem = NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if ($isadmin) {
            $actions['PluginTypologyTypology'.MassiveAction::CLASS_ACTION_SEPARATOR.'duplicate']    = _sx('button','Duplicate');

            if (Session::haveRight('transfer', READ)
                     && Session::isMultiEntitiesMode()
            ) {
               $actions['PluginTypologyTypology'.MassiveAction::CLASS_ACTION_SEPARATOR.'transfer'] = __('Transfer');
            }
         }
      }
      return $actions;
   }
   
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case "duplicate" :
            echo Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
            break;
         case "transfer" :
            Dropdown::show('Entity');
            echo Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
            break;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      $criteria = new PluginTypologyTypologyCriteria();
      $definition = new PluginTypologyTypologyCriteriaDefinition();

      switch ($ma->getAction()) {

          case "transfer" :
            $input = $ma->getInput();
            if ($item->getType() == 'PluginTypologyTypology') {
               foreach ($ids as $key) {
                  $item->getFromDB($key);
                  
                  $restrict = "`plugin_typology_typologies_id` = '".$key."'";
                  $crits = getAllDatasFromTable("glpi_plugin_typology_typologycriterias", $restrict);
                  if (!empty($crits)) {
                     foreach ($crits as $crit) {

                        $criteria->getFromDB($crit["id"]);

                        $condition = "`plugin_typology_typologycriterias_id` = '".$crit["id"]."'";
                        $defs = getAllDatasFromTable("glpi_plugin_typology_typologycriteriadefinitions", $condition);
                        if (!empty($defs)) {
                           foreach ($defs as $def) {

                              $definition->getFromDB($def["id"]);

                              unset($values);
                              $values["id"] = $def["id"];
                              $values["entities_id"] = $input['entities_id'];
                              $definition->update($values);
                           }
                        }
                        unset($values);
                        $values["id"] = $crit["id"];
                        $values["entities_id"] = $input['entities_id'];
                        $criteria->update($values);
                     }
                  }

                  unset($values);
                  $values["id"] = $key;
                  $values["entities_id"] = $input['entities_id'];

                  if ($item->update($values)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                      $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            break;
            
         case 'duplicate':
            $input = $ma->getInput();
            if ($item->getType() == 'PluginTypologyTypology') {
               foreach ($ids as $key) {

                  $item->getFromDB($key);

                  $restrict = "`plugin_typology_typologies_id` = '" . $key . "'";
                  $crits    = getAllDatasFromTable("glpi_plugin_typology_typologycriterias", $restrict);

                  unset($item->fields["id"]);
                  $item->fields["name"]    = addslashes($item->fields["name"] . " Copy");
                  $item->fields["comment"] = addslashes($item->fields["comment"]);
                  //TODO duplicate notes
//                  $item->fields["notepad"] = addslashes($item->fields["notepad"]);
                  $newIDtypo               = $item->add($item->fields);

                  if (!empty($crits)) {
                     foreach ($crits as $crit) {

                        $criteria->getFromDB($crit["id"]);

                        $condition = "`plugin_typology_typologycriterias_id` = '" . $crit["id"] . "'";
                        $defs      = getAllDatasFromTable("glpi_plugin_typology_typologycriteriadefinitions", $condition);

                        unset($criteria->fields["id"]);
                        $criteria->fields["name"]                          = addslashes($criteria->fields["name"]);
                        $criteria->fields["plugin_typology_typologies_id"] = $newIDtypo;
                        $criteria->fields["itemtype"]                      = addslashes($criteria->fields["itemtype"]);
                        $newIDcrit                                         = $criteria->add($criteria->fields);

                        if (!empty($defs)) {
                           foreach ($defs as $def) {

                              $definition->getFromDB($def["id"]);

                              unset($definition->fields["id"]);
                              $definition->fields["plugin_typology_typologycriterias_id"] = $newIDcrit;
                              $definition->fields["field"]                                = addslashes($definition->fields["field"]);
                              $definition->fields["action_type"]                          = addslashes($definition->fields["action_type"]);
                              $definition->fields["value"]                                = addslashes($definition->fields["value"]);
                              $definition->add($definition->fields);

                           }
                        }
                     }
                  }
               }
            }
            break;
      }
   }
}

?>