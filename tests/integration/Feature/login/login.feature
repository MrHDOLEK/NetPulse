Feature: Login and authenticated dashboard shell
  Once an administrator account exists, the web UI is locked behind a form
  login. The dashboard root (`/`) is reachable only after a successful login;
  every other web request bounces to the login page. The agent API and the
  Prometheus scrape endpoint stay outside the firewall and are never affected.

  Scenario: The login page renders the sign-in form
    Given an administrator account exists with email "admin@example.com" and password "correct-horse-battery"
    When I send a GET request to "/login"
    Then the response code is 200
    And the response content type contains "text/html"
    And the response body contains "_username"
    And the response body contains "_password"
    And the response body contains "_csrf_token"

  Scenario: An unauthenticated request to the dashboard is redirected to login
    Given an administrator account exists with email "admin@example.com" and password "correct-horse-battery"
    When I send a GET request to "/"
    Then the response code is 302
    And the response redirects to a location containing "/login"

  Scenario: Valid credentials authenticate and the dashboard shell renders
    Given an administrator account exists with email "admin@example.com" and password "correct-horse-battery"
    When I log in with email "admin@example.com" and password "correct-horse-battery"
    Then the response code is 302
    And the response redirects to a location containing "/"
    When I follow the redirect
    Then the response code is 200
    And the response body contains "Dashboard"

  Scenario: Wrong credentials are rejected and the dashboard stays protected
    Given an administrator account exists with email "admin@example.com" and password "correct-horse-battery"
    When I log in with email "admin@example.com" and password "wrong-password"
    Then the response redirects to a location containing "/login"
    When I send an authenticated GET request to "/"
    Then the response code is 302
    And the response redirects to a location containing "/login"
