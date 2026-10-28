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

namespace qbank_importasversion;

use core\context\module as context_module;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the importer against core question banks, files and quiz references.
 *
 * @package qbank_importasversion
 * @copyright 2026 Oleksandr Kulkov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(importer::class)]
final class core_integration_test extends \advanced_testcase {
    /** @var \stdClass Course containing the bank. */
    private \stdClass $course;
    /** @var \question_definition Original question. */
    private \question_definition $question;
    /** @var \qformat_xml XML reader. */
    private \qformat_xml $format;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => context_module::instance($bank->cmid)->id]);
        $original = $generator->create_question('truefalse', null, [
            'category' => $category->id, 'idnumber' => 'shared-question',
        ]);
        $this->question = \question_bank::load_question($original->id);
        $this->format = new \qformat_xml();
        $this->format->displayprogress = false;
        $this->format->setCourse($this->course);
    }

    /**
     * Importing keeps the existing entry and both fixed and latest quiz references.
     */
    public function test_quiz_references_survive_import(): void {
        global $DB;
        $latestquiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $fixedquiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        quiz_add_quiz_question($this->question->id, $latestquiz);
        quiz_add_quiz_question($this->question->id, $fixedquiz);
        $fixedslot = $DB->get_field('quiz_slots', 'id', ['quizid' => $fixedquiz->id], MUST_EXIST);
        $DB->set_field('question_references', 'version', 1, ['component' => 'mod_quiz', 'itemid' => $fixedslot]);
        $references = $DB->get_records('question_references');
        $entries = $DB->get_records('question_bank_entries');
        $original = $DB->get_record('question', ['id' => $this->question->id]);

        $result = importer::import_file(
            $this->format,
            $this->question,
            __DIR__ . '/fixtures/edited-true-false-question.xml',
            false,
            false,
            true
        );

        $this->assertTrue($result);
        $entries[$this->question->questionbankentryid]->nextversion++;
        $this->assertEquals($entries, $DB->get_records('question_bank_entries'));
        $this->assertEquals($references, $DB->get_records('question_references'));
        $this->assertEquals($original, $DB->get_record('question', ['id' => $this->question->id]));
        $newid = $DB->get_field('question_versions', 'questionid', [
            'questionbankentryid' => $this->question->questionbankentryid, 'version' => 2,
        ], MUST_EXIST);
        foreach ([[$latestquiz, $newid], [$fixedquiz, $this->question->id]] as [$quiz, $expectedid]) {
            $settings = quiz_settings::create($quiz->id);
            $settings->preload_questions();
            $settings->load_questions();
            $this->assertEquals([$expectedid], array_keys($settings->get_questions()));
        }
    }

    /**
     * Embedded files belong to the new version and use the original bank's context.
     */
    public function test_embedded_files_are_imported_in_bank_context(): void {
        global $DB;
        $fs = get_file_storage();
        $oldfile = $fs->create_file_from_string([
            'contextid' => $this->question->contextid, 'component' => 'question', 'filearea' => 'questiontext',
            'itemid' => $this->question->id, 'filepath' => '/', 'filename' => 'old.txt',
        ], 'Original attachment');
        $content = "New attachment\n\x00\xff";
        $filexml = '<file name="new.bin" path="/" encoding="base64">' . base64_encode($content) . '</file>';
        $xml = file_get_contents(__DIR__ . '/fixtures/edited-true-false-question.xml');
        $xml = str_replace('</questiontext>', $filexml . '</questiontext>', $xml);
        $xml = str_replace('</generalfeedback>', $filexml . '</generalfeedback>', $xml);
        $xml = str_replace('</feedback>', $filexml . '</feedback>', $xml);
        $path = make_request_directory() . '/with-files.xml';
        file_put_contents($path, $xml);

        $this->assertTrue(importer::import_file($this->format, $this->question, $path, false, false, true));
        $newid = $DB->get_field('question_versions', 'questionid', [
            'questionbankentryid' => $this->question->questionbankentryid, 'version' => 2,
        ], MUST_EXIST);
        foreach (['questiontext', 'generalfeedback'] as $area) {
            $file = $fs->get_file($this->question->contextid, 'question', $area, $newid, '/', 'new.bin');
            $this->assertNotFalse($file);
            $this->assertSame($content, $file->get_content());
        }
        foreach ($DB->get_records('question_answers', ['question' => $newid]) as $answer) {
            $file = $fs->get_file($this->question->contextid, 'question', 'answerfeedback', $answer->id, '/', 'new.bin');
            $this->assertNotFalse($file);
            $this->assertSame($content, $file->get_content());
        }
        $this->assertSame('Original attachment', $fs->get_file_by_id($oldfile->get_id())->get_content());
        $this->assertFalse($fs->get_file(
            $this->question->contextid,
            'question',
            'questiontext',
            $this->question->id,
            '/',
            'new.bin'
        ));
    }

    /**
     * Core recognises the preserved component as a standard plugin.
     */
    public function test_plugin_is_standard(): void {
        $plugin = \core\plugin_manager::instance()->get_plugin_info('qbank_importasversion');
        $this->assertTrue($plugin->is_standard());
    }
}
