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

namespace local_kikursbauer\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Lists what the site offers for course building: activity modules and installed H5P libraries.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_info extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns modules and H5P libraries.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB;
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kikursbauer:use', $context);

        $modules = array_values(array_map(fn($m) => ['name' => $m->name, 'visible' => (int) $m->visible],
            $DB->get_records('modules', null, 'name', 'id, name, visible')));
        $libraries = array_values(array_map(fn($l) => [
                'machinename' => $l->machinename,
                'majorversion' => (int) $l->majorversion,
                'minorversion' => (int) $l->minorversion,
                'patchversion' => (int) $l->patchversion,
                'runnable' => (int) $l->runnable,
            ], $DB->get_records('h5p_libraries', null, 'machinename, majorversion, minorversion',
                'id, machinename, majorversion, minorversion, patchversion, runnable')));
        return ['modules' => $modules, 'h5plibraries' => $libraries];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'modules' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_PLUGIN, 'Module name'),
                'visible' => new external_value(PARAM_INT, 'Enabled on the site'),
            ])),
            'h5plibraries' => new external_multiple_structure(new external_single_structure([
                'machinename' => new external_value(PARAM_RAW, 'e.g. H5P.MultiChoice'),
                'majorversion' => new external_value(PARAM_INT, 'Major version'),
                'minorversion' => new external_value(PARAM_INT, 'Minor version'),
                'patchversion' => new external_value(PARAM_INT, 'Patch version'),
                'runnable' => new external_value(PARAM_INT, '1 = content type, 0 = dependency'),
            ])),
        ]);
    }
}
