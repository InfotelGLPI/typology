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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginTypologyRuleTypologyCollection extends RuleCollection {

   // From RuleCollection
   public $stop_on_first_match=true;
   public static $right='plugin_typology';
   public $menu_option='typologies';
   
   function getTitle() {

      return __('Rules for assigning a typology to a computer','typology');
   }
   
   function __construct($entity=0) {
      $this->entity = $entity;
   }
   
   function showInheritedTab() {
      return Session::haveRight("plugin_typology", UPDATE) 
               && ($this->entity);
   }

   function showChildrensTab() {
      return Session::haveRight("plugin_typology", UPDATE) 
               && (count($_SESSION['glpiactiveentities']) > 1);
   }
}
?>