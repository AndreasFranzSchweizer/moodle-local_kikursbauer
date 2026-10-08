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
 * Deutsche Sprachstrings für local_kikursbauer.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['beforecmnotinsection'] = 'Die Aktivität {$a} (beforecmid) liegt nicht im Zielabschnitt.';
$string['feedbackanswered'] = 'Das Feedback wurde bereits beantwortet. Die Fragen lassen sich über den Dienst nicht mehr ersetzen.';
$string['importerror'] = 'Fragenimport fehlgeschlagen: {$a}';
$string['invalidfile'] = 'Ungültiger Dateiname oder Dateiinhalt (Base64 erwartet).';
$string['invalidformat'] = 'Das Kursformat „{$a}“ ist nicht installiert oder nicht aktiviert.';
$string['invalidmodname'] = 'Der Aktivitätstyp „{$a}“ kann über diesen Dienst nicht angelegt werden.';
$string['invalidsection'] = 'Ungültige Abschnittsnummer: {$a}';
$string['invalidsettingkey'] = 'Die Einstellung „{$a}“ ist für diesen Aktivitätstyp nicht erlaubt.';
$string['invalidsettings'] = 'Der Parameter settings muss ein JSON-Objekt sein.';
$string['kikursbauer:use'] = 'Webservice KI-Kursbauer nutzen';
$string['missingsetting'] = 'Pflichteinstellung fehlt: {$a}';
$string['notowncategory'] = 'Der Kurs „{$a}“ liegt nicht in einem Kursbereich, in dem Sie Kurse anlegen dürfen. Der KI-Kursbauer arbeitet nur im eigenen Kursbereich.';
$string['owncategoryonly'] = 'Nur eigener Kursbereich';
$string['owncategoryonly_desc'] = 'Änderungen sind nur in Kursen erlaubt, die in einem Kursbereich liegen, in dem die Lehrkraft Kurse anlegen darf (z. B. „Schuljahr 26/27 › eigener Name“). Kurse von Kolleg/innen, in denen die Lehrkraft als Trainer/in eingeschrieben ist, bleiben für den Assistenten dann gesperrt.';
$string['pluginname'] = 'KI-Kursbauer';
$string['privacy:metadata'] = 'Das Plugin KI-Kursbauer speichert keine personenbezogenen Daten.';
$string['quizhasattempts'] = 'In diesem Test gibt es bereits Versuche von Teilnehmenden. Fragen und Bewertungseinstellungen lassen sich über den Dienst nicht mehr ändern.';
$string['status'] = 'Status';
$string['status_desc'] = '<ul><li>Webservices aktiviert: {$a->webservices}</li><li>Protokoll REST aktiviert: {$a->rest}</li><li>Dienst „KI-Kursbauer“ aktiviert: {$a->service}</li><li>Rolle „KI-Kursbauer“: {$a->role}</li></ul><p>Lehrkräfte erhalten Zugriff über die Systemrolle <a href="{$a->assignurl}">KI-Kursbauer</a>. Den Token holen sie sich selbst unter Profil › Einstellungen › Sicherheitsschlüssel.</p>';
$string['statusno'] = '❌ nein';
$string['statusyes'] = '✅ ja';
