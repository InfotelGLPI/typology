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

use GlpiPlugin\Typology\RuleTypologyCollection;
use GlpiPlugin\Typology\Typology;
use GlpiPlugin\Typology\Typology_Item;

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

$typo = new Typology();
$typo_item = new Typology_Item();

if (isset($_POST["add"])) {
   $typo->check(-1, CREATE, $_POST);
   $newID = $typo->add($_POST);

   Html::back();

} else if (isset($_POST["delete"])) {
   $typo->check($_POST['id'], DELETE);
   $typo->delete($_POST);

   $typo->redirectToList();

} else if (isset($_POST["update"])) {
   $typo->check($_POST['id'], UPDATE);
   $typo->update($_POST);

   Html::back();

} else if (isset($_POST["purge"])) {
   $typo->check($_POST['id'], PURGE);
   $typo->delete($_POST, 1);
   $typo->redirectToList();

} else if (isset($_POST["restore"])) {
   $typo->check($_POST['id'], PURGE);
   $typo->restore($_POST);
   $typo->redirectToList();

} else if (isset($_POST["add_item"])) {

   // The item type is posted: the types of the plugin only, and an item the user may read,
   // before its rules are run
   if (!empty($_POST['itemtype'])) {
      if (!in_array($_POST['itemtype'], Typology::getTypes(true), true)
          || !($item = getItemForItemtype($_POST['itemtype']))) {
         throw new \Glpi\Exception\Http\BadRequestHttpException();
      }
      $item->check((int) $_POST['items_id'], READ);
      $typo->check((int) $_POST['plugin_typology_typologies_id'], READ);

      $input = ['plugin_typology_typologies_id' => (int) $_POST['plugin_typology_typologies_id'],
                              'items_id'      => (int) $_POST['items_id'],
                              'itemtype'      => $_POST['itemtype']];
      if ($item->getFromDB($input['items_id'])) {
         $ruleCollection = new RuleTypologyCollection($item->fields['entities_id']);
         $fields= [];
         $item->input = $input['plugin_typology_typologies_id'];
         $fields=$ruleCollection->processAllRules($item->fields, $fields, []);
         //Store rule that matched

         if (isset($fields['_ruleid'])) {
            if ($input['plugin_typology_typologies_id'] != $fields['plugin_typology_typologies_id']) {
               $message = __s('Element not match with the rule for assigning the typology:', 'typology')." ".
                  htmlescape((string) Dropdown::getDropdownName('glpi_plugin_typology_typologies', $input['plugin_typology_typologies_id']));
               Session::addMessageAfterRedirect($message, ERROR, true);
            } else {
               $typo_item->check(-1, UPDATE, $input);
               $typo_item->add($input);

               $values = ['plugin_typology_typologies_id' => $input['plugin_typology_typologies_id'],
                            'items_id'      => $input['items_id'],
                            'itemtype'      => $input['itemtype']];

               Typology_Item::addLog($values, Typology_Item::LOG_ADD);

            }
         } else {
            $message = __('Element not match with rules for assigning a typology', 'typology');
            Session::addMessageAfterRedirect($message, ERROR, true);
         }
      }
   }
   Html::back();

} else if (isset($_POST["update_item"])) {

   if (!empty($_POST['itemtype'])) {
      // The relation first: its own item and typology are recomputed, not the posted ones
      $typo_item->check((int) $_POST['id'], UPDATE);
      $input = Typology_Item::checkValidated([
         'id'                            => $typo_item->getID(),
         'items_id'                      => $typo_item->fields['items_id'],
         'plugin_typology_typologies_id' => $typo_item->fields['plugin_typology_typologies_id'],
      ]);
      $typo_item->update($input);

      $values = ['plugin_typology_typologies_id' => $typo_item->fields['plugin_typology_typologies_id'],
                            'items_id'      => $typo_item->fields['items_id'],
                            'itemtype'      => $typo_item->fields['itemtype']];

       Typology_Item::addLog($values, Typology_Item::LOG_UPDATE);

   }
   Html::back();

} else if (isset($_POST["delete_item"])) {

   if (!empty($_POST['itemtype'])) {
      // The relation must be removable by the user; the history is written from the relation
      // itself, never from the request
      $typo_item->check((int) $_POST['id'], PURGE);
      $values = ['plugin_typology_typologies_id' => $typo_item->fields['plugin_typology_typologies_id'],
                            'items_id'      => $typo_item->fields['items_id'],
                            'itemtype'      => $typo_item->fields['itemtype']];

      $typo_item->delete(['id' => (int) $_POST['id']]);

       Typology_Item::addLog($values, Typology_Item::LOG_DELETE);

   } else {
      foreach ((array) ($_POST["item"] ?? []) as $key => $val) {
         if ($val==1) {
            $typo_item->check((int) $key, PURGE);
            $typo_item->delete(['id' => (int) $key]);
         }
      }
   }
   Html::back();

} else {
   $typo->checkGlobal(READ);
   Html::header(Typology::getTypeName(2), '', "tools", Typology::class);

   $typo->display($_GET);
   Html::footer();
}
