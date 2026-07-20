<?php
namespace gradereport_csvanalytics\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class upload_form extends \moodleform {
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
