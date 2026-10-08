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
 * Adds entries to a glossary.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_glossary_entries extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the glossary'),
            'entries' => new external_multiple_structure(
                new external_single_structure([
                    'concept' => new external_value(PARAM_TEXT, 'Term'),
                    'definition' => new external_value(PARAM_RAW, 'Definition as HTML'),
                    'aliases' => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Alias'), 'Alternative spellings / keywords', VALUE_DEFAULT, []
                    ),
                ])
            ),
            'autolink' => new external_value(PARAM_BOOL,
                'Link the terms automatically in course texts (needs the glossary auto-linking filter)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Adds the entries.
     *
     * @param int $cmid
     * @param array $entries
     * @param bool $autolink
     * @return array
     */
    public static function execute(int $cmid, array $entries, bool $autolink = false): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/glossary/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'entries' => $entries,
            'autolink' => $autolink,
        ]);

        $cm = get_coursemodule_from_id('glossary', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        helper::require_course_access($course, $context, ['mod/glossary:write']);

        $glossary = $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($params['autolink'] && !$glossary->usedynalink) {
            $DB->set_field('glossary', 'usedynalink', 1, ['id' => $glossary->id]);
            $glossary->usedynalink = 1;
        }

        $entryids = [];
        foreach ($params['entries'] as $data) {
            $entry = (object) [
                'concept' => $data['concept'],
                'aliases' => implode("\n", $data['aliases']),
                'definition_editor' => [
                    'text' => $data['definition'],
                    'format' => FORMAT_HTML,
                    'itemid' => file_get_unused_draft_itemid(),
                ],
                'usedynalink' => $params['autolink'] ? 1 : 0,
                'casesensitive' => 0,
                'fullmatch' => 1,
            ];
            $entry = glossary_edit_entry($entry, $course, $cm, $glossary, $context);
            $entryids[] = (int) $entry->id;
        }

        return [
            'count' => count($entryids),
            'entryids' => $entryids,
            'url' => (new \moodle_url('/mod/glossary/view.php', ['id' => $cm->id]))->out(false),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of entries added'),
            'entryids' => new external_multiple_structure(new external_value(PARAM_INT, 'Entry id')),
            'url' => new external_value(PARAM_URL, 'URL of the glossary'),
        ]);
    }
}
