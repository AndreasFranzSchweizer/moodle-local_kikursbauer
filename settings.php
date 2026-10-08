<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings of local_kikursbauer: status overview and the switch "own course area only".
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_kikursbauer', get_string('pluginname', 'local_kikursbauer'));

    if ($ADMIN->fulltree) {
        global $DB;
        $yes = get_string('statusyes', 'local_kikursbauer');
        $no = get_string('statusno', 'local_kikursbauer');
        $protocols = explode(',', (string) get_config('core', 'webserviceprotocols'));
        $roleid = $DB->get_field('role', 'id', ['shortname' => \local_kikursbauer\helper::ROLE_SHORTNAME]);
        $status = (object) [
            'webservices' => empty($CFG->enablewebservices) ? $no : $yes,
            'rest' => in_array('rest', $protocols) ? $yes : $no,
            'service' => $DB->record_exists('external_services',
                ['shortname' => \local_kikursbauer\helper::SERVICE_SHORTNAME, 'enabled' => 1]) ? $yes : $no,
            'role' => $roleid ? $yes : $no,
            'assignurl' => (new moodle_url('/admin/roles/assign.php',
                ['contextid' => context_system::instance()->id, 'roleid' => (int) $roleid]))->out(false),
        ];
        $settings->add(new admin_setting_heading('local_kikursbauer/status',
            get_string('status', 'local_kikursbauer'), get_string('status_desc', 'local_kikursbauer', $status)));
    }

    $settings->add(new admin_setting_configcheckbox('local_kikursbauer/owncategoryonly',
        get_string('owncategoryonly', 'local_kikursbauer'), get_string('owncategoryonly_desc', 'local_kikursbauer'), 1));

    $ADMIN->add('localplugins', $settings);
}
