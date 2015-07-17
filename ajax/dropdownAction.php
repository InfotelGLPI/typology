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

// Direct access to file
if (strpos($_SERVER['PHP_SELF'],"dropdownAction.php")) {
   include ('../../../inc/includes.php');
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

//Display list of logical operator depending on field
PluginTypologyTypologyCriteriaDefinition::dropdownSelect($_POST['itemtype'],
                                                         $_POST['typocrit_id'],
                                                         $_POST['field']);

?>