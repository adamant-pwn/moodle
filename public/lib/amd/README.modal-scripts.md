# External scripts in trusted modal bodies

Draft developer documentation for [MDL-89548](https://moodle.atlassian.net/browse/MDL-89548).
This describes the proposed patch, not a released API.

`core/modal` accepts the optional `executeExternalScripts` configuration flag, defaulting
to false. Set it only for a trusted body whose external scripts should behave as they do
in a full page. Question-bank Usage and Comments previews opt in to restore ordinary
question-preview behavior.

```js
import Modal from 'core/modal';

const modal = await Modal.create({
    title: 'Trusted preview',
    body: trustedPreviewHtml,
    executeExternalScripts: true,
});
modal.show();
```

`trustedPreviewHtml` must already have passed the applicable content-permission and
cleaning checks. The flag does not sanitize HTML or script URLs and must not be enabled
for arbitrary user-supplied content. It is opt-in: existing modal callers keep their
previous default. No site setting or database migration is introduced.

For string bodies and promise-based fragment bodies, external `script[src]` elements
are activated in document order before notifying content filters and firing
`bodyRendered`. Attributes are copied to the activated elements. Load failures allow
later scripts and filtering to proceed; they do not constitute successful dependency
initialization. Inline script elements are not activated by this new option. A fragment's
separately supplied initialization JavaScript continues through the existing fragment path.

If a preview dependency fails, inspect the browser network/console output and verify the
script URL and permissions. Do not bypass content restrictions to make a preview work.
Code reacting to body completion should use the modal's completion events/promises rather
than assuming `setBody` synchronously finishes script loading and filtering.

After acceptance, incorporate this option into the core Modal developer documentation.
The source upgrade note is `.upgradenotes/MDL-89548-2026091600000000.yml`.
