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
 * Imports GIFT questions into a quiz's question bank and adds them to the quiz.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_quiz_questions extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the quiz'),
            'gift' => new external_value(PARAM_RAW, 'Questions in GIFT format'),
            'maxmark' => new external_value(PARAM_FLOAT, 'Points per question', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Imports the questions and adds them to the quiz.
     *
     * @param int $cmid
     * @param string $gift
     * @param float $maxmark
     * @return array
     */
    public static function execute(int $cmid, string $gift, float $maxmark = 1.0): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'gift' => $gift,
            'maxmark' => $maxmark,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        helper::require_course_access($course, $context, ['mod/quiz:manage', 'moodle/question:add']);

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        helper::require_no_attempts((int) $quiz->id);

        // The questions are stored in the question bank of the quiz itself (module context). Moodle 5.0+ requires this,
        // Moodle 4.5 supports it as well.
        $category = question_get_default_category($context->id, true);
        if (!$category) {
            $category = question_make_default_categories([$context]);
        }

        $filename = make_request_directory() . '/import.gift';
        file_put_contents($filename, $params['gift']);

        $qformat = new \qformat_gift();
        $qformat->setCategory($category);
        $qformat->setContexts([$context]);
        $qformat->setCourse($course);
        $qformat->setFilename($filename);
        $qformat->setRealfilename('import.gift');
        $qformat->setMatchgrades('nearest');
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        if (method_exists($qformat, 'set_display_progress')) {
            $qformat->set_display_progress(false);
        }

        // The importer prints notifications; keep them out of the web service response.
        ob_start();
        $ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
        $output = ob_get_clean();
        if (!$ok || empty($qformat->questionids)) {
            $message = trim(preg_replace('/\s+/', ' ', strip_tags($output)));
            throw new \moodle_exception('importerror', 'local_kikursbauer', '', $message ?: 'no questions found');
        }

        $questionids = [];
        foreach ($qformat->questionids as $questionid) {
            // Page 0 appends the question, starting new pages according to the quiz setting questionsperpage.
            quiz_add_quiz_question($questionid, $quiz, 0, $params['maxmark']);
            $questionids[] = (int) $questionid;
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        return [
            'count' => count($questionids),
            'questionids' => $questionids,
            'url' => (new \moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]))->out(false),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of questions added'),
            'questionids' => new external_multiple_structure(new external_value(PARAM_INT, 'Question id')),
            'url' => new external_value(PARAM_URL, 'URL of the quiz edit page'),
        ]);
    }
}
