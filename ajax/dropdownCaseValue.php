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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Typology\TypologyCriteriaDefinition;

if (strpos($_SERVER['PHP_SELF'], "dropdownCaseValue.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}
Session::checkLoginUser();
Session::checkRight('plugin_typology', UPDATE);

// Back on the empty choice of the field list: nothing to display
if (empty($_POST['field'])) {
    return;
}

// The criterion must be one the user may update, the field a valid "column;table;type"
if (!TypologyCriteriaDefinition::canEditField($_POST['typocrit_id'] ?? 0, $_POST['field'] ?? null)) {
    throw new AccessDeniedHttpException();
}

$options = ['itemtype'     => $_POST['itemtype'] ?? '',
    'typocrit_id'  => (int) $_POST['typocrit_id'],
    'field'        => $_POST['field'],
    'action_type'  => $_POST['action_type'] ?? ''];

//Display list of values or text field depending on action_type
TypologyCriteriaDefinition::dropdownValues($options);
