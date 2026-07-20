<?php

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);

$PAGE->set_url(new moodle_url('/local/coursetimeline/index.php', array('id' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_coursetimeline'));
$PAGE->set_heading($course->fullname);

// Include the JS module
$PAGE->requires->js_call_amd('local_coursetimeline/finder', 'init', [$courseid]);

echo $OUTPUT->header();

$canmanage = has_capability('local/coursetimeline:manage', $context);

// Prepare context for the template if needed
$templatecontext = [
    'courseid' => $courseid,
    'canmanage' => $canmanage
];

echo $OUTPUT->render_from_template('local_coursetimeline/resource_finder', $templatecontext);

echo $OUTPUT->footer();
