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
use core_external\external_single_structure;
use core_external\external_value;
use local_kikursbauer\helper;

/**
 * Moves an activity to a section of the same course.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_module extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'section' => new external_value(PARAM_INT, 'Target section number (0 = general section)'),
            'beforecmid' => new external_value(PARAM_INT, 'Put it before this course module (0 = end of section)',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Moves the module.
     *
     * @param int $cmid
     * @param int $section
     * @param int $beforecmid
     * @return array
     */
    public static function execute(int $cmid, int $section, int $beforecmid = 0): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'section' => $section,
            'beforecmid' => $beforecmid,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_course::instance($course->id);
        helper::require_course_access($course, $context, ['moodle/course:manageactivities']);

        helper::ensure_section($course, $params['section']);
        $modinfo = get_fast_modinfo($course->id, 0, true);
        $sectioninfo = $modinfo->get_section_info($params['section'], MUST_EXIST);
        $cm = $modinfo->get_cm($cm->id);

        $before = null;
        if ($params['beforecmid']) {
            $beforecm = $modinfo->get_cm($params['beforecmid']);
            if ((int) $beforecm->sectionnum !== $params['section']) {
                throw new \moodle_exception('beforecmnotinsection', 'local_kikursbauer', '', $params['beforecmid']);
            }
            $before = (int) $beforecm->id;
        }

        moveto_module($cm, $sectioninfo, $before);
        rebuild_course_cache($course->id, true);

        return [
            'cmid' => (int) $cm->id,
            'section' => $params['section'],
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'url' => new external_value(PARAM_URL, 'Course URL'),
        ]);
    }
}
