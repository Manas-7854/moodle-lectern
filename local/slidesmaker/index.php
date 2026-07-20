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
 * Controller for the PDF-to-slides generation workflow.
 *
 * @package    local_slidesmaker
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Check authentication
require_login($course);

// Set up the page context (Course context)
$context = context_course::instance($course->id);
$PAGE->set_context($context);

// Check permissions
require_capability('local/slidesmaker:generate', $context);

// Set up the page
$url = new moodle_url('/local/slidesmaker/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'local_slidesmaker'));
$PAGE->set_heading($course->fullname);

// Define API Endpoints.
$aibackendurl = get_config('local_slidesmaker', 'aibackend_url');
$apispliturl = $aibackendurl . '/split_pdf_chapters';
$apigenerateurl = $aibackendurl . '/generate_presentation';

// Determine Step.
$step = optional_param('step', 1, PARAM_INT);

/**
 * Sends a file (with optional extra POST fields) to an AI backend endpoint.
 *
 * @package    local_slidesmaker
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @param string $url the endpoint URL
 * @param stored_file $file the file to send
 * @param array $extrafields additional POST fields to send alongside the file
 * @return array [http status code, response body, curl error message]
 */
function local_slidesmaker_send_file($url, $file, $extrafields = []) {
    $tempdir = make_request_directory();
    $tempfile = $tempdir . '/' . $file->get_filename();
    $file->copy_content_to($tempfile);

    $cfile = new CURLFile($tempfile, 'application/pdf', $file->get_filename());
    $postfields = array_merge(['file' => $cfile], $extrafields);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errormsg = curl_error($ch);
    curl_close($ch);

    return [$httpcode, $response, $errormsg];
}

if ($step == 1) {
    // Step 1: Upload Book
    $form = new \local_slidesmaker\form\upload_form($url);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    } else if ($data = $form->get_data()) {
        // Handle File Upload & Split
        $draftitemid = $data->bookfile;
        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);
        $file = reset($files);

        if ($file) {
            // Call API 1: Split Chapters
            [$code, $resp, $err] = local_slidesmaker_send_file($apispliturl, $file);

            if ($code == 200 && $resp) {
                $json = json_decode($resp, true);
                if (isset($json['chapters'])) {
                    // Success! Proceed to Step 2
                    // Store chapters and draftitemid in SESSION to pass to next step form
                    $SESSION->slidesmaker_chapters = $json['chapters'];
                    $SESSION->slidesmaker_draftitemid = $draftitemid;

                    redirect(new moodle_url('/local/slidesmaker/index.php', ['id' => $course->id, 'step' => 2]));
                } else {
                    echo $OUTPUT->header();
                    echo $OUTPUT->notification('API did not return chapters list.', 'error');
                    echo $OUTPUT->footer();
                    exit;
                }
            } else {
                echo $OUTPUT->header();
                echo $OUTPUT->notification(get_string('error_api', 'local_slidesmaker') . ': ' . $err, 'error');
                $form->display();
                echo $OUTPUT->footer();
                exit;
            }
        }
    } else {
        echo $OUTPUT->header();
        $form->display();
        echo $OUTPUT->footer();
    }
} else if ($step == 2) {
    // Step 2: Select Chapters & Topic
    if (!isset($SESSION->slidesmaker_chapters) || !isset($SESSION->slidesmaker_draftitemid)) {
        redirect(new moodle_url('/local/slidesmaker/index.php', ['id' => $course->id, 'step' => 1]));
    }

    $chapters = $SESSION->slidesmaker_chapters;
    $draftitemid = $SESSION->slidesmaker_draftitemid;

    $form = new \local_slidesmaker\form\selection_form($url, ['chapters' => $chapters, 'draftitemid' => $draftitemid]);

    if ($form->is_cancelled()) {
        unset($SESSION->slidesmaker_chapters);
        unset($SESSION->slidesmaker_draftitemid);
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    } else if ($fromform = $form->get_data()) {
        // Collect selected chapters
        $selectedchapters = [];
        foreach ($fromform as $key => $value) {
            if (strpos($key, 'chapter_') === 0 && $value == 1) {
                // Find the chapter title via md5 match
                foreach ($chapters as $ch) {
                     $title = isset($ch['title']) ? $ch['title'] : 'Unknown Chapter';
                    if ('chapter_' . md5($title) === $key) {
                        $selectedchapters[] = $title;
                    }
                }
            }
        }

        if (empty($selectedchapters)) {
             echo $OUTPUT->header();
             echo $OUTPUT->notification(get_string('error_no_chapters', 'local_slidesmaker'), 'error');
             $form->display();
             echo $OUTPUT->footer();
             exit;
        }

        // Call API 2: Generate Presentation
        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);
        // Reuse draftitemid from hidden field or session
        $draftitemid = $fromform->draftitemid;
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);
        $file = reset($files);

        if ($file) {
            // Prepare payload
            $chapternamesstr = implode(', ', $selectedchapters);
            $topic = $fromform->topic;

            [$code, $resp, $err] = local_slidesmaker_send_file($apigenerateurl, $file, [
                'topic' => $topic,
                'chapter_names' => $chapternamesstr,
            ]);

            if ($code == 200 && $resp) {
                // Clear session
                unset($SESSION->slidesmaker_chapters);
                unset($SESSION->slidesmaker_draftitemid);

                // Serve PDF download
                while (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="slides.pdf"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $resp;
                die();
            } else {
                echo $OUTPUT->header();
                echo $OUTPUT->notification(get_string('error_api', 'local_slidesmaker') . ': ' . $err, 'error');
                $form->display();
                echo $OUTPUT->footer();
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->notification('File expired or missing. Please start over.', 'error');
            echo $OUTPUT->footer();
        }
    } else {
        echo $OUTPUT->header();
        $form->display();
        echo $OUTPUT->footer();
    }
}
