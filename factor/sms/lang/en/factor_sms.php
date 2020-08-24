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
 * Language strings.
 *
 * @package     factor_sms
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'SMS factor';
$string['privacy:metadata'] = 'The SMS factor plugin does not store any personal data';
$string['loginsubmit'] = 'Verify Code';
$string['loginskip'] = "I didn't receive a code";
$string['settings:aws:key'] = 'Key';
$string['settings:aws:key_help'] = 'Amazon API key credential.';
$string['settings:aws:secret'] = 'Secret';
$string['settings:aws:secret_help'] = 'Amazon API secret credential.';
$string['settings:aws:region'] = 'Region';
$string['settings:aws:region_help'] = 'Amazon API gateway region.';
$string['settings:duration'] = 'Validity duration';
$string['settings:duration_help'] = 'The period of time that the code is valid.';
$string['smssent'] = 'An SMS message containing your verification code was sent to {$a}.';
$string['smsstring'] = '{$a->code} is your {$a->site} authentication code.

@{$a->url} #{$a->code}';
$string['verificationcode'] = 'Enter verification code for confirmation';
$string['verificationcode_help'] = 'Verification code has been sent to your email address';
$string['info'] = 'Setup a phone number to receive MFA tokens on.';
$string['setupfactor'] = 'SMS factor setup';
$string['summarycondition'] = 'Using an SMS token';
$string['wrongcode'] = 'Invalid verification code.';
$string['action:revoke'] = 'Revoke SMS factor';
$string['awssdkrequired'] = 'The local_aws plugin leveraging the AWS SDK is required to use this factor. Please install local_aws.';