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
use local_kikursbauer\helper;

/**
 * Sets name, summary and visibility of course sections (creating missing sections hidden).
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_sections extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'section' => new external_value(PARAM_INT, 'Section number (0 = general section, 1 = first topic)'),
                    'name' => new external_value(PARAM_TEXT, 'Section name', VALUE_OPTIONAL),
                    'summary' => new external_value(PARAM_RAW, 'Section description as HTML', VALUE_OPTIONAL),
                    'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_OPTIONAL),
                    'formatoptions' => new external_value(PARAM_RAW,
                        'JSON object with course format options of the section', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }

    /**
     * Updates the sections.
     *
     * @param int $courseid
     * @param array $sections
     * @return array
     */
    public static function execute(int $courseid, array $sections): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sections' => $sections,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        helper::require_course_access($course, $context, ['moodle/course:update']);

        $result = [];
        foreach ($params['sections'] as $sectiondata) {
            $section = helper::ensure_section($course, $sectiondata['section']);

            $data = [];
            if (isset($sectiondata['formatoptions'])) {
                // Format options are saved by the course format during the section update; unknown keys are ignored.
                $options = json_decode($sectiondata['formatoptions'], true);
                if (!is_array($options)) {
                    throw new \moodle_exception('invalidsettings', 'local_kikursbauer');
                }
                $data = $options;
            }
            if (isset($sectiondata['name'])) {
                $data['name'] = $sectiondata['name'];
            }
            if (isset($sectiondata['summary'])) {
                $data['summary'] = $sectiondata['summary'];
                $data['summaryformat'] = FORMAT_HTML;
            }
            if (isset($sectiondata['visible'])) {
                $data['visible'] = (int) $sectiondata['visible'];
            }
            if ($data) {
                helper::update_section($course, $section, $data);
            }
            $stored = $DB->get_records_menu('course_format_options',
                ['courseid' => $course->id, 'sectionid' => $section->id], '', 'name, value');
            $result[] = [
                'section' => (int) $section->section,
                'id' => (int) $section->id,
                'formatoptions' => json_encode($stored ?: new \stdClass()),
            ];
        }

        return [
            'sections' => $result,
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
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'section' => new external_value(PARAM_INT, 'Section number'),
                    'id' => new external_value(PARAM_INT, 'Section id'),
                    'formatoptions' => new external_value(PARAM_RAW, 'Stored format options of the section as JSON'),
                ])
            ),
            'url' => new external_value(PARAM_URL, 'Course URL'),
        ]);
    }
}
