<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add a link to the Course Administration menu => "Slides Maker"
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_slidesmaker_extend_navigation_course($navigation, $course, $context) {
    // Only show if user has the capability in this course context
    if (has_capability('local/slidesmaker:generate', $context)) {
        
        $url = new moodle_url('/local/slidesmaker/index.php', ['id' => $course->id]);
        
        $navigation->add(
            get_string('pluginname', 'local_slidesmaker'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'slidesmaker',
            new pix_icon('i/presentation', '')
        );
    }
}
