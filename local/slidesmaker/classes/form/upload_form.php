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
 * Form to upload a textbook PDF for chapter splitting.
 *
 * @package    local_slidesmaker
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    /**
     * Defines the form elements.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('pluginname', 'local_slidesmaker'));

        $mform->addElement('html', '<p>' . get_string('upload_book_intro', 'local_slidesmaker') . '</p>');

        // File Manager for PDF Upload
        $mform->addElement('filepicker', 'bookfile', get_string('uploadbook', 'local_slidesmaker'), null, ['accepted_types' => ['.pdf']]);
        $mform->addHelpButton('bookfile', 'uploadbook', 'local_slidesmaker');
        $mform->addRule('bookfile', null, 'required', null, 'client');

        $mform->addElement('hidden', 'step', 1);
        $mform->setType('step', PARAM_INT);

        // Submit Button
        $this->add_action_buttons(false, get_string('analyze_book', 'local_slidesmaker'));
    }
}
