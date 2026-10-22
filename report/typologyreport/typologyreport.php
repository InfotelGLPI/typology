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

//Options for GLPI 0.71 and newer : need slave db to access the report
global $DB, $HEADER_LOADED;

use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Typology\Typology;
use GlpiPlugin\Typology\Typology_Item;

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 1;

// The right of the typologies, whatever the reports plugin checks on its side
Session::checkRight(Typology::$rightname, READ);
// The report lists computers and their users: the right on the computers too
Session::checkRight(Computer::$rightname, READ);

$titre = __('Typologies list by service with materials list', 'typology');

// Instantiate Report with Name
$report = new PluginReportsAutoReport($titre);

//Report's search criterias
$typocrit = New PluginReportsDropdownCriteria($report, 'glpi_plugin_typology_typologies.id',
   'glpi_plugin_typology_typologies', Typology::getTypeName(1));

//Display criterias form is needed
$report->displayCriteriasForm();

//colname with sort allowed
$columns = ['entity' => ['sorton' => 'entity'],
   'groups_id' => ['sorton' => 'groups_id'],
   'typoID'=>['sorton'=>'typoID'],
   'COUNT'=> ['sorton'=>'COUNT']];

$output_type = Search::HTML_OUTPUT;

if (isset ($_POST['list_limit'])) {
   $_SESSION['glpilist_limit'] = max(1, (int) $_POST['list_limit']);
   unset ($_POST['list_limit']);
}
if (!isset ($_REQUEST['sort'])) {
   $_REQUEST['sort'] = "entity";
   $_REQUEST['order'] = "ASC";
}

$limit = $_SESSION['glpilist_limit'];

if (isset ($_POST["display_type"])) {
   // PDF and spreadsheet outputs are produced by the core Search output classes
   $output_type = (int) $_POST["display_type"];
   if ($output_type < 0) {
      $output_type = - $output_type;
      $limit = 0;
   }
} //else {
//   $output_type = Search::HTML_OUTPUT;
//}

//Report title
$title = __('Typologies state by service', 'typology');

$styleItemTitle = 'font-size: 12px;
   font-weight: bold;
   background-color: #e1cc7b;
   text-align: center;
   -moz-border-radius: 4px;
   -webkit-border-radius: 4px;
   -o-border-radius: 4px;
   padding: 2px;';

//to verify if typology exist in this entity
// SQL statement
// Query builder criteria: the typologies of the user, the typology chosen, and the computers
// of the entities of the user (a recursive typology must not list the others)
$typo_condition     = getEntitiesRestrictCriteria('glpi_plugin_typology_typologies', '', '', true);
$computer_condition = getEntitiesRestrictCriteria('glpi_computers');
$typo_criteria      = $typocrit->getNewSqlCriteriasRestriction();

$criteria = [
   'FROM'  => 'glpi_plugin_typology_typologies',
   'WHERE' => $typo_condition + $typo_criteria,
];

$res   = $DB->request($criteria);
$nbtot = count($res);
if ($limit) {
   // The action of the form: integers only
   $limit = (int) $limit;
   $start = max(0, (int) ($_GET["start"] ?? 0));
   if ($start >= $nbtot) {
      $start = 0;
   }
   if ($start > 0 || $start + $limit < $nbtot) {
      $res = $DB->request($criteria + ['START' => $start, 'LIMIT' => $limit]);
   }
} else {
   $start = 0;
}

if ($nbtot == 0) {
   if (!$HEADER_LOADED) {
      Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
      Report::title();
   }
   echo "<div class='center'><span class='typology_font_red_bold'>".__('No item found')."</span></div>";
   Html::footer();
} else if ($output_type == Search::HTML_OUTPUT) {
   if (!$HEADER_LOADED) {
      Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
      Report::title();
   }
   echo "<div class='center'><table class='tab_cadre_fixe'>";
   echo "<tr><th>".$title."</th></tr>\n";

   // sub-Title
   echo "<tr><th>$titre</th></tr>\n";

   echo "<tr class='tab_bg_2 center'><td class='center'>";
   echo "<form method='POST' action='" . htmlescape($_SERVER["PHP_SELF"] . "?start=" . (int) $start) . "'>\n";

   $param = "";
   foreach ($_POST as $key => $val) {
      if (is_array($val)) {
         foreach ($val as $k => $v) {
            echo Html::hidden($key . "[" . $k . "]", ['value' => $v]);
            if (!empty ($param)) {
               $param .= "&";
            }
            $param .= $key."[".$k."]=".urlencode($v);
         }
      } else {
         echo Html::hidden($key, ['value' => $val]);
         if (!empty ($param)) {
            $param .= "&";
         }
         $param .= "$key=" . urlencode($val);
      }
   }
   Dropdown::showOutputFormat();
   Html::closeForm();
   echo "</td></tr>";
   echo "</table></div>";

   Html::printPager($start, $nbtot, $_SERVER['PHP_SELF'], $param);
}

if ($res && $nbtot >0) {

   // The four columns below
   $nbCols = 4;
   $nbrows = count($res);
   $num = 1;
   $link  = $_SERVER['PHP_SELF'];
   $order = 'ASC';
   $issort = false;

   echo Search::showHeader($output_type, $nbrows, $nbCols, true);

   echo Search::showNewLine($output_type);
   showTitle($output_type, $num, __('Entity'), 'entity', true);
   showTitle($output_type, $num, __('Service'), 'groups_id', true);
   showTitle($output_type, $num, Typology::getTypeName(1), 'typoID', true);
   showTitle($output_type, $num, __('Number', 'typology'), 'COUNT', true);
   echo Search::showEndLine($output_type);

   //By service and typology. The service of a computer is its group (Group_Item, normal type,
   // not the technical one): a computer of several groups is counted in each, one without any
   // in "None" (groups_id 0)
   $resultService = $DB->request([
      'SELECT'    => [
         'glpi_plugin_typology_typologies.entities_id AS entity',
         new QueryExpression('COALESCE(' . $DB->quoteName('glpi_groups_items.groups_id') . ', 0) AS '
            . $DB->quoteName('groups_id')),
         'glpi_plugin_typology_typologies.id AS typoID',
      ],
      'COUNT'     => 'COUNT',
      'FROM'      => 'glpi_plugin_typology_typologies_items',
      'LEFT JOIN' => [
         'glpi_plugin_typology_typologies' => [
            'ON' => [
               'glpi_plugin_typology_typologies_items' => 'plugin_typology_typologies_id',
               'glpi_plugin_typology_typologies'       => 'id',
            ],
         ],
         'glpi_computers' => [
            'ON' => [
               'glpi_plugin_typology_typologies_items' => 'items_id',
               'glpi_computers'                        => 'id', [
                  'AND' => ['glpi_plugin_typology_typologies_items.itemtype' => 'Computer'],
               ],
            ],
         ],
         'glpi_groups_items' => [
            'ON' => [
               'glpi_groups_items' => 'items_id',
               'glpi_computers'    => 'id', [
                  'AND' => [
                     'glpi_groups_items.itemtype' => Computer::class,
                     'glpi_groups_items.type'     => Group_Item::GROUP_TYPE_NORMAL,
                  ],
               ],
            ],
         ],
         'glpi_entities' => [
            'ON' => [
               'glpi_plugin_typology_typologies' => 'entities_id',
               'glpi_entities'                   => 'id',
            ],
         ],
      ],
      'WHERE'     => $typo_condition + $typo_criteria + $computer_condition,
      'GROUPBY'   => [
         'glpi_entities.entities_id',
         'groups_id',
         'glpi_plugin_typology_typologies.id',
      ],
      'ORDER'     => getOrderBy('entity', $columns),
   ]);
   $row_num = 1;
   foreach ($resultService as $dataService) {

      // Search::showItem() outputs the value as is: every name from the database is escaped
      if ($dataService['groups_id'] == 0) {
         $serviceName = __s('None');
      } else {
         $serviceName = htmlescape((string) Dropdown::getDropdownName("glpi_groups", $dataService['groups_id']));
      }

      if ($dataService['typoID'] == '0') {
         $typoName = __s('None');
      } else {
         $typoName = htmlescape((string) Dropdown::getDropdownName("glpi_plugin_typology_typologies", $dataService["typoID"]));
      }

      $entityName = htmlescape((string) Dropdown::getDropdownName('glpi_entities', $dataService['entity']));

      $row_num++;
      $num = 1;
      echo Search::showNewLine($output_type);
      echo Search::showItem($output_type, $entityName, $num, $row_num);
      echo Search::showItem($output_type, $serviceName, $num, $row_num);
      echo Search::showItem($output_type, $typoName, $num, $row_num);
      echo Search::showItem($output_type, $dataService["COUNT"], $num, $row_num);
      echo Search::showEndLine($output_type);

      $row_num++;
      $num =1;
      echo Search::showNewLine($output_type);
      echo Search::showItem($output_type, '', $num, $row_num);
      if ($output_type == Search::HTML_OUTPUT) {
         echo Search::showItem($output_type, __('Detail (workstation concerned)', 'typology'), $num, $row_num, "colspan = '3' style='$styleItemTitle'");
      } else {
         echo Search::showItem($output_type, __('Detail (workstation concerned)', 'typology'), $num, $row_num);
         echo Search::showItem($output_type, '', $num, $row_num);
         echo Search::showItem($output_type, '', $num, $row_num);
      }
      echo Search::showEndLine($output_type);

      $row_num++;
      $num=1;
      echo Search::showNewLine($output_type);
      echo Search::showItem($output_type, '', $num, $row_num);
      if ($output_type == Search::HTML_OUTPUT) {
         echo Search::showItem($output_type, __('Name'), $num, $row_num, "style='$styleItemTitle'");
         echo Search::showItem($output_type, __('User'), $num, $row_num, "style='$styleItemTitle'");
         echo Search::showItem($output_type, __('Responding to typology\'s criteria', 'typology'), $num, $row_num, "style='$styleItemTitle'");
      } else {
         echo Search::showItem($output_type, __('Name'), $num, $row_num);
         echo Search::showItem($output_type, __('User'), $num, $row_num);
         echo Search::showItem($output_type, __('Responding to typology\'s criteria', 'typology'), $num, $row_num);
      }
      echo Search::showEndLine($output_type);

      //by computer
      $resultComputer = $DB->request([
         'SELECT'    => [
            'glpi_plugin_typology_typologies_items.itemtype',
            'glpi_plugin_typology_typologies_items.items_id',
            'glpi_plugin_typology_typologies_items.is_validated',
            'glpi_plugin_typology_typologies_items.error',
         ],
         'FROM'      => 'glpi_plugin_typology_typologies_items',
         'LEFT JOIN' => [
            'glpi_plugin_typology_typologies' => [
               'ON' => [
                  'glpi_plugin_typology_typologies_items' => 'plugin_typology_typologies_id',
                  'glpi_plugin_typology_typologies'       => 'id',
               ],
            ],
            'glpi_computers' => [
               'ON' => [
                  'glpi_plugin_typology_typologies_items' => 'items_id',
                  'glpi_computers'                        => 'id',
               ],
            ],
            'glpi_groups_items' => [
               'ON' => [
                  'glpi_groups_items' => 'items_id',
                  'glpi_computers'    => 'id', [
                     'AND' => [
                        'glpi_groups_items.itemtype' => Computer::class,
                        'glpi_groups_items.type'     => Group_Item::GROUP_TYPE_NORMAL,
                     ],
                  ],
               ],
            ],
         ],
         'WHERE'     => [
            // The computers of this service, or without any for the "None" row
            'glpi_groups_items.groups_id'                                         => (int) $dataService['groups_id'] > 0
               ? (int) $dataService['groups_id']
               : null,
            'glpi_plugin_typology_typologies_items.plugin_typology_typologies_id' => (int) $dataService['typoID'],
            'glpi_plugin_typology_typologies_items.itemtype'                      => 'Computer',
         ] + $typo_condition + $typo_criteria + $computer_condition,
         'ORDER'     => 'glpi_computers.name',
      ]);
      $computeOK=0;
      $computeNOTOK=0;

      foreach ($resultComputer as $dataComputer) {

         $link=Toolbox::getItemTypeFormURL("Computer");

         $computerName = "<a href='".htmlescape($link)."?id=".(int) $dataComputer["items_id"]."' target='_blank'>".
            htmlescape((string) Dropdown::getDropdownName("glpi_computers", $dataComputer["items_id"]))."</a>";

         $computer = new Computer();
         $computer->getFromDB($dataComputer["items_id"]);

         $userName = htmlescape((string) getUserName($computer->fields["users_id"] ?? 0));

         if ($dataComputer["is_validated"] > 0) {
            $critTypOK = __('Yes');
            $computeOK++;
         } else {
            $critTypOK = "<span class='typology_font_red'>".__('No')." ".
                           __('for the criteria', 'typology')." ";
            $i=0;

            $critTypOK.= Typology_Item::displayErrors($dataComputer["error"]);

            $critTypOK.="</span>";
            $computeNOTOK++;
         }

         $row_num++;
         $num=1;
         echo Search::showNewLine($output_type);
         echo Search::showItem($output_type, '', $num, $row_num);
         echo Search::showItem($output_type, $computerName, $num, $row_num);
         echo Search::showItem($output_type, $userName, $num, $row_num);
         echo Search::showItem($output_type, $critTypOK, $num, $row_num);
         echo Search::showEndLine($output_type);
      }

      $row_num++;
      $num=1;

      $message = "<b><span class='typology_font_green'>".__('Responding', 'typology')." ".
         $computeOK." / ".$dataService["COUNT"]."</span>".", "."<span class='typology_font_red'>".
         __('Not responding', 'typology')." ".$computeNOTOK." / ".$dataService["COUNT"]."</span></b>";
      echo Search::showNewLine($output_type);
      echo Search::showItem($output_type, '', $num, $row_num);
      echo Search::showItem($output_type, '', $num, $row_num);
      echo Search::showItem($output_type, __('Total'), $num, $row_num);
      echo Search::showItem($output_type, $message, $num, $row_num);
      echo Search::showEndLine($output_type);


      $row_num++;
      $num=1;
      echo Search::showNewLine($output_type);
      if ($output_type == Search::HTML_OUTPUT) {
         echo Search::showItem($output_type, '', $num, $row_num, "colspan = '4' style='$styleItemTitle'");
      } else {
         echo Search::showItem($output_type, '', $num, $row_num);
         echo Search::showItem($output_type, '', $num, $row_num);
         echo Search::showItem($output_type, '', $num, $row_num);
         echo Search::showItem($output_type, '', $num, $row_num);
      }
      echo Search::showEndLine($output_type);

   }
   echo Search::showFooter($output_type, $title);
}

if ($output_type == Search::HTML_OUTPUT) {
   Html::footer();
}

/**
 * Display the column title and allow the sort
 *
 * @param $output_type
 * @param $num
 * @param $title
 * @param $columnname
 * @param bool $sort
 * @return mixed
 */
function showTitle($output_type, &$num, $title, $columnname, $sort = false) {

   if ($output_type != Search::HTML_OUTPUT ||$sort==false) {
      echo Search::showHeaderItem($output_type, $title, $num);
      return;
   }
   $order = 'ASC';
   $issort = false;
   if (isset($_REQUEST['sort']) && $_REQUEST['sort']==$columnname) {
      $issort = true;
      if (isset($_REQUEST['order']) && $_REQUEST['order']=='ASC') {
         $order = 'DESC';
      }
   }
   $link  = $_SERVER['PHP_SELF'];
   $first = true;
   foreach ($_REQUEST as $name => $value) {
      if (!in_array($name, ['sort','order','PHPSESSID'])) {
         $link .= ($first ? '?' : '&amp;');
         $link .= $name .'='.urlencode($value);
         $first = false;
      }
   }
   $link .= ($first ? '?' : '&amp;').'sort='.urlencode($columnname);
   $link .= '&amp;order='.$order;
   echo Search::showHeaderItem($output_type, $title, $num,
      $link, $issort, ($order=='ASC'?'DESC':'ASC'));
}

/**
 * Build the ORDER BY clause
 *
 * @param $default string, name of the column used by default
 * @return string
 */
function getOrderBy($default, $columns) {

   if (!isset($_REQUEST['order']) || $_REQUEST['order']!='DESC') {
      $_REQUEST['order'] = 'ASC';
   }
   $order   = $_REQUEST['order'];

   // A column of the allow-list of the report, for the query builder
   $tab = getOrderByFields($default, $columns);
   if ($tab !== '') {
      return ["$tab $order"];
   }
   return [];
}

/**
 * Get the fields used for order
 *
 * @param $default string, name of the column used by default
 *
 * @return string of column names
 */
function getOrderByFields($default, $columns) {

   if (!isset($_REQUEST['sort'])) {
      $_REQUEST['sort'] = $default;
   }
   $colsort = $_REQUEST['sort'];

   foreach ($columns as $colname => $column) {
      if ($colname==$colsort) {
         return $column['sorton'];
      }
   }
   return "";
}

