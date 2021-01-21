<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page to reset factor for users.
 *
 * @package     tool_mfa
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
admin_externalpage_setup('tool_mfa_resetfactor');

$bulk = !empty($SESSION->bulk_users);

$factors = \tool_mfa\plugininfo\factor::get_factors();
$form = new \tool_mfa\local\form\reset_factor(null, array('factors' => $factors, 'bulk' => $bulk));

if ($form->is_cancelled()) {
    if ($bulk) {
        $url = new moodle_url('/admin/user/user_bulk.php');
    } else {
        $url = new moodle_url('/admin/category.php', ['category' => 'toolmfafolder']);
    }
    redirect($url);
} else if ($fromform = $form->get_data()) {
    // Get factor from select index.
    if ($fromform->factor !== 'all') {
        $factor = $factors[$fromform->factor];
    } else {
        $factor = 'all';
    }

    // Setup var to put into notification strings.
    $stringvar = $factor === 'all' ? get_string('all') : $factor->get_display_name();

    // Setup user array for bulk action.
    $users = $bulk ? $SESSION->bulk_users : [$fromform->user];

    foreach($users as $user) {
        if (!$user instanceof stdClass) {
            $user = \core_user::get_user($user);
        }
        if ($factor === 'all') {
            $DB->delete_records('tool_mfa', ['userid' => $user->id]);
        } else {
            $factor->delete_factor_for_user($user);
        }

        // Add a user preference, to display a notification to the user that their factor was reset.
        $factorname = $factor === 'all' ? 'all' : $factor->name;
        $prefname = 'tool_mfa_reset_' . $factorname;
        set_user_preference($prefname, true, $user);

        // If we are just doing 1 user.
        if (!$bulk) {
            $stringarr = array('factor' => $stringvar, 'username' => $user->username);
            \core\notification::success(get_string('resetsuccess', 'tool_mfa', $stringarr));

            // Reload page.
            redirect($PAGE->url);
        }
    }

    \core\notification::success(get_string('resetsuccessbulk', 'tool_mfa', $stringvar));
    unset($SESSION->bulk_users);
    // Redirect to bulk actions page.
    redirect(new moodle_url('/admin/user/user_bulk.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('resetfactor', 'tool_mfa'));
$form->display();
echo $OUTPUT->footer();
