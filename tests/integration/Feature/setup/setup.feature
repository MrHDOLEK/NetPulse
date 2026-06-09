Feature: First-run admin setup
  Before any admin account exists, the web UI funnels every non-exempt
  request to a one-time setup page that creates the first administrator.
  The agent API and the Prometheus scrape endpoint are never affected.

  Scenario: With no users, a web request is redirected to setup
    Given no users exist
    When I send a GET request to "/"
    Then the response code is 302
    And the response redirects to a location containing "/setup"

  Scenario: With no users, the setup page renders the setup form
    Given no users exist
    When I send a GET request to "/setup"
    Then the response code is 200
    And the response content type contains "text/html"
    And the response body contains "_csrf_token"
    And the response body contains "passwordConfirm"

  Scenario: Submitting valid credentials creates the first admin and redirects to login
    Given no users exist
    When I submit the setup form with email "admin@example.com" and password "correct-horse-battery"
    Then the response code is 302
    And the response redirects to a location containing "/login"
    And exactly 1 user exists

  Scenario: Once an admin exists, the setup page is gone
    Given an admin user already exists
    When I send a GET request to "/setup"
    Then the response code is 404

  Scenario: The agent API is never redirected to setup
    Given no users exist
    When I send a GET request to "/api/v1/ping"
    Then the response code is 200
    And the response content type contains "application/json"

  Scenario: The Prometheus scrape endpoint is never redirected to setup
    Given no users exist
    When I send a GET request to "/metrics"
    Then the response code is 200
