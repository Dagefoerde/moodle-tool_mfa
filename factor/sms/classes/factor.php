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
            [
                'autocomplete' => 'one-time-code',
                'autofocus' => 'autofocus',
                'inputmode' => 'numeric',
                'pattern'   => '[0-9]*',
            ]);
        $mform->setType("verificationcode", PARAM_INT);
        return $mform;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function login_form_definition_after_data($mform) {
        $instanceid = $this->generate_and_sms_code();
        $mform = $this->add_redacted_sent_message($mform, $instanceid);
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
            $return['verificationcode'] = get_string('wrongcode', 'factor_sms');
        }

        return $return;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function setup_factor_form_definition($mform) {
        global $OUTPUT, $USER;
        if (empty($USER->phone2)) {
            // User has no phone number. Send them to profile to set it.
            $profurl = new \moodle_url('/user/edit.php', ['id' => $USER->id]);
            $proflink = \html_writer::link($profurl, get_string('profilelink', 'factor_sms'));
            $mform->addElement('html', $OUTPUT->notification(
                get_string('profilesetnumber', 'factor_sms', $proflink), 'notifyinfo'));
            $mform->addElement('hidden', 'verificationcode', 0);
        } else {
            $mform->addElement('text', 'verificationcode', get_string('verificationcode', 'factor_sms'),
            [
                'autocomplete' => 'one-time-code',
                'autofocus' => 'autofocus',
                'inputmode' => 'numeric',
                'pattern'   => '[0-9]*',
            ]);
        }
        $mform->setType("verificationcode", PARAM_INT);

    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function setup_factor_form_definition_after_data($mform) {
        global $USER, $SESSION;

        // Nothing if they dont have a number added.
        if (empty($USER->phone2)) {
            return $mform;
        }

        if (empty($SESSION->mfa_sms_setup_code)) {
            // They need a code to verify this number.
            // Lets generate a code and send it in place, we don't need this to get to the DB.
            $code = random_int(100000, 999999);

            $this->sms_verification_code(0, $code);
            $SESSION->mfa_sms_setup_code = $code;
            $mform = $this->add_redacted_sent_message($mform);
        } else {
            // Dont generate or send, just tell them you did!
            $mform = $this->add_redacted_sent_message($mform);
        }
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function setup_factor_form_validation($data) {
        global $SESSION, $USER;

        // Prevent submission if they dont have a number added.
        if (empty($USER->phone2)) {
            return ['verificationcode' => ''];
        }

        $errors = [];
        if ($data['verificationcode'] !== $SESSION->mfa_sms_setup_code) {
            $errors['verificationcode'] = get_string('wrongcode', 'factor_sms');
        }

        return $errors;
    }

    public function setup_user_factor($data) {
        global $DB, $SESSION, $USER;

        // Nothing if they dont have a number added.
        if (empty($USER->phone2)) {
            return 0;
        }

        $row = new \stdClass();
        $row->userid = $USER->id;
        $row->factor = $this->name;
        $row->secret = '';
        $row->label = $USER->phone2;
        $row->timecreated = time();
        $row->createdfromip = $USER->lastip;
        $row->timemodified = time();
        $row->lastverified = time();
        $row->revoked = 0;

        $id = $DB->insert_record('tool_mfa', $row);
        $record = $DB->get_record('tool_mfa', array('id' => $id));
        $this->create_event_after_factor_setup($USER);

        // Remove session code.
        unset($SESSION->mfa_sms_setup_code);

        return $record;
    }

    /**
     * Adds a redacted sent messaage to the mform with the users number.
     *
     * @param stdClass $mform the form to modify.
     * @param int|null $instanceid the instance to take the number from.
     */
    private function add_redacted_sent_message($mform, $instanceid = null) {
        global $DB, $USER;

        if (!empty($instanceid)) {
            $number = $DB->get_field('tool_mfa', 'label', ['id' => $instanceid]);
        } else {
            $number = $USER->phone2;
        }

        // Create partial num for display.
        $len = strlen($number);
        // Keep last 3 characters.
        $redacted = str_repeat('x', $len - 3);
        $redacted .= substr($number, -3);

        $mform->addElement('html', \html_writer::tag('p', get_string('smssent', 'factor_sms', $redacted) . '<br>'));
        return $mform;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function get_all_user_factors($user) {
        global $DB;

        $sql = 'SELECT *
                  FROM {tool_mfa}
                 WHERE userid = ?
                   AND factor = ?
                   AND label IS NOT NULL
                   AND revoked = 0
                   AND secret = ?';

        return $DB->get_records_sql($sql, [$user->id, $this->name, '']);
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function is_enabled() {
        global $CFG;
        // If local_aws is not installed, not enabled.
        if (!file_exists($CFG->dirroot . '/local/aws/version.php')) {
            return false;
        } else {
            return parent::is_enabled();
        }
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
     * SMS Factor implementation
     *
     * {@inheritDoc}
     */
    public function show_setup_buttons() {
        global $DB, $USER;
        // If there is already a factor setup, don't allow multiple (for now).
        $sql = 'SELECT *
                  FROM {tool_mfa}
                 WHERE userid = ?
                   AND factor = ?
                   AND secret = ?
                   AND revoked = 0';

        $record = $DB->get_record_sql($sql, [$USER->id, $this->name, '']);
        return !empty($record) ? false : true;
    }

    /**
     * SMS Factor implementation.
     *
     * {@inheritDoc}
     */
    public function has_revoke() {
        return true;
    }

    /**
     * Generates and sms' the code for login to the user, stores codes in DB.
     *
     * @return int the instance ID being used.
     */
    private function generate_and_sms_code() {
        global $DB, $USER;

        // Get instance that isnt parent SMS type (empty secret check).
        // This check must exclude the main singleton record, with an empty secret.
        // It must only grab the record with the user agent as the label.
        $sql = 'SELECT *
                  FROM {tool_mfa}
                 WHERE userid = ?
                   AND factor = ?
                   AND secret <> ?
                   AND revoked = 0';

        $record = $DB->get_record_sql($sql, [$USER->id, $this->name, '']);
        $duration = get_config('factor_sms', 'duration');
        $newcode = random_int(100000, 999999);

        $numsql = 'SELECT label
                     FROM {tool_mfa}
                    WHERE userid = ?
                      AND factor = ?
                      AND secret = ?
                      AND revoked = 0';
        $number = $DB->get_field_sql($numsql, [$USER->id, $this->name, '']);

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
            return $instanceid;

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
            return $instanceid;
        }
        return $record->id;
    }

    /**
     * This function sends an SMS code to the user based on the instanceid provided.
     * If rawcode is provided, it will send the raw code through to the phone number in the user profile.
     *
     * @param int $instanceid the factor instance to send the code for.
     * @param int|null $rawcode If provided, a raw code to send to the users profile phone no.
     * @return void
     */
    private function sms_verification_code($instanceid, $rawcode = null) {
        global $CFG, $DB, $SITE, $USER;

        // Here we should get the information, then construct the message.
        $instance = $DB->get_record('tool_mfa', ['id' => $instanceid]);
        $content = ['site' => $SITE->fullname, 'url' => $CFG->wwwroot];
        $content['code'] = !empty($rawcode) ? $rawcode : $instance->secret;
        $phonenumber = !empty($rawcode) ? $USER->phone2 : $instance->label;
        $message = get_string('smsstring', 'factor_sms', $content);

        $gateway = new \factor_sms\local\smsgateway\aws_sns();
        $gateway->send_sms_message($message, $phonenumber);
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
                   AND secret <> ?';
        $record = $DB->get_record_sql($sql, [$USER->id, $this->name, '']);

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
                  AND secret <> ?';
        $DB->delete_records_select('tool_mfa', $selectsql, array($USER->id, $this->name, ''));

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
