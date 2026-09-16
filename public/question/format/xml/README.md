# Readable text attachments

Draft documentation for [MDL-89828](https://moodle.atlassian.net/browse/MDL-89828).
This describes the proposed patch, not a released Moodle feature.

Moodle XML accepts `encoding="utf-8"` for text attachments in addition to the existing
`encoding="base64"` representation. For example:

```xml
<file name="notation.css" path="/" encoding="utf-8" xml:space="preserve"><![CDATA[.vector {
  font-weight: bold;
}
]]></file>
```

Use `xml:space="preserve"` to retain whitespace-only content and parser chunks. CDATA or XML
escaping protects characters such as `<` and `&`; split CDATA sections when the source contains
`]]>`. The imported content remains an ordinary Moodle stored file, referenced through
`@@PLUGINFILE@@`. File execution and filtering permissions are unchanged.

The question-bank export form offers **Export text attachments as readable UTF-8** for Moodle XML.
It is disabled by default because older importers unconditionally base64-decode file contents.
Programmatic exporters can opt in with `$format->set_readable_files(true)`.

Readable export applies only to text MIME types, JavaScript, JSON, XML and SVG whose UTF-8 contents
can be represented in XML 1.0 without changing their bytes. Files containing carriage returns,
invalid UTF-8 or forbidden XML characters remain base64, as do binary MIME types. This preserves
CRLF files exactly instead of silently applying XML line-ending normalization. Manually authored
UTF-8 XML still follows normal XML newline normalization rules.

Missing encoding attributes retain legacy base64 behavior. Unsupported explicit encodings are
rejected instead of silently decoding the contents incorrectly. The legacy image-base64 fields
are unaffected.

## Testing

Run the XML parser tests and the question XML test suite:

```sh
vendor/bin/phpunit public/lib/tests/xml_parser_test.php
vendor/bin/phpunit --testsuite qformat_xml_testsuite
```

The new `readable_files_test` covers opt-in and default exports, Unicode, CDATA delimiters,
whitespace-only files, chunk boundaries, empty files, binary/invalid text fallback, nested paths,
and mixed-encoding question/feedback imports. Existing XML tests cover legacy question formats.

For a manual check, import `tests/fixtures/readable_files.xml`, export its category as Moodle XML
with the new checkbox unchecked and checked, and compare the attachment representations. Reimport
the readable result into a test category and verify both files retain their original contents.
The exported CSS must keep its leading newline and indentation. Also check that selecting another
export format hides the XML-only checkbox.

The Behat scenarios in `tests/behat/readable_files.feature` cover the Moodle UI:
raw UTF-8 attachment import, base64 export by default, readable export when selected,
and hiding the option for a different format. Exact byte preservation and mixed
encoding round trips are covered by the PHPUnit suite.

## Teacher workflow (draft user documentation)

Use this option when reviewing or maintaining attached source files in version control.
In the question bank, open **Export**, choose **Moodle XML format**, select the category,
and enable **Export text attachments as readable UTF-8** before exporting. Leave the
option unchecked if the destination Moodle does not include this change. Other export
formats do not show the option. Existing users get the same base64 export by default.

Import the resulting file through the usual question-bank **Import** page with Moodle XML
selected. Import needs no additional UTF-8 setting: the encoding is declared on each file.
One question can contain both readable text files and base64 attachments.

There is no database migration or new site-administration setting. New and upgraded sites
use the same default. This does not grant permission to execute JavaScript, bypass content
filtering, or change who may import/export questions.

If import reports an unsupported file encoding, correct the file's encoding declaration
and content together, or regenerate a standard base64 export. Do not relabel raw text as
base64 or vice versa. A file remaining base64 in readable mode is expected when its MIME
type or bytes are unsuitable; see the preservation rules above. For an older destination,
regenerate the export with readable mode disabled rather than importing unsupported UTF-8.

After acceptance, the user-facing text belongs in the versioned Moodle XML import/export
documentation, and the format/setter contract in the developer documentation. Keep the
Tracker documentation labels until those accepted-version pages have been updated.
