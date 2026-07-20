<?php
namespace local_slidesmaker\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class upload_form extends \moodleform {
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
