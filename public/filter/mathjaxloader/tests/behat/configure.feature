@filter @filter_mathjaxloader
Feature: Preserve MathJax configuration during fragment loading
  In order to load maths in AJAX fragments
  As a plugin author
  I need repeated site configuration to preserve pending startup hooks

  @javascript
  Scenario: Repeated MathJax configuration preserves plugin packages and startup hooks
    Given I log in as "admin"
    And I am on fixture page "/filter/mathjaxloader/tests/behat/fixtures/configure_testpage.php"
    When I click on "Repeat configuration" "button"
    Then I should see "Startup hook retained" in the "#result" "css_element"

  @javascript
  Scenario: Changed MathJax site configuration replaces the pending configuration
    Given I log in as "admin"
    And I am on fixture page "/filter/mathjaxloader/tests/behat/fixtures/configure_testpage.php"
    When I click on "Change pending configuration" "button"
    Then I should see "Changed site configuration applied" in the "#result" "css_element"

  @javascript
  Scenario: Repeated MathJax configuration preserves an already loaded instance
    Given I log in as "admin"
    And I am on fixture page "/filter/mathjaxloader/tests/behat/fixtures/configure_testpage.php"
    When I click on "Configure after loading" "button"
    Then I should see "Running MathJax retained" in the "#result" "css_element"
