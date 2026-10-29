# Draft browser regression tests

These 14 cases exercise the actual MathJax 4 renderer in Chromium. The only Moodle
stub is the filter event module; source tests use ES modules and the AMD variant
loads the generated files through Moodle's bundled RequireJS. No Moodle database,
PHP server or authenticated session is used. This suite is not yet wired into
Moodle CI and does not replace Moodle/STACK integration testing.

Install `playwright` and `mathjax@4.0.0` in a separate test directory, with Chromium
available (or install Playwright's Chromium). From the Moodle checkout:

```sh
PLAYWRIGHT_MODULE=/absolute/test/node_modules/playwright \
MATHJAX_ROOT=/absolute/test/node_modules/mathjax \
CHROMIUM_PATH=/usr/bin/chromium \
node --test public/filter/mathjaxloader/tests/browser/scoped.cjs
```

Repeat with `TEST_AMD=1` after building the AMD modules:

```sh
npx grunt amd --root=public/filter/mathjaxloader
```

Omit `CHROMIUM_PATH` to use the Chromium installed by Playwright. The server binds
to a random loopback port and serves local test assets; all page state is disposable.
The test harness requires Node 22 or newer.

Coverage: initial/late registration; administrator macro preservation and conflicts;
forged markers; insertion, ancestor updates and replacement through AJAX; overlapping
and duplicate scopes; invalid configuration/prototype keys; independent macro values;
administrator package removals; failed package loading; and lazy package loading.

## Moodle integration smoke test

`tests/behat/scoped.feature` runs a Behat-only trusted-renderer fixture through
Moodle's real PHP page and AMD loader. It checks that a texhtml input renders,
accepts typing, and does not enable allowTexHTML on the shared TeX input.
Run the scenario with Moodle's standard Behat environment. The fixture loads the
same MathJax 4.0.0 CDN used by the default filter setting and requires network access.
This complements the standalone isolation suite; it does not yet cover STACK's
HTML-cleaning pipeline, question input binding, or scoped AJAX updates in Moodle.
