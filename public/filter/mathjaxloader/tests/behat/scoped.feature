@filter @filter_mathjaxloader @javascript
Feature: Trusted plugins can render scoped MathJax content
  In order to use an input inside TeX without enabling it globally
  As a plugin developer
  I can register a trusted renderer-owned root

  Scenario: A scoped TeX input renders through Moodle AMD without changing global options
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | name | intro                                                                                       | course |
      | label    | L1   | <a href="../filter/mathjaxloader/tests/fixtures/scoped.php">Scoped MathJax fixture</a> | C1     |
    And I am on the "C1" "Course" page logged in as "admin"
    When I follow "Scoped MathJax fixture"
    Then I should see "Scoped input rendered; global TeX unchanged"
    And I set the field with xpath "//div[@id='trusted-root']//mjx-container//input" to "42"
    And the field with xpath "//div[@id='trusted-root']//mjx-container//input" matches value "42"
