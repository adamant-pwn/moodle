# Readable text attachments

Proposed for [MDL-89828](https://moodle.atlassian.net/browse/MDL-89828).

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
