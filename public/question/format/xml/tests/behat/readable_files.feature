@qformat @qformat_xml @javascript @_file_upload
Feature: Readable attachments in Moodle XML
  In order to review question attachments in version control
  As a teacher
  I can export readable text by default and choose compatibility with older Moodle versions

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | qbank    | Qbank 1 | C1     | qbank1   |
    And I am on the "Qbank 1" "core_question > question import" page logged in as "admin"
    And I set the field "id_format_xml" to "1"
    And I upload "question/format/xml/tests/fixtures/readable_files.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    And I press "Continue"

  Scenario: UTF-8 attachments import and export as readable text by default
    When I am on the "Qbank 1" "core_question > question export" page logged in as "admin"
    And I set the field "id_format_xml" to "1"
    Then the field "Use legacy-compatible attachment encoding" matches value "0"
    When I press "Export questions to file"
    Then following "click here" should download a file that:
      | Contains text | encoding="utf-8" xml:space="preserve" |
      | Contains text | font-weight: bold;                    |
      | Contains text | const message = "α < β & γ";           |

  Scenario: Legacy-compatible export is explicitly enabled and specific to XML
    When I am on the "Qbank 1" "core_question > question export" page logged in as "admin"
    And I set the field "id_format_xml" to "1"
    And I set the field "Use legacy-compatible attachment encoding" to "1"
    And I press "Export questions to file"
    Then following "click here" should download a file that:
      | Contains text | name="notation.css" path="/" encoding="base64" |
      | Contains text | name="example.js" path="/" encoding="base64"   |
    When I am on the "Qbank 1" "core_question > question export" page logged in as "admin"
    And I set the field "id_format_gift" to "1"
    Then "Use legacy-compatible attachment encoding" "field" should not be visible
