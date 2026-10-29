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
 * Exercise repeated MathJax configuration without an external CDN.
 *
 * @package filter_mathjaxloader
 * @copyright 2026 Oleksandr Kulkov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
defined('BEHAT_SITE_RUNNING') || die('Only available on Behat test server');
require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/filter/mathjaxloader/tests/behat/fixtures/configure_testpage.php');
$PAGE->set_title('MathJax configuration regression');
$PAGE->set_heading('MathJax configuration regression');
echo $OUTPUT->header();
echo html_writer::tag('button', 'Repeat configuration', ['id' => 'repeat']);
echo html_writer::tag('button', 'Change pending configuration', ['id' => 'change']);
echo html_writer::tag('button', 'Configure after loading', ['id' => 'loaded']);
echo html_writer::tag('pre', 'Ready', ['id' => 'result']);

$PAGE->requires->js_amd_inline(<<<'JS'
require(['filter_mathjaxloader/loader'], function(Loader) {
    const result = document.getElementById('result');
    const params = {
        mathjaxurl: M.cfg.wwwroot + '/filter/mathjaxloader/tests/behat/fixtures/mathjax_stub.js',
        mathjaxconfig: '{}',
        lang: 'en',
    };
    const setup = () => {
        Loader.configure(params);
        const config = window.MathJax;
        config.loader.load.push('[tex]/newcommand');
        config.tex = {packages: {'[+]': ['customPackage']}};
        config.startup.ready = () => { result.textContent = 'Startup hook retained'; };
        return config;
    };
    document.getElementById('repeat').onclick = () => {
        const config = setup();
        Loader.configure({...params, mathjaxconfig: '  { }  '});
        const preserved = window.MathJax === config &&
            config.loader.load.includes('ui/safe') && config.loader.load.includes('[tex]/newcommand') &&
            config.tex.packages['[+]'].includes('customPackage') && config.startup.typeset === false;
        if (!preserved) {
            result.textContent = 'Configuration was replaced';
            return;
        }
        Loader.loadMathJax().then(() => {
            window.MathJax.startup.ready();
            return;
        }).catch(error => { result.textContent = String(error); });
    };
    document.getElementById('change').onclick = () => {
        const config = setup();
        const changed = {...params, lang: 'de', mathjaxconfig: '{"tex":{"macros":{"siteMacro":"new"}}}'};
        Loader.configure(changed);
        const updated = window.MathJax !== config && window.MathJax.locale === 'de' &&
            window.MathJax.tex.macros.siteMacro === 'new' && !window.MathJax.startup.ready &&
            window.MathJax.loader.load.includes('ui/safe') && window.MathJax.startup.typeset === false;
        result.textContent = updated ? 'Changed site configuration applied' : 'Site configuration was not updated';
    };
    document.getElementById('loaded').onclick = () => {
        const config = setup();
        Loader.loadMathJax().then(() => {
            Loader.configure({...params, lang: 'de'});
            result.textContent = window.MathJax === config && window.MathJax.version === 'test-fixture' ?
                'Running MathJax retained' : 'Running MathJax was replaced';
            return;
        }).catch(error => { result.textContent = String(error); });
    };
});
JS);
echo $OUTPUT->footer();
