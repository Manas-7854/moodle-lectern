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

namespace local_slidesmaker\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to select which chapters (and a topic) to include in the generated slides.
 *
 * @package    local_slidesmaker
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selection_form extends \moodleform {
    /**
     * Defines the form elements.
     */
    public function definition() {
        $mform = $this->_form;

        // $this->_customdata should contain 'chapters' and 'draftitemid'
        $chapters = isset($this->_customdata['chapters']) ? $this->_customdata['chapters'] : [];
        $draftitemid = isset($this->_customdata['draftitemid']) ? $this->_customdata['draftitemid'] : 0;

        $mform->addElement('header', 'general', get_string('select_content', 'local_slidesmaker'));

        // Topic Text Field
        $mform->addElement('text', 'topic', get_string('topic', 'local_slidesmaker'));
        $mform->setType('topic', PARAM_TEXT);
        $mform->addRule('topic', null, 'required', null, 'client');
        $mform->addHelpButton('topic', 'topic', 'local_slidesmaker');

        // Chapters Checkboxes
        $mform->addElement('header', 'chapters_header', get_string('chapters', 'local_slidesmaker'));
        $mform->addElement('static', 'description', '', get_string('select_chapters_desc', 'local_slidesmaker'));

        // Add Select All/Deselect All buttons
        $js = "
        <script>
        function toggle_slidesmaker_chapters(checked) {
            var checkboxes = document.querySelectorAll('input[type=\"checkbox\"][name^=\"chapter_\"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checked;
            });
        }
        </script>
        <div class='mb-2 ml-3'>
            <button type='button' class='btn btn-secondary btn-sm' onclick='toggle_slidesmaker_chapters(true)'>" . get_string('selectall') . "</button>
            <button type='button' class='btn btn-secondary btn-sm' onclick='toggle_slidesmaker_chapters(false)'>" . get_string('deselectall') . "</button>
        </div>
        ";
        $mform->addElement('html', $js);

        // Start Scrollable Container
        $scrollstyle = 'max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; '
            . 'padding: 10px; border-radius: 5px; background: #fafafa; margin-bottom: 20px;';
        $mform->addElement('html', '<div class="slidesmaker-chapters-scroll" style="' . $scrollstyle . '">');

        foreach ($chapters as $chapter) {
            // Assuming chapter has 'title', 'start_page', 'end_page'
            $title = isset($chapter['title']) ? $chapter['title'] : 'Unknown Chapter';
            $label = '<strong>' . s($title) . '</strong>';

            if (isset($chapter['start_page']) && isset($chapter['end_page'])) {
                 $label .= ' <span class="badge badge-secondary ml-2">Pages ' . s($chapter['start_page']) . '-' . s($chapter['end_page']) . '</span>';
            }
            if (isset($chapter['page_count'])) {
                 $label .= ' <small class="text-muted">(' . s($chapter['page_count']) . ' pages)</small>';
            }

            // Using formatting in label requires 4th argument of checkbox
            $mform->addElement('checkbox', 'chapter_' . md5($title), null, $label);
            // Default to checked
            $mform->setDefault('chapter_' . md5($title), 1);
        }

        // End Scrollable Container
        $mform->addElement('html', '</div>');

        // Hidden field to pass draftitemid to the next step
        $mform->addElement('hidden', 'draftitemid', $draftitemid);
        $mform->setType('draftitemid', PARAM_INT);

        $mform->addElement('hidden', 'step', 2);
        $mform->setType('step', PARAM_INT);

        // We also need to pass the list of chapter titles (keys) so we know what to look for on submission,
        // OR we can just iterate through all posted data looking for 'chapter_' prefixes.
        // Let's rely on iterating post data in index.php or reconstructing keys here if needed.
        // Actually, simplest is to iterate post data.

        // Submit Button
        $this->add_action_buttons(false, get_string('generateslides', 'local_slidesmaker'));
    }
}
