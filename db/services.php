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
 * Web service functions and the built-in service "KI-Kursbauer".
 *
 * The descriptions are shown to the AI client as tool descriptions, so they document the parameters in detail.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_kikursbauer_get_tools' => [
        'classname' => \local_kikursbauer\external\get_tools::class,
        'description' => 'Returns all functions of the service "KI-Kursbauer" with their parameters as JSON schema '
            . '(used by the local bridge to offer them as tools).',
        'type' => 'read',
        'capabilities' => 'local/kikursbauer:use',
    ],
    'local_kikursbauer_create_course' => [
        'classname' => \local_kikursbauer\external\create_course::class,
        'description' => 'Creates a new course in a course category in which the user may create courses (the teacher\'s '
            . 'own course area) and enrols the user as teacher. The course is HIDDEN by default. format: topics, weeks or '
            . 'tiles (if installed). Returns courseid; then build the content with the other functions.',
        'type' => 'write',
        'capabilities' => 'moodle/course:create',
    ],
    'local_kikursbauer_update_sections' => [
        'classname' => \local_kikursbauer\external\update_sections::class,
        'description' => 'Sets name, HTML description (summary) and visibility of course sections, addressed by section '
            . 'number (0 = general section, 1 = first topic, ...). Sections that do not exist yet are created HIDDEN '
            . 'unless "visible" is given. Only the given fields are changed. "formatoptions" is a JSON object with course '
            . 'format options of the section; the stored format options are returned per section. For tile icons of '
            . 'format_tiles use format_tiles_set_image instead.',
        'type' => 'write',
        'capabilities' => 'moodle/course:update',
    ],
    'local_kikursbauer_add_module' => [
        'classname' => \local_kikursbauer\external\add_module::class,
        'description' => 'Adds an activity or resource with content to the end of a course section (missing sections '
            . 'are created hidden). New activities are HIDDEN by default (visible = false); the teacher checks and shows '
            . 'them. modname: page | label | url | book | assign | forum | quiz | glossary | resource | folder | h5pactivity | '
            . 'feedback. '
            . '"intro" is the description as HTML (for label: the HTML shown directly on the course page). '
            . '"settings" is a JSON object; allowed keys: '
            . 'page: {"content": "<p>..</p>"}; url: {"externalurl": "https://..."} (required); '
            . 'book: {"chapters": [{"title": "..", "content": "<p>..</p>", "subchapter": false}, ...]}; '
            . 'assign: {"submissiontypes": ["onlinetext", "file"], "duedate": <unix timestamp>, "grade": 10}; '
            . 'forum: {"type": "general" | "qanda" | "single" | "eachuser" | "blog"}; '
            . 'quiz: {"grade": 10, "attempts": 0 (= unlimited), "timelimit": <seconds>, "questionsperpage": 1, '
            . '"preferredbehaviour": "deferredfeedback" | "interactive" | "immediatefeedback", "shuffleanswers": 1}; '
            . 'glossary: {"displayformat": "dictionary"}; '
            . 'resource: {"files": [{"filename": "a.pdf", "content": "<base64>"}]} (required, first file = main file); '
            . 'folder: {"files": [{"filename": "a.pdf", "content": "<base64>", "filepath": "/sub/"}]}; '
            . 'h5pactivity: {"package": {"filename": "x.h5p", "content": "<base64>"}} (required; the package should only use '
            . 'H5P libraries that are already installed, see local_kikursbauer_site_info); '
            . 'feedback: {"items": [{"typ": "textarea" | "textfield" | "multichoice" | "label" | "info" | "numeric", '
            . '"name": "question", "presentation": "60|5" or "r>>>>>a|b|c", "required": 0}], "anonymous": 1 (= anonymous) '
            . '| 2, "page_after_submit": "<p>..</p>"}; all types: "completion", "completionview". '
            . 'Other keys are rejected. Returns cmid and url; use the cmid of a quiz with local_kikursbauer_add_quiz_questions.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_kikursbauer_add_quiz_questions' => [
        'classname' => \local_kikursbauer\external\add_quiz_questions::class,
        'description' => 'Imports questions written in GIFT format into the question bank of a quiz and adds them '
            . 'to the quiz (in the given order, paged according to the quiz setting "questionsperpage"). Refused if '
            . 'students already attempted the quiz. '
            . 'Supported: multiple choice {=right ~wrong ~wrong}, multiple answers {~%50%a ~%50%b ~%-100%c}, '
            . 'true/false {T} / {F}, short answer {=3/4 =0,75}, numerical {#0.75:0.01}, matching {=a -> 1 =b -> 2}, '
            . 'essay {}. Separate questions with a blank line, name them with ::Name::, add feedback with #. '
            . 'Characters ~ = # { } : must be escaped with a backslash inside texts. '
            . 'Use [html] after the name for HTML question text. Recalculates the quiz total afterwards.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:add',
    ],
    'local_kikursbauer_update_module' => [
        'classname' => \local_kikursbauer\external\update_module::class,
        'description' => 'Changes an existing activity or resource, addressed by its course module id (cmid). '
            . 'Only the given parameters are changed: "name", "intro" (description as HTML, for label: the content shown '
            . 'on the course page), "visible". "settings" is a JSON object; allowed keys: '
            . 'page: content, display, printintro, printheading, printlastmodified; url: externalurl, display; '
            . 'book: numbering, navstyle, chapters: [{"pagenum": 2, "title": "..", "content": "<p>..</p>"}, '
            . '{"title": "new chapter", "content": ".."}] (chapters with pagenum are updated, without pagenum appended); '
            . 'assign: duedate, cutoffdate, allowsubmissionsfromdate, gradingduedate, alwaysshowdescription; '
            . 'quiz: timeopen, timeclose, timelimit, attempts (refused if students already attempted the quiz); '
            . 'glossary: displayformat, entbypage; resource/folder: files (replaces all files), display; '
            . 'h5pactivity: package (replaces the package); feedback: items (replaces all questions, refused once '
            . 'somebody has answered), anonymous, page_after_submit, timeopen, timeclose.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_kikursbauer_move_module' => [
        'classname' => \local_kikursbauer\external\move_module::class,
        'description' => 'Moves an activity or resource (cmid) to a section of the same course (section number, '
            . '0 = general section) before the activity "beforecmid", or to the end of the section if beforecmid is 0.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_kikursbauer_add_glossary_entries' => [
        'classname' => \local_kikursbauer\external\add_glossary_entries::class,
        'description' => 'Adds entries to a glossary (create the glossary first with local_kikursbauer_add_module, '
            . 'modname "glossary"). Each entry has "concept" (term), "definition" (HTML) and optional "aliases" '
            . '(list of alternative terms). "autolink": link the terms automatically in all course texts '
            . '(requires the filter "Glossary auto-linking" to be enabled).',
        'type' => 'write',
        'capabilities' => 'mod/glossary:write',
    ],
    'local_kikursbauer_upload_file' => [
        'classname' => \local_kikursbauer\external\upload_file::class,
        'description' => 'Stores a file (e.g. an image, base64 encoded in "filecontent") in the file area of an activity '
            . '("cmid"; page: content, other modules: description), of a book chapter ("cmid" and "pagenum") or of a section '
            . 'summary ("courseid" and "section", cmid 0). An existing file with the same name is replaced. Returns '
            . '"placeholder" (@@PLUGINFILE@@/filename): use it as src in the HTML of the same activity, chapter or summary, e.g. '
            . '<img src=\'@@PLUGINFILE@@/bild.png\'>, and save that HTML with update_module or update_sections. '
            . 'With "tilephoto" = true (course format Tiles, "courseid" and "section") the image becomes the photo of '
            . 'that tile instead.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, moodle/course:update',
    ],
    'local_kikursbauer_site_info' => [
        'classname' => \local_kikursbauer\external\site_info::class,
        'description' => 'Lists the activity modules of the site and the installed H5P libraries (machine name, version, '
            . 'runnable). Use it before building H5P packages: packages should only use libraries that are installed.',
        'type' => 'read',
        'capabilities' => 'local/kikursbauer:use',
    ],
];

// Core functions the course builder needs. None of them returns data about other users (no participants,
// submissions, grades or posts) and none can delete content.
$kikursbauerfunctions = array_merge(array_keys($functions), [
    'core_webservice_get_site_info',
    'core_course_get_categories',
    'core_course_get_courses_by_field',
    'core_course_get_contents',
    'core_course_get_course_module',
    'mod_page_get_pages_by_courses',
    'mod_label_get_labels_by_courses',
    'mod_url_get_urls_by_courses',
    'mod_book_get_books_by_courses',
    'mod_quiz_get_quizzes_by_courses',
    'mod_assign_get_assignments',
    'mod_resource_get_resources_by_courses',
]);

// Tile icons, only if the course format Tiles is installed.
global $DB;
if (!empty($DB) && $DB->record_exists('external_functions', ['name' => 'format_tiles_set_image'])) {
    $kikursbauerfunctions[] = 'format_tiles_set_image';
}

$services = [
    'KI-Kursbauer' => [
        'functions' => $kikursbauerfunctions,
        'shortname' => 'local_kikursbauer',
        'requiredcapability' => 'local/kikursbauer:use',
        'restrictedusers' => 0,
        'enabled' => 1,
        'downloadfiles' => 1,
        'uploadfiles' => 0,
    ],
];
