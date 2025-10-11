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

global $CFG_GLPI;

use GlpiPlugin\Typology\Typology;
use GlpiPlugin\Typology\TypologyCriteria;
use GlpiPlugin\Typology\TypologyCriteriaDefinition;

$typo = new Typology();
$criteria = new TypologyCriteria();

if (isset($_POST["update"])) {

   $criteria->check($_POST['id'], UPDATE);
   $criteria->update($_POST);
   Html::back();

} else if (isset($_POST["add"])) {

   if (isset($_POST["itemtype"])
         && !empty($_POST["itemtype"])) {
      $criteria->check(-1, CREATE, $_POST);
      $newID = $criteria->add($_POST);
      Html::redirect(PLUGIN_TYPOLOGY_WEBDIR . "/front/typologycriteria.form.php?id=$newID");
   } else {
      Session::addMessageAfterRedirect(__('No element to be tested'), false, ERROR);
      Html::back();
   }

} else if (isset($_POST["purge"])) {

   $criteria->check($_POST['id'], PURGE);
   $criteria->delete($_POST);
   $criteria->redirectToList();

} else if (isset($_POST["add_action"])) {

   $criteria->check($_POST['plugin_typology_typologycriterias_id'], UPDATE);
   $definition = new TypologyCriteriaDefinition();
   $definition->add($_POST);

   // Mise à jour de l'heure de modification pour le critère
   $criteria->update(['id'       => $_POST['plugin_typology_typologycriterias_id'],
                           'date_mod' => $_SESSION['glpi_currenttime']]);
   Html::back();

} else if (isset($_POST["delete_action"])) {

   $definition = new TypologyCriteriaDefinition();

   if (isset($_POST["item"]) && count($_POST["item"])) {
      foreach ($_POST["item"] as $key => $val) {
         if ($val == 1) {
            if ($definition->can($key, UPDATE)) {
               $definition->delete(['id' => $key]);
            }
         }
      }
   } else if (isset($_POST['id'])) {
      $definition->check($_POST['id'], UPDATE);
      $definition->delete($_POST);
   }

   $criteria->check($_POST['plugin_typology_typologycriterias_id'], UPDATE);

   // Can't do this in RuleAction, so do it here
   $criteria->update(['id'       => $_POST['plugin_typology_typologycriterias_id'],
                           'date_mod' => $_SESSION['glpi_currenttime']]);
   Html::back();

} else {
   $typo->checkGlobal(READ);
   Html::header(Typology::getTypeName(2), '', "tools", Typology::class);

   $criteria->display($_GET);
   Html::footer();
}
