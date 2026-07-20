<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT);  // Lectureaudio instance ID - not supported directly but standard patterns often use it.

if ($id) {
    $cm         = get_coursemodule_from_id('lectureaudio', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $lectureaudio  = $DB->get_record('lectureaudio', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    // We typically require id.
    print_error('missingparameter');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/lectureaudio:view', $context); // Standard view capability. Assuming it defaults to allow.

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/lectureaudio/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($lectureaudio->name));
$PAGE->set_heading(format_string($course->fullname));

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($lectureaudio->name));

if ($lectureaudio->intro) {
    echo $OUTPUT->box(format_module_intro('lectureaudio', $lectureaudio, $cm->id), 'generalbox mod_introbox', 'intro');
}

// Check permissions for recording.
// Using 'moodle/course:manageactivities' as a proxy for teacher/manager who can record.
$canrecord = has_capability('moodle/course:manageactivities', $context);

// Check for existing recording and transcript.
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_lectureaudio', 'content', 0, 'timecreated DESC', false);
$recordingurl = '';
$hasrecording = false;
$transcripturl = '';
$hastranscript = false;
$summaryContent = '';
$hasSummary = false;

foreach ($files as $file) {
    $filename = $file->get_filename();
    $url = moodle_url::make_pluginfile_url($context->id, 'mod_lectureaudio', 'content', 0, '/', $filename); 
    
    if (pathinfo($filename, PATHINFO_EXTENSION) === 'wav') {
        $recordingurl = $url;
        $hasrecording = true;
    }
    if (pathinfo($filename, PATHINFO_EXTENSION) === 'txt') {
        $transcripturl = $url;
        $hastranscript = true;
    }
    if (pathinfo($filename, PATHINFO_EXTENSION) === 'md') {
        $summaryContent = $file->get_content();
        $hasSummary = true;
    }
}

// Prepare template data.
$data = [
    'canrecord' => $canrecord,
    'hasrecording' => $hasrecording,
    'recordingurl' => $recordingurl,
    'hastranscript' => $hastranscript,
    'transcripturl' => $transcripturl,
    'hasSummary' => $hasSummary,
    'summaryContent' => $hasSummary ? $summaryContent : '',
    'cmid' => $cm->id,
    'instanceid' => $lectureaudio->id,
    'contextid' => $context->id,
    'wwwroot' => $CFG->wwwroot
];

// Initialize recorder JS if user can record OR if we have a summary to display (using JS parser)
// Actually, even students might want to see the summary rendered nicely.
// For now, let's keep JS loading for 'canrecord' but also maybe for visualization only?
// The current JS is "Recorder". We might simply always load it or split it.
// To keep it simple: Load it if canrecord or hasSummary.
if ($canrecord || $hasSummary) {
    $PAGE->requires->js_call_amd('mod_lectureaudio/recorder', 'init', [$data]);
}

echo $OUTPUT->render_from_template('mod_lectureaudio/view', $data);

echo $OUTPUT->footer();
