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

namespace core_form;

use advanced_testcase;
use MoodleQuickForm_editor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/form/editor.php");

/**
 * Tests for the editor form element
 *
 * @package    core_form
 * @covers     \MoodleQuickForm_editor
 * @copyright  2026 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_test extends advanced_testcase {
    /**
     * Test editor file-management capability defaults.
     */
    public function test_file_manager_capability(): void {
        $this->assertFalse(get_texteditor('textarea')->provides_file_manager());
        $this->assertTrue(get_texteditor('tiny')->provides_file_manager());
    }

    /**
     * Test the generic file manager is offered for a source editor.
     */
    public function test_generic_file_manager_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference('htmleditor', 'textarea');

        $element = new MoodleQuickForm_editor(
            'description_editor',
            'Description',
            ['id' => 'id_description_editor'],
            ['context' => \context_system::instance(), 'maxfiles' => -1],
        );
        $element->setValue([
            'text' => '<p>Example</p>',
            'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid(),
        ]);

        $html = $element->toHtml();
        $this->assertStringContainsString('/lib/form/manage_editor_files.php', $html);
        $this->assertStringContainsString('Manage files', $html);

        $disabled = new MoodleQuickForm_editor(
            'disabled_editor',
            'Disabled',
            ['id' => 'id_disabled_editor'],
            ['context' => \context_system::instance(), 'maxfiles' => -1, 'enable_filemanagement' => false],
        );
        $disabled->setValue([
            'text' => '<p>Example</p>',
            'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid(),
        ]);
        $this->assertStringNotContainsString('/lib/form/manage_editor_files.php', $disabled->toHtml());
    }

    /**
     * Test retrieving frozen HTML
     */
    public function test_get_frozen_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Ensure "URL" filter is active.
        filter_set_global_state('urltolink', TEXTFILTER_ON);

        $element = new MoodleQuickForm_editor('description_editor', 'Description');
        $element->setValue(['text' => 'http://example.com', 'format' => FORMAT_HTML]);

        $this->assertStringContainsString(
            '<a href="http://example.com" class="_blanktarget">http://example.com</a>',
            $element->getFrozenHtml(),
        );
    }
}
