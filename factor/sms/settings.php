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
 * Settings
 *
 * @package     factor_sms
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings->add(new admin_setting_configcheckbox('factor_sms/enabled',
    new lang_string('settings:enablefactor', 'tool_mfa'),
    new lang_string('settings:enablefactor_help', 'tool_mfa'), 0));

$settings->add(new admin_setting_configtext('factor_sms/weight',
    new lang_string('settings:weight', 'tool_mfa'),
    new lang_string('settings:weight_help', 'tool_mfa'), 100, PARAM_INT));

// Gateway selection.
$gateways = \factor_sms\plugininfo\smsgateway::get_gateways();
$selectopts = ['none' => get_string('nogateway', 'factor_sms')];
foreach ($gateways as $gateway) {
    $selectedopts[$gateway->name] = get_string('pluginname', 'smsgateway_' . $gateway->name);
}
$settings->add(new admin_setting_configselect('factor_sms/selectedgateway',
    get_string('settings:selectedgateway', 'factor_sms'),
    get_string('settings:selectedgateway_help', 'factor_sms'), 'none', $selectopts));

$currentselection = get_config('factor_sms', 'selectedgateway');
if ($currentselection !== 'none') {
    \factor_sms\plugininfo\smsgateway::load_gateway_settings($currentselection);
}
/**$settings->

// AWS Settings.
$settings->add(new admin_setting_configtext('factor_sms/api_key',
    get_string('settings:aws:key', 'factor_sms'),
    get_string('settings:aws:key_help', 'factor_sms'),
    ''));

$settings->add(new admin_setting_configpasswordunmask('factor_sms/api_secret',
    get_string('settings:aws:secret', 'factor_sms'),
    get_string('settings:aws:secret_help', 'factor_sms'),
    ''));

// These are the only regions that  is available in.
$regionoptions = array(
    'us-east-1'      => 'US East (N. Virginia)',
    'us-west-1'      => 'US West (N. California)',
    'us-west-2'      => 'US West (Oregon)',
    'ap-northeast-1' => 'Asia Pacific (Tokyo)',
    'ap-south-1'     => 'Asia Pacific (Mumbai)',
    'ap-southeast-1' => 'Asia Pacific (Singapore)',
    'ap-southeast-2' => 'Asia Pacific (Sydney)',
    'eu-west-1'      => 'EU (Ireland)',
);
$settings->add(new admin_setting_configselect('local_smartmedia/api_region',
    get_string('settings:aws:region', 'local_smartmedia'),
    get_string('settings:aws:region_help', 'local_smartmedia'),
    'ap-southeast-2',
    $regionoptions));

$settings->add(new admin_setting_configduration('factor_email/duration',
    get_string('settings:duration', 'factor_email'),
    get_string('settings:duration_help', 'factor_email'), 30 * MINSECS, MINSECS));**/
