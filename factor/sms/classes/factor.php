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
 * SMS Factor class.
 *
 * @package     factor_sms
 * @subpackage  tool_mfa
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace factor_sms;

defined('MOODLE_INTERNAL') || die();

use tool_mfa\local\factor\object_factor_base;

class factor extends object_factor_base {
    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function login_form_definition($mform) {

        $mform->addElement('text', 'verificationcode', get_string('verificationcode', 'factor_sms'),
            ['autocomplete' => 'one-time-code']);
        $mform->setType("verificationcode", PARAM_ALPHANUM);
        return $mform;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function login_form_definition_after_data($mform) {
        $this->generate_and_sms_code();
        return $mform;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function login_form_validation($data) {
        $return = array();

        if (!$this->check_verification_code($data['verificationcode'])) {
            $return['verificationcode'] = get_string('error:wrongverification', 'factor_email');
        }

        return $return;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function get_all_user_factors($user) {
        global $DB;

        $records = $DB->get_records('tool_mfa', array(
            'userid' => $user->id,
            'factor' => $this->name,
        ));

        if (!empty($records)) {
            return $records;
        }

        // Null records returned, build new record.
        $record = array(
            'userid' => $user->id,
            'factor' => $this->name,
            'createdfromip' => $user->lastip,
            'timecreated' => time(),
            'revoked' => 0,
        );
        $record['id'] = $DB->insert_record('tool_mfa', $record, true);
        return [(object) $record];
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function has_input() {
        return true;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function has_setup() {
        return true;
    }

    /**
     * Generates and emails the code for login to the user, stores codes in DB.
     *
     * @return void
     */
    private function generate_and_sms_code() {
        global $DB, $USER;

        // Get instance that isnt parent SMS type (empty label check).
        // This check must exclude the main singleton record, with the label as the email.
        // It must only grab the record with the user agent as the label.
        $sql = 'SELECT *
                  FROM {tool_mfa}
                 WHERE userid = ?
                   AND factor = ?
               AND label IS NOT NULL';

        $record = $DB->get_record_sql($sql, array($USER->id, 'sms'));
        $duration = get_config('factor_sms', 'duration');
        $newcode = random_int(100000, 999999);

        $number = $USER->phone2;

        if (empty($record)) {
            // No code active, generate new code.
            $instanceid = $DB->insert_record('tool_mfa', array(
                'userid' => $USER->id,
                'factor' => 'sms',
                'secret' => $newcode,
                'label' => $number,
                'timecreated' => time(),
                'createdfromip' => $USER->lastip,
                'timemodified' => time(),
                'lastverified' => time(),
                'revoked' => 0,
            ), true);
            $this->sms_verification_code($instanceid);

        } else if ($record->timecreated + $duration < time()) {
            // Old code found. Keep id, update fields.
            $DB->update_record('tool_mfa', array(
                'id' => $record->id,
                'secret' => $newcode,
                'label' => $number,
                'timecreated' => time(),
                'createdfromip' => $USER->lastip,
                'timemodified' => time(),
                'lastverified' => time(),
                'revoked' => 0,
            ));
            $instanceid = $record->id;
            $this->sms_verification_code($instanceid);
        }
    }

    private function sms_verification_code($instanceid) {
        global $DB;

        // Here we should get the information, then construct the message.
        $instance = $DB->get_record('tool_mfa', ['id' => $instanceid]);
        $user = \core_user::get_user($instance->userid);
        $phonenumber = $user->phone2;
        $gateway = new \factor_sms\local\smsgateway\aws_sns();
        $gateway->send_sms_message($instance->secret, $phonenumber);
    }

    /**
     * Verifies entered code against stored DB record.
     *
     * @return bool
     */
    private function check_verification_code($enteredcode) {
        global $DB, $USER;
        $duration = get_config('factor_email', 'duration');

        // Get instance that isnt parent email type (label check).
        // This check must exclude the main singleton record, with the label as the email.
        // It must only grab the record with the user agent as the label.
        $sql = 'SELECT *
                  FROM {tool_mfa}
                 WHERE userid = ?
                   AND factor = ?
               AND label IS NOT NULL';
        $record = $DB->get_record_sql($sql, array($USER->id, 'sms'));

        if ($enteredcode == $record->secret) {
            if ($record->timecreated + $duration > time()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cleans up email records once MFA passed.
     *
     * {@inheritDoc}
     */
    public function post_pass_state() {
        global $DB, $USER;
        // Delete all SMS records except base record.
        $selectsql = 'userid = ?
                  AND factor = ?
                  AND label IS NOT NULL';
        $DB->delete_records_select('tool_mfa', $selectsql, array($USER->id, 'sms'));

        // Update factor timeverified.
        parent::post_pass_state();
    }

    /**
     * SMS factor implementation.
     *
     * {@inheritDoc}
     */
    public function possible_states($user) {
        return array(
            \tool_mfa\plugininfo\factor::STATE_PASS,
            \tool_mfa\plugininfo\factor::STATE_NEUTRAL,
            \tool_mfa\plugininfo\factor::STATE_UNKNOWN,
        );
    }
}
