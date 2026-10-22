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

namespace GlpiPlugin\Typology;

use Alert;
use CommonDBTM;
use DbUtils;
use Dropdown;
use Html;
use MassiveAction;
use NotificationEvent;
use Plugin;
use Rule;
use Session;

/**
 * Class Typology
 */
class Typology extends CommonDBTM
{
    // From CommonDBTM
    public $dohistory         = true;
    public static $rightname         = "plugin_typology";
    protected $usenotepad        = true;
    protected $usenotepadrights  = true;
    protected static $forward_entity_to = [TypologyCriteria::class];

    public static $types = ['Computer'];

    public static function getIcon()
    {
        return "ti ti-list-check";
    }

    public static $types_criteria = [
        'Computer',
        'Monitor',
        'Software',
        'Peripheral',
        'Printer',
        'IPAddress',
        //      'NetworkPort'
    /*'Phone'*/];

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param integer $nb Number of items
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {

        return _n('Typology', 'Typologies', $nb, 'typology');
    }

    /**
     * Display tab for each typology
     * */
    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(TypologyCriteria::class, $ong, $options);
        $this->addStandardTab(Typology_Item::class, $ong, $options);
        $this->addStandardTab('Document', $ong, $options);
        $this->addStandardTab('Notepad', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    /**
     * Actions done when a typo is deleted from the database
     *
     * @return
     **/
    /**
     * The entity of a typology is posted by its form: one the user may reach only
     */
    public function prepareInputForUpdate($input)
    {
        if (isset($input['entities_id'])
            && (int) $input['entities_id'] != (int) ($this->fields['entities_id'] ?? -1)
            && !Session::haveAccessToEntity((int) $input['entities_id'])) {
            Session::addMessageAfterRedirect(
                __s('You do not have access to the destination entity', 'typology'),
                false,
                ERROR
            );
            return false;
        }

        return parent::prepareInputForUpdate($input);
    }

    /**
     * The criteria and their definitions belong to the entity of their typology: they follow it.
     * Their own update refuses an entity change, hence the direct writes.
     */
    public function post_updateItem($history = true)
    {
        global $DB;

        parent::post_updateItem($history);

        if (!in_array('entities_id', $this->updates, true)
            && !in_array('is_recursive', $this->updates, true)) {
            return;
        }

        $values = [
            'entities_id'  => (int) $this->fields['entities_id'],
            'is_recursive' => (int) $this->fields['is_recursive'],
        ];
        $criterias_ids = array_column(iterator_to_array($DB->request([
            'SELECT' => 'id',
            'FROM'   => TypologyCriteria::getTable(),
            'WHERE'  => ['plugin_typology_typologies_id' => $this->getID()],
        ])), 'id');
        if (!empty($criterias_ids)) {
            $DB->update(TypologyCriteria::getTable(), $values, ['id' => $criterias_ids]);
            $DB->update(
                TypologyCriteriaDefinition::getTable(),
                $values,
                ['plugin_typology_typologycriterias_id' => $criterias_ids]
            );
        }
    }

    public function cleanDBonPurge()
    {

        //Clean typology_item
        $temp1 = new Typology_Item();
        $temp1->deleteByCriteria(['plugin_typology_typologies_id' => $this->fields['id']]);

        //Clean typologycriteria
        $temp2 = new TypologyCriteria();
        $temp2->deleteByCriteria(['plugin_typology_typologies_id' => $this->fields['id']]);

        //Clean rule
        Rule::cleanForItemAction($this);

        return true;
    }

    /**
     * For other plugins, add a type to the linkable types
     *
     * @since version 1.3.0
     *
     * @param $type string class name
     **/
    public static function registerType($type)
    {
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
    public static function getTypes($all = false)
    {

        if ($all) {
            return self::$types;
        }

        // Only allowed types
        $types = self::$types;
        $dbu = new DbUtils();
        foreach ($types as $key => $type) {
            if (!($item = $dbu->getItemForItemtype($type))) {
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
    public static function registerTypeCriteria($typeCriteria)
    {
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
    public static function getTypesCriteria()
    {

        // Only allowed types
        $types_criteria = self::$types_criteria;
        $devtypes       = self::getComputerDeviceTypes();
        $dbu = new DbUtils();
        foreach ($types_criteria as $key => $type_criteria) {
            if (!($item = $dbu->getItemForItemtype($type_criteria))) {
                continue;
            }

            //         if (!$item->canView()) {
            //            unset($types_criteria[$key]);
            //         }
        }

        foreach ($devtypes as $itemtype) {
            $device = new $itemtype();
            if ($device->can(-1, READ)) {
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
     * @return boolean item found
     **/
    public function showForm($ID, $options = [])
    {

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td>";
        echo "<td>";
        echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
        echo "</td>";
        echo "<td rowspan=2>" . __('Comments') . "</td>";
        echo "<td rowspan=2>";
        Html::textarea(['name'            => 'comment',
            'value'           => $this->fields["comment"],
            'cols'       => 45,
            'rows'       => 8,
            'enable_richtext' => false]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";

        if (!$ID) {
            echo "<td>" . __('Last update') . "</td>";
            echo "<td>";
            echo Html::convDateTime($_SESSION["glpi_currenttime"]);
        } else {
            echo "<td>" . __('Last update') . "</td>";
            echo "<td>" . ($this->fields["date_mod"] ? Html::convDateTime($this->fields["date_mod"])
               : __('Never'));
        }

        echo "</td></tr>";
        echo Html::hidden('entities_id', ['value' => $_SESSION["glpiactive_entity"]]);

        $this->showFormButtons($options);

        return true;
    }

    /**
     * Provides search options configuration. Do not rely directly
     * on this, @see CommonDBTM::searchOptions instead.
     *
     * @since 9.3
     *
     * This should be overloaded in Class
     *
     * @return array a *not indexed* array of search options
     *
     * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
     **/
    public function rawSearchOptions()
    {

        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];
        $tab[] = [
            'id'            => 1,
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'itemlink_type' => $this->getType(),
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 2,
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
        ];
        $tab[] = [
            'id'            => 14,
            'table'         => $this->getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'massiveaction' => false,
            'datatype'      => 'datetime',
        ];
        $tab[] = [
            'id'            => 16,
            'table'         => $this->getTable(),
            'field'         => 'comment',
            'name'          => __('Comments'),
            'datatype'      => 'text',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id'            => 80,
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => __('Entity'),
            'massiveaction' => false,
            'datatype'      => 'dropdown',
        ];
        $tab[] = [
            'id'            => 86,
            'table'         => $this->getTable(),
            'field'         => 'is_recursive',
            'name'          => __('Child entities'),
            'datatype'      => 'bool',
            'massiveaction' => true,
        ];

        return $tab;
    }


    /**
     * @return array
     */
    /**
     * Every type a criterion may hold, whatever the rights of the session (cron included):
     * the allow-list of the stored and posted criterion types
     *
     * @return list<string>
     */
    public static function getAllTypesCriteria(): array
    {
        return array_values(array_unique(array_merge(self::$types_criteria, self::getComputerDeviceTypes())));
    }

    public static function getComputerDeviceTypes()
    {
        return [/*1 => 'DeviceMotherboard', */
            2 => 'DeviceProcessor', 3 => 'DeviceMemory',
            4 => 'DeviceHardDrive',/*,   5 => 'DeviceNetworkCard', 6 => 'DeviceDrive',
                   7 => 'DeviceControl',     8 => 'DeviceGraphicCard', 9 => 'DeviceSoundCard',
                   10 => 'DevicePci',        11 => 'DeviceCase',       12 => 'DevicePowerSupply'*/];
    }

    ////// CRON FUNCTIONS ///////
    //Cron action
    /**
     * @param $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {

        switch ($name) {
            case 'UpdateTypology':
                return [
                    'description' => __('Recalculate typology for the elements', 'typology')];   // Optional
            case 'NotValidated':
                return [
                    'description' => __('Elements not match with the typology', 'typology')];   // Optional
        }
        return [];
    }

    /**
     * @return string[]
     */
    public function queryUpdateTypology()
    {

        $query = [
            'SELECT' => '*',
            'FROM' => 'glpi_plugin_typology_typologies_items',
        ];

        return $query;
    }

    /**
     * @return array
     */
    public function queryNotValidated()
    {

        $query = [
            'SELECT' => ['glpi_plugin_typology_typologies_items.*',
                'glpi_plugin_typology_typologies.name',
                'glpi_plugin_typology_typologies.entities_id'],
            'FROM' => 'glpi_plugin_typology_typologies_items',
            'LEFT JOIN'       => [
                'glpi_plugin_typology_typologies' => [
                    'ON' => [
                        'glpi_plugin_typology_typologies_items' => 'plugin_typology_typologies_id',
                        'glpi_plugin_typology_typologies'          => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_plugin_typology_typologies_items.is_validated' => 0,
            ],
            'GROUPBY'   => 'glpi_plugin_typology_typologies.name',
        ];

        return $query;
    }


    /**
     * Cron action on tasks : UpdateTypology
     *
     * @param $task
     *
     **/
    public static function cronUpdateTypology($task = null)
    {
        global $DB;

        $cron_status = 0;
        $message     = [];

        $typo        = new self();
        $query_items = $typo->queryUpdateTypology();

        $querys = [Alert::END => $query_items];

        $task_infos    = [];
        $task_messages = [];

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                //update all linked item to a typology
                if (isset($data['id'])) {
                    $input = Typology_Item::checkValidated($data);
                }

                if ($data['error'] != $input['error']) {
                    $typo_item = new Typology_Item();
                    $typo_item->getFromDB($data['id']);

                    $typo_item->update($input);
                    $typo->getFromDB($data['plugin_typology_typologies_id']);
                    $entity = $typo->fields['entities_id'];
                    if (!isset($message[$entity])) {
                        $message[$entity] = '';
                    }
                    $task_infos[$type][$entity][] = $data;
                    if (!isset($task_messages[$type][$entity])) {
                        $task_messages[$type][$entity] = __('Typology of the linked elements is updated.', 'typology') . "\n";
                    }
                    $task_messages[$type][$entity] .= $message[$entity];
                }
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($task_infos[$type] as $entity => $items) {
                Plugin::loadLang('typology');

                $message     = $task_messages[$type][$entity];
                $cron_status = 1;
                // Messages go to the task log only, never to the HTML of a session
                if ($task) {
                    $task->log(Dropdown::getDropdownName(
                        "glpi_entities",
                        $entity
                    ) . ":  $message\n");
                    $task->addVolume(count($items));
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron action on tasks : UpdateTypology
     *
     * @param $task
     *
     **/
    public static function cronNotValidated($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $cron_status = 0;
        $message     = [];

        $typo        = new self();
        $query_items = $typo->queryNotValidated();

        $querys = [Alert::END => $query_items];

        $task_infos    = [];
        $task_messages = [];
        $dbu = new DbUtils();

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                // Get items entity
                $item = $dbu->getItemForItemtype($data['itemtype']);
                $item->getFromDB($data['items_id']);

                if (!isset($item->fields['is_deleted'])
                || !$item->fields['is_deleted']) {
                    if (isset($item->fields['entities_id'])) {
                        $entity = $item->fields['entities_id'];

                        // Plain text: the message only goes to the log of the task
                        $message                      = $data["name"] . ": "
                                                  . $data["error"] . "\n";
                        $task_infos[$type][$entity][] = $data;

                        if (!isset($task_messages[$type][$entity])) {
                            $task_messages[$type][$entity] = __('Elements not match with the typology', 'typology') . "\n";
                        }
                        $task_messages[$type][$entity] .= $message;
                    }
                }
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($task_infos[$type] as $entity => $items) {
                Plugin::loadLang('typology');

                $message     = $task_messages[$type][$entity];
                $cron_status = 1;

                if (NotificationEvent::raiseEvent(
                    "AlertNotValidatedTypology",
                    new Typology(),
                    ['entities_id' => $entity,
                        'items'       => $items]
                )) {
                    $message     = $task_messages[$type][$entity];
                    $cron_status = 1;
                    // Messages go to the task log only, never to the HTML of a session
                    if ($task) {
                        $task->log(Dropdown::getDropdownName(
                            "glpi_entities",
                            $entity
                        ) . ":  $message\n");
                        $task->addVolume(1);
                    }
                } elseif ($task) {
                    $task->log(Dropdown::getDropdownName(
                        "glpi_entities",
                        $entity
                    ) . ":  $message\n");
                    $task->addVolume(count($items));
                }
            }
        }

        return $cron_status;
    }


    /**
     * Get the specific massive actions
     *
     * @since version 0.84
     *
     * @param $checkitem
     *
     * @return
     **/
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::getCurrentInterface() == 'central') {
            if ($isadmin) {
                $actions['GlpiPlugin\Typology\Typology' . MassiveAction::CLASS_ACTION_SEPARATOR . 'duplicate'] = _sx('button', 'Duplicate');

                if (Session::haveRight('transfer', READ)
                && Session::isMultiEntitiesMode()) {
                    $actions['GlpiPlugin\Typology\Typology' . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __('Transfer');
                }
            }
        }
        return $actions;
    }

    /**
     * Class-specific method used to show the fields to specify the massive action
     *
     * @since 0.85
     *
     * @param MassiveAction $ma the current massive action object
     *
     * @return boolean false if parameters displayed ?
     **/
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {

        switch ($ma->getAction()) {
            case "duplicate":
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
            case "transfer":
                Dropdown::show('Entity');
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     **/
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {

        $criteria   = new TypologyCriteria();
        $definition = new TypologyCriteriaDefinition();
        $dbu        = new DbUtils();

        switch ($ma->getAction()) {
            case "transfer":
                $input = $ma->getInput();
                $entities_id = (int) ($input['entities_id'] ?? -1);
                if ($item->getType() == Typology::class) {
                    foreach ($ids as $key) {
                        $key = (int) $key;
                        // The ids and the destination are posted: the typology must be
                        // updatable and the destination entity within the reach of the user
                        if (!$item->can($key, UPDATE) || !Session::haveAccessToEntity($entities_id)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            continue;
                        }

                        // The criteria and their definitions follow (post_updateItem())
                        unset($values);
                        $values["id"]          = $key;
                        $values["entities_id"] = $entities_id;

                        if ($item->update($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;

            case 'duplicate':
                if ($item->getType() == Typology::class) {
                    foreach ($ids as $key) {
                        $key = (int) $key;
                        // The ids are posted: the source must be readable and a typology creatable
                        if (!$item->can($key, READ) || !Typology::canCreate()) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            continue;
                        }

                        $restrict = ["plugin_typology_typologies_id" => $key];
                        $crits    = $dbu->getAllDataFromTable(
                            "glpi_plugin_typology_typologycriterias",
                            $restrict
                        );

                        // GLPI stores values raw: copied as they are, without addslashes()
                        unset($item->fields["id"]);
                        $item->fields["name"] = $item->fields["name"] . " Copy";
                        // The copy is created in an entity the user may create in
                        if (!$item->can(-1, CREATE, $item->fields) || !($newIDtypo = $item->add($item->fields))) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            continue;
                        }
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        if (!empty($crits)) {
                            foreach ($crits as $crit) {
                                $criteria->getFromDB($crit["id"]);

                                $condition = ["plugin_typology_typologycriterias_id" => $crit["id"]];
                                $defs      = $dbu->getAllDataFromTable(
                                    "glpi_plugin_typology_typologycriteriadefinitions",
                                    $condition
                                );

                                unset($criteria->fields["id"]);
                                $criteria->fields["plugin_typology_typologies_id"] = $newIDtypo;
                                $criteria->fields["entities_id"]                   = $item->fields["entities_id"];
                                $newIDcrit                                         = $criteria->add($criteria->fields);

                                if ($newIDcrit && !empty($defs)) {
                                    foreach ($defs as $def) {
                                        $definition->getFromDB($def["id"]);

                                        unset($definition->fields["id"]);
                                        $definition->fields["plugin_typology_typologycriterias_id"] = $newIDcrit;
                                        $definition->fields["entities_id"]                          = $item->fields["entities_id"];
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


    public static function removeRightsFromSession()
    {
        if (isset($_SESSION['glpimenu']['tools']['types'][Typology::class])) {
            unset($_SESSION['glpimenu']['tools']['types'][Typology::class]);
        }
        if (isset($_SESSION['glpimenu']['tools']['content'][Typology::class])) {
            unset($_SESSION['glpimenu']['tools']['content'][Typology::class]);
        }
    }
}
