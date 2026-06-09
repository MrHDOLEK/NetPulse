Feature: Liveness and readiness probes

  Scenario: Liveness probe responds immediately
    When I send a GET request to "/api/v1/ping"
    Then the response code is 200
    And the response content is:
      """
      {"status":"ok"}
      """

  Scenario: Readiness probe reports the database as healthy
    When I send a GET request to "/api/v1/healthcheck"
    Then the response code is 200
    And the response content is:
      """
      {"status":"healthy","checks":{"database":{"status":"up"}}}
      """
