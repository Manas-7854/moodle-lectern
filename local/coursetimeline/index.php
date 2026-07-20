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
 * Course page for finding resources and generating an AI course timeline.
 *
 * @package    local_coursetimeline
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);

$PAGE->set_url(new moodle_url('/local/coursetimeline/index.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_coursetimeline'));
$PAGE->set_heading($course->fullname);

// Include the JS module
$aibackendurl = get_config('local_coursetimeline', 'aibackend_url');
$PAGE->requires->js_call_amd('local_coursetimeline/finder', 'init', [$courseid, $aibackendurl]);

echo $OUTPUT->header();

$canmanage = has_capability('local/coursetimeline:manage', $context);

// Prepare context for the template if needed
$templatecontext = [
    'courseid' => $courseid,
    'canmanage' => $canmanage,
];

echo $OUTPUT->render_from_template('local_coursetimeline/resource_finder', $templatecontext);

echo $OUTPUT->footer();
