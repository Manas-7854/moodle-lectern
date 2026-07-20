<?php
namespace mod_lectureaudio;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

class external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function upload_recording_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the activity'),
            'filecontent' => new external_value(PARAM_RAW, 'Base64 encoded file content', VALUE_DEFAULT, ''),
            'transcript' => new external_value(PARAM_RAW, 'Transcript text', VALUE_DEFAULT, ''),
            'summary' => new external_value(PARAM_RAW, 'Summary markdown', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Uploads the audio recording
     * @param int $contextid
     * @param string $filecontent
     * @param string $transcript
     * @param string $summary
     * @return array
     */
    public static function upload_recording($contextid, $filecontent = '', $transcript = '', $summary = '') {
        global $DB, $USER;
        
        // Increase limits for large audio uploads.
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 0);

        // Parameter validation.
        $params = self::validate_parameters(self::upload_recording_parameters(), [
            'contextid' => $contextid,
            'filecontent' => $filecontent,
            'transcript' => $transcript,
            'summary' => $summary
        ]);
        
        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        
        // Security check.
        require_capability('moodle/course:manageactivities', $context);

        $fs = get_file_storage();
        
        // Delete previous files if we are uploading new ones?
        // Note: this wipes both if we call this.
        // Let's assume we want to clear old attempts.
        $fs->delete_area_files($context->id, 'mod_lectureaudio', 'content');
        
        if (!empty($params['filecontent'])) {
            // Decode base64.
            $filedata = base64_decode($params['filecontent']);
            
            // Save file.
            $fileinfo = [
                'contextid' => $context->id,
                'component' => 'mod_lectureaudio',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'recording_' . date('YmdHis') . '.wav',
                'timecreated' => time(),
                'timemodified' => time(),
                'userid' => $USER->id
            ];
            
            $fs->create_file_from_string($fileinfo, $filedata);
        }

        if (!empty($params['transcript'])) {
            $transcriptinfo = [
                'contextid' => $context->id,
                'component' => 'mod_lectureaudio',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'transcript_' . date('YmdHis') . '.txt',
                'timecreated' => time(),
                'timemodified' => time(),
                'userid' => $USER->id
            ];
            
            $fs->create_file_from_string($transcriptinfo, $params['transcript']);
        }

        if (!empty($params['summary'])) {
            $summaryinfo = [
                'contextid' => $context->id,
                'component' => 'mod_lectureaudio',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'summary_' . date('YmdHis') . '.md',
                'timecreated' => time(),
                'timemodified' => time(),
                'userid' => $USER->id
            ];
            
            $fs->create_file_from_string($summaryinfo, $params['summary']);
        }
        
        return ['status' => true];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function upload_recording_returns() {
         return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of upload')
         ]);
    }
}
