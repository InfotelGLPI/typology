<?php
/*
 -------------------------------------------------------------------------
 Typology plugin for GLPI
 Copyright (C) 2006-2012 by the Typology Development Team.

 https://forge.indepnet.net/projects/typology
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Typology.

 Typology is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Typology is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Typology. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */
 
function plugin_typology_install() {
   global $DB;

   include_once (GLPI_ROOT . "/plugins/typology/inc/profile.class.php");

   if (!TableExists("glpi_plugin_typology_typologies")) {
      
      // table sql creation
      $DB->runFile(GLPI_ROOT . "/plugins/typology/sql/empty-1.1.0.sql");
      
      $query_id = "SELECT `id` FROM `glpi_notificationtemplates` WHERE `itemtype`='PluginTypologyTypology' AND `name` = 'Alert no validated typology'";
      $result = $DB->query($query_id) or die ($DB->error());
      $itemtype = $DB->result($result,0,'id');

      $query="INSERT INTO `glpi_notificationtemplatetranslations`
                                 VALUES(NULL, ".$itemtype.", '','##typology.action## : ##typology.entity##',
                        '##FOREACHitems##
   ##lang.typology.name## : ##typology.name##
   ##lang.typology.itemtype## : ##typology.itemtype##
   ##lang.typology.items_id## : ##typology.items_id##
   ##lang.typology.itemlocation## : ##typology.itemlocation##
   ##lang.typology.itemuser## : ##typology.itemuser##
   ##lang.typology.error## : ##typology.error##
   ##ENDFOREACHitems##',
   '&lt;table class=\"tab_cadre\" border=\"1\" cellspacing=\"2\" cellpadding=\"3\"&gt;
   &lt;tbody&gt;
   &lt;tr&gt;
   &lt;td style=\"text-align: left;\" bgcolor=\"#cccccc\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##lang.typology.name##&lt;/span&gt;&lt;/td&gt;
   &lt;td style=\"text-align: left;\" bgcolor=\"#cccccc\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##lang.typology.itemtype##&lt;/span&gt;&lt;/td&gt;
   &lt;td style=\"text-align: left;\" bgcolor=\"#cccccc\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##lang.typology.items_id##&lt;/span&gt;&lt;/td&gt;
   &lt;td style=\"text-align: left;\" bgcolor=\"#cccccc\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##lang.typology.itemlocation##&lt;/span&gt;&lt;/td&gt;
   &lt;td style=\"text-align: left;\" bgcolor=\"#cccccc\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##lang.typology.itemuser##&lt;/span&gt;&lt;/td&gt;
   &lt;td style=\"text-align: left;\" bgcolor=\"#cccccc\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##lang.typology.error##&lt;/span&gt;&lt;/td&gt;
   &lt;/tr&gt;
   ##FOREACHtypologyitems##
   &lt;tr&gt;
   &lt;td&gt;&lt;a href=\"##typology.url##\" target=\"_blank\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##typology.name##&lt;/span&gt;&lt;/a&gt;&lt;/td&gt;
   &lt;td&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##typology.itemtype##&lt;/span&gt;&lt;/td&gt;
   &lt;td&gt;&lt;a href=\"##typology.itemurl##\" target=\"_blank\"&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##typology.items_id##&lt;/span&gt;&lt;/a&gt;&lt;/td&gt;
   &lt;td&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##typology.itemlocation##&lt;/span&gt;&lt;/td&gt;
   &lt;td&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##typology.itemuser##&lt;/span&gt;&lt;/td&gt;
   &lt;td&gt;&lt;span style=\"font-family: Verdana; font-size: 11px; text-align: left;\"&gt;##typology.error##&lt;/span&gt;&lt;/td&gt;
   &lt;/tr&gt;
   ##ENDFOREACHtypologyitems##
   &lt;/tbody&gt;
   &lt;/table&gt;');";
      $result=$DB->query($query);

      $query = "INSERT INTO `glpi_notifications`
                                   VALUES (NULL, 'Alert no validated typology', 0, 'PluginTypologyTypology', 'AlertNotValidatedTypology',
                                          'mail',".$itemtype.",
                                          '', 1, 1, '2010-02-17 22:36:46');";
      $result=$DB->query($query);
   }

   if(TableExists("glpi_plugin_typology_typologycriterias")){

      $query = "UPDATE `glpi_plugin_typology_typologycriterias`
                     SET `itemtype`='IPAddress'
                     WHERE `itemtype`='NetworkPort'";
      $result=$DB->query($query);

      $query = "UPDATE `glpi_plugin_typology_typologycriteriadefinitions`
                     SET `field`='name;glpi_ipaddresses;itemlink'
                     WHERE `field` LIKE '%glpi_networkports%'";
      $result=$DB->query($query);
   }
   
   if(TableExists("glpi_plugin_typology_profiles")){

      $notepad_tables = array('glpi_plugin_typology_typologies');

      foreach ($notepad_tables as $t) {
         // Migrate data
         if (FieldExists($t, 'notepad')) {
            $query = "SELECT id, notepad
                      FROM `$t`
                      WHERE notepad IS NOT NULL
                            AND notepad <>'';";
            foreach ($DB->request($query) as $data) {
               $iq = "INSERT INTO `glpi_notepads`
                             (`itemtype`, `items_id`, `content`, `date`, `date_mod`)
                      VALUES ('".getItemTypeForTable($t)."', '".$data['id']."',
                              '".addslashes($data['notepad'])."', NOW(), NOW())";
               $DB->queryOrDie($iq, "0.85 migrate notepad data");
            }
            $query = "ALTER TABLE `glpi_plugin_typology_typologies` DROP COLUMN `notepad`;";
            $DB->query($query);
         }
      }
   }

   CronTask::Register('PluginTypologyTypology', 'UpdateTypology', DAY_TIMESTAMP);
   CronTask::Register('PluginTypologyTypology', 'NotValidated', DAY_TIMESTAMP);

   PluginTypologyProfile::initProfile();
   PluginTypologyProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   $migration = new Migration("2.0.0");
   $migration->dropTable('glpi_plugin_typology_profiles');
   
   return true;
}

// Uninstall process for plugin : need to return true if succeeded
function plugin_typology_uninstall() {
   global $DB;
   
   include_once (GLPI_ROOT."/plugins/typology/inc/profile.class.php");
   include_once (GLPI_ROOT."/plugins/typology/inc/menu.class.php");
   
   // Plugin tables deletion
   $tables = array("glpi_plugin_typology_typologies",
                    "glpi_plugin_typology_typologycriterias",
                    "glpi_plugin_typology_typologycriteriadefinitions",
                    "glpi_plugin_typology_typologies_items");

   foreach ($tables as $table)
      $DB->query("DROP TABLE IF EXISTS `$table`;");

   // Plugin adding information on general table deletion
   $tables_glpi = array("glpi_displaypreferences", 
                        "glpi_documents_items",
                        "glpi_bookmarks", 
                        "glpi_logs",
                        "glpi_notepads");

   foreach ($tables_glpi as $table_glpi)
      $DB->query("DELETE FROM `$table_glpi` WHERE `itemtype` = 'PluginTypologyTypology';");

   //drop rules
   $Rule = new Rule();
   $a_rules = $Rule->find("`sub_type`='PluginTypologyRuleTypology'");
   foreach ($a_rules as $data) {
      $Rule->delete($data);
   }
   
   $notif = new Notification();
   $options = array('itemtype' => 'PluginTypologyTypology',
                    'event'    => 'AlertNotValidatedTypology',
                    'FIELDS'   => 'id');
   foreach ($DB->request('glpi_notifications', $options) as $data) {
      $notif->delete($data);
   }
   
   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginTypologyProfile::getAllRights() as $right) {
      $profileRight->deleteByCriteria(array('name' => $right['field']));
   }
   PluginTypologyMenu::removeRightsFromSession();
   
   PluginTypologyProfile::removeRightsFromSession();
   
   return true;
}

function plugin_typology_postinit() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['item_purge']['typology'] = array();
   $PLUGIN_HOOKS['item_add']['typology'] = array();

   foreach (PluginTypologyTypology::getTypes(true) as $type) {
      $PLUGIN_HOOKS['item_purge']['typology'][$type]
         = array('PluginTypologyTypology_Item','cleanItemTypology');
      $PLUGIN_HOOKS['item_add']['typology'][$type]
         = array('PluginTypologyTypology_Item', 'addItem');
      $PLUGIN_HOOKS['item_update']['typology'][$type]
         = array('PluginTypologyTypology_Item', 'updateItem');
      CommonGLPI::registerStandardTab($type, 'PluginTypologyTypology_Item');
   }
}

// Define dropdown relations
function plugin_typology_getDatabaseRelations() {

   $plugin = new Plugin();
   if ($plugin->isActivated("typology"))
      return array ("glpi_entities" => array ("glpi_plugin_typology_typologies" =>"entities_id",
                                              "glpi_plugin_typology_typologycriterias" => "entities_id",
                                              "glpi_plugin_typology_typologycriteriadefinitions" => "entities_id"),
                    "glpi_plugin_typology_typologies" => array(
                                       "glpi_plugin_typology_typologycriterias" => "plugin_typology_typologies_id",
                                       "glpi_plugin_typology_typologies_items" => "plugin_typology_typologies_id"),
                    "glpi_plugin_typology_typologycriterias" => array(
                                       "glpi_plugin_typology_typologycriteriadefinitions" => "plugin_typology_typologycriterias_id"));
   else
      return array();
}

////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////
/*
// Define actions :
function plugin_typology_MassiveActions($type) {

   switch ($type) {
      default:
      // Actions from items lists
      if (in_array($type, PluginTypologyTypology::getTypes(true))) {
         return array(
            "plugin_typology_add_item" => _sx('button','Assign a typology to this material','typology'),
            "plugin_typology_del_item" => _sx('button','Remove typology from this material','typology'),
            "plugin_typology_compute_item" => _sx('button','Recalculate typology for the elements','typology'));
      }
      break;
   }
   return array();
}


// How to display specific actions ?
// options contain at least itemtype and and action
function plugin_typology_MassiveActionsDisplay($options = array()) {


   if (in_array($options['itemtype'], PluginTypologyTypology::getTypes(true))) {
      switch ($options['action']) {
         //add item to a typo
         case "plugin_typology_add_item":
            echo "</br>&nbsp;".PluginTypologyTypology::getTypeName(2)." : ";
            Dropdown::show('PluginTypologyTypology', 
                     array('name' => "plugin_typology_typologies_id"));
            echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".
               _sx('button', 'Post')."\" >";
            break;
         //delete item to a typo
         case "plugin_typology_del_item":
            echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".
               _sx('button', 'Post')."\" >";
            break;
         //delete item to a typo
         case "plugin_typology_compute_item":
            echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".
               _sx('button', 'Post')."\" >";
            break;
      }
   }
   return "";
}

// How to process specific actions ?
function plugin_typology_MassiveActionsProcess($data) {

   $typo = new PluginTypologyTypology();
   $typo_item = new PluginTypologyTypology_Item();
   $criteria = new PluginTypologyTypologyCriteria();
   $definition = new PluginTypologyTypologyCriteriaDefinition();
   
   switch ($data['action']) {

      //add item to a typo
      case "plugin_typology_add_item":
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
            
               $input = array('plugin_typology_typologies_id' => $data['plugin_typology_typologies_id'],
                              'items_id'      => $key,
                              'itemtype'      => $data['itemtype']);
               $item = new $data['itemtype']();
               if ($item->getFromDB($key)) {
                  $ruleCollection = new PluginTypologyRuleTypologyCollection($item->fields['entities_id']);
                  $fields= array();
                  $item->input = $input['plugin_typology_typologies_id'];
                  $fields=$ruleCollection->processAllRules($item->fields,$fields, array());
                  //Store rule that matched

                  if (isset($fields['_ruleid'])) {
                     if ($input['plugin_typology_typologies_id'] != $fields['plugin_typology_typologies_id']){
                        $message = __('Element not match with the rule for assigning the typology:','typology')." ".
                           Dropdown::getDropdownName('glpi_plugin_typology_typologies',$input['plugin_typology_typologies_id']);
                        Session::addMessageAfterRedirect($message,ERROR,true);
                     } else {
                        $typo_item->add($input);
                     }
                  } else {
                     $message = __('Element not match with rules for assigning a typology','typology');
                     Session::addMessageAfterRedirect($message, ERROR, true);
                  }
               }
            }
         }
         break;
      //get out an item from a store
      case "plugin_typology_del_item":
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
               $input = array('items_id'      => $key,
                              'itemtype'      => $data['itemtype']);
               $restrict = "`items_id` = '".$key."'
                        AND `itemtype` = '".$data['itemtype']."'";
               $items = getAllDatasFromTable("glpi_plugin_typology_typologies_items",$restrict);
               if (!empty($items)) {
                  foreach ($items as $item) {
                     $input = array('id' => $item["id"],
                                    'delete' => 'delete');
                  }
                  $typo_item->delete($input);
               }
            }
         }
         break;
         //add item to a typo
      case "plugin_typology_compute_item":
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
               
               $restrict = "`items_id` = '".$key."'
                        AND `itemtype` = '".$data['itemtype']."'";
               $items = getAllDatasFromTable("glpi_plugin_typology_typologies_items",$restrict);
               if (!empty($items)) {
                  foreach ($items as $item) {
                     $values = array('id' => $item["id"],
                                    'plugin_typology_typologies_id' => $item['plugin_typology_typologies_id'],
                                    'items_id'      => $key,
                                    'itemtype'      => $data['itemtype']);
                     $input=PluginTypologyTypology_Item::checkValidated($values);
                     $typo_item->update($input);
                  }
               }
            }
         }
         break;
   }
}*/

////// SEARCH FUNCTIONS ///////(){

// Define search option for types of the plugins
function plugin_typology_getAddSearchOptions($itemtype) {

   $plugin = new Plugin();
   $sopt = array();

   if ($plugin->isActivated('typology')
         && Session::haveRight("plugin_typology", READ)) {
      if (in_array($itemtype, PluginTypologyTypology::getTypes(true))) {
         $sopt[4650]['table']         = 'glpi_plugin_typology_typologies';
         $sopt[4650]['field']         = 'name';
         $sopt[4650]['name']          = PluginTypologyTypology::getTypeName(1)." - ".
                                         __('Typology\'s name','typology');
         $sopt[4650]['forcegroupby']  = true;
         $sopt[4650]['datatype']      = 'itemlink';
         $sopt[4650]['massiveaction'] = false;
         $sopt[4650]['itemlink_type'] = 'PluginTypologyTypology';
         $sopt[4650]['joinparams']    = array('beforejoin'
                                                => array('table'      => 'glpi_plugin_typology_typologies_items',
                                                         'joinparams' => array('jointype' => 'itemtype_item')));
         
         $sopt[4651]['table']         = 'glpi_plugin_typology_typologies_items';
         $sopt[4651]['field']         = 'is_validated';
         $sopt[4651]['datatype']      = 'bool';
         $sopt[4651]['massiveaction'] = false;
         $sopt[4651]['name']          = PluginTypologyTypology::getTypeName(1)." - ".
                                          __('Responding to typology\'s criteria','typology');
         $sopt[4651]['forcegroupby']  = true;
         $sopt[4651]['joinparams']    = array('jointype' => 'itemtype_item');
         
         $sopt[4652]['table']         = 'glpi_plugin_typology_typologies_items';
         $sopt[4652]['field']         = 'error';
         $sopt[4652]['name']          = PluginTypologyTypology::getTypeName(1)." - ".
                                          __('Result details');
         $sopt[4652]['forcegroupby']  = true;
         $sopt[4652]['massiveaction'] = false;
         $sopt[4652]['joinparams']    = array('jointype' => 'itemtype_item');

      }
   }
   return $sopt;
}

function plugin_typology_giveItem($type,$ID,$data,$num) {

   $searchopt=&Search::getOptions($type);
   $table=$searchopt[$ID]["table"];
   $field=$searchopt[$ID]["field"];

   switch ($type) {
      case 'Computer':
      switch ($table.'.'.$field) {

            case "glpi_plugin_typology_typologies_items.is_validated" :
               if (empty($data[$num][0]['name'])) {
                  $out = '';
               } else {
                  $validated = explode("$$", $data[$num][0]['name']);
                  $out = Dropdown::getYesNo($validated[0]);
               }
               return $out;
               break;
            case "glpi_plugin_typology_typologies_items.error" :
                  $list = explode("$$", $data[$num][0]['name']);
                  $out = PluginTypologyTypology_Item::displayErrors($list[0]);
               return $out;
               break;
         }
      break;
   }
   return "";
}

// Do special actions for dynamic report
function plugin_typology_dynamicReport($parm) {

   // Return false if no specific display is done, then use standard display
   return false;
}

?>