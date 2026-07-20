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

namespace gradereport_csvanalytics\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to upload a grades CSV file for analysis.
 *
 * @package    gradereport_csvanalytics
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    /**
     * Defines the form elements.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'uploadheader', get_string('uploadcsv', 'gradereport_csvanalytics'));

        $mform->addElement('filepicker', 'csvfile', get_string('selectcsv', 'gradereport_csvanalytics'), null, [
            'accepted_types' => ['.csv'],
            'maxbytes' => 0,
            'return_types' => FILE_INTERNAL,
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('upload', 'gradereport_csvanalytics'));
    }
}
