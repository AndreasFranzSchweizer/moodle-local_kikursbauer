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
 * Adds an activity or resource with content to a course section.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_module extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number (0 = general section, 1 = first topic)'),
            'modname' => new external_value(PARAM_PLUGIN, 'Module type: page, label, url, book, assign, forum, quiz, glossary, '
                . 'resource, folder, h5pactivity, feedback'),
            'name' => new external_value(PARAM_TEXT, 'Name of the activity'),
            'intro' => new external_value(PARAM_RAW, 'Description as HTML (label: the content shown on the course page)',
                VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students (default: hidden)', VALUE_DEFAULT, false),
            'settings' => new external_value(PARAM_RAW, 'JSON object with module specific settings', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Creates the module.
     *
     * @param int $courseid
     * @param int $section
     * @param string $modname
     * @param string $name
     * @param string $intro
     * @param bool $visible
     * @param string $settings JSON object
     * @return array
     */
    public static function execute(int $courseid, int $section, string $modname, string $name, string $intro = '',
            bool $visible = false, string $settings = '{}'): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'modname' => $modname,
            'name' => $name,
            'intro' => $intro,
            'visible' => $visible,
            'settings' => $settings,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        helper::require_course_access($course, $context, ['moodle/course:manageactivities']);

        if (!in_array($params['modname'], helper::MODNAMES, true)) {
            throw new \moodle_exception('invalidmodname', 'local_kikursbauer', '', $params['modname']);
        }
        $settings = helper::decode_settings($params['settings'],
            array_merge(helper::ADD_SETTINGS[$params['modname']], helper::ADD_COMMON));

        helper::ensure_section($course, $params['section']);

        $moduleinfo = (object) [];
        foreach (helper::module_defaults($params['modname'], $settings) as $key => $value) {
            $moduleinfo->$key = $value;
        }
        foreach ($settings as $key => $value) {
            if (!in_array($key, helper::SPECIAL_SETTINGS, true)) {
                $moduleinfo->$key = $value;
            }
        }
        // Set last, so that nothing in the settings can redirect the module to another course or section.
        $moduleinfo->modulename = $params['modname'];
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $params['section'];
        $moduleinfo->visible = (int) $params['visible'];
        $moduleinfo->name = $params['name'];
        $moduleinfo->cmidnumber = '';
        $moduleinfo->introeditor = [
            'text' => $params['intro'],
            'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid(),
        ];

        // Checks the capability to add this module type and creates the course module.
        $moduleinfo = create_module($moduleinfo);

        if ($params['modname'] === 'book' && !empty($settings['chapters']) && is_array($settings['chapters'])) {
            helper::add_book_chapters((int) $moduleinfo->instance, $settings['chapters']);
        }
        if ($params['modname'] === 'feedback' && !empty($settings['items']) && is_array($settings['items'])) {
            helper::set_feedback_items((int) $moduleinfo->instance, $settings['items']);
        }

        $url = new \moodle_url('/mod/' . $params['modname'] . '/view.php', ['id' => $moduleinfo->coursemodule]);
        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
            'url' => $url->out(false),
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
            'instanceid' => new external_value(PARAM_INT, 'Module instance id'),
            'url' => new external_value(PARAM_URL, 'URL of the activity'),
        ]);
    }
}
