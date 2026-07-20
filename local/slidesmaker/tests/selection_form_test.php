<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_slidesmaker;

use local_slidesmaker\form\selection_form;

/**
 * Unit tests for the chapter selection form.
 *
 * @package    local_slidesmaker
 * @copyright  2026 Moodle Plugins Portfolio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_slidesmaker\form\selection_form
 */
final class selection_form_test extends \advanced_testcase {
    public function test_chapter_titles_from_the_api_are_escaped(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Chapter data as it would come back from the (untrusted) external AI backend.
        $chapters = [[
            'title' => '<script>alert(1)</script>',
            'start_page' => 1,
            'end_page' => 5,
            'page_count' => 5,
        ]];

        $form = new selection_form(new \moodle_url('/local/slidesmaker/index.php'), [
            'chapters' => $chapters,
            'draftitemid' => 0,
        ]);
        $html = $form->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_chapter_checkbox_name_is_keyed_by_title_hash(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $title = 'Chapter One: Introduction';
        $form = new selection_form(new \moodle_url('/local/slidesmaker/index.php'), [
            'chapters' => [['title' => $title]],
            'draftitemid' => 0,
        ]);
        $html = $form->render();

        $this->assertStringContainsString('chapter_' . md5($title), $html);
    }

    public function test_each_chapter_gets_a_checkbox_keyed_by_title_hash(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $titles = ['Chapter One', 'Chapter Two'];
        $chapters = array_map(fn($title) => ['title' => $title], $titles);

        $form = new selection_form(new \moodle_url('/local/slidesmaker/index.php'), [
            'chapters' => $chapters,
            'draftitemid' => 0,
        ]);
        $mform = $form->_form;

        foreach ($titles as $title) {
            $element = $mform->getElement('chapter_' . md5($title));
            $this->assertInstanceOf(\HTML_QuickForm_checkbox::class, $element);
            // Chapters are checked by default (index.php relies on this to auto-select all).
            $this->assertEquals(1, $element->getValue());
        }
    }
}
