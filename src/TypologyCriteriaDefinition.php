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

use Ajax;
use CommonDBChild;
use CommonDBTM;
use CommonDevice;
use CommonGLPI;
use CommonTreeDropdown;
use Computer;
use DbUtils;
use DBmysql;
use DeviceHardDrive;
use DeviceMemory;
use DeviceProcessor;
use Dropdown;
use Glpi\Asset\Asset_PeripheralAsset;
use Glpi\DBAL\QueryExpression;
use Html;
use IPAddress;
use Monitor;
use NetworkName;
use NetworkPort;
use Peripheral;
use Printer;
use Session;
use Software;
use User;

/**
 * Class TypologyCriteriaDefinition
 */
class TypologyCriteriaDefinition extends CommonDBChild
{

    public static $itemtype  = TypologyCriteria::class;
    public static $items_id  = 'plugin_typology_typologycriterias_id';
    public $dohistory = true;
    static $rightname = "plugin_typology";

    /** Operators a definition may hold */
    public const ACTION_TYPES = ['contains', 'notcontains', 'equals', 'notequals', 'lessthan', 'morethan',
        'under', 'notunder', 'regex_match', 'regex_not_match'];

    public function prepareInputForAdd($input)
    {
        $input = $this->checkDefinition($input);
        if ($input === false) {
            return false;
        }

        // A definition belongs to the entity of its criterion, which the user must be allowed to update
        $criteria = new TypologyCriteria();
        if (!isset($input['plugin_typology_typologycriterias_id'])
            || !$criteria->can((int) $input['plugin_typology_typologycriterias_id'], UPDATE)) {
            Session::addMessageAfterRedirect(__s('No right to update the criterion', 'typology'), false, ERROR);
            return false;
        }
        $input['plugin_typology_typologycriterias_id'] = $criteria->getID();
        $input['entities_id']                          = $criteria->fields['entities_id'];
        $input['is_recursive']                         = $criteria->fields['is_recursive'];

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        // The parent criterion and so the entity of a definition are fixed once created
        unset($input['plugin_typology_typologycriterias_id'], $input['entities_id'], $input['is_recursive']);

        $input = $this->checkDefinition($input);

        return $input === false ? false : parent::prepareInputForUpdate($input);
    }

    /**
     * Check a "column;table;type" field posted to the AJAX endpoints
     * and that the user may update the criterion it is chosen for
     *
     * @param mixed $typocrit_id
     * @param mixed $field
     *
     * @return bool
     */
    public static function canEditField($typocrit_id, $field): bool
    {
        $criteria = new TypologyCriteria();
        if (!$criteria->can((int) $typocrit_id, UPDATE)) {
            return false;
        }

        return self::isValidField($field);
    }

    /**
     * @param mixed $field "column;table;type"
     *
     * @return bool
     */
    private static function isValidField($field): bool
    {
        global $DB;

        if (!is_string($field)) {
            return false;
        }
        $parts = explode(';', $field);

        return count($parts) === 3
            && $DB->tableExists($parts[1])
            && ($parts[0] === 'count' || $DB->fieldExists($parts[1], $parts[0] === 'softwareversions_id' ? 'id' : $parts[0]))
            // Datatype of a search option (bool, number, dropdown...), empty for the special ones
            && preg_match('/^[a-z_]*$/', $parts[2]) === 1;
    }

    /**
     * The field ("column;table;type") and the operator are posted, then used to build the
     * compliance query of every computer: the table and the column must exist, the type and
     * the operator be among the known ones
     */
    private function checkDefinition(array $input): array|false
    {
        if (isset($input['field']) && !self::isValidField($input['field'])) {
            Session::addMessageAfterRedirect(__s('Invalid field', 'typology'), false, ERROR);
            return false;
        }
        if (isset($input['action_type']) && !in_array($input['action_type'], self::ACTION_TYPES, true)) {
            Session::addMessageAfterRedirect(__s('Invalid operator', 'typology'), false, ERROR);
            return false;
        }
        // A regular expression is evaluated on every computer: it must be a valid one
        if (in_array($input['action_type'] ?? '', ['regex_match', 'regex_not_match'], true)
            && self::matchRegex((string) ($input['value'] ?? ''), '') === null) {
            Session::addMessageAfterRedirect(__s('Invalid regular expression', 'typology'), false, ERROR);
            return false;
        }

        return $input;
    }

    /**
     * Regular expression of a definition, with a bounded backtracking: a pattern saved by a user
     * must not freeze the console nor the automatic action
     *
     * @param string $pattern
     * @param string $subject
     *
     * @return bool|null null for an invalid pattern or a failed evaluation
     */
    public static function matchRegex(string $pattern, string $subject): ?bool
    {
        $backtrack_limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '100000');
        try {
            $result = @preg_match($pattern, $subject);
        } finally {
            ini_set('pcre.backtrack_limit', (string) $backtrack_limit);
        }

        return $result === false ? null : $result === 1;
    }

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

        return _n('Definition', 'Definitions', $nb, 'typology');
    }

    public static function getIcon()
    {
        return Typology::getIcon();
    }

   /**
    * display typologycriteriaDefinition's tab for each typologycriteria
    *
    * @param CommonGLPI $item
    * @param int        $withtemplate
    *
    * @return string
    */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (!$withtemplate) {
            switch ($item->getType()) {
                case TypologyCriteria::class:
                    $nb = self::countForItem($item->fields['id']);
                    return [self::createTabEntry(self::getTypeName(), $nb)];
            }
        }
        return '';
    }

   /**
    * Count of definitions
    *
    * @param $id
    *
    * @return int
    */
    static function countForItem($id)
    {
        $typoCritDef = new TypologyCriteriaDefinition();
        $datas       = $typoCritDef->find(['plugin_typology_typologycriterias_id' => $id]);
        return count($datas);
    }

   /**
    * display tab's content for each typologycriteria
    *
    * @static
    *
    * @param CommonGLPI $item
    * @param int        $tabnum
    * @param int        $withtemplate
    *
    * @return bool|true
    */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == TypologyCriteria::class) {
            if (Session::haveRight("plugin_typology", READ)) {
                self::showForCriteria($item);
            } else {
                echo __('You don\'t have right to create a definition for this criteria. Thank to contact a person having this right.', 'typology');
            }
        }
        return true;
    }

   /**
    * Display the add Definition form
    *
    * @param $typocrit_id
    **/
    static function showForCriteria(TypologyCriteria $typocrit)
    {
        global $DB;

        $typocrit_id = $typocrit->getField('id');

        $canedit = Session::haveRight("plugin_typology", UPDATE);
        $rand    = mt_rand();

        $definitions = 'glpi_plugin_typology_typologycriteriadefinitions';
        $iterator    = $DB->request([
            'SELECT'    => [
                "$definitions.id",
                'glpi_plugin_typology_typologycriterias.itemtype',
                'glpi_plugin_typology_typologycriterias.link',
                "$definitions.field",
                "$definitions.action_type",
                "$definitions.value",
                "$definitions.plugin_typology_typologycriterias_id",
            ],
            'FROM'      => $definitions,
            'LEFT JOIN' => [
                'glpi_plugin_typology_typologycriterias' => [
                    'ON' => ['glpi_plugin_typology_typologycriterias' => 'id', $definitions => 'plugin_typology_typologycriterias_id'],
                ],
            ],
            'WHERE'     => ["$definitions.plugin_typology_typologycriterias_id" => (int) $typocrit_id],
            'ORDER'     => "$definitions.id",
        ]);

        echo "<div class='firstbloc'>";

        if ($iterator) {
            if (Session::haveRight("plugin_typology", UPDATE)) {
                echo "<form method='post' action='".PLUGIN_TYPOLOGY_WEBDIR."/front/typologycriteria.form.php'>";
                echo "<table class='tab_cadre_fixe'>";
                echo "<tr><th colspan='6'>" . TypologyCriteriaDefinition::getTypeName(1) . "</tr>";
                echo Html::hidden('plugin_typology_typologycriterias_id', ['value' => $typocrit_id]);
                echo Html::hidden('entities_id', ['value' => $typocrit->getField('entities_id')]);
                echo Html::hidden('is_recursive', ['value' => $typocrit->getField('is_recursive')]);
                echo "<tr class='tab_bg_1 center'>";
                echo "<td>" . _n('Field', 'Fields', 2) . "</td><td>";
                TypologyCriteriaDefinition::dropdownFields($typocrit_id);
                echo "</td>";

                echo "<td>";
                echo "<span id='span_actions' name='span_actions'></span></td>";

                echo "<td>";
                echo "<span id='span_values' name='span_values'></span></td>";

                echo "<td class='tab_bg_2 left' width='80px'>";
                echo Html::submit(_sx('button', 'Add'), ['name' => 'add_action', 'class' => 'btn btn-primary']);
                echo "</td></tr>\n";
                echo "</table>";
                Html::closeForm();
                echo "</div>";
            }
            // The list is always shown, even empty (it used to test the column count)
            if (count($iterator) >= 0) {
                if ($canedit) {
                    Html::openMassiveActionsForm('massDef' . $rand);
                    $massiveactionparams = ['item' => $typocrit, 'container' => 'massDef'  . $rand];
                    Html::showMassiveActions($massiveactionparams);
                }

                echo "<div class='left'><table class='tab_cadre_fixe'>";
                echo "<tr><th colspan='4'>" . TypologyCriteriaDefinition::getTypeName(2) . "</th></tr>";

                echo "<tr class='tab_bg_1 center'>";
                if ($canedit) {
                    echo "<th width='10'>" . Html::getCheckAllAsCheckbox('massDef' . $rand) . "</th>";
                }
                echo "<th>" . _n('Field', 'Fields', 2) . "</th>";
                echo "<th class='center b'>" . __('Logical operator') . "</th>";
                echo "<th class='center b'>" . __('Value') . "</th>";
                echo "</tr>";

                foreach ($iterator as $ligne) {
                    echo "<tr class='tab_bg_2'>";

                    if ($canedit) {
                        echo "<td width='10'>";
                        Html::showMassiveActionCheckBox(__CLASS__, $ligne["id"]);
                        echo "</td>";
                    }

                    self::showMinimalDefinitionForm($ligne);

                    echo "</tr>";
                }
                echo "</table>";
                if ($canedit) {
                    $massiveactionparams['ontop'] = false;
                    Html::showMassiveActions($massiveactionparams);
                    Html::closeForm();
                }
                echo "</div>";
            }
        }
    }

   /**
    * Get the standard massive actions which are forbidden
    *
    * @return array array of massive actions
    **@since version 0.84
    *
    */
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';

        return $forbidden;
    }

   /**
    * Display all the fields available depending on itemtype selected
    *
    * @static
    *
    * @param     $typocrit_id
    * @param int $value
    *
    * @return mixed
    */
    static function dropdownFields($typocrit_id, $value = 0)
    {
        global $DB, $CFG_GLPI;
        $typoCritDef                                                 = new TypologyCriteriaDefinition();
        $typoCritDef->fields['plugin_typology_typologycriterias_id'] = $typocrit_id;
        $typoCrit                                                    = new TypologyCriteria();
        $typoCrit->getFromDB($typocrit_id);
        $itemtype = $typoCrit->fields['itemtype'];

        $dbu = new DbUtils();

        if (!isset($typoCritDef->fields['entities_id'])) {
            $typoCritDef->fields['entities_id'] = $_SESSION['glpiactive_entity'];
        }

       //Search option for this type
        $target = new $itemtype();

        echo "<select name='field' id='field' class='form-select'>";
        echo "<option value='0'>" . Dropdown::EMPTY_VALUE . "</option>";

        foreach ($DB->listFields($dbu->getTableForItemType($itemtype)) as $field) {
            $searchOption = $target->getSearchOptionByField('field', $field['Field']);
            if (empty($searchOption)) {
                if ($table = $dbu->getTableNameForForeignKeyField($field['Field'])) {
                    $searchOption = $target->getSearchOptionByField('field', 'name', $table);
                }
            }

            if (empty($searchOption)) {
                if ($table = $dbu->getTableNameForForeignKeyField($field['Field'])) {
                    $crit = $dbu->getItemForItemtype($dbu->getItemTypeForTable($table));
                    if ($crit instanceof CommonTreeDropdown) {
                        $searchOption = $target->getSearchOptionByField('field', 'completename', $table);
                    } else {
                        $searchOption = $target->getSearchOptionByField('field', 'name', $table);
                    }
                }
            }

            if (!empty($searchOption)
             && !in_array($field['Field'], self::getUnallowedFields($itemtype))
            ) {
                if (!empty($searchOption['datatype'])) {
                    echo "<option value='" . $field['Field'] . ";" . $searchOption['table'] . ";" . $searchOption['datatype'] . "'";
                } else {
                    echo "<option value='" . $field['Field'] . ";" . $searchOption['table'] . ";'";
                }

                echo ">" . $searchOption['name'] . "</option>";
            }
        }
        if ($itemtype == 'DeviceMemory') {
            echo "<option value='count;glpi_items_devicememories;number'>" .
              _x('Quantity', 'Number') . "</option>";
        } elseif ($itemtype == 'DeviceProcessor') {
            echo "<option value='count;glpi_items_deviceprocessors;number'>" .
              _x('Quantity', 'Number') . "</option>";
        } elseif ($itemtype == 'Software') {
            echo "<option value='softwareversions_id;glpi_softwareversions;'>" .
              __('Name') . " - " . _n('Version', 'Versions', 2) . "</option>";
        }
        echo "</select>";

        $params = ['field'       => '__VALUE__',
                 'value'       => $value,
                 'itemtype'    => $itemtype,
                 'typocrit_id' => $typocrit_id];

        Ajax::updateItemOnSelectEvent(
            "field",
            "span_actions",
            PLUGIN_TYPOLOGY_WEBDIR . "/ajax/dropdownAction.php",
            $params
        );
    }

   /**
    * List field not allowed
    *
    * @static
    *
    * @param $itemclass
    *
    * @return array
    */
    static function getUnallowedFields($itemclass)
    {
        switch ($itemclass) {
           //         case "DevicePowerSupply":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DevicePci":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DeviceCase":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DeviceGraphicCard":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DeviceMotherboard":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DeviceNetworkCard":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DeviceSoundCard":
           //            return array('comment',
           //               'designation');
           //            break;
           //         case "DeviceControl":
           //            return array('comment',
           //               'designation');
           //            break;
            case "DeviceHardDrive":
                return ['comment',
                    'designation',
                    'rpm',
                    'interfacetypes_id',
                    'cache',
                    'manufacturers_id'];
            break;
            case "Printer":
                return ['id',
                    'name',
                    'entities_id',
                    'is_recursive',
                    'date_mod',
                    'contact',
                    'contact_num',
                    'users_id_tech',
                    'groups_id_tech',
                    'serial',
                    'otherserial',
                    'comment',
                    'locations_id',
                    'domains_id',
                    'printermodels_id',
                    'manufacturers_id',
                    'is_global',
                    'init_pages_counter',
                    'last_pages_counter',
                    'notepad',
                    'groups_id',
                    'users_id',
                    'states_id'];
            break;
           //         case "DeviceDrive":
           //            return array('comment',
           //               'designation');
           //            break;
            case "Software":
                return ['id',
                    //               'name',
                    'entities_id',
                    'is_recursive',
                    'comment',
                    'locations_id',
                    'users_id_tech',
                    'groups_id_tech',
                    'softwares_id',
                    'manufacturers_id',
                    'date_mod',
                    'notepad',
                    'groups_id',
                    'users_id',
                    'is_helpdesk_visible'];
            break;
            case "Monitor":
                return ['id',
                    'name',
                    'entities_id',
                    'date_mod',
                    'contact',
                    'contact_num',
                    'users_id_tech',
                    'groups_id_tech',
                    'comment',
                    'serial',
                    'otherserial',
                    'have_bnc',
                    'locations_id',
                    'monitormodels_id',
                    'manufacturers_id',
                    'is_global',
                    'notepad',
                    'users_id',
                    'groups_id',
                    'states_id'];
            break;
            case "DeviceMemory":
                return ['comment',
                    'designation',
                    'manufacturers_id',
                    'devicememorytypes_id'];
            break;
            case "Computer":
                return ['id',
                    'name',
                    'entities_id',
                    'serial',
                    'otherserial',
                    'contact',
                    'contact_num',
                    'users_id_tech',
                    'groups_id_tech',
                    'comment',
                    'date_mod',
                    //               'os_license_number',
                    'os_licenseid',
                    'autoupdatesystems_id',
                    //               'locations_id',
                    'manufacturers_id',
                    'computermodels_id',
                    'notepad',
                    'is_ocs_import',
                    'users_id',
                    'groups_id',
                    //'states_id',
                    'uuid'];
            break;
            case "NetworkPort":
                return ['id',
                    'itemtype',
                    'items_id',
                    'logical_number',
                    //               'name',
                    'mac',
                    'networkinterfaces_id',
                    'netpoints_id',
                    'comment'];
            break;
            case "IPAddress":
                return ['id',
                    'itemtype',
                    'items_id',
                    'logical_number',
                    //               'name',
                    'mac',
                    'networkinterfaces_id',
                    'netpoints_id',
                    'comment'];
            break;
            case "DeviceProcessor":
                return ['comment',
                    'manufacturers_id',
                    //               'specif_default',
                    'frequence'];
            break;
            case "Peripheral":
                return ['id',
                    'name',
                    'entities_id',
                    'date_mod',
                    'contact',
                    'contact_num',
                    'users_id_tech',
                    'manufacturers_id',
                    'peripheralmodels_id',
                    'brand',
                    'groups_id_tech',
                    'comment',
                    'serial',
                    'otherserial',
                    'locations_id',
                    'is_global',
                    'notepad',
                    'users_id',
                    'groups_id',
                    'states_id'];
            break;
           //         case "Phone" :
           //            return array('id',
           //               'name',
           //               'entities_id',
           //               'date_mod',
           //               'contact',
           //               'contact_num',
           //               'users_id_tech',
           //               'groups_id_tech',
           //               'comment',
           //               'serial',
           //               'otherserial',
           //               'locations_id',
           //               'is_global',
           //               'notepad',
           //               'users_id',
           //               'groups_id',
           //               'states_id');
           //            break;
        }
    }

   /**
    * Display logical operator depending on field selected
    *
    * @static
    *
    * @param     $itemtype
    * @param     $typocrit_id
    * @param     $field
    * @param int $value
    *
    * @return mixed
    */
    static function dropdownSelect($itemtype, $typocrit_id, $field, $value = 0)
    {
        global $CFG_GLPI;
        $test         = explode(";", $field);
        $itemTable    = $test[1];
        $itemDataType = $test[2];

        $dbu = new DbUtils();

        $typoCritDef                                                 = new TypologyCriteriaDefinition();
        $typoCritDef->fields['plugin_typology_typologycriterias_id'] = $typocrit_id;
        $typoCritDef->fields['field']                                = $field;

        if (!isset($typoCritDef->fields['field']) || !$typoCritDef->fields['field']) {
            echo "</span>";
            return;
        }

        if (!isset($typoCritDef->fields['entities_id'])) {
            $typoCritDef->fields['entities_id'] = $_SESSION['glpiactive_entity'];
        }

        echo "<select name='action_type' id='action_type' class='form-select'>";
        echo "<option value='0'>" . Dropdown::EMPTY_VALUE . "</option>";

        switch ($itemDataType) {
            case "bool":
                echo "<option value='equals'>" . __('is') . "</option>";
                echo "<option value='notequals'>" . __('is not') . "</option>";
                break;
            case "number":
                echo "<option value='equals'>" . __('is') . "</option>";
                echo "<option value='notequals'>" . __('is not') . "</option>";
                echo "<option value='lessthan'>" . __('Less than', 'typology') . "</option>";
                echo "<option value='morethan'>" . __('More than', 'typology') . "</option>";
                break;
            case "text":
                echo "<option value='equals'>" . __('is') . "</option>";
                echo "<option value='notequals'>" . __('is not') . "</option>";
                echo "<option value='lessthan'>" . __('Less than', 'typology') . "</option>";
                echo "<option value='morethan'>" . __('More than', 'typology') . "</option>";
                break;
            case "string":
                echo "<option value='equals'>" . __('is') . "</option>";
                echo "<option value='notequals'>" . __('is not') . "</option>";
                echo "<option value='contains'>" . __('contains') . "</option>";
                echo "<option value='notcontains'>" . __('does not contain') . "</option>";
                break;
           //         case "ip" :
           //            echo "<option value='equals'>" . __('is') . "</option>";
           //            echo "<option value='notequals'>" . __('is not') . "</option>";
           //            echo "<option value='contains'>" . __('contains') . "</option>";
           //            echo "<option value='notcontains'>" . __('does not contain') . "</option>";
           //            echo "<option value='regex_match'>" . __('regular expression matches') . "</option>";
           //            echo "<option value='regex_not_match'>" . __('regular expression does not match') . "</option>";
           //            break;
            default:
                $item = $dbu->getItemForItemtype($dbu->getItemTypeForTable($itemTable));
                switch ($itemTable) {
                    case "glpi_users":
                        echo "<option value='equals'>" . __('is') . "</option>";
                        echo "<option value='notequals'>" . __('is not') . "</option>";
                        echo "<option value='contains'>" . __('contains') . "</option>";
                        echo "<option value='notcontains'>" . __('does not contain') . "</option>";
                        break;
                    case "glpi_softwareversions":
                        echo "<option value='equals'>" . __('is') . "</option>";
                        echo "<option value='notequals'>" . __('is not') . "</option>";
                        break;
                    default:
                        echo "<option value='equals'>" . __('is') . "</option>";
                        echo "<option value='notequals'>" . __('is not') . "</option>";
                        echo "<option value='contains'>" . __('contains') . "</option>";
                        echo "<option value='notcontains'>" . __('does not contain') . "</option>";
                        if ($item instanceof CommonTreeDropdown) {
                            echo "<option value='under'>" . __('under') . "</option>";
                            echo "<option value='notunder'>" . __('not under') . "</option>";
                        }
                        if ($itemTable == 'glpi_ipaddresses') {
                            echo "<option value='regex_match'>" . __('regular expression matches') . "</option>";
                            echo "<option value='regex_not_match'>" . __('regular expression does not match') . "</option>";
                        }
                        break;
                }
                break;
        }
        echo "</select>";

        $params = ['action_type' => '__VALUE__',
                 'value'       => $value,
                 'itemtype'    => $itemtype,
                 'field'       => $field,
                 'typocrit_id' => $typocrit_id];

        Ajax::updateItemOnSelectEvent(
            "action_type",
            "span_values",
            PLUGIN_TYPOLOGY_WEBDIR . "/ajax/dropdownCaseValue.php",
            $params
        );

        if ($value > 0) {
            echo "<script type='text/javascript' >\n";
            echo "document.getElementById('action_type').value='" . $value . "';";
            echo "</script>\n";

            $params["typetable"] = $value;
            Ajax::UpdateItem(
                "span_values",
                PLUGIN_TYPOLOGY_WEBDIR . "/ajax/dropdownCaseValue.php",
                $params
            );
        }
    }

   /**
    * Display all values available depending on field selected
    *
    * @static
    *
    * @param     $options
    * @param int $value
    */
    static function dropdownValues($options)
    {

        $itemtype    = $options['itemtype'];
        $typocrit_id = $options['typocrit_id'];
        $field       = $options['field'];
        $action      = $options['action_type'];

        $test         = explode(";", $field);
        $itemField    = $test[0];
        $itemTable    = $test[1];
        $itemDataType = $test[2];

        $typoCritDef                                                 = new TypologyCriteriaDefinition();
        $typoCritDef->fields['plugin_typology_typologycriterias_id'] = $typocrit_id;
        $typoCritDef->fields['field']                                = $field;
        $typoCrit                                                    = new TypologyCriteria();
        $typoCrit->getFromDB($typocrit_id);

        if ($action == 'contains'
          || $action == 'notcontains'
          || $action == 'regex_match'
          || $action == 'regex_not_match') {
            echo Html::input('value', ['value' => '', 'size' => 40]);
        } else {
            switch ($itemDataType) {
                case "bool":
                    Dropdown::showYesNo('value');
                    break;
                case "number":
                    echo Html::input('value', ['value' => '', 'size' => 40]);
                    if ($itemField == 'size') {
                        echo "\"";
                    }
                    break;
                case "string":
                    echo Html::input('value', ['value' => '', 'size' => 40]);
                    break;
                case "text":
                    echo Html::input('value', ['value' => '', 'size' => 40]);
                    if ($itemtype == 'DeviceHardDrive') {
                        echo " " . __('Mio');
                    } elseif ($itemField == 'frequence') {
                        echo " " . __('MHz');
                    } elseif ($itemtype == 'DeviceMemory' && $itemField == 'specif_default') {
                        echo " " . __('Mio');
                    } elseif ($itemtype == 'DeviceProcessor' && $itemField == 'specif_default') {
                        echo " " . __('MHz');
                    }
                    break;

                default:
                    switch ($itemTable) {
                        case "glpi_users":
                             User::dropdown(['right' => 'all',
                                     'name'  => 'value']);
                            break;
                        case "glpi_softwareversions":
                            Software::dropdownSoftwareToInstall("value", $typoCrit->fields['entities_id']);
                            break;
                        default:
                            $dbu       = new DbUtils();
                            $itemclass = $dbu->getItemTypeForTable($itemTable);
                            Dropdown::show($itemclass, ['name' => 'value', 'entity' => $typoCrit->fields['entities_id']]);
                            break;
                    }
                    break;
            }
        }

        echo "</span></td>";
    }

   /**
    * Show the minimal form for the definition criteria
    *
    * @param $ligne
    **/
    static function showMinimalDefinitionForm($ligne, $options = [])
    {
        global $DB, $CFG_GLPI;

        $params['seeResult']   = 0;
        $params['seeItemtype'] = 0;
        $img_OK                = "<i style='color:forestgreen' class='question ti ti-circle-check fa-2x'></i>";
        $img_NOT               = "<i style='color:darkred' class='question ti ti-circle-x fa-2x'></i>";

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        $item = new $ligne["itemtype"]();

        if ($params['seeItemtype']) {
            echo "<td class='center'>";

            echo $item->getTypeName(0);

            echo "</td>";

            echo "<td class='center'>";
            if ($ligne['link'] == 0) {
                echo __('and');
            } elseif ($ligne['link'] == 1) {
                echo __('or');
            }
            echo "</td>";
        }

        echo "<td class='center'>";

        $test         = explode(";", $ligne["field"]);
        $itemField    = $test[0];
        $itemTable    = $test[1];
        $itemDataType = $test[2];

        $dbu = new DbUtils();
        if ($itemField == 'count') {
            echo _x('Quantity', 'Number');
        } else {
            $searchOption = $item->getSearchOptionByField('field', $itemField);

            if (empty($searchOption)) {
                $crit = $dbu->getItemForItemtype($dbu->getItemTypeForTable($itemTable));
                if ($crit instanceof CommonTreeDropdown) {
                    $searchOption = $item->getSearchOptionByField('field', 'completename', $itemTable);
                    echo $searchOption['name'];
                } else {
                    $searchOption = $item->getSearchOptionByField('field', 'name', $itemTable);
                    echo $searchOption['name'];
                }
            } else {
                echo $searchOption['name'];
            }
        }
        echo "</td>";

        if ($params['seeResult']) {
            echo "<td class='center'>";
            if ($ligne['result'] == 'ok') {
                echo $img_OK;
            } elseif ($ligne['result'] == 'not_ok') {
                echo $img_NOT;
            }
            echo " </td>";
        }

       // logical operator
        echo "<td class='center'>";

        if ($ligne['action_type'] == 'contains') {
            echo __('contains');
        } elseif ($ligne['action_type'] == 'notcontains') {
            echo __('does not contain');
        } elseif ($ligne['action_type'] == 'lessthan') {
            echo __('Less than', 'typology');
        } elseif ($ligne['action_type'] == 'morethan') {
            echo __('More than', 'typology');
        } elseif ($ligne['action_type'] == 'regex_match') {
            echo __('regular expression matches');
        } elseif ($ligne['action_type'] == 'regex_not_match') {
            echo __('regular expression does not match');
        } elseif ($ligne['action_type'] == 'equals') {
            echo __('is');
        } elseif ($ligne['action_type'] == 'notequals') {
            echo __('is not');
        } elseif ($ligne['action_type'] == 'under') {
            echo __('under');
        } elseif ($ligne['action_type'] == 'notunder') {
            echo __('not under');
        }

        echo "</td>";

       // criteria value
        echo "<td class='center'>";

        // Stored values, typed by a plugin editor: escaped for every viewer
        $value = (string) $ligne["value"];
        if ($ligne['action_type'] == 'contains'
          || $ligne['action_type'] == 'notcontains'
          || $ligne['action_type'] == 'regex_match'
          || $ligne['action_type'] == 'regex_not_match') {
            echo htmlescape($value);
        } else {
            switch ($itemDataType) {
                case "bool":
                    echo htmlescape(Dropdown::getYesNo($ligne["value"]));
                    break;
                case "number":
                    echo htmlescape($value);
                    if ($itemField == 'size') {
                        echo "\"";
                    }
                    break;
                case "string":
                    echo htmlescape($value);
                    break;
                case "text":
                    echo htmlescape($value);
                    if ($ligne["itemtype"] == 'DeviceHardDrive') {
                        echo " " . __('Mio');
                    } elseif ($itemField == 'frequence') {
                        echo " " . __('MHz');
                    } elseif ($ligne["itemtype"] == 'DeviceMemory' && $itemField == 'specif_default') {
                        echo " " . __('Mio');
                    } elseif ($ligne["itemtype"] == 'DeviceProcessor' && $itemField == 'specif_default') {
                        echo " " . __('MHz');
                    }
                    break;
               //            case "ip" :
               //               echo $ligne["value"];
               //               break;
                default:
                    switch ($itemTable) {
                        case "glpi_users":
                             echo htmlescape((string) $dbu->getUserName((int) $ligne["value"]));
                            break;
                        case "glpi_softwareversions":
                            foreach ($DB->request([
                                'SELECT'     => [
                                    'glpi_softwares.name AS softname',
                                    'glpi_softwareversions.name AS vname',
                                    'glpi_softwareversions.id AS vid',
                                ],
                                'FROM'       => 'glpi_softwareversions',
                                'INNER JOIN' => [
                                    'glpi_softwares' => ['ON' => ['glpi_softwareversions' => 'softwares_id', 'glpi_softwares' => 'id']],
                                ],
                                'WHERE'      => ['glpi_softwareversions.id' => (int) $ligne["value"]],
                            ]) as $data) {
                                echo htmlescape((string) $data['softname']) . " - ";
                                if ($data['vname'] == '') {
                                     echo "(" . (int) $data['vid'] . ")";
                                } else {
                                    echo htmlescape((string) $data['vname']);
                                }
                            }
                            break;
                        default:
                            if ($item instanceof CommonDevice) {
                                $item->getFromDB((int) $ligne["value"]);
                                echo htmlescape((string) $item->getName());
                            } else {
                                echo htmlescape((string) Dropdown::getDropdownName($itemTable, (int) $ligne["value"]));
                            }
                            break;
                    }
                    break;
            }
        }

        echo "</td>";

       // computer value
        if ($params['seeResult']) {
            echo "<td class='center'>";

            if (($ligne['realvalue'][0] > 1
              && $ligne['realvalue'][1] > 1)
             || ($ligne['realvalue'][0] == 1
                 && $ligne['realvalue'][1] == 2)) {
                echo (int) $ligne['realvalue'][0] . ' / ' . (int) $ligne['realvalue'][1];
                echo "<br>";
            } elseif ($ligne['realvalue'][0] == 0
                    && $ligne['realvalue'][1] != 1) {
                echo __s('Item not found');
            }

            if ((isset($ligne['list_ok'])
              && $ligne['realvalue'][0] > 0)
             || ($ligne['realvalue'][0] == 0
                 && $ligne['realvalue'][1] == 1)) {
                $count = count($ligne['list_ok']);
                $i     = 0;

                foreach ($ligne['list_ok'] as $key => $val) {
                    $i++;
                    if ($val == '') {
                        $val = __('Item not found');
                    }
                    // The values are read on the item (name, serial, software...): plain text, escaped here
                    if ($itemDataType != "bool") {
                        echo htmlescape((string) $val);
                        if ($itemField == 'size') {
                            echo "&quot;";
                        } elseif ($ligne["itemtype"] == 'DeviceHardDrive') {
                            echo " " . __s('Mio');
                        } elseif ($itemField == 'frequence') {
                            echo " " . __s('MHz');
                        } elseif ($ligne["itemtype"] == 'DeviceMemory' && $itemField == 'specif_default') {
                            echo " " . __s('Mio');
                        } elseif ($ligne["itemtype"] == 'DeviceProcessor' && $itemField == 'specif_default') {
                            echo " " . __s('MHz');
                        }
                    } else {
                        echo htmlescape(Dropdown::getYesNo($val));
                    }
                    if ($i < $count) {
                        echo "<br>";
                    }
                }
            }
            echo "</td>";
        }
    }

   /**
    * recover all value for console management display
    *
    * @static
    *
    * @param $tabCritID
    * @param $pcID
    * @param $itemtype
    * @param $display
    *
    * @return null
    */
    static function getConsoleData($tabCritID, $pcID, $itemtype, $display)
    {
        $valueFromDef = null;

        foreach ($tabCritID as $critID) {
            $valueFromDef[$itemtype][$critID] = self::getValueFromDef($critID);
        }

        $valueFromDef = self::getRealValue($pcID, $valueFromDef);

        if ($display) {
            $valueFromDef = self::getComputeResultByDef($valueFromDef, $pcID);
        } else {
            $valueFromDef = self::getComputeResultByCriteria($valueFromDef);
        }
        return $valueFromDef;
    }

   /**
    * get all definitions value for each criteria
    *
    * @static
    *
    * @param $critID
    *
    * @return array
    */
    static function getValueFromDef($critID)
    {
        global $DB;
        $resp = [];

        foreach ($DB->request([
            'FROM' => 'glpi_plugin_typology_typologycriteriadefinitions',
            'LEFT JOIN' => [
                'glpi_plugin_typology_typologycriterias' => [
                    'glpi_plugin_typology_typologycriterias.id' => 'glpi_plugin_typology_typologycriteriadefinitions.plugin_typology_typologycriterias_id'
                ]
            ],
            'FIELDS' => [
                'glpi_plugin_typology_typologycriteriadefinitions.id',
                'glpi_plugin_typology_typologycriteriadefinitions.plugin_typology_typologycriterias_id',
                'glpi_plugin_typology_typologycriterias.link',
                'glpi_plugin_typology_typologycriteriadefinitions.field',
                'glpi_plugin_typology_typologycriteriadefinitions.action_type',
                'glpi_plugin_typology_typologycriteriadefinitions.value',
            ],
            'WHERE' => [
                'glpi_plugin_typology_typologycriteriadefinitions.plugin_typology_typologycriterias_id' => $critID
            ],
            'ORDER' => 'glpi_plugin_typology_typologycriteriadefinitions.id'
        ]) as $data) {
            $resp[$data['id']] = $data;
        }
        return $resp;
    }


   /**
    * get real computer value
    *
    * @static
    *
    * @param $pcID
    * @param $valueFromDef
    *
    * @return mixed
    */
    static function getRealValue($pcID, $valueFromDef)
    {
        global $DB;

        $dbu = new DbUtils();

        foreach ($valueFromDef as $itemtype => $allcrit) {
            if (!empty($allcrit)) {
                $item = new $itemtype();

                foreach ($allcrit as $key1 => $allDef) {
                    if (!empty($allDef)) {
                        foreach ($allDef as $key2 => $def) {
                            if (!empty($def)) {
                                 $test         = explode(";", $def["field"]);
                                 $itemField    = $test[0];
                                 $itemTable    = $test[1];
                                 $itemDataType = $test[2];

                                 //1-SELECT
                                $searchOption = [];
                                if ($itemField == 'count') {
                                    $fields = ['COUNT(*) as Field'];
                                    $searchOption['table'] = $itemTable;
                                } elseif ($itemField == 'softwareversions_id') {
                                    $searchOption['table'] = $itemTable;
                                    $fields = ["`{$searchOption['table']}`.`id` as Field"];
                                } elseif ($itemField == 'softwarecategories_id') {
                                    $searchOption['table'] = $itemTable;
                                    $fields = [
                                    "`glpi_softwares`.`name` as softwares_name",
                                    "`glpi_softwarecategories`.`name` as softwarecategories_name",
                                    "`glpi_softwarecategories`.`name` as Field"
                                    ];
                                } else {
                                    $searchOption = $item->getSearchOptionByField('field', $itemField);

                                    if (empty($searchOption)) {
                                          $crit = $dbu->getItemForItemtype($dbu->getItemTypeForTable($itemTable));
                                        if ($crit instanceof CommonTreeDropdown) {
                                            $searchOption = $item->getSearchOptionByField('field', 'completename', $itemTable);
                                        } else {
                                            $searchOption = $item->getSearchOptionByField('field', 'name', $itemTable);
                                        }
                                    }
                                    $fields = [
                                    "`{$searchOption['table']}`.`{$searchOption['field']}` as Field",
                                    "`{$searchOption['table']}`.`id` as Field_id"
                                    ];
                                }

                            // 2 - FROM
                                $params = [];
                                switch ($itemtype) {
                                    case "Computer":
                                        $params['FROM'] = 'glpi_computers';
                                        if ($searchOption['table'] != 'glpi_computers') {
                                               $fk = $dbu->getForeignKeyFieldForTable($searchOption['table']); // ex: locations_id
                                                $params['INNER JOIN'][$searchOption['table']] = [
                                                 'ON' => [
                                                  'glpi_computers' => $fk,
                                                  $searchOption['table'] => 'id',
                                                 ]
                                                ];
                                        }
                                        $params['WHERE'] = ['glpi_computers.id' => $pcID];
                                        break;
                                    case "Monitor":
                                    case "Peripheral":
                                    case "Printer":
                                        // Items connected to the computer: glpi_computers_items
                                        // became the relation between assets and peripheral assets
                                        $link_table     = Asset_PeripheralAsset::getTable();
                                        $item_table     = $dbu->getTableForItemType($itemtype);
                                        $params['FROM'] = $link_table;

                                        if (strstr($searchOption['table'], 'types')
                                            || $searchOption['table'] == 'glpi_networks') {
                                            $fk = $dbu->getForeignKeyFieldForTable($searchOption['table']);

                                            $params['INNER JOIN'][$item_table] = ['ON' => [
                                                $link_table => 'items_id_peripheral',
                                                $item_table => 'id',
                                            ]];
                                            $params['INNER JOIN'][$searchOption['table']] = ['ON' => [
                                                $item_table             => $fk,
                                                $searchOption['table'] => 'id',
                                            ]];
                                        } else {
                                            $params['INNER JOIN'][$searchOption['table']] = ['ON' => [
                                                $link_table             => 'items_id_peripheral',
                                                $searchOption['table'] => 'id',
                                            ]];
                                        }

                                        $params['WHERE'] = [
                                            $link_table . '.itemtype_peripheral' => $itemtype,
                                            $link_table . '.itemtype_asset'      => Computer::class,
                                            $link_table . '.items_id_asset'      => $pcID,
                                        ];
                                        break;
                                    case "Software":
                                        $params['FROM'] = 'glpi_items_softwareversions';
                                        $params['LEFT JOIN'] = [
                                            'glpi_softwareversions' => [
                                        'glpi_items_softwareversions.softwareversions_id' => 'glpi_softwareversions.id'
                                            ],
                                            'glpi_softwares' => [
                                                'glpi_softwareversions.softwares_id' => 'glpi_softwares.id'
                                            ],
                                            'glpi_softwarecategories' => [
                                                'glpi_softwares.softwarecategories_id' => 'glpi_softwarecategories.id'
                                            ]
                                        ];
                                        $params['WHERE'] = [
                                            'glpi_items_softwareversions.itemtype' => 'Computer',
                                            'glpi_items_softwareversions.items_id' => $pcID
                                        ];
                                        break;
                                    case "IPAddress":
                                        $params['FROM'] = 'glpi_networkports';
                                        $params['LEFT JOIN'] = [
                                            'glpi_networknames' => [
                                        'glpi_networkports.id' => 'glpi_networknames.items_id',
                                            ],
                                            'glpi_ipaddresses' => [
                                                'glpi_networknames.id' => 'glpi_ipaddresses.items_id',
                                            ]
                                        ];
                                        $params['WHERE'] = [
                                            'glpi_networkports.itemtype' => 'Computer',
                                            'glpi_networkports.items_id' => $pcID,
                                            'glpi_networknames.itemtype' => 'NetworkPort',
                                            'glpi_ipaddresses.itemtype' => 'NetworkName'
                                        ];
                                        break;

                                    case "DeviceProcessor":
                                    case "DeviceMemory":
                                    case "DeviceHardDrive":
                                        if ($itemField == 'count') {
                                            $params['FROM'] = $searchOption['table'];
                                            $params['WHERE'] = [
                                                'items_id' => $pcID,
                                                'itemtype' => 'Computer'
                                            ];
                                        } else {
                                            $linktable = $dbu->getTableForItemType('items_' . $itemtype);
                                            $fk        = $dbu->getForeignKeyFieldForTable($dbu->getTableForItemType($itemtype));

                                            $params['FROM'] = $linktable;
                                            $params['INNER JOIN'][$searchOption['table']] = [
                                                "`$linktable`.`$fk`" => "`{$searchOption['table']}`.`id`"
                                            ];
                                            $params['WHERE'] = [
                                                "$linktable.items_id" => $pcID,
                                                "$linktable.itemtype" => 'Computer'
                                            ];
                                        }
                                        break;
                                }

                                $nbReal     = 0;
                                $real_value = null;
                                $nbok       = 0;
                                $list       = [];
                                $params['FIELDS'] = $fields;
                                foreach ($DB->request($params) as $data) {
                                    $nbReal++;

                                    if ($def['action_type'] == 'equals') {
                                        if ($itemDataType == "text"
                                     //                                    || $itemDataType == "ip"
                                        || $itemField == 'softwareversions_id') {
                                            if ($data['Field'] == $def["value"]) {
                                                $nbok++;

                                                if ($itemField == 'softwareversions_id') {
                                                     $softwareData = [];
                                                    foreach ($DB->request([
                                                         'FROM' => 'glpi_softwareversions',
                                                         'LEFT JOIN' => [
                                                       'glpi_softwares' => [
                                                    'glpi_softwares.id' => 'glpi_softwareversions.softwares_id'
                                                       ]
                                                         ],
                                                         'FIELDS' => [
                                                             'glpi_softwares.name as softname',
                                                             'glpi_softwareversions.name as vname',
                                                             'glpi_softwareversions.id as vid',
                                                         ],
                                                         'WHERE' => [
                                                             'glpi_softwareversions.id' => $def["value"]
                                                         ]
                                                     ]) as $softwareData) {
                                                        $name = $softwareData['softname'] . " - ";
                                                        if ($softwareData['vname'] == '') {
                                                            $name .= "(" . $softwareData['vid'] . ")";
                                                        } else {
                                                            $name .= $softwareData['vname'];
                                                        }
                                                        $list[] = $name;
                                                    }
                                                } else {
                                                    $list[] = $data['Field'];
                                                }
                                            }
                                        } elseif ($itemDataType == "bool" || $itemDataType == "number") {
                                            if ($data['Field'] == $def["value"]) {
                                                $nbok++;
                                                $list[] = $data['Field'];
                                            }
                                        } else {
                                            if (stristr($searchOption['table'], 'device')) { // If device type
                                                $tabResult = null;
                                                foreach ($DB->request([
                                                'FROM'   => $searchOption['table'],
                                                'FIELDS' => [$searchOption['table'] . '.designation'],
                                                'WHERE'  => [$searchOption['table'] . '.designation' => $data['Field']]
                                                ]) as $row) {
                                                    $tabResult = $row;
                                                    break; // On prend juste la première ligne
                                                }
                                                $dropdownResult = $tabResult['designation'] ?? null;
                                            } else {
                                                $dropdownResult = Dropdown::getDropdownName($searchOption['table'], $def["value"]);
                                            }

                                            if ($data['Field'] == $dropdownResult) {
                                                $nbok++;

                                                if ($itemField == 'softwarecategories_id') {
                                                     $name = $data['Field'];
                                                    if (isset($data['softwares_name'])) {
                                                        $name .= ' (' . $data['softwares_name'] . ')';
                                                    }
                                                    $list[] = $name;
                                                } else {
                                                    $list[] = $data['Field'];
                                                }
                                            }
                                        }
                                    } elseif ($def['action_type'] == 'notequals') {
                                        if ($itemDataType == "text"
                                        || $itemDataType == "ip"
                                        || $itemField == 'softwareversions_id') {
                                            if ($data['Field'] == $def["value"]) {
                                                $nbok++;
                                                if ($itemField == 'softwareversions_id') {
                                                    foreach ($DB->request([
                                                               'FROM' => 'glpi_softwareversions',
                                                               'INNER JOIN' => [
                                                              'glpi_softwares' => [
                                                                  'glpi_softwares.id' => 'glpi_softwareversions.softwares_id'
                                                              ]
                                                               ],
                                                               'FIELDS' => [
                                                                   'glpi_softwares.name as softname',
                                                                   'glpi_softwareversions.name as vname',
                                                                   'glpi_softwareversions.id as vid',
                                                               ],
                                                               'WHERE' => [
                                                                   'glpi_softwareversions.id' => $def["value"]
                                                               ]
                                                    ]) as $data) {
                                                        $name = $data['softname'] . " - ";
                                                        if ($data['vname'] == '') {
                                                            $name .= "(" . $data['vid'] . ")";
                                                        } else {
                                                            $name .= $data['vname'];
                                                        }
                                                        $list[] = $name;
                                                    }
                                                } else {
                                                      $list[] = $data['Field'];
                                                }
                                            }
                                        } elseif ($itemDataType == "bool" || $itemDataType == "number") {
                                            if ($data['Field'] == $def["value"]) {
                                                $nbok++;
                                                $list[] = $data['Field'];
                                            }
                                        } else {
                                            if (stristr($searchOption['table'], 'device')) {// If device type
                                                $tabResult = null;
                                                foreach ($DB->request([
                                                'FROM'  => $searchOption['table'],
                                                'FIELDS'=> [$searchOption['table'] . '.designation'],
                                                'WHERE' => [$searchOption['table'] . '.designation' => $data['Field']]
                                                ]) as $row) {
                                                        $tabResult = $row;
                                                        break;
                                                }
                                                $dropdownResult = $tabResult['designation'] ?? null;
                                            } else {
                                                $dropdownResult = Dropdown::getDropdownName($searchOption['table'], $def["value"]);
                                            }

                                            if ($data['Field'] == $dropdownResult) {
                                                  $nbok++;

                                                if ($itemField == 'softwarecategories_id') {
                                                    $name = $data['Field'];
                                                    if (isset($data['softwares_name'])) {
                                                        $name .= ' (' . $data['softwares_name'] . ')';
                                                    }
                                                    $list[] = $name;
                                                } else {
                                                    $list[] = $data['Field'];
                                                }
                                            }
                                        }
                                    } elseif ($def['action_type'] == 'lessthan') {
                                        if ($data['Field'] <= $def["value"]) {
                                             $nbok++;
                                             $list[] = $data['Field'];
                                        }
                                    } elseif ($def['action_type'] == 'morethan') {
                                        if ($data['Field'] >= $def["value"]) {
                                                 $nbok++;
                                                 $list[] = $data['Field'];
                                        }
                                    } elseif ($def['action_type'] == 'contains') {
                                        if (stristr($data['Field'], $def["value"])) {
                                                     $nbok++;
                                            if ($itemField == 'softwarecategories_id') {
                                                $name = $data['Field'];
                                                if (isset($data['softwares_name'])) {
                                                        $name .= ' (' . $data['softwares_name'] . ')';
                                                }
                                                $list[] = $name;
                                            } else {
                                                $list[] = $data['Field'];
                                            }
                                        }
                                    } elseif ($def['action_type'] == 'notcontains') {
                                        if (stristr($data['Field'], $def["value"])) {
                                                         $nbok++;
                                            if ($itemField == 'softwarecategories_id') {
                                                    $name = $data['Field'];
                                                if (isset($data['softwares_name'])) {
                                                    $name .= ' (' . $data['softwares_name'] . ')';
                                                }
                                                $list[] = $name;
                                            } else {
                                                $list[] = $data['Field'];
                                            }
                                        }
                                    } elseif ($def['action_type'] == 'regex_match') {
                                        // An invalid or too costly pattern matches nothing
                                        if (self::matchRegex((string) $def["value"], (string) $data['Field']) === true) {
                                            $nbok++;
                                            $list[] = $data['Field'];
                                        }
                                    } elseif ($def['action_type'] == 'regex_not_match') {
                                        if (self::matchRegex((string) $def["value"], (string) $data['Field']) === false) {
                                            $nbok++;
                                            $list[] = $data['Field'];
                                        }
                                    } elseif ($def['action_type'] == 'under') {
                                        $sons = $dbu->getSonsOf($itemTable, $def["value"]);
                                        if (in_array($data['Field_id'], $sons)) {
                                                                    $nbok++;
                                                                    $list[] = $data['Field'];
                                        }
                                    } elseif ($def['action_type'] == 'notunder') {
                                        $sons = $dbu->getSonsOf($itemTable, $def["value"]);

                                        if (!in_array($data['Field_id'], $sons)) {
                                                                    $nbok++;
                                                                    $list[] = $data['Field'];
                                        }
                                    }
                                }
                                $params['FIELDS'] = $fields;
                            //display the real value even if $nbok==0
                                if ($nbReal == 1 && $nbok == 0) {
                                    foreach ($DB->request($params) as $data) {
                                        $list[] = $data['Field'];
                                    }
                                }
                                $valueFromDef[$itemtype][$key1][$key2]["list_ok"]   = $list;
                                $valueFromDef[$itemtype][$key1][$key2]["realvalue"] = [$nbok, $nbReal];
                            }
                        }
                    }
                }
            }
        }
        return $valueFromDef;
    }

   /**
    * get result comparison def value and pc value
    *
    * @static
    *
    * @param $valueFromDef
    * @param $pcID
    *
    * @return mixed
    */
    static function getComputeResultByDef($valueFromDef, $pcID)
    {
        global $DB;

        $dbu = new DbUtils();

        $pcID = (int) $pcID;
        foreach ($valueFromDef as $itemtype => $allcrit) {
            if (!empty($allcrit)) {
                // Stored criterion type: the types of the plugin only
                if (!in_array($itemtype, Typology::getAllTypesCriteria(), true)
                    || !($item = getItemForItemtype($itemtype))) {
                    continue;
                }

                foreach ($allcrit as $key1 => $allDef) {
                    if (!empty($allDef)) {
                        foreach ($allDef as $key2 => $def) {
                            if (!empty($def)) {
                                 $test         = explode(";", (string) $def["field"]);
                                 $itemField    = $test[0] ?? '';
                                 $itemTable    = $test[1] ?? '';
                                 $itemDataType = $test[2] ?? '';
                                // The field is stored as "column;table;type": the table ends up as
                                // an SQL identifier, it must be a real one
                                if (!$DB->tableExists($itemTable)) {
                                    continue;
                                }

                                if ($itemField == 'count') {
                                    $searchOption['table'] = $itemTable;
                                } elseif ($itemField == 'softwareversions_id') {
                                    $searchOption['table'] = $itemTable;
                                    $searchOption['field'] = 'id';
                                    $searchOption['name']  = __('Name') . " - " . _n('Version', 'Versions', 2);
                                    ;
                                } else {
                                    $searchOption = $item->getSearchOptionByField('field', $itemField);

                                    if (empty($searchOption)) {
                                         $crit = $dbu->getItemForItemtype($dbu->getItemTypeForTable($itemTable));
                                        if ($crit instanceof CommonTreeDropdown) {
                                            $searchOption = $item->getSearchOptionByField('field', 'completename', $itemTable);
                                        } else {
                                            $searchOption = $item->getSearchOptionByField('field', 'name', $itemTable);
                                        }
                                    }
                                }
                                if (empty($searchOption['table'])
                                    || !$DB->tableExists($searchOption['table'])
                                    || (isset($searchOption['field']) && !$DB->fieldExists($searchOption['table'], $searchOption['field']))) {
                                    continue;
                                }
                                $table = $searchOption['table'];
                                $col   = $table . '.' . ($searchOption['field'] ?? 'id');

                                // 1 - FROM and joins: the items of the computer for the criterion type
                                $criteria = self::getComplianceFrom($itemtype, $itemField, $table, $pcID);
                                if ($criteria === null) {
                                    continue;
                                }

                                // 2 - comparison to the value of the definition, as criteria arrays:
                                // the values are bound by the query builder, never concatenated
                                $condition = self::getComplianceCondition($def, $item, $itemField, $itemTable, $itemDataType, $table, $col);
                                if ($condition !== null) {
                                    if ($itemField == 'count' && $itemDataType == 'number') {
                                        $criteria['HAVING'] = $condition;
                                    } else {
                                        $criteria['WHERE'][] = $condition;
                                    }
                                }

                                $nbConsole = $DB->request($criteria)->current();
                                if (($nbConsole['COUNT'] ?? 0) > 0) {
                                    if ($def['action_type'] == 'notequals' || $def['action_type'] == 'notcontains') {
                                         $valueFromDef[$itemtype][$key1][$key2]["result"] = 'not_ok';
                                    } else {
                                        $valueFromDef[$itemtype][$key1][$key2]["result"] = 'ok';
                                    }
                                } else {
                                    if ($def['action_type'] == 'notequals' || $def['action_type'] == 'notcontains') {
                                         $valueFromDef[$itemtype][$key1][$key2]["result"] = 'ok';
                                    } else {
                                        $valueFromDef[$itemtype][$key1][$key2]["result"] = 'not_ok';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $valueFromDef;
    }

   /**
    * Base of the compliance query of a definition: the items of the computer for the criterion
    * type, joined to the table of the field. The tables are real ones (checked by the caller).
    *
    * @param string $itemtype  criterion type
    * @param string $itemField column of the definition ("count" for a number of devices)
    * @param string $table     table of the field
    * @param int    $pcID      computer
    *
    * @return array|null criteria of DBmysql::request(), null for an unknown type
    */
    private static function getComplianceFrom(string $itemtype, string $itemField, string $table, int $pcID): ?array
    {
        global $DB;

        $dbu      = new DbUtils();
        $criteria = [
            'SELECT' => [new QueryExpression('COUNT(*) AS ' . $DB->quoteName('COUNT'))],
            'WHERE'  => [],
        ];

        switch ($itemtype) {
            case Computer::class:
                $criteria['FROM'] = 'glpi_computers';
                if ($table != 'glpi_computers') {
                    $criteria['INNER JOIN'][$table] = ['ON' => [
                        'glpi_computers' => $dbu->getForeignKeyFieldForTable($table),
                        $table           => 'id',
                    ]];
                }
                $criteria['WHERE']['glpi_computers.id'] = $pcID;
                break;

            case Monitor::class:
            case Peripheral::class:
            case Printer::class:
                // Items connected to the computer: glpi_computers_items became the relation
                // between assets and peripheral assets in GLPI 11
                $item_table       = $dbu->getTableForItemType($itemtype);
                $fk               = $dbu->getForeignKeyFieldForTable($table);
                $link_table       = Asset_PeripheralAsset::getTable();
                $criteria['FROM'] = $link_table;
                if (strstr($table, 'types') || $table == 'glpi_networks') {
                    // Type or network of the item: the item, then its dropdown
                    $criteria['INNER JOIN'][$item_table] = ['ON' => [
                        $link_table => 'items_id_peripheral',
                        $item_table => 'id',
                    ]];
                    $criteria['INNER JOIN'][$table] = ['ON' => [
                        $item_table => $fk,
                        $table      => 'id',
                    ]];
                } else {
                    $criteria['INNER JOIN'][$table] = ['ON' => [
                        $link_table => 'items_id_peripheral',
                        $table      => 'id',
                    ]];
                }
                $criteria['WHERE'][$link_table . '.itemtype_peripheral'] = $itemtype;
                $criteria['WHERE'][$link_table . '.itemtype_asset']      = Computer::class;
                $criteria['WHERE'][$link_table . '.items_id_asset']      = $pcID;
                break;

            case Software::class:
                $criteria['FROM']      = 'glpi_items_softwareversions';
                $criteria['LEFT JOIN'] = [
                    'glpi_softwareversions'   => ['ON' => [
                        'glpi_items_softwareversions' => 'softwareversions_id',
                        'glpi_softwareversions'       => 'id',
                    ]],
                    'glpi_softwares'          => ['ON' => [
                        'glpi_softwareversions' => 'softwares_id',
                        'glpi_softwares'        => 'id',
                    ]],
                    'glpi_softwarecategories' => ['ON' => [
                        'glpi_softwares'          => 'softwarecategories_id',
                        'glpi_softwarecategories' => 'id',
                    ]],
                ];
                $criteria['WHERE']['glpi_items_softwareversions.itemtype'] = Computer::class;
                $criteria['WHERE']['glpi_items_softwareversions.items_id'] = $pcID;
                break;

            case IPAddress::class:
                $criteria['FROM']      = 'glpi_networkports';
                $criteria['LEFT JOIN'] = [
                    'glpi_networknames' => ['ON' => [
                        'glpi_networkports' => 'id',
                        'glpi_networknames' => 'items_id',
                        ['AND' => ['glpi_networknames.itemtype' => NetworkPort::class]],
                    ]],
                    'glpi_ipaddresses'  => ['ON' => [
                        'glpi_networknames' => 'id',
                        'glpi_ipaddresses'  => 'items_id',
                        ['AND' => ['glpi_ipaddresses.itemtype' => NetworkName::class]],
                    ]],
                ];
                $criteria['WHERE']['glpi_networkports.itemtype'] = Computer::class;
                $criteria['WHERE']['glpi_networkports.items_id'] = $pcID;
                break;

            case DeviceProcessor::class:
            case DeviceMemory::class:
            case DeviceHardDrive::class:
                if ($itemField == 'count') {
                    $criteria['FROM']                        = $table;
                    $criteria['WHERE'][$table . '.items_id'] = $pcID;
                    $criteria['WHERE'][$table . '.itemtype'] = Computer::class;
                } else {
                    $linktable        = $dbu->getTableForItemType('items_' . $itemtype);
                    $criteria['FROM'] = $linktable;
                    $criteria['INNER JOIN'][$table] = ['ON' => [
                        $linktable => $dbu->getForeignKeyFieldForTable($dbu->getTableForItemType($itemtype)),
                        $table     => 'id',
                    ]];
                    $criteria['WHERE'][$linktable . '.items_id'] = $pcID;
                    $criteria['WHERE'][$linktable . '.itemtype'] = Computer::class;
                }
                break;

            default:
                return null;
        }

        return $criteria;
    }

   /**
    * Comparison of the field to the value of a definition, as a criteria array. The "not"
    * operators look for the same match: the caller inverts the result.
    *
    * @param array      $def          definition
    * @param CommonDBTM $item         instance of the criterion type
    * @param string     $itemField    column of the definition
    * @param string     $itemTable    table of the definition
    * @param string     $itemDataType type of the definition
    * @param string     $table        table of the field
    * @param string     $col          "table.column" of the field
    *
    * @return array|null null when the definition adds no condition
    */
    private static function getComplianceCondition(
        array $def,
        CommonDBTM $item,
        string $itemField,
        string $itemTable,
        string $itemDataType,
        string $table,
        string $col
    ): ?array {
        $dbu    = new DbUtils();
        $action = $def['action_type'];
        $value  = (string) $def['value'];

        $is_equal    = in_array($action, ['equals', 'notequals'], true);
        $is_contains = in_array($action, ['contains', 'notcontains'], true);
        $comparison  = ['equals' => '=', 'notequals' => '=', 'lessthan' => '<=', 'morethan' => '>='][$action] ?? null;

        switch ($itemDataType) {
            case 'bool':
                return $is_equal ? [$col => $value] : null;

            case 'number':
                if ($comparison === null) {
                    return null;
                }
                // A number of devices compares the count of the query
                return $itemField == 'count'
                    ? ['COUNT' => [$comparison, (float) $value]]
                    : [$col => [$comparison, (float) $value]];

            case 'text':
                return $comparison !== null ? [$col => [$comparison, (float) $value]] : null;

            case 'string':
                if ($is_contains) {
                    return [$col => ['LIKE', '%' . $value . '%']];
                }
                return $is_equal ? [$col => $value] : null;
        }

        switch ($itemTable) {
            case 'glpi_users':
                if ($is_contains) {
                    return [$col => ['LIKE', '%' . $value . '%']];
                }
                return $is_equal ? [$col => (string) $dbu->getUserName((int) $value)] : null;

            case 'glpi_softwareversions':
                return $is_equal ? [$col => $value] : null;
        }

        if ($is_contains) {
            return [$col => ['LIKE', '%' . $value . '%']];
        }
        if ($is_equal) {
            // The definition stores the id of the device or of the dropdown: its name is compared
            if ($item instanceof CommonDevice) {
                $device = clone $item;
                $device->getFromDB((int) $value);
                return [$col => (string) $device->getName()];
            }
            return [$col => (string) Dropdown::getDropdownName($table, (int) $value)];
        }
        if ($dbu->getItemForItemtype($dbu->getItemTypeForTable($itemTable)) instanceof CommonTreeDropdown) {
            $sons = array_map('intval', $dbu->getSonsOf($itemTable, (int) $value));
            if ($action == 'under') {
                return [$table . '.id' => $sons];
            }
            if ($action == 'notunder') {
                return ['NOT' => [$table . '.id' => $sons]];
            }
            return null;
        }
        if ($itemTable == 'glpi_ipaddresses') {
            // Regular expression on the IP addresses
            $regex = trim($value, '/');
            if ($action == 'regex_match') {
                return [$col => ['REGEXP', $regex]];
            }
            if ($action == 'regex_not_match') {
                return ['NOT' => [$col => ['REGEXP', $regex]]];
            }
        }

        return null;
    }

   /**
    * get global result value vs computer by itemtype
    *
    * @static
    *
    * @param $valueFromDef
    *
    * @return mixed
    */
    static function getComputeResultByCriteria($valueFromDef)
    {

        foreach ($valueFromDef as $itemtype => $allcrit) {
            if (!empty($allcrit)) {
                foreach ($allcrit as $key1 => $allDef) {// in all criteria
                    if (!empty($allDef)) {
                        foreach ($allDef as $key2 => $def) {// checking all definitions
                            if ($def['action_type'] == 'notequals'
                            || $def['action_type'] == 'notcontains') {// EXEPTION
                                if ($def['link'] == 0) {// If link == 0, AND operator
                                    if ($def['realvalue'][0] > 0) {
                                        $ItemNotOkAND[$itemtype][$key1][] = 1;
                                    } else {
                                        $ItemNotOkAND[$itemtype][$key1][] = 0;
                                    }
                                } else {// If link != 0, OR operator
                                    if ($def['realvalue'][0] > 0) {
                                            $ItemOkOR[$itemtype][$key1][] = 0;
                                    } else {
                                        $ItemOkOR[$itemtype][$key1][] = 1;
                                    }
                                }
                            } else {// Normal action
                                if ($def['link'] == 0) {// If link == 0, AND operator
                                    if ($def['realvalue'][0] == 0) {
                                        $ItemNotOkAND[$itemtype][$key1][] = 1;
                                    } else {
                                        $ItemNotOkAND[$itemtype][$key1][] = 0;
                                    }
                                } else {// If link != 0, OR operator
                                    if ($def['realvalue'][0] == 0) {
                                          $ItemOkOR[$itemtype][$key1][] = 0;
                                    } else {
                                          $ItemOkOR[$itemtype][$key1][] = 1;
                                    }
                                }
                            }
                        }
                        // Results between the operator OR, AND
                        if (isset($ItemNotOkAND[$itemtype][$key1])) {// AND
                            if (in_array(1, $ItemNotOkAND[$itemtype][$key1])) {
                                $valueFromDef[$itemtype][$key1]["result"] = 'not_ok';
                            } else {
                                $valueFromDef[$itemtype][$key1]["result"] = 'ok';
                            }
                        } elseif (isset($ItemOkOR[$itemtype][$key1])) {// OR
                            if (in_array(1, $ItemOkOR[$itemtype][$key1])) {
                                  $valueFromDef[$itemtype][$key1]["result"] = 'ok';
                            } else {
                                 $valueFromDef[$itemtype][$key1]["result"] = 'not_ok';
                            }
                        }
                    }
                }
            }
        }
        return $valueFromDef;
    }
}
