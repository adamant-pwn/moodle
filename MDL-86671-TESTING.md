# MDL-86671 testing instructions

Candidate: `416ab3a74f662b6e2c2071d8522076b5f6331140`, based on Moodle main `46c042e7da65f8cb2ec7dc19f46566ce61a393e1` (6.0dev).

Use a disposable test site. The integration retains the `qbank_importasversion` component name.

## Automated tests

1. Set up Moodle's PHPUnit test environment and run `vendor/bin/phpunit --testsuite qbank_importasversion_testsuite,qformat_xml_testsuite`.
2. Set up Moodle's Behat environment with a browser and run the `@qbank_importasversion` tag.

The tests cover import warnings/errors, tag handling, attachments, fixed and latest quiz references, standard-plugin recognition, cancellation, invalid multi-question uploads and question-edit permissions.

## Manual import workflow

1. Create a question-bank activity and a question in it. Export that question as Moodle XML.
2. Add the question to two quizzes: pin one reference to its current version and set the other to Always latest.
3. Change the question's name or text in the XML. In the question bank, choose Import a new version for the existing question and upload the XML.
4. Verify that the same bank entry has a new version, that its previous version remains available, and that the imported changes appear in the new version.
5. Verify that the pinned quiz still uses the original version and the Always latest quiz resolves to the new version.
6. Repeat with a question containing an embedded image; verify that the image renders after import.

## Existing-plugin upgrade

1. On the unpatched base, install released qbank_importasversion 1.5.1 (version 2026042100).
2. Create a question in a question-bank activity, import a second version and add it to a quiz.
3. Disable the plugin in Manage question bank plugins. Record its version, question versions and quiz reference.
4. Apply the core candidate and run Moodle's upgrade.
5. Verify that the component is classified as standard, has version 2026102800 and remains disabled. Check that the existing question versions and quiz reference remain present.
6. Enable the plugin and verify that the import action works for the existing question.

## Optional STACK compatibility

Install STACK and its declared question-behaviour dependencies separately, configure Maxima and confirm STACK's health checks work. Export a STACK question with inputs, a PRT, a deployed seed and an embedded question test. Import the XML as a new version of that question. Verify those elements are retained, run the embedded tests, and grade both a correct and an incorrect response on the new version.

## Local results and limits

On 2026-10-28, the candidate passed 101 PHPUnit tests (474 assertions), seven Firefox scenarios (97 steps), and a separate real STACK round-trip/grading check (13 assertions). The released-plugin upgrade check retained the disabled setting, question versions and quiz reference. PHP syntax, Moodle coding style and patch application checks passed.

Environment: PHP 8.3.33, PostgreSQL 17.11, PHPUnit 11.5.56 and Firefox. STACK used commit `1b4e42d44930157a01f49ab35057e88b0883884d` and ordinary Maxima 5.44.0; optional optimized-image creation did not work in the test container. The browser scenarios preceded a final formatting-only change; PHPUnit and coding-style checks ran on the final source.

The upgrade check covered the plugin transition on the same Moodle main base. A complete older-Moodle-to-main upgrade, other database/PHP/browser combinations, and the full Moodle test suite were not tested locally.
