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

namespace core_form\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form containing the file manager for one editor draft area.
 *
 * @package    core_form
 * @copyright  2026 Oleksandr Kulkov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_editor_files_form extends \moodleform {
    /**
     * Define the file manager form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->setDisableShortforms(true);
        $mform->addElement(
            'filemanager',
            'files_filemanager',
            get_string('filemanager', 'form'),
            null,
            $this->_customdata['options'],
        );
    }
}
