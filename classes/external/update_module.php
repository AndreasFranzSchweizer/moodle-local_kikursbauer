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
 * Changes name, description, visibility and allowed fields of an activity.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_module extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_TEXT, 'New name', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'New description as HTML', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, null),
            'settings' => new external_value(PARAM_RAW, 'JSON object with fields to change', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Updates the module.
     *
     * @param int $cmid
     * @param string|null $name
     * @param string|null $intro
     * @param bool|null $visible
     * @param string $settings JSON object
     * @return array
     */
    public static function execute(int $cmid, ?string $name = null, ?string $intro = null, ?bool $visible = null,
            string $settings = '{}'): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'name' => $name,
            'intro' => $intro,
            'visible' => $visible,
            'settings' => $settings,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_module::instance($cm->id);
        helper::require_course_access($course, $context, ['moodle/course:manageactivities']);

        $settings = helper::decode_settings((string) $params['settings'], helper::UPDATE_SETTINGS[$cm->modname] ?? []);
        if ($settings && $cm->modname === 'quiz') {
            helper::require_no_attempts((int) $cm->instance);
        }

        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
        $columns = $DB->get_columns($cm->modname);
        $update = ['id' => $instance->id];
        $changed = [];

        if ($params['name'] !== null) {
            $update['name'] = $params['name'];
            $changed[] = 'name';
        }
        if ($params['intro'] !== null && isset($columns['intro'])) {
            $update['intro'] = $params['intro'];
            $update['introformat'] = FORMAT_HTML;
            $changed[] = 'intro';
        }
        foreach ($settings as $key => $value) {
            if ($key === 'chapters') {
                if (!is_array($value)) {
                    throw new \moodle_exception('invalidsettingkey', 'local_kikursbauer', '', $key);
                }
                helper::update_book_chapters((int) $cm->instance, $value);
                $changed[] = 'chapters';
                continue;
            }
            if ($key === 'files' || $key === 'package' || $key === 'items') {
                if (!is_array($value)) {
                    throw new \moodle_exception('invalidsettingkey', 'local_kikursbauer', '', $key);
                }
                if ($key === 'files') {
                    helper::replace_module_files($cm, $value);
                } else if ($key === 'package') {
                    helper::replace_h5p_package($cm, $value);
                } else {
                    helper::set_feedback_items((int) $cm->instance, $value);
                }
                $changed[] = $key;
                continue;
            }
            if (!isset($columns[$key]) || is_array($value) || is_object($value)) {
                throw new \moodle_exception('invalidsettingkey', 'local_kikursbauer', '', $key);
            }
            $update[$key] = $value;
            $changed[] = $key;
        }
        if ($cm->modname === 'page' && isset($update['content'])) {
            $update['contentformat'] = FORMAT_HTML;
            $update['revision'] = $instance->revision + 1;
        }

        if (count($update) > 1) {
            if (isset($columns['timemodified'])) {
                $update['timemodified'] = time();
            }
            $DB->update_record($cm->modname, (object) $update);
        }
        if ($params['visible'] !== null) {
            set_coursemodule_visible($cm->id, (int) $params['visible']);
            $changed[] = 'visible';
        }

        rebuild_course_cache($course->id, true);
        \core\event\course_module_updated::create_from_cm($cm, $context)->trigger();

        $url = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
        return [
            'cmid' => (int) $cm->id,
            'changed' => $changed,
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
            'changed' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'Changed field')),
            'url' => new external_value(PARAM_URL, 'URL of the activity'),
        ]);
    }
}
