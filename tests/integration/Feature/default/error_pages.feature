Feature: Error responses are scoped by request path
  Web routes render HTML error pages, while the agent API and the
  Prometheus scrape endpoint keep returning machine-readable JSON.

  Scenario: An unknown web path returns an HTML 404
    Given an administrator account exists
    When I send a GET request to "/nope"
    Then the response code is 404
    And the response content type contains "text/html"

  Scenario: An unknown API path returns a JSON 404
    When I send a GET request to "/api/v1/nope"
    Then the response code is 404
    And the response content type contains "application/json"
