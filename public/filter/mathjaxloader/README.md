# Draft: scoped MathJax configuration (MDL-89559)

This prototype adds one plugin-facing JavaScript entry point:

```js
import {typesetWithConfig} from 'filter_mathjaxloader/loader';

// Keep a reference to an element created by the plugin's own renderer.
// Its contents, including all mathematics, must be trusted or appropriately cleaned.
await typesetWithConfig(container, {
    packages: ['texhtml'],
    tex: {allowTexHTML: true},
});
// Attach listeners to generated inputs after typesetting has completed.
```

Call after the filter's `configure()` and before the container's first typesetting
or filter-content-updated event. Registration is synchronous even though rendering
returns a promise. Do not use an author-supplied selector, configuration, CSS class,
HTML attribute or arbitrary question text to authorise this call. Installed plugin
code is the trust boundary; this API is not a sandbox for JavaScript.

## Configuration and isolation

The prototype deliberately supports built-in TeX package names and JSON TeX options
only. It does not accept remote script URLs, loader paths, callbacks, output options,
or arbitrary startup configuration. Package names are deduplicated and sorted.
Explicit administrator TeX options cannot be replaced: object options merge
recursively; unequal existing scalar or array values reject. Administrator package
removals cannot be reversed. Contributions on different roots need not agree because
they use different TeX input instances. Repeated and nested registration rejects.

Each root gets a separate TeX input and MathDocument, using the site's
existing MathJax 4 installation and document options (including the safe handler).
The output jax is shared so CHTML styles and SVG glyph IDs accumulate across roots;
plugins cannot contribute output options.
The shared TeX input is never configured with the contributed options or packages.
Packages load through MathJax's loader before the scoped input is constructed, even
when the shared document has already started. Ordinary pages do not load contributed
packages. The prototype does not require a second MathJax script or an iframe.

This isolates input configuration, not executable extension code: MathJax's package
registry is shared, and package implementations can have global side effects.
Only trusted installed plugin code should make contributions. The texhtml extension
still does **not** sanitize HTML. Neither this API nor MathJax's safe extension makes
untrusted HTML safe to insert into the DOM. A plugin must not mix student-supplied
mathematics into a root for which it enables privileged options.

Roots are registered by object identity in a WeakMap. A copied class, ID or data
attribute cannot opt content into privileged processing. The class used to discover
roots in ancestor update events carries no authority. While the ordinary renderer
processes an ancestor, registered roots temporarily receive `data-MJX`, which MathJax
skips even when a descendant has `mathjax_process`. Previous attribute values are
restored in `finally`. Both rendering paths share a queue so these temporary markers
cannot overlap scoped rendering.

## AJAX and failures

Filter updates within a registered root reuse its MathDocument. Updates enclosing
both ordinary and scoped content dispatch to their respective renderers. Removed
MathItems are cleared before rerendering replaced children. Replacing the root
itself does not transfer trust; the plugin must register the replacement explicitly.
Keep the discovery class intact while a scope is in use.

Registration rejects disconnected elements, overlapping roots and already-rendered
content. Render/load failures reject the initial promise and remove the registration.
A failed contribution does not poison the queue for unrelated mathematics. The plugin
must handle a rejected promise and choose its own fallback UI.

## Review boundaries

This is a design prototype, not a completed STACK integration or a security audit.
It requires MathJax 4 for scoped rendering; existing ordinary rendering is retained.
The PHP filter, Moodle HTML cleaning pipeline and STACK question renderer are not
changed. Real STACK usage must arrange registration order and authoring/cleaning of
`tex-html` markup and attach input behaviour after MathJax reconstructs the HTML. Input keyboard
events must not bubble into MathJax's explorer; otherwise its navigation handlers
can consume typing. The Behat fixture demonstrates binding these handlers to the
rendered input without changing the site-wide accessibility configuration.

Before integration, maintainers should review:

- Whether this JavaScript entry point is the appropriate contribution contract,
  or a PHP hook should define available profiles and registration ordering.
- Compatibility and cost of one input/document per root, including menu,
  accessibility, equation numbering and large question pages.
- Reliance on MathJax startup constructors/document factory and its `data-MJX`
  discovery behaviour across supported MathJax 4 configurations.
- Whether the contract should initially whitelist supported TeX packages.
- Integration into Moodle's standard Behat/CI coverage. The included real-browser
  regression suite currently runs independently of a Moodle installation.

See [browser test instructions](tests/browser/README.md) and
[MDL-89559](https://moodle.atlassian.net/browse/MDL-89559).

## Plugin author example: binding a rendered input

This is a draft API; neither the patch nor this example establishes a supported STACK
integration. After the Moodle filter has been configured, a trusted plugin can register
its own connected renderer-created element before the element is first typeset:

```js
import {typesetWithConfig} from 'filter_mathjaxloader/loader';

await typesetWithConfig(container, {
    packages: ['texhtml'],
    tex: {allowTexHTML: true},
});
const input = container.querySelector('mjx-container input');
if (input) {
    ['focusin', 'focusout', 'mousedown', 'click', 'keydown', 'keypress', 'keyup'].forEach(type => {
        input.addEventListener(type, event => event.stopPropagation());
    });
}
```

The container's source must be trusted or appropriately cleaned before this call. The
example illustrates retaining input focus and typing without activating the expression
explorer; it is not a complete accessibility solution. The plugin still needs labels,
keyboard navigation, input-value synchronization and listeners for its own form/grading
lifecycle. Rebind after any rendering operation that reconstructs the input. Do not turn
off accessibility globally to make one input work.

Handle rejected promises explicitly. An administrator conflict should be resolved by
using permitted configuration, not by overriding the administrator setting. A disconnected,
already-rendered or overlapping root requires correcting registration order/ownership.
A package-load failure should leave a usable fallback rather than silently claiming the
interactive content is ready. MathJax versions before 4 do not support this scoped API.

No new administrator setting or database migration is introduced; existing ordinary
rendering continues to use the site's configuration. After architectural acceptance,
the contract and examples should be incorporated into the MathJax filter developer docs.
Keep `dev_docs_required` until that accepted API documentation is published and linked.
