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

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class PluginTypologyNotificationTargetTypology extends NotificationTarget {
   
   function getEvents() {

      return array ('AlertNotValidatedTypology' => __('Elements not match with the typology','typology'));
   }

   function getDatasForTemplate($event,$options=array()) {
      global $CFG_GLPI;
      
      if ($event == 'AlertNotValidatedTypology') {
         
         $this->datas['##typology.entity##'] =
                           Dropdown::getDropdownName('glpi_entities',
                                                     $options['entities_id']);
         $this->datas['##lang.typology.entity##'] =__('Entity');
         $this->datas['##typology.action##'] = __('Elements not match with the typology','typology');

         $this->datas['##lang.typology.name##'] = PluginTypologyTypology::getTypeName(1);
         $this->datas['##lang.typology.itemtype##'] = __('Type');
         $this->datas['##lang.typology.items_id##'] = __('Name');
         $this->datas['##lang.typology.error##'] = __('Error');
         $this->datas['##lang.typology.url##'] = __('Link to the typology','typology');
         $this->datas['##lang.typology.itemurl##'] = __('Link to the element','typology');
         $this->datas['##lang.typology.itemuser##'] = __('User');
         $this->datas['##lang.typology.itemlocation##'] = __('Location');

         foreach($options['items'] as $id => $item) {
            $tmp = array();
            
            $tmp['##typology.name##'] = $item['name'];
            $itemtype = new $item['itemtype']();
            $itemtype->getFromDB($item["items_id"]);
            $tmp['##typology.itemtype##'] = $itemtype->getTypeName();
            $tmp['##typology.items_id##'] = $itemtype->getName();
            $tmp['##typology.error##'] = PluginTypologyTypology_Item::displayErrors($item['error'],false);
            $tmp['##typology.url##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=plugin_typology_".
               $item['plugin_typology_typologies_id']);
            $tmp['##typology.itemurl##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=".
               Toolbox::strtolower($item['itemtype'])."_".$item["items_id"]);
            $tmp['##typology.itemuser##'] = getUserName($itemtype->fields["users_id"]);
            $tmp['##typology.itemlocation##'] = Dropdown::getDropdownName("glpi_locations",
               $itemtype->fields['locations_id']);

            $this->datas['typologyitems'][] = $tmp;
         }
      }
   }
   
   function getTags() {

      $tags = array('typology.name'             => PluginTypologyTypology::getTypeName(1),
                   'typology.itemtype'          => __('Type'),
                   'typology.items_id'          => __('Name'),
                   'typology.error'             => __('Error'),
                   'typology.url'               => __('Link to the typology','typology'),
                   'typology.itemurl'           => __('Link to the element','typology'),
                   'typology.itemuser'          => __('User'),
                   'typology.itemlocation'      => __('Location'));
      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'=>$tag,'label'=>$label,
                                   'value'=>true));
      }
      asort($this->tag_descriptions);
   }
}

?>