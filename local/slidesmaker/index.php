<?php
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

// Define API Endpoints
$api_split_url = 'http://127.0.0.1:8000/split_pdf_chapters';
$api_generate_url = 'http://127.0.0.1:8000/generate_presentation';

// Determine Step
$step = optional_param('step', 1, PARAM_INT);

// Helper function to send file to API
function local_slidesmaker_send_file($url, $file, $extra_fields = []) {
    $tempdir = make_request_directory();
    $tempfile = $tempdir . '/' . $file->get_filename();
    $file->copy_content_to($tempfile);
    
    $cfile = new CURLFile($tempfile, 'application/pdf', $file->get_filename());
    $post_fields = array_merge(['file' => $cfile], $extra_fields);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error_msg = curl_error($ch);
    curl_close($ch);
    
    return [$http_code, $response, $error_msg];
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
            list($code, $resp, $err) = local_slidesmaker_send_file($api_split_url, $file);
            
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
        $selected_chapters = [];
        foreach ($fromform as $key => $value) {
            if (strpos($key, 'chapter_') === 0 && $value == 1) {
                // Find the chapter title via md5 match
                foreach ($chapters as $ch) {
                     $title = isset($ch['title']) ? $ch['title'] : 'Unknown Chapter';
                     if ('chapter_' . md5($title) === $key) {
                         $selected_chapters[] = $title;
                     }
                }
            }
        }
        
        if (empty($selected_chapters)) {
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
            $chapter_names_str = implode(', ', $selected_chapters);
            $topic = $fromform->topic;
            
            list($code, $resp, $err) = local_slidesmaker_send_file($api_generate_url, $file, [
                'topic' => $topic,
                'chapter_names' => $chapter_names_str
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
