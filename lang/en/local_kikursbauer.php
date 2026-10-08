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
 * Language strings for local_kikursbauer.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['beforecmnotinsection'] = 'The course module {$a} (beforecmid) is not in the target section.';
$string['feedbackanswered'] = 'The feedback has already been answered, its questions can no longer be replaced.';
$string['importerror'] = 'Question import failed: {$a}';
$string['invalidfile'] = 'Invalid file name or file content (base64 expected).';
$string['invalidformat'] = 'The course format "{$a}" is not installed or not enabled.';
$string['invalidmodname'] = 'Module type "{$a}" cannot be created with this service.';
$string['invalidsection'] = 'Invalid section number: {$a}';
$string['invalidsettingkey'] = 'The setting "{$a}" is not allowed for this module type.';
$string['invalidsettings'] = 'The settings parameter must be a JSON object.';
$string['kikursbauer:use'] = 'Use the web service KI-Kursbauer';
$string['missingsetting'] = 'Missing required setting: {$a}';
$string['notowncategory'] = 'The course "{$a}" is not in a course category in which you may create courses. The KI-Kursbauer only works in your own course area.';
$string['owncategoryonly'] = 'Own course area only';
$string['owncategoryonly_desc'] = 'Changes are only allowed in courses that lie in a category in which the teacher may create courses (e.g. "School year 26/27 › own name"). Courses of colleagues in which the teacher is enrolled as a teacher are then locked for the assistant.';
$string['pluginname'] = 'KI-Kursbauer';
$string['privacy:metadata'] = 'The KI-Kursbauer plugin does not store any personal data.';
$string['quizhasattempts'] = 'Students have already attempted this quiz. Questions and grading settings can no longer be changed through the service.';
$string['status'] = 'Status';
$string['status_desc'] = '<ul><li>Web services enabled: {$a->webservices}</li><li>Protocol REST enabled: {$a->rest}</li><li>Service "KI-Kursbauer" enabled: {$a->service}</li><li>Role "KI-Kursbauer": {$a->role}</li></ul><p>Teachers get access by the system role <a href="{$a->assignurl}">KI-Kursbauer</a>. They create their token themselves under Profile › Preferences › Security keys.</p>';
$string['statusno'] = '❌ no';
$string['statusyes'] = '✅ yes';
