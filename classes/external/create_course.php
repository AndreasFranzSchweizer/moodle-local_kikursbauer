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

/**
 * Creates a hidden course in a category and enrols the creator like course/edit.php does.
 *
 * core_course_create_courses does not enrol the creator, so a teacher with the role "course creator" could not edit
 * the new course afterwards.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_course extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Course category id (the teacher\'s own course area)'),
            'fullname' => new external_value(PARAM_TEXT, 'Full course name'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name (unique on the site)'),
            'format' => new external_value(PARAM_PLUGIN, 'Course format, e.g. topics or tiles', VALUE_DEFAULT, 'topics'),
            'numsections' => new external_value(PARAM_INT, 'Number of sections to create', VALUE_DEFAULT, 0),
            'summary' => new external_value(PARAM_RAW, 'Course summary as HTML', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students (default: hidden)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Creates the course.
     *
     * @param int $categoryid
     * @param string $fullname
     * @param string $shortname
     * @param string $format
     * @param int $numsections
     * @param string $summary
     * @param bool $visible
     * @return array
     */
    public static function execute(int $categoryid, string $fullname, string $shortname, string $format = 'topics',
            int $numsections = 0, string $summary = '', bool $visible = false): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
            'format' => $format,
            'numsections' => $numsections,
            'summary' => $summary,
            'visible' => $visible,
        ]);

        $categorycontext = \context_coursecat::instance($params['categoryid']);
        self::validate_context($categorycontext);
        require_capability('local/kikursbauer:use', \context_system::instance());
        require_capability('moodle/course:create', $categorycontext);

        if (!in_array($params['format'], get_sorted_course_formats(true), true)) {
            throw new \moodle_exception('invalidformat', 'local_kikursbauer', '', $params['format']);
        }

        $course = create_course((object) [
            'category' => $params['categoryid'],
            'fullname' => $params['fullname'],
            'shortname' => $params['shortname'],
            'format' => $params['format'],
            'numsections' => max(0, $params['numsections']),
            'summary' => $params['summary'],
            'summaryformat' => FORMAT_HTML,
            'visible' => (int) $params['visible'],
            'startdate' => usergetmidnight(time()),
        ]);

        // Enrol the creator with the default role for course creators (as course/edit.php does).
        $context = \context_course::instance($course->id);
        if (!empty($CFG->creatornewroleid) && !is_viewing($context, null, 'moodle/role:assign')
                && !is_enrolled($context, null, 'moodle/role:assign')) {
            enrol_try_internal_enrol($course->id, $USER->id, $CFG->creatornewroleid);
        }

        return [
            'courseid' => (int) $course->id,
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
            'courseid' => new external_value(PARAM_INT, 'New course id'),
            'url' => new external_value(PARAM_URL, 'Course URL'),
        ]);
    }
}
