<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 typology plugin for GLPI
 Copyright (C) 2009-2022 by the typology Development Team.

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

use GlpiPlugin\Typology\NotificationTargetTypology;
use GlpiPlugin\Typology\Profile;
use GlpiPlugin\Typology\RuleTypology;
use GlpiPlugin\Typology\Typology;
use GlpiPlugin\Typology\Typology_Item;

function plugin_typology_install()
{
    global $DB;

    if (!$DB->tableExists("glpi_plugin_typology_typologies")) {
        $update = false;
        // table sql creation
        $DB->runFile(PLUGIN_TYPOLOGY_DIR . "/sql/empty-5.0.0.sql");

        // Add record notification
        call_user_func([NotificationTargetTypology::class, 'install']);
    }

    if ($DB->tableExists("glpi_plugin_typology_typologycriterias")) {
        $query = "UPDATE `glpi_plugin_typology_typologycriterias`
                     SET `itemtype`='IPAddress'
                     WHERE `itemtype`='NetworkPort'";
        $DB->doQuery($query);

        $query = "UPDATE `glpi_plugin_typology_typologycriteriadefinitions`
                     SET `field`='name;glpi_ipaddresses;itemlink'
                     WHERE `field` LIKE '%glpi_networkports%'";
        $DB->doQuery($query);
    }

    if ($DB->tableExists("glpi_plugin_typology_profiles")) {
        $notepad_tables = ['glpi_plugin_typology_typologies'];
        $dbu = new DbUtils();
        foreach ($notepad_tables as $t) {
            // Migrate data
            $iterator = $DB->request([
                'SELECT' => [
                    'notepad',
                    'id',
                ],
                'FROM' => $t,
                'WHERE' => [
                    'NOT' => ['notepad' => null],
                    'notepad' => ['<>', ''],
                ],
            ]);
            if (count($iterator) > 0) {
                foreach ($iterator as $data) {
                    $iq = "INSERT INTO `glpi_notepads`
                          (`itemtype`, `items_id`, `content`, `date`, `date_mod`)
                   VALUES ('" . $dbu->getItemTypeForTable($t) . "', '" . $data['id'] . "',
                           '" . addslashes($data['notepad']) . "', NOW(), NOW())";
                    $DB->doQuery($iq, "0.85 migrate notepad data");
                }
            }
            $query = "ALTER TABLE `glpi_plugin_typology_typologies` DROP COLUMN `notepad`;";
            $DB->doQuery($query);
        }
    }

    CronTask::Register(Typology::class, 'UpdateTypology', DAY_TIMESTAMP);
    CronTask::Register(Typology::class, 'NotValidated', DAY_TIMESTAMP);

    Profile::initProfile();
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    $migration = new Migration("2.3.0");
    $migration->dropTable('glpi_plugin_typology_profiles');

    return true;
}

// Uninstall process for plugin : need to return true if succeeded
/**
 * @return bool
 */
function plugin_typology_uninstall()
{
    global $DB;

    //drop rules
    $Rule = new Rule();
    $a_rules = $Rule->find(['sub_type' => RuleTypology::class]);
    foreach ($a_rules as $data) {
        $Rule->delete($data);
    }

    // Plugin tables deletion
    $tables = ["glpi_plugin_typology_typologies",
        "glpi_plugin_typology_typologycriterias",
        "glpi_plugin_typology_typologycriteriadefinitions",
        "glpi_plugin_typology_typologies_items"];

    foreach ($tables as $table) {
        $DB->dropTable($table, true);
    }

    $tables_glpi = ["glpi_displaypreferences",
        "glpi_documents_items",
        "glpi_savedsearches",
        "glpi_notepads",
        "glpi_alerts",
        "glpi_links_itemtypes",
        "glpi_items_tickets",
        "glpi_dropdowntranslations",
        "glpi_impactitems"];

    foreach ($tables_glpi as $table_glpi) {
        $DB->delete($table_glpi, ['itemtype' => Typology::class]);
    }


    $notif = new Notification();
    $options = ['itemtype' => Typology::class,
        'event'    => 'AlertNotValidatedTypology'];
    foreach ($DB->request([
        'FROM' => 'glpi_notifications',
        'WHERE' => $options,
    ]) as $data) {
        $notif->delete($data);
    }

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }
    Typology::removeRightsFromSession();

    Profile::removeRightsFromSession();

    return true;
}

function plugin_typology_postinit()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['item_purge']['typology'] = [];
    $PLUGIN_HOOKS['item_add']['typology'] = [];

    foreach (Typology::getTypes(true) as $type) {
        $PLUGIN_HOOKS['item_purge']['typology'][$type]
           = [Typology_Item::class,'cleanItemTypology'];
        $PLUGIN_HOOKS['item_add']['typology'][$type]
           = [Typology_Item::class, 'addItem'];
        $PLUGIN_HOOKS['item_update']['typology'][$type]
           = [Typology_Item::class, 'updateItem'];
        CommonGLPI::registerStandardTab($type, Typology_Item::class);
    }
}

// Define dropdown relations
/**
 * @return array
 */
function plugin_typology_getDatabaseRelations()
{

    if (Plugin::isPluginActive("typology")) {
        return  ["glpi_entities" =>  ["glpi_plugin_typology_typologies" => "entities_id",
            "glpi_plugin_typology_typologycriterias" => "entities_id",
            "glpi_plugin_typology_typologycriteriadefinitions" => "entities_id"],
            "glpi_plugin_typology_typologies" => [
                "glpi_plugin_typology_typologycriterias" => "plugin_typology_typologies_id",
                "glpi_plugin_typology_typologies_items" => "plugin_typology_typologies_id"],
            "glpi_plugin_typology_typologycriterias" => [
                "glpi_plugin_typology_typologycriteriadefinitions" => "plugin_typology_typologycriterias_id"]];
    } else {
        return [];
    }
}

////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////

// Define actions :
/**
 * @param $type
 *
 * @return array
 */
function plugin_typology_MassiveActions($type)
{

    if (Plugin::isPluginActive('typology')) {
        switch ($type) {
            default:
                // Actions from items lists
                if (in_array($type, Typology::getTypes(true))) {
                    return [
                        'GlpiPlugin\Typology\Typology_Item' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_item' => __('Assign a typology to this material', 'typology'),
                        'GlpiPlugin\Typology\Typology_Item' . MassiveAction::CLASS_ACTION_SEPARATOR . 'delete_item' => __('Delete the typology of this material', 'typology'),
                        'GlpiPlugin\Typology\Typology_Item' . MassiveAction::CLASS_ACTION_SEPARATOR . 'update_allitem' => __('Recalculate typology for the elements', 'typology')];
                }
                break;
        }
    }
    return [];
}

////// SEARCH FUNCTIONS ///////(){

// Define search option for types of the plugins
/**
 * @param $itemtype
 *
 * @return array
 */
function plugin_typology_getAddSearchOptions($itemtype)
{

    $sopt = [];

    if (Plugin::isPluginActive('typology')
          && Session::haveRight("plugin_typology", READ)) {
        if (in_array($itemtype, Typology::getTypes(true))) {
            $sopt[4650]['table']         = 'glpi_plugin_typology_typologies';
            $sopt[4650]['field']         = 'name';
            $sopt[4650]['name']          = Typology::getTypeName(1) . " - "
                                           . __('Typology\'s name', 'typology');
            $sopt[4650]['forcegroupby']  = true;
            $sopt[4650]['datatype']      = 'itemlink';
            $sopt[4650]['massiveaction'] = false;
            $sopt[4650]['itemlink_type'] = Typology::class;
            $sopt[4650]['joinparams']    = ['beforejoin'
                                                     => ['table'      => 'glpi_plugin_typology_typologies_items',
                                                         'joinparams' => ['jointype' => 'itemtype_item']]];

            $sopt[4651]['table']         = 'glpi_plugin_typology_typologies_items';
            $sopt[4651]['field']         = 'is_validated';
            $sopt[4651]['datatype']      = 'bool';
            $sopt[4651]['massiveaction'] = false;
            $sopt[4651]['name']          = Typology::getTypeName(1) . " - "
                                           . __('Responding to typology\'s criteria', 'typology');
            $sopt[4651]['forcegroupby']  = true;
            $sopt[4651]['joinparams']    = ['jointype' => 'itemtype_item'];

            $sopt[4652]['table']         = 'glpi_plugin_typology_typologies_items';
            $sopt[4652]['field']         = 'error';
            $sopt[4652]['name']          = Typology::getTypeName(1) . " - "
                                           . __('Result details');
            $sopt[4652]['forcegroupby']  = true;
            $sopt[4652]['massiveaction'] = false;
            $sopt[4652]['joinparams']    = ['jointype' => 'itemtype_item'];
        }
    }
    return $sopt;
}

/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
 *
 * @return
 */
function plugin_typology_giveItem($type, $ID, $data, $num)
{

    $options = Search::getOptions($type);
    $searchopt = & $options;
    $table = $searchopt[$ID]["table"];
    $field = $searchopt[$ID]["field"];

    switch ($type) {
        case 'Computer':
            switch ($table . '.' . $field) {

                case "glpi_plugin_typology_typologies_items.is_validated":
                    if (empty($data[$num][0]['name'])) {
                        $out = '';
                    } else {
                        $validated = explode("$$", $data[$num][0]['name']);
                        $out = Dropdown::getYesNo($validated[0]);
                    }
                    return $out;
                case "glpi_plugin_typology_typologies_items.error":
                    $list = explode("$$", $data[$num][0]['name']);
                    $out = Typology_Item::displayErrors($list[0]);
                    return $out;
            }
            break;
    }
    return "";
}

// Do special actions for dynamic report
/**
 * @param $parm
 *
 * @return bool
 */
function plugin_typology_dynamicReport($parm)
{

    // Return false if no specific display is done, then use standard display
    return false;
}
