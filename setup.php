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

// Init the hooks of the plugins -Needed
function plugin_init_typology() {
   global $PLUGIN_HOOKS;
   
   $PLUGIN_HOOKS['add_css']['typology']        = 'typology.css';
   $PLUGIN_HOOKS['csrf_compliant']['typology'] = true;
   $PLUGIN_HOOKS['change_profile']['typology'] = array('PluginTypologyProfile','initProfile');

   if (Session::getLoginUserID()) {

      Plugin::registerClass('PluginTypologyProfile',
         array('addtabon' => 'Profile'));
      
      Plugin::registerClass('PluginTypologyTypology', array(
         'notificationtemplates_types' => true,
      ));
      // Display a menu entry ?
      if (Session::haveRight("plugin_typology", READ)) {
         // menu entry
         $PLUGIN_HOOKS['menu_toadd']['typology'] = array('tools'   => 'PluginTypologyMenu');
      }
      
      if (Session::haveRight("plugin_typology", UPDATE)) {
         //use massiveaction in the plugin
         $PLUGIN_HOOKS['use_massive_action']['typology']=1;
         $PLUGIN_HOOKS['redirect_page']['typology'] = 'front/typology.form.php';
      }

      Plugin::registerClass('PluginTypologyRuleTypologyCollection', array(
         'rulecollections_types' => true
      ));
      
      if (class_exists('PluginBehaviorsCommon')) {
         PluginBehaviorsCommon::addCloneType('PluginTypologyRuleTypology','PluginBehaviorsRule');
      }
      
      $PLUGIN_HOOKS['post_init']['typology'] = 'plugin_typology_postinit';
   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_typology() {

   return array (
      'name'           => _n('Typology', 'Typologies', 2, 'typology'),
      'version'        => '2.3.1',
      'author'         => "<a href='http://infotel.com/services/expertise-technique/glpi/'>Infotel</a>",
      'license'        => 'GPLv2+',
      'homepage'       => 'https://github.com/InfotelGLPI/typology',
      'minGlpiVersion' => '9.1');

}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_typology_check_prerequisites() {
   if (version_compare(GLPI_VERSION,'9.1','lt') || version_compare(GLPI_VERSION,'9.2','ge')) {
      _e('This plugin requires GLPI >= 9.1', 'typology');
      return false;
   }
   return true;
}

// Uninstall process for plugin : need to return true if succeeded : may display messages or add to message after redirect
function plugin_typology_check_config() {
   return true;
}
?>
