<?php
namespace local_slidesmaker\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class prompt_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('pluginname', 'local_slidesmaker'));

        // Topic Text Field
        $mform->addElement('text', 'topic', get_string('topic', 'local_slidesmaker'));
        $mform->setType('topic', PARAM_TEXT);
        $mform->addRule('topic', null, 'required', null, 'client');
        $mform->addHelpButton('topic', 'topic', 'local_slidesmaker');

        // File Manager for PDF Upload
        $mform->addElement('filepicker', 'referencefile', get_string('uploadpdf', 'local_slidesmaker'), null, ['accepted_types' => ['.pdf']]);
        $mform->addHelpButton('referencefile', 'uploadpdf', 'local_slidesmaker');

        // Submit Button
        $this->add_action_buttons(false, get_string('generateslides', 'local_slidesmaker'));
    }
}
