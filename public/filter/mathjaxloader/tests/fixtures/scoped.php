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

/**
 * Behat fixture for trusted-plugin scoped MathJax rendering.
 *
 * @package filter_mathjaxloader
 * @copyright 2026 Oleksandr Kulkov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
defined('BEHAT_SITE_RUNNING') || die('Only available on Behat test server');
require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/filter/mathjaxloader/tests/fixtures/scoped.php');
$PAGE->set_title('Scoped MathJax fixture');
$PAGE->set_heading('Scoped MathJax fixture');
$PAGE->requires->js_init_code(<<<'JS'
require(['filter_mathjaxloader/loader'], function(loader) {
    loader.configure({
        mathjaxurl: 'https://cdn.jsdelivr.net/npm/mathjax@4.0.0/tex-mml-chtml.js',
        mathjaxconfig: '{}',
        lang: 'en'
    });
    const scope = document.getElementById('trusted-root');
    scope.innerHTML = String.raw`\(x+<tex-html><input type="text" name="scopedanswer" aria-label="Scoped answer"></tex-html>\)`;
    loader.typesetWithConfig(scope, {packages: ['texhtml'], tex: {allowTexHTML: true}})
        .then(function() {
            const input = scope.querySelector('mjx-container input');
            // Keep typing in the input from activating MathJax's keyboard explorer.
            if (input) {
                ['focusin', 'focusout', 'mousedown', 'click', 'keydown', 'keypress', 'keyup'].forEach(type => {
                    input.addEventListener(type, event => event.stopPropagation());
                });
            }
            const globalTex = MathJax.startup.input.find(jax => jax.name === 'TeX');
            document.getElementById('result').textContent =
                scope.querySelector('mjx-container input') && !globalTex.options.allowTexHTML
                ? 'Scoped input rendered; global TeX unchanged' : 'Isolation check failed';
        }).catch(function(error) {
            document.getElementById('result').textContent = error.message;
        });
});
JS);
echo $OUTPUT->header();
echo html_writer::div('', '', ['id' => 'trusted-root']);
echo html_writer::div('Waiting for MathJax', '', ['id' => 'result']);
echo $OUTPUT->footer();
