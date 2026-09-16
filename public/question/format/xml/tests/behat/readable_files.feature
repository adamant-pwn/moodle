@qformat @qformat_xml @javascript @_file_upload
Feature: Readable attachments in Moodle XML
  In order to review question attachments in version control
  As a teacher
  I can import UTF-8 attachments and opt into readable exports

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

  Scenario: UTF-8 attachments import and export as base64 by default
    When I am on the "Qbank 1" "core_question > question export" page logged in as "admin"
    And I set the field "id_format_xml" to "1"
    Then the field "Export text attachments as readable UTF-8" matches value "0"
    When I press "Export questions to file"
    Then following "click here" should download a file that:
      | Contains text | name="notation.css" path="/" encoding="base64" |
      | Contains text | name="example.js" path="/" encoding="base64"   |

  Scenario: Readable export is explicitly enabled and specific to XML
    When I am on the "Qbank 1" "core_question > question export" page logged in as "admin"
    And I set the field "id_format_xml" to "1"
    And I set the field "Export text attachments as readable UTF-8" to "1"
    And I press "Export questions to file"
    Then following "click here" should download a file that:
      | Contains text | encoding="utf-8" xml:space="preserve" |
      | Contains text | font-weight: bold;                    |
      | Contains text | const message = "α < β & γ";           |
    When I am on the "Qbank 1" "core_question > question export" page logged in as "admin"
    And I set the field "id_format_gift" to "1"
    Then "Export text attachments as readable UTF-8" "field" should not be visible
