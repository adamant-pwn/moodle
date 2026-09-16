@core @core_form @javascript
Feature: Manage editor attachments with a plain text editor
  In order to maintain embedded files without changing editors
  As a teacher
  I can open a generic file manager without losing unsaved text

  Scenario: Source editor retains unsaved course summary while managing files
    Given the following "courses" exist:
      | fullname | shortname | summaryformat |
      | Course 1 | C1        | 1             |
    And the following "user preferences" exist:
      | user  | preference | value    |
      | admin | htmleditor | textarea |
    And I am on the "C1" "course" page logged in as "admin"
    And I follow "Settings"
    And I set the field "Course summary" to "Unsaved source text"
    When I click on "Manage files" "link"
    And I switch to a second window
    Then I should see "File manager"
    And ".filemanager.fm-loaded" "css_element" should exist
    When I switch to the main window
    Then the field "Course summary" matches value "Unsaved source text"
    And I close all opened windows

  Scenario: TinyMCE does not duplicate its attachment manager
    Given the following config values are set as admin:
      | texteditors | tiny,textarea |
    And the following "courses" exist:
      | fullname | shortname | summaryformat |
      | Course 1 | C1        | 1             |
    And the following "user preferences" exist:
      | user  | preference | value |
      | admin | htmleditor | tiny  |
    When I am on the "C1" "course" page logged in as "admin"
    And I follow "Settings"
    Then "a[href*='/lib/form/manage_editor_files.php']" "css_element" should not exist
