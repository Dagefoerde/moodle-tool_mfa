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
 * AWS SNS SMS Gateway class
 *
 * @package     factor_sms
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace factor_sms\local\smsgateway;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');
use Aws\Sns\SnsClient;

class aws_sns implements gateway_interface {
    public function send_sms_message($messagecontent, $phonenumber) {
        global $CFG, $SITE;

        $config = get_config('factor_sms');

        $client = new SnsClient([
            'version' => 'latest',
            'region' => $config->api_region,
            'credentials' => [
                'key' => $config->api_key,
                'secret' => $config->api_secret
            ],
            'http' => ['proxy' => \local_aws\local\aws_helper::get_proxy_string()]
        ]);

        // Setup the sender information.
        $senderid = !empty($CFG->supportname) ? $CFG->supportname : $SITE->fullname;
        // Remove spaces from ID.
        $senderid = str_replace(' ', '', (trim($senderid)));

        // These messages need to be transactional.
        $client->SetSMSAttributes([
            'attributes' => [
                'DefaultSMSType' => 'Transactional',
                'DefaultSenderID' => $senderid,
            ],
        ]);

        // Phone number mangling here to make it happy.
        if (strpos($phonenumber, '+' !== 0)) {
            // Not in the right standard. Transform it.
            // TODO AWS Pinpoint verification.
        }

        // Actually send the message.
        $client->publish([
            'Message' => $messagecontent,
            'PhoneNumber' => $phonenumber,
        ]);
    }
}