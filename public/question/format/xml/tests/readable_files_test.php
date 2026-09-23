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

namespace qformat_xml;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Round-trip tests for readable question attachments.
 *
 * @package qformat_xml
 * @copyright 2026 Oleksandr Kulkov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\qformat_xml::class)]
final class readable_files_test extends \advanced_testcase {
    /**
     * Text and binary content, including XML parser chunk boundaries.
     *
     * @return array Test cases with MIME type, contents and readable-export eligibility.
     */
    public static function files_provider(): array {
        return [
            'css' => ['text/css', ".v { color: red; }\n", true],
            'javascript' => ['application/javascript', "if (a < b && c > 0) {\n  console.log('λ');\n}\n", true],
            'svg' => ['image/svg+xml', '<svg><text>α &amp; β</text></svg>', true],
            'json' => ['application/json', '{"value": "漢字 😀"}', true],
            'split cdata' => ['text/plain', "before]]>after\n", true],
            'empty' => ['text/plain', '', true],
            'zero' => ['text/plain', '0', true],
            'whitespace only' => ['text/plain', " \t\n\n  ", true],
            'leading trailing whitespace' => ['text/css', "\n\n  .x {}\n \t\n", true],
            'chunk boundary whitespace' => ['text/plain', 'a' . str_repeat(' ', 10000) . "b\n", true],
            'carriage returns' => ['text/plain', "first\r\nsecond\r", false],
            'nul' => ['text/plain', "a\0b", false],
            'invalid utf8' => ['text/plain', "a\xffb", false],
            'invalid xml unicode' => ['text/plain', "a\xef\xbf\xbeb", false],
            'binary' => ['application/octet-stream', "\x00\xff\x01", false],
            'png' => ['image/png', "\x89PNG\r\n\x1a\n\x00\xff", false],
            'jpeg' => ['image/jpeg', "\xff\xd8\xff\xe0\x00\x10JFIF\x00", false],
            'binary ascii' => ['application/octet-stream', 'some bytes', false],
        ];
    }

    /**
     * Readable exports restore the exact attachment bytes, including whitespace.
     *
     * @param string $mimetype File MIME type.
     * @param string $content File contents.
     * @param bool $readable Whether the content is eligible for readable export.
     */
    #[DataProvider('files_provider')]
    public function test_round_trip(string $mimetype, string $content, bool $readable): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $contextid = \context_user::instance($USER->id)->id;
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(), 'filepath' => '/assets/',
            'filename' => 'source.txt', 'mimetype' => $mimetype,
        ], $content);
        $format = new \qformat_xml();
        foreach ([null, true, false] as $legacy) {
            if ($legacy !== null) {
                $format->set_legacy_files($legacy);
            }
            $xml = $format->write_files([$file]);
            $encoding = !$legacy && $readable ? 'utf-8' : 'base64';
            $this->assertStringContainsString('encoding="' . $encoding . '"', $xml);
            $parsed = (new \core\xml_parser())->parse('<files>' . $xml . '</files>', 0, 'UTF-8', true);
            $itemid = $format->import_files_as_draft($parsed['files']['#']['file']);
            $restored = get_file_storage()->get_file($contextid, 'user', 'draft', $itemid, '/assets/', 'source.txt');
            $this->assertNotFalse($restored);
            $this->assertSame($content, $restored->get_content());
        }
    }

    /**
     * A normal question import accepts mixed encodings in separate file areas.
     */
    public function test_question_import(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $xml = '<quiz><question type="description"><name><text>Files</text></name>' .
            '<questiontext format="html"><text><![CDATA[<p>Example</p>]]></text>' .
            '<file name="code.js" path="/" encoding="utf-8" xml:space="preserve"><![CDATA[' .
            "\n const x = 1;\n " . ']]></file></questiontext>' .
            '<generalfeedback format="html"><text>Feedback</text>' .
            '<file name="binary.dat" encoding="base64">AP8=</file></generalfeedback></question></quiz>';
        $questions = (new \qformat_xml())->readquestions([$xml]);
        $this->assertCount(1, $questions);
        $contextid = \context_user::instance($USER->id)->id;
        $fs = get_file_storage();
        $file = $fs->get_file($contextid, 'user', 'draft', $questions[0]->questiontextitemid, '/', 'code.js');
        $this->assertSame("\n const x = 1;\n ", $file->get_content());
        $file = $fs->get_file($contextid, 'user', 'draft', $questions[0]->generalfeedbackitemid, '/', 'binary.dat');
        $this->assertSame("\0\xff", $file->get_content());
    }

    /**
     * Legacy attachments without an encoding attribute remain base64.
     */
    public function test_missing_encoding(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $format = new \qformat_xml();
        $id = $format->import_files_as_draft([['@' => ['name' => 'old.txt'], '#' => 'TW9vZGxl']]);
        $file = get_file_storage()->get_file(\context_user::instance($USER->id)->id, 'user', 'draft', $id, '/', 'old.txt');
        $this->assertSame('Moodle', $file->get_content());
    }

    /**
     * Unknown encodings must not silently corrupt an attachment.
     */
    public function test_unknown_encoding(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Unsupported file encoding');
        (new \qformat_xml())->import_files_as_draft([
            ['@' => ['name' => 'source.txt', 'encoding' => 'unknown'], '#' => 'source'],
        ]);
    }

    /**
     * The standard export form leaves legacy compatibility disabled by default.
     */
    public function test_export_form_option(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/exportquestions/export.php');
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $context->id,
        ]);
        $form = new \qbank_exportquestions\form\export_form(null, [
            'contexts' => [$context], 'defaultcategory' => $category->id . ',' . $context->id,
        ]);
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $checkboxes = $xpath->query('//input[@name="legacyfiles" and @type="checkbox"]');
        $this->assertCount(1, $checkboxes);
        $this->assertFalse($checkboxes->item(0)->hasAttribute('checked'));
    }
}
