# Editor-independent attachment management

Draft user and developer documentation for [MDL-89662](https://moodle.atlassian.net/browse/MDL-89662).
This describes the proposed patch, not a released Moodle feature.

## For teachers and other form users

Plain text and source editors can expose a **Manage files** link beside a file-enabled
editor field. It opens Moodle's file manager in a separate window, so text you have not
saved in the original form is retained. Use the file manager's controls to upload,
download, rename or delete draft attachments, then return to the original window and
save the original form to persist its content and attachments.

There is no separate Save button on the manager page. File operations update the draft
area; saving the original form is still necessary. Closing the manager does not submit
the original form. Deleting or renaming a referenced file can break a link in the text;
update the source references as necessary. Uploading a file does not insert a reference
into the editor automatically.

The link is shown only when the selected editor supports repositories, does not supply
its own visible manager, and file management is enabled for that field. TinyMCE keeps its
own attachment interface. A form with file management disabled gets no generic link.
The editor actually selected also depends on the content format: an HTML editor may not
be selected for plain-text content even when it is the user's preferred editor.

There is no new site setting or database migration. New and upgraded installations use
the same rules. Existing form permissions, draft-file ownership and configured file
limits still apply. Guests cannot use the manager. If the link opens no window, check
whether the browser blocked the popup; if it is absent, check the field's file-management
support and selected editor rather than changing site permissions.

## For editor and form developers

`texteditor::provides_file_manager(): bool` defaults to false. Override it to return true
only if the editor provides its own visible embedded-file manager; TinyMCE does so.
Repository-aware editors inheriting false receive the generic control from
`MoodleQuickForm_editor`. Existing editors need no change to receive the fallback.

The editor option `enable_filemanagement => false` suppresses the generic control.
The generated URL carries the field's draft item ID, context and configured file limits.
Use the form-generated link rather than constructing a URL from untrusted input.
The manager works on the current user's draft area, not the final stored-file area.
The owning form must retain its normal draft preparation and save logic.

The implementation does not add a source editor, rewrite source text, insert links, or
provide a way to load or execute attached JavaScript. The normal Moodle file/content
security rules continue to apply.

## Documentation handoff

After acceptance, move the user workflow into the versioned editor/embedded-file guide
and the capability contract into the Forms API/editor developer guide. The upgrade-note
source is `.upgradenotes/MDL-89662-2026091600000000.yml`. Remove the documentation-required
labels only after the official accepted-version documentation and Tracker links are updated.
