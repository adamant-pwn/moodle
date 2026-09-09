// Draft browser regression suite. See README.md in this directory.
const {test, before, after} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const mathjaxRoot = process.env.MATHJAX_ROOT;
const src = path.resolve(__dirname, '../../amd/src');
const built = process.env.TEST_AMD === '1';
let browser, server, origin;
before(async() => {
    assert.ok(mathjaxRoot, 'Set MATHJAX_ROOT to the MathJax npm package directory');
    server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const type = url.pathname.endsWith('.js') ? 'application/javascript' : 'text/plain';
        res.setHeader('Content-Type', type);
        try {
            if (url.pathname === '/require.js') {
                res.end(fs.readFileSync(path.resolve(__dirname, '../../../../lib/requirejs/require.js')));
            } else if (url.pathname.startsWith('/amd/')) {
                res.end(fs.readFileSync(path.resolve(src, '../build', path.basename(url.pathname))));
            } else if (url.pathname === '/loader.js') {
                res.end(fs.readFileSync(path.join(src, 'loader.js'), 'utf8')
                    .replace("'core_filters/events'", "'/events.js'")
                    .replace("'filter_mathjaxloader/scoped'", "'/scoped.js'"));
            } else if (url.pathname === '/scoped.js') {
                res.end(fs.readFileSync(path.join(src, 'scoped.js')));
            } else if (url.pathname === '/events.js') {
                res.end(`export const eventTypes = {filterContentUpdated: 'filter-update'};
                    export const notifyFilterContentRenderingComplete = nodes => window.completed.push(...nodes);`);
            } else if (url.pathname.startsWith('/mathjax/')) {
                const relative = url.pathname.slice('/mathjax/'.length);
                // The font's bundled default path is relative to the MathJax script.
                const file = relative.startsWith('@mathjax/') ?
                    path.resolve(mathjaxRoot, '..', relative) : path.resolve(mathjaxRoot, relative);
                res.end(fs.readFileSync(file));
            } else {
                res.setHeader('Content-Type', 'text/html');
                res.end('<!doctype html><html><head><title>MathJax scope test</title></head><body></body></html>');
            }
        } catch (e) {
            res.statusCode = 404;
            res.end(e.message);
        }
    }).listen(0, '127.0.0.1');
    await new Promise(resolve => server.once('listening', resolve));
    origin = `http://127.0.0.1:${server.address().port}`;
    browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_PATH});
});
after(async() => { await browser?.close(); await new Promise(resolve => server?.close(resolve)); });

const run = async(callback, admin = {}) => {
    const page = await browser.newPage();
    try {
        await page.goto(origin);
        if (built) { await page.addScriptTag({url: origin + '/require.js'}); }
        await page.evaluate(async({origin, admin, built}) => {
            window.completed = [];
            if (built) {
                define('core_filters/events', [], () => ({eventTypes: {filterContentUpdated: 'filter-update'},
                    notifyFilterContentRenderingComplete: nodes => completed.push(...nodes)}));
                require.config({paths: {'filter_mathjaxloader/loader': '/amd/loader.min',
                    'filter_mathjaxloader/scoped': '/amd/scoped.min'}});
                window.api = await new Promise((resolve, reject) => require(['filter_mathjaxloader/loader'], resolve, reject));
            } else { window.api = await import('/loader.js'); }
            api.configure({mathjaxurl: origin + '/mathjax/tex-mml-chtml.js', lang: 'en',
                mathjaxconfig: JSON.stringify(admin)});
            window.add = (id, html, parent = document.body) => {
                const node = document.createElement('div');
                node.id = id;
                node.innerHTML = html;
                parent.append(node);
                return node;
            };
            window.config = {packages: ['texhtml'], tex: {allowTexHTML: true}};
            window.equation = id => String.raw`\(x+<tex-html><input id="${id}"></tex-html>\)`;
            window.update = async node => {
                const count = completed.length;
                api.contentUpdated({detail: {nodes: [node]}});
                for (let i = 0; i < 500 && count === completed.length; ++i) {
                    await new Promise(resolve => setTimeout(resolve, 10));
                }
                if (completed.length === count) { throw new Error('Filter completion timed out'); }
                await MathJax.startup.promise;
            };
        }, {origin, admin, built});
        return await page.evaluate(callback);
    } finally { await page.close(); }
};

test('initial scope renders HTML without changing shared TeX options', async() => {
    assert.deepEqual(await run(async() => {
        const scope = add('trusted', equation('answer'));
        await api.typesetWithConfig(scope, config);
        const plain = add('plain', String.raw`\(y+1\)`);
        plain.className = 'filter_mathjaxloader_equation';
        await update(plain);
        const tex = MathJax.startup.input.find(jax => jax.name === 'TeX');
        return [!!scope.querySelector('mjx-container input'), !!plain.querySelector('mjx-container'),
            tex.options.allowTexHTML === true, tex.options.packages.includes('texhtml')];
    }), [true, true, false, false]);
});

test('registration after startup works and preserves administrator macros', async() => {
    assert.deepEqual(await run(async() => {
        await api.loadMathJax(); await MathJax.startup.promise;
        const scope = add('trusted', String.raw`\(\adminmacro+<tex-html><input></tex-html>\)`);
        await api.typesetWithConfig(scope, config);
        return [!!scope.querySelector('mjx-container input'), !scope.querySelector('[data-mjx-error]'),
            MathJax.config.tex.macros.adminmacro];
    }, {tex: {macros: {adminmacro: 'x'}}}), [true, true, 'x']);
});

test('forged scope markup never enables texhtml', async() => {
    assert.deepEqual(await run(async() => {
        await api.typesetWithConfig(add('trusted', equation('answer')), config);
        const fake = add('fake', equation('forged'));
        fake.className = 'filter_mathjaxloader_scope filter_mathjaxloader_equation';
        fake.dataset.mathjaxConfig = JSON.stringify(config);
        await update(fake);
        return [!!fake.querySelector('mjx-container input'),
            MathJax.startup.input.find(jax => jax.name === 'TeX').options.allowTexHTML === true];
    }), [false, false]);
});

test('AJAX insertion inside a scope reuses its configuration', async() => {
    assert.deepEqual(await run(async() => {
        const scope = add('trusted', equation('first'));
        await api.typesetWithConfig(scope, config);
        const child = add('child', equation('second'), scope);
        await update(child);
        return [!!scope.querySelector('mjx-container #first'), !!scope.querySelector('mjx-container #second')];
    }), [true, true]);
});

test('ancestor AJAX update isolates scopes even with mathjax_process descendants', async() => {
    assert.deepEqual(await run(async() => {
        const parent = add('parent', '');
        parent.className = 'filter_mathjaxloader_equation';
        const scope = add('trusted', equation('first'), parent);
        await api.typesetWithConfig(scope, config);
        const child = add('child', equation('second'), scope);
        child.className = 'mathjax_process';
        const plain = add('plain', String.raw`\(y+1\)`, parent);
        await update(parent);
        return [!!scope.querySelector('mjx-container #second'), !!plain.querySelector('mjx-container'),
            scope.hasAttribute('data-MJX')];
    }), [true, true, false]);
});

test('replacement DOM does not inherit trust', async() => {
    assert.equal(await run(async() => {
        const scope = add('trusted', equation('first'));
        await api.typesetWithConfig(scope, config);
        const replacement = document.createElement('div');
        replacement.id = scope.id;
        replacement.className = scope.className + ' filter_mathjaxloader_equation';
        replacement.innerHTML = equation('replacement');
        scope.replaceWith(replacement);
        await update(replacement);
        return !!replacement.querySelector('mjx-container input');
    }), false);
});

test('overlapping and duplicate registrations reject', async() => {
    assert.deepEqual(await run(async() => {
        const parent = add('parent', '');
        const scope = add('trusted', equation('first'), parent);
        await api.typesetWithConfig(scope, config);
        const child = add('child', equation('second'), scope);
        const results = [];
        for (const node of [parent, scope, child]) {
            try { await api.typesetWithConfig(node, config); results.push(false); }
            catch { results.push(true); }
        }
        return results;
    }), [true, true, true]);
});

test('explicit administrator conflict rejects without blocking ordinary maths', async() => {
    assert.deepEqual(await run(async() => {
        let rejected = false;
        try { await api.typesetWithConfig(add('trusted', equation('answer')), config); }
        catch { rejected = true; }
        const normal = add('normal', String.raw`\(z+1\)`);
        normal.className = 'filter_mathjaxloader_equation'; await update(normal);
        return [rejected, !!normal.querySelector('mjx-container')];
    }, {tex: {allowTexHTML: false}}), [true, true]);
});

test('invalid data, external package paths and prototype keys reject', async() => {
    assert.deepEqual(await run(async() => {
        const values = [{packages: ['https://example.invalid/x']}, {loader: {}},
            {tex: {packages: ['texhtml']}}, {tex: {x: () => {}}},
            JSON.parse('{"tex":{"__proto__":{"polluted":true}}}'), null];
        const results = [];
        for (const [i, value] of values.entries()) {
            try { await api.typesetWithConfig(add(`n${i}`, ''), value); results.push(false); }
            catch { results.push(true); }
        }
        return [...results, Object.prototype.polluted === undefined];
    }), Array(7).fill(true));
});

test('two scopes keep conflicting macros independent', async() => {
    assert.deepEqual(await run(async() => {
        const a = add('a', String.raw`\(\localmacro\)`);
        const b = add('b', String.raw`\(\localmacro\)`);
        await Promise.all([api.typesetWithConfig(a, {tex: {macros: {localmacro: 'A'}}}),
            api.typesetWithConfig(b, {tex: {macros: {localmacro: 'B'}}})]);
        return [a.querySelector('mjx-c.mjx-c1D434')?.getBoundingClientRect().width > 0,
            b.querySelector('mjx-c.mjx-c1D435')?.getBoundingClientRect().width > 0,
            MathJax.startup.input.find(jax => jax.name === 'TeX').options.macros?.localmacro === undefined];
    }), [true, true, true]);
});

test('administrator package removal cannot be reversed by a contribution', async() => {
    assert.equal(await run(async() => {
        try { await api.typesetWithConfig(add('trusted', equation('answer')), config); }
        catch { return true; }
        return false;
    }, {tex: {packages: {'[-]': ['texhtml']}}}), true);
});

test('missing package rejects, releases the scope, and leaves the queue usable', async() => {
    assert.deepEqual(await run(async() => {
        const scope = add('trusted', String.raw`\(x+1\)`);
        let rejected = false;
        try { await api.typesetWithConfig(scope, {packages: ['moodle-missing-test-package']}); }
        catch { rejected = true; }
        scope.classList.add('filter_mathjaxloader_equation');
        await update(scope);
        return [rejected, scope.classList.contains('filter_mathjaxloader_scope'), !!scope.querySelector('mjx-container')];
    }), [true, false, true]);
});

test('replacing scope contents renders new maths and releases detached math items', async() => {
    assert.deepEqual(await run(async() => {
        const scope = add('trusted', equation('first'));
        await api.typesetWithConfig(scope, config);
        scope.innerHTML = equation('second');
        await update(scope);
        return [!!scope.querySelector('mjx-container #second'), scope.querySelectorAll('mjx-container').length];
    }), [true, 1]);
});

test('ordinary pages do not request a contributed package', async() => {
    assert.equal(await run(async() => {
        const ordinary = add('ordinary', String.raw`\(x+1\)`);
        ordinary.className = 'filter_mathjaxloader_equation';
        await update(ordinary);
        return performance.getEntriesByType('resource').some(entry => entry.name.includes('/texhtml'));
    }), false);
});
