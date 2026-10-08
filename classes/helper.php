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

namespace local_kikursbauer;

/**
 * Helper functions shared by the KI-Kursbauer web services.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** @var string Short name of the built-in service. */
    const SERVICE_SHORTNAME = 'local_kikursbauer';

    /** @var string Short name of the system role created on installation. */
    const ROLE_SHORTNAME = 'kikursbauer';

    /** @var string[] Capabilities of the role: use the service, use REST, create own tokens. */
    const ROLE_CAPABILITIES = ['local/kikursbauer:use', 'webservice/rest:use', 'moodle/webservice:createtoken'];

    /** @var string[] Module types that can be created. */
    const MODNAMES = ['page', 'label', 'url', 'book', 'assign', 'forum', 'quiz', 'glossary', 'resource', 'folder',
        'h5pactivity', 'feedback'];

    /** @var string[] Settings consumed by this plugin itself and not passed to the module as fields. */
    const SPECIAL_SETTINGS = ['chapters', 'submissiontypes', 'files', 'package', 'items', 'page_after_submit'];

    /** @var string[] Question types that can be added to a feedback activity. */
    const FEEDBACK_TYPES = ['label', 'info', 'textarea', 'textfield', 'numeric', 'multichoice', 'multichoicerated',
        'pagebreak'];

    /** @var string[] Settings allowed for every module type in add_module. */
    const ADD_COMMON = ['completion', 'completionview'];

    /** @var array Settings allowed in add_module, per module type. */
    const ADD_SETTINGS = [
        'page' => ['content', 'display', 'printintro', 'printheading', 'printlastmodified'],
        'label' => [],
        'url' => ['externalurl', 'display', 'printintro'],
        'book' => ['chapters', 'numbering', 'navstyle'],
        'assign' => ['submissiontypes', 'grade', 'duedate', 'cutoffdate', 'allowsubmissionsfromdate', 'gradingduedate',
            'alwaysshowdescription', 'submissiondrafts', 'requiresubmissionstatement', 'maxattempts', 'attemptreopenmethod',
            'completionsubmit'],
        'forum' => ['type', 'forcesubscribe'],
        'quiz' => ['grade', 'attempts', 'timelimit', 'timeopen', 'timeclose', 'questionsperpage', 'preferredbehaviour',
            'shuffleanswers', 'navmethod', 'grademethod', 'decimalpoints'],
        'glossary' => ['displayformat', 'entbypage', 'usedynalink', 'defaultapproval'],
        'resource' => ['files', 'display', 'showsize', 'showtype', 'showdate'],
        'folder' => ['files', 'display', 'showexpanded', 'showdownloadfolder', 'forcedownload'],
        'h5pactivity' => ['package', 'grade', 'enabletracking', 'grademethod', 'reviewmode'],
        'feedback' => ['items', 'anonymous', 'multiple_submit', 'autonumbering', 'page_after_submit', 'timeopen',
            'timeclose', 'completionsubmit'],
    ];

    /** @var array Fields of the module table that update_module may change, per module type. */
    const UPDATE_SETTINGS = [
        'page' => ['content', 'display', 'printintro', 'printheading', 'printlastmodified'],
        'label' => [],
        'url' => ['externalurl', 'display'],
        'book' => ['chapters', 'numbering', 'navstyle'],
        'assign' => ['duedate', 'cutoffdate', 'allowsubmissionsfromdate', 'gradingduedate', 'alwaysshowdescription'],
        'forum' => [],
        'quiz' => ['timeopen', 'timeclose', 'timelimit', 'attempts'],
        'glossary' => ['displayformat', 'entbypage'],
        'resource' => ['files', 'display', 'showsize', 'showtype', 'showdate'],
        'folder' => ['files', 'display', 'showexpanded'],
        'h5pactivity' => ['package'],
        'feedback' => ['items', 'anonymous', 'multiple_submit', 'autonumbering', 'page_after_submit', 'timeopen',
            'timeclose'],
    ];

    /**
     * Checks that the current user may work in the course: validates the context, requires the capabilities and,
     * unless switched off, requires that the course lies in a category in which the user may create courses
     * (the teacher's own course area, not courses of colleagues where the teacher is only enrolled).
     *
     * @param \stdClass $course
     * @param \context $context course or module context
     * @param string[] $capabilities
     */
    public static function require_course_access(\stdClass $course, \context $context, array $capabilities): void {
        \core_external\external_api::validate_context($context);
        foreach ($capabilities as $capability) {
            require_capability($capability, $context);
        }
        if (get_config('local_kikursbauer', 'owncategoryonly') === '0') {
            return;
        }
        $categorycontext = \context_coursecat::instance($course->category, IGNORE_MISSING);
        if (!$categorycontext || !has_capability('moodle/course:create', $categorycontext)) {
            throw new \moodle_exception('notowncategory', 'local_kikursbauer', '', format_string($course->fullname));
        }
    }

    /**
     * Throws if students (not previews) already attempted the quiz.
     *
     * @param int $quizid
     */
    public static function require_no_attempts(int $quizid): void {
        global $DB;
        if ($DB->record_exists('quiz_attempts', ['quiz' => $quizid, 'preview' => 0])) {
            throw new \moodle_exception('quizhasattempts', 'local_kikursbauer');
        }
    }

    /**
     * Decodes the settings JSON and rejects keys that are not allowed for the module type.
     *
     * @param string $json
     * @param string[] $allowed
     * @return array
     */
    public static function decode_settings(string $json, array $allowed): array {
        $settings = json_decode(trim($json) === '' ? '{}' : $json, true);
        if (!is_array($settings)) {
            throw new \moodle_exception('invalidsettings', 'local_kikursbauer');
        }
        foreach (array_keys($settings) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \moodle_exception('invalidsettingkey', 'local_kikursbauer', '', $key);
            }
        }
        return $settings;
    }

    /**
     * Creates the role "KI-Kursbauer" (system context) if missing and gives it the capabilities of ROLE_CAPABILITIES.
     */
    public static function ensure_role(): void {
        global $DB;
        // The capabilities of this plugin are registered after install.php has run, so register them now.
        update_capabilities('local_kikursbauer');

        $roleid = $DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
        if (!$roleid) {
            $roleid = create_role('KI-Kursbauer', self::ROLE_SHORTNAME,
                'Darf Moodle-Kurse mit dem KI-Assistenten über den Webservice „KI-Kursbauer“ bearbeiten – nur dort, '
                . 'wo die Person ohnehin Rechte hat. Den Token holt sich die Person unter Profil › Einstellungen › '
                . 'Sicherheitsschlüssel.');
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $systemcontext = \context_system::instance();
        foreach (self::ROLE_CAPABILITIES as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }
        $systemcontext->mark_dirty();
    }

    /**
     * Returns the section record with the given number, creating it (and any missing sections before it) first.
     * Newly created sections are hidden unless $hidenew is false.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param bool $hidenew
     * @return \stdClass course_sections record
     */
    public static function ensure_section(\stdClass $course, int $sectionnum, bool $hidenew = true): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $maxsections = (int) get_config('moodlecourse', 'maxsections');
        if ($sectionnum < 0 || ($maxsections > 0 && $sectionnum > $maxsections)) {
            throw new \moodle_exception('invalidsection', 'local_kikursbauer', '', $sectionnum);
        }

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum]);
        if ($section) {
            return $section;
        }

        $existing = $DB->get_fieldset_select('course_sections', 'section', 'course = ?', [$course->id]);
        \core_courseformat\formatactions::section($course)->create_if_missing(range(0, $sectionnum));
        if ($hidenew && $sectionnum >= 1) {
            foreach (array_diff(range(1, $sectionnum), $existing) as $number) {
                $new = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $number], '*', MUST_EXIST);
                self::update_section($course, $new, ['visible' => 0]);
            }
        }
        return $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
    }

    /**
     * Updates fields (name, summary, summaryformat, visible, format options) of a section.
     *
     * @param \stdClass $course
     * @param \stdClass $section course_sections record
     * @param array $data
     */
    public static function update_section(\stdClass $course, \stdClass $section, array $data): void {
        // Moodle 4.4+ expects a section_info object here, not the plain course_sections record.
        // get_fast_modinfo(..., true) only clears the static cache and returns null, so load modinfo afterwards.
        get_fast_modinfo($course->id, 0, true);
        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id, MUST_EXIST);
        \core_courseformat\formatactions::section($course)->update($sectioninfo, $data);
    }

    /**
     * Returns sensible default fields for creating a module of the given type.
     *
     * The values mirror what the module form would submit, so that the module's add_instance() works
     * without a form. Settings supplied by the caller override these defaults.
     *
     * @param string $modname
     * @param array $settings caller settings (read only)
     * @return array
     */
    public static function module_defaults(string $modname, array $settings): array {
        switch ($modname) {
            case 'page':
                $cfg = get_config('page');
                return [
                    'content' => '',
                    'contentformat' => FORMAT_HTML,
                    'display' => $cfg->display ?? 5,
                    'popupwidth' => $cfg->popupwidth ?? 620,
                    'popupheight' => $cfg->popupheight ?? 450,
                    'printintro' => 0,
                    'printheading' => 1,
                    'printlastmodified' => $cfg->printlastmodified ?? 1,
                    'revision' => 1,
                ];

            case 'url':
                if (empty($settings['externalurl'])) {
                    throw new \moodle_exception('missingsetting', 'local_kikursbauer', '', 'externalurl');
                }
                $cfg = get_config('url');
                return [
                    'display' => $cfg->display ?? 0,
                    'popupwidth' => $cfg->popupwidth ?? 620,
                    'popupheight' => $cfg->popupheight ?? 450,
                    'printintro' => 1,
                ];

            case 'book':
                $cfg = get_config('book');
                return [
                    'numbering' => $cfg->numbering ?? 1,
                    'navstyle' => $cfg->navstyle ?? 1,
                    'customtitles' => 0,
                    'revision' => 0,
                ];

            case 'assign':
                $types = $settings['submissiontypes'] ?? ['onlinetext', 'file'];
                $cfg = get_config('assign');
                return [
                    'assignsubmission_onlinetext_enabled' => in_array('onlinetext', $types) ? 1 : 0,
                    'assignsubmission_onlinetext_wordlimit' => 0,
                    'assignsubmission_onlinetext_wordlimit_enabled' => 0,
                    'assignsubmission_file_enabled' => in_array('file', $types) ? 1 : 0,
                    'assignsubmission_file_maxfiles' => get_config('assignsubmission_file', 'maxfiles') ?: 20,
                    'assignsubmission_file_maxsizebytes' => 0,
                    'assignsubmission_file_filetypes' => '',
                    'assignsubmission_comments_enabled' => 0,
                    'assignfeedback_comments_enabled' => 1,
                    'assignfeedback_comments_commentinline' => 0,
                    'assignfeedback_file_enabled' => 0,
                    'assignfeedback_offline_enabled' => 0,
                    'alwaysshowdescription' => 1,
                    'submissiondrafts' => 0,
                    'requiresubmissionstatement' => 0,
                    'sendnotifications' => 0,
                    'sendlatenotifications' => 0,
                    'sendstudentnotifications' => 1,
                    'allowsubmissionsfromdate' => 0,
                    'duedate' => 0,
                    'cutoffdate' => 0,
                    'gradingduedate' => 0,
                    'grade' => 10,
                    'teamsubmission' => 0,
                    'requireallteammemberssubmit' => 0,
                    'teamsubmissiongroupingid' => 0,
                    'preventsubmissionnotingroup' => 0,
                    'blindmarking' => 0,
                    'hidegrader' => 0,
                    'markingworkflow' => 0,
                    'markingallocation' => 0,
                    'markinganonymous' => 0,
                    'attemptreopenmethod' => $cfg->attemptreopenmethod ?? 'untilpass',
                    'maxattempts' => $cfg->maxattempts ?? 1,
                    'timelimit' => 0,
                    'submissionattachments' => 0,
                    'gradepenalty' => 0,
                ];

            case 'forum':
                return [
                    'type' => 'general',
                    'forcesubscribe' => 0,
                    'trackingtype' => 1,
                    'maxbytes' => 0,
                    'maxattachments' => 9,
                    'displaywordcount' => 0,
                    'rsstype' => 0,
                    'rssarticles' => 0,
                    'blockperiod' => 0,
                    'blockafter' => 0,
                    'warnafter' => 0,
                    'assessed' => 0,
                    'scale' => 0,
                    'grade_forum' => 0,
                    'lockdiscussionafter' => 0,
                    'duedate' => 0,
                    'cutoffdate' => 0,
                    'completiondiscussions' => 0,
                    'completionreplies' => 0,
                    'completionposts' => 0,
                ];

            case 'quiz':
                $cfg = get_config('quiz');
                $defaults = [
                    'timeopen' => 0,
                    'timeclose' => 0,
                    'timelimit' => 0,
                    'overduehandling' => $cfg->overduehandling ?? 'autosubmit',
                    'graceperiod' => $cfg->graceperiod ?? 86400,
                    'preferredbehaviour' => $cfg->preferredbehaviour ?? 'deferredfeedback',
                    'canredoquestions' => $cfg->canredoquestions ?? 0,
                    'attempts' => $cfg->attempts ?? 0,
                    'attemptonlast' => $cfg->attemptonlast ?? 0,
                    'grademethod' => $cfg->grademethod ?? 1,
                    'decimalpoints' => $cfg->decimalpoints ?? 2,
                    'questiondecimalpoints' => $cfg->questiondecimalpoints ?? -1,
                    'questionsperpage' => $cfg->questionsperpage ?? 1,
                    'navmethod' => $cfg->navmethod ?? 'free',
                    'shuffleanswers' => $cfg->shuffleanswers ?? 1,
                    'showuserpicture' => 0,
                    'showblocks' => 0,
                    'sumgrades' => 0,
                    'grade' => 10,
                    'quizpassword' => '',
                    'subnet' => '',
                    'browsersecurity' => '-',
                    'delay1' => 0,
                    'delay2' => 0,
                    'allowofflineattempts' => 0,
                    'completionattemptsexhausted' => 0,
                    'completionminattempts' => 0,
                ];
                // Review options are stored in the config as bitmasks (e.g. reviewattempt). quiz_process_options()
                // treats every flag that is *set* as enabled, so only the enabled flags are added.
                $times = ['during' => 0x10000, 'immediately' => 0x01000, 'open' => 0x00100, 'closed' => 0x00010];
                foreach ((array) $cfg as $key => $value) {
                    if (preg_match('/^review([a-z]+)$/', $key, $matches) && is_numeric($value)) {
                        foreach ($times as $when => $mask) {
                            if ((int) $value & $mask) {
                                $defaults[$matches[1] . $when] = 1;
                            }
                        }
                    }
                }
                return $defaults;

            case 'glossary':
                return [
                    'globalglossary' => 0,
                    'mainglossary' => 0,
                    'displayformat' => 'dictionary',
                    'approvaldisplayformat' => 'default',
                    'entbypage' => 10,
                    'showspecial' => 1,
                    'showalphabet' => 1,
                    'showall' => 1,
                    'allowduplicatedentries' => 0,
                    'allowcomments' => 0,
                    'allowprintview' => 1,
                    'usedynalink' => 0,
                    'defaultapproval' => 1,
                    'editalways' => 0,
                    'rsstype' => 0,
                    'rssarticles' => 0,
                    'assessed' => 0,
                    'scale' => 0,
                    'completionentries' => 0,
                ];

            case 'resource':
                if (empty($settings['files']) || !is_array($settings['files'])) {
                    throw new \moodle_exception('missingsetting', 'local_kikursbauer', '', 'files');
                }
                $cfg = get_config('resource');
                return [
                    'display' => 0,
                    'printintro' => 1,
                    'showsize' => 1,
                    'showtype' => 1,
                    'showdate' => 0,
                    'filterfiles' => $cfg->filterfiles ?? 0,
                    'popupwidth' => $cfg->popupwidth ?? 620,
                    'popupheight' => $cfg->popupheight ?? 450,
                    'revision' => 1,
                    'tobemigrated' => 0,
                    'legacyfiles' => 0,
                    'files' => self::draft_from_files($settings['files']),
                ];

            case 'folder':
                return [
                    'display' => 0,
                    'showexpanded' => 1,
                    'showdownloadfolder' => 1,
                    'forcedownload' => 1,
                    'revision' => 1,
                    'files' => self::draft_from_files(is_array($settings['files'] ?? null) ? $settings['files'] : []),
                ];

            case 'h5pactivity':
                if (empty($settings['package']) || !is_array($settings['package'])) {
                    throw new \moodle_exception('missingsetting', 'local_kikursbauer', '', 'package');
                }
                $core = (new \core_h5p\factory())->get_core();
                return [
                    'grade' => 0,
                    'enabletracking' => 1,
                    'grademethod' => 1,
                    'reviewmode' => 1,
                    'displayoptions' => \core_h5p\helper::get_display_options($core,
                        \core_h5p\helper::decode_display_options($core)),
                    'packagefile' => self::draft_from_files([$settings['package']]),
                ];

            case 'feedback':
                return [
                    'anonymous' => 2,
                    'email_notification' => 0,
                    'multiple_submit' => 1,
                    'autonumbering' => 0,
                    'site_after_submit' => '',
                    'page_after_submit' => '',
                    'page_after_submitformat' => FORMAT_HTML,
                    'page_after_submit_editor' => ['text' => (string) ($settings['page_after_submit'] ?? ''),
                        'format' => FORMAT_HTML, 'itemid' => 0],
                    'publish_stats' => 0,
                    'timeopen' => 0,
                    'timeclose' => 0,
                    'completionsubmit' => 0,
                ];

            default:
                return [];
        }
    }

    /**
     * Creates a draft area of the current user with the given files.
     *
     * @param array $files list of ['filename' => string, 'content' => base64 string, 'filepath' => '/sub/']
     * @return int draft item id (0 if no files)
     */
    public static function draft_from_files(array $files): int {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
        if (!$files) {
            return 0;
        }
        $draftid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        foreach (array_values($files) as $file) {
            $file = (array) $file;
            $name = clean_param($file['filename'] ?? '', PARAM_FILE);
            $content = base64_decode($file['content'] ?? '', true);
            $path = clean_param($file['filepath'] ?? '/', PARAM_PATH);
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
            if ($name === '' || $content === false) {
                throw new \moodle_exception('invalidfile', 'local_kikursbauer');
            }
            $fs->create_file_from_string([
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftid,
                'filepath' => $path,
                'filename' => $name,
            ], $content);
        }
        return $draftid;
    }

    /**
     * Replaces the files of a resource or folder (the first file of a resource becomes its main file).
     *
     * @param \cm_info|\stdClass $cm
     * @param array $files list of ['filename' => string, 'content' => base64 string]
     */
    public static function replace_module_files($cm, array $files): void {
        global $DB;
        if (!$files) {
            throw new \moodle_exception('missingsetting', 'local_kikursbauer', '', 'files');
        }
        $context = \context_module::instance($cm->id);
        $component = 'mod_' . $cm->modname;
        $draftid = self::draft_from_files($files);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, $component, 'content');
        file_save_draft_area_files($draftid, $context->id, $component, 'content', 0, ['subdirs' => true]);
        $stored = $fs->get_area_files($context->id, $component, 'content', 0, 'sortorder', false);
        if ($cm->modname === 'resource' && $stored) {
            $first = reset($stored);
            file_set_sortorder($context->id, $component, 'content', 0, $first->get_filepath(), $first->get_filename(), 1);
        }
        $revision = (int) $DB->get_field($cm->modname, 'revision', ['id' => $cm->instance]);
        $DB->set_field($cm->modname, 'revision', $revision + 1, ['id' => $cm->instance]);
    }

    /**
     * Replaces the package of an H5P activity.
     *
     * @param \cm_info|\stdClass $cm
     * @param array $package ['filename' => 'x.h5p', 'content' => base64 string]
     */
    public static function replace_h5p_package($cm, array $package): void {
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_h5pactivity', 'package');
        file_save_draft_area_files(self::draft_from_files([$package]), $context->id, 'mod_h5pactivity', 'package', 0,
            ['subdirs' => 0, 'maxfiles' => 1]);
    }

    /**
     * Replaces the questions of a feedback activity. Refused as soon as somebody has answered it.
     *
     * typ: label (presentation = HTML), info, textarea (presentation "cols|rows"), textfield ("size|maxlength"),
     * numeric ("min|max"), multichoice (presentation "r>>>>>a|b|c" radio, "c>>>>>..." checkboxes, "d>>>>>..."
     * dropdown; append "<<<<<1" for horizontal layout), multichoicerated, pagebreak.
     *
     * @param int $feedbackid
     * @param array $items list of ['typ', 'name', 'label', 'presentation', 'required']
     * @return int number of items
     */
    public static function set_feedback_items(int $feedbackid, array $items): int {
        global $DB;
        if ($DB->record_exists('feedback_completed', ['feedback' => $feedbackid])) {
            throw new \moodle_exception('feedbackanswered', 'local_kikursbauer');
        }
        $records = [];
        foreach (array_values($items) as $position => $item) {
            $item = (array) $item;
            $typ = clean_param($item['typ'] ?? 'textarea', PARAM_ALPHA);
            if (!in_array($typ, self::FEEDBACK_TYPES, true)) {
                throw new \moodle_exception('invalidsettingkey', 'local_kikursbauer', '', 'items: ' . $typ);
            }
            $records[] = (object) [
                'feedback' => $feedbackid,
                'template' => 0,
                'name' => (string) ($item['name'] ?? ''),
                'label' => clean_param($item['label'] ?? '', PARAM_TEXT),
                'presentation' => (string) ($item['presentation'] ?? ($typ === 'textarea' ? '60|5' : '')),
                'typ' => $typ,
                'hasvalue' => in_array($typ, ['label', 'pagebreak']) ? 0 : 1,
                'position' => $position + 1,
                'required' => empty($item['required']) ? 0 : 1,
                'dependitem' => 0,
                'dependvalue' => '',
                'options' => (string) ($item['options'] ?? ''),
            ];
        }
        $DB->delete_records('feedback_item', ['feedback' => $feedbackid]);
        foreach ($records as $record) {
            $DB->insert_record('feedback_item', $record);
        }
        return count($records);
    }

    /**
     * Appends chapters to a book.
     *
     * @param int $bookid
     * @param array $chapters list of ['title' => string, 'content' => html, 'subchapter' => bool]
     * @return int number of chapters added
     */
    public static function add_book_chapters(int $bookid, array $chapters): int {
        global $DB;

        $book = $DB->get_record('book', ['id' => $bookid], '*', MUST_EXIST);
        $pagenum = (int) $DB->get_field('book_chapters', 'COALESCE(MAX(pagenum), 0)', ['bookid' => $bookid]);
        $now = time();
        foreach (array_values($chapters) as $chapter) {
            $chapter = (array) $chapter;
            $pagenum++;
            $DB->insert_record('book_chapters', (object) [
                'bookid' => $book->id,
                'pagenum' => $pagenum,
                // The first chapter of a book cannot be a subchapter.
                'subchapter' => ($pagenum > 1 && !empty($chapter['subchapter'])) ? 1 : 0,
                'title' => clean_param($chapter['title'] ?? ('Kapitel ' . $pagenum), PARAM_TEXT),
                'content' => (string) ($chapter['content'] ?? ''),
                'contentformat' => FORMAT_HTML,
                'hidden' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'importsrc' => '',
            ]);
        }
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        return count($chapters);
    }

    /**
     * Updates existing book chapters (addressed by pagenum) and appends chapters without pagenum.
     *
     * @param int $bookid
     * @param array $chapters list of ['pagenum' => int, 'title' => string, 'content' => html, 'subchapter' => bool]
     * @return int number of chapters updated or added
     */
    public static function update_book_chapters(int $bookid, array $chapters): int {
        global $DB;

        $new = [];
        foreach (array_values($chapters) as $chapter) {
            $chapter = (array) $chapter;
            if (empty($chapter['pagenum'])) {
                $new[] = $chapter;
                continue;
            }
            $record = $DB->get_record('book_chapters', ['bookid' => $bookid, 'pagenum' => (int) $chapter['pagenum']],
                '*', MUST_EXIST);
            if (isset($chapter['title'])) {
                $record->title = clean_param($chapter['title'], PARAM_TEXT);
            }
            if (isset($chapter['content'])) {
                $record->content = (string) $chapter['content'];
                $record->contentformat = FORMAT_HTML;
            }
            if (isset($chapter['subchapter']) && $record->pagenum > 1) {
                $record->subchapter = $chapter['subchapter'] ? 1 : 0;
            }
            $record->timemodified = time();
            $DB->update_record('book_chapters', $record);
        }

        if ($new) {
            // Also increases the book revision.
            self::add_book_chapters($bookid, $new);
        } else {
            $revision = (int) $DB->get_field('book', 'revision', ['id' => $bookid], MUST_EXIST);
            $DB->set_field('book', 'revision', $revision + 1, ['id' => $bookid]);
        }
        return count($chapters);
    }
}
