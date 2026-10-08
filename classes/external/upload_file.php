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
 * Stores a file (e.g. an image) in the file area of an activity, a book chapter or a section summary.
 *
 * The returned placeholder (@@PLUGINFILE@@/filename) is used in the HTML of that activity, chapter or summary,
 * Moodle rewrites it to the real file URL when the content is displayed.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_file extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filename' => new external_value(PARAM_FILE, 'File name, e.g. mainboard.png'),
            'filecontent' => new external_value(PARAM_RAW, 'File content, base64 encoded'),
            'cmid' => new external_value(PARAM_INT, 'Course module id (0 = section summary)', VALUE_DEFAULT, 0),
            'pagenum' => new external_value(PARAM_INT, 'Book only: chapter number (0 = book description)', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Section summary only: course id', VALUE_DEFAULT, 0),
            'section' => new external_value(PARAM_INT, 'Section summary only: section number', VALUE_DEFAULT, -1),
            'tilephoto' => new external_value(PARAM_BOOL, 'Format tiles: store as photo of the tile "section" of course '
                . '"courseid" (replaces an existing tile photo; the image should be about 360 px wide)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Stores the file.
     *
     * @param string $filename
     * @param string $filecontent base64
     * @param int $cmid
     * @param int $pagenum
     * @param int $courseid
     * @param int $section
     * @param bool $tilephoto
     * @return array
     */
    public static function execute(string $filename, string $filecontent, int $cmid = 0, int $pagenum = 0,
            int $courseid = 0, int $section = -1, bool $tilephoto = false): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'filename' => $filename,
            'filecontent' => $filecontent,
            'cmid' => $cmid,
            'pagenum' => $pagenum,
            'courseid' => $courseid,
            'section' => $section,
            'tilephoto' => $tilephoto,
        ]);

        $name = clean_param($params['filename'], PARAM_FILE);
        $content = base64_decode($params['filecontent'], true);
        if ($name === '' || $content === false) {
            throw new \moodle_exception('invalidfile', 'local_kikursbauer');
        }

        if ($params['tilephoto']) {
            return self::store_tilephoto($params['courseid'], $params['section'], $name, $content);
        }

        if ($params['cmid'] > 0) {
            [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
            $context = \context_module::instance($cm->id);
            helper::require_course_access($course, $context, ['moodle/course:manageactivities']);
            $component = 'mod_' . $cm->modname;
            $filearea = 'intro';
            $itemid = 0;
            if ($cm->modname === 'page') {
                $filearea = 'content';
            } else if ($cm->modname === 'book' && $params['pagenum'] > 0) {
                $filearea = 'chapter';
                $itemid = (int) $DB->get_field('book_chapters', 'id',
                    ['bookid' => $cm->instance, 'pagenum' => $params['pagenum']], MUST_EXIST);
            }
        } else {
            $course = get_course($params['courseid']);
            $context = \context_course::instance($course->id);
            helper::require_course_access($course, $context, ['moodle/course:update']);
            $component = 'course';
            $filearea = 'section';
            $itemid = (int) $DB->get_field('course_sections', 'id',
                ['course' => $course->id, 'section' => $params['section']], MUST_EXIST);
        }

        $fs = get_file_storage();
        $record = [
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $name,
        ];
        if ($existing = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $name)) {
            $existing->delete();
        }
        $file = $fs->create_file_from_string($record, $content);

        $url = \moodle_url::make_pluginfile_url($context->id, $component, $filearea, $itemid, '/', $name);
        return [
            'placeholder' => '@@PLUGINFILE@@/' . rawurlencode($name),
            'url' => $url->out(false),
            'filesize' => (int) $file->get_filesize(),
        ];
    }

    /**
     * Stores the photo of a tile (course format Tiles) and sets it as the tile image of the section.
     *
     * Written directly, because format_tiles_set_image with a draft file fails on some databases (the context level
     * comes back as string and the section is not found).
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $content
     * @return array
     */
    protected static function store_tilephoto(int $courseid, int $sectionnum, string $name, string $content): array {
        global $DB;
        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        helper::require_course_access($course, $context, ['moodle/course:update']);
        if ($course->format !== 'tiles') {
            throw new \moodle_exception('invalidformat', 'local_kikursbauer', '', 'tiles');
        }
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => $sectionnum],
            MUST_EXIST);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'format_tiles', 'tilephoto', $sectionid);
        $file = $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'format_tiles',
            'filearea' => 'tilephoto', 'itemid' => $sectionid, 'filepath' => '/tilephoto/', 'filename' => $name], $content);
        if (class_exists('\format_tiles\local\format_option')) {
            \format_tiles\local\format_option::set($course->id, 1, $sectionid, $name);
        } else {
            $record = ['courseid' => $course->id, 'format' => 'tiles', 'sectionid' => $sectionid, 'name' => 'tilephoto'];
            if ($existing = $DB->get_record('course_format_options', $record)) {
                $DB->set_field('course_format_options', 'value', $name, ['id' => $existing->id]);
            } else {
                $DB->insert_record('course_format_options', (object) ($record + ['value' => $name]));
            }
        }
        rebuild_course_cache($course->id, true);

        $url = \moodle_url::make_pluginfile_url($context->id, 'format_tiles', 'tilephoto', $sectionid, '/tilephoto/', $name);
        return [
            'placeholder' => '',
            'url' => $url->out(false),
            'filesize' => (int) $file->get_filesize(),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'placeholder' => new external_value(PARAM_RAW, 'Use this as src/href in the HTML of the same activity/chapter/summary'),
            'url' => new external_value(PARAM_URL, 'Direct file URL'),
            'filesize' => new external_value(PARAM_INT, 'File size in bytes'),
        ]);
    }
}
