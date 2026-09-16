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

namespace core;

use core\exception\xml_format_exception;

/**
 * This test compares library against the original xmlize XML importer.
 *
 * @package    core
 * @category   test
 * @covers     \core\xml_parser
 * @copyright  2017 Kilian Singer {@link http://quantumtechnology.info}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class xml_parser_test extends \basic_testcase {
    /**
     * Test an XML import using a valid XML file.
     *
     * The test expected file was generated using the original xmlize
     * implentation found at https://github.com/rmccue/XMLize/blob/master/xmlize-php5.inc.
     */
    public function test_xmlimport_of_proper_file(): void {
        $xml = file_get_contents(__DIR__ . '/sample_questions.xml');
        $serialised = file_get_contents(__DIR__ . '/sample_questions.ser');
        $this->assertEquals(unserialize($serialised), (new xml_parser())->parse($xml));
    }

    /**
     * Test an XML import using invalid XML.
     */
    public function test_xmlimport_of_wrong_file(): void {
        $xml = file_get_contents(__DIR__ . '/sample_questions_wrong.xml');
        $this->expectException(xml_format_exception::class);
        $this->expectExceptionMessage('Error parsing XML: Mismatched tag at line 18, char 23');
        (new xml_parser())->parse($xml, 1, "UTF-8", true);
    }

    /**
     * Test an XML import using legacy question data with old image tag.
     */
    public function test_xmlimport_of_sample_question_with_old_image_tag(): void {
        $xml = file_get_contents(__DIR__ . '/sample_questions_with_old_image_tag.xml');
        $serialised = file_get_contents(__DIR__ . '/sample_questions_with_old_image_tag.ser');

        // Compare the legacy representation in its serialized state and after unserialization.
        $this->assertEquals($serialised, serialize((new xml_parser())->parse($xml)));
        $this->assertEquals(unserialize($serialised), (new xml_parser())->parse($xml));
    }

    /** xml:space applies to descendants, resets with default, and does not affect siblings. */
    public function test_xml_space(): void {
        $xml = '<root><keep xml:space="preserve"><child>  </child>' .
            '<reset xml:space="default">  </reset></keep><other>  </other></root>';
        $parser = new xml_parser();
        $data = $parser->parse($xml, 0, 'UTF-8', true)['root']['#'];
        $this->assertSame('  ', $data['keep'][0]['#']['child'][0]['#']);
        $this->assertSame('', $data['keep'][0]['#']['reset'][0]['#']);
        $this->assertSame('', $data['other'][0]['#']);
        $this->assertSame('', $parser->parse('<root>  </root>')['root']['#']);
    }

}
