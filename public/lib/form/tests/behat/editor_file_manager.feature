@core @core_form
Feature: Manage editor attachments with a plain text editor
  In order to maintain embedded files without changing editors
  As a teacher
  I can open a generic file manager without losing unsaved text

  @javascript
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
    And I switch to the main window
    And the field "Course summary" matches value "Unsaved source text"
    And I close all opened windows

  @javascript
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

  @javascript
  Scenario: Component Library demonstrates enabled and disabled attachment management
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "user preferences" exist:
      | user  | preference | value    |
      | admin | htmleditor | textarea |
    And the following "activities" exist:
      | activity | name | intro                                                                                      | course |
      | label    | L1   | <a href="../admin/tool/componentlibrary/examples/formfields.php">Form element examples</a> | C1     |
    And I am on the "C1" "course" page logged in as "admin"
    When I follow "Form element examples"
    And I expand all fieldsets
    Then "#fitem_id_editor a[href*='manage_editor_files.php']" "css_element" should exist
    And "#fitem_id_editorwithoutfiles a[href*='manage_editor_files.php']" "css_element" should not exist
