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
 * Subplugin information.
 *
 * @package     factor_sms
 * @author      Peter Burnett <peterburnett@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace factor_sms\plugininfo;

defined('MOODLE_INTERNAL') || die();

class smsgateway extends \core\plugininfo\base {
    public static function get_gateways() {
        $return = array();
        $gateways = \core_plugin_manager::instance()->get_plugins_of_type('smsgateway');

        foreach ($gateways as $gateway) {
            $classname = '\\smsgateway_'.$gateway->name.'\\gateway';
            if (class_exists($classname)) {
                $return[] = new $classname($gateway->name);
            }
        }
        return $return;
    }

    public static function get_gateway($gatewayname) {
        $classname = '\\smsgateway_' . $gatewayname . '\\gateway';
        if (class_exists($classname)) {
            return new $classname($gatewayname);
        } else {
            return null;
        }
    }

    public static function get_enabled_gateway() {
        $selected = get_config('factor_sms', 'selectedgateway');
        $gatewayinstance = self::get_gateway($selected);
        if (!empty($gatewayinstance)) {
            return $gatewayinstance;
        } else {
            return null;
        }
    }

    public static function load_gateway_settings($gatewayname) {

    }
}