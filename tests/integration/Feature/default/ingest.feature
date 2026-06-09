Feature: Probe result ingest

  Scenario: Successful ingest of a completed Ookla result returns 201
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333",
        "type": "result",
        "ping": {"latency": 12.5, "jitter": 1.2, "low": 11.0, "high": 14.0},
        "download": {"bandwidth": 117875000, "bytes": 1200000000, "elapsed": 9000, "latency": {"iqm": 18.4}},
        "upload": {"bandwidth": 23375000, "bytes": 240000000, "elapsed": 8000, "latency": {"iqm": 22.1}},
        "packetLoss": 0.0,
        "isp": "Orange Polska",
        "server": {"id": 12746, "name": "Orange Polska", "location": "Warsaw", "host": "speedtest.orange.pl", "port": 8080},
        "result": {"url": "https://www.speedtest.net/result/c/abc-123"}
      }
      """
    Then the response code is 201
    And the latest measurement on the connection is recorded as healthy

  Scenario: A completed result below the download threshold is recorded as unhealthy
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333",
        "type": "result",
        "ping": {"latency": 12.5, "jitter": 1.2, "low": 11.0, "high": 14.0},
        "download": {"bandwidth": 50000000, "bytes": 500000000, "elapsed": 9000, "latency": {"iqm": 18.4}},
        "upload": {"bandwidth": 23375000, "bytes": 240000000, "elapsed": 8000, "latency": {"iqm": 22.1}},
        "packetLoss": 0.0,
        "isp": "Orange Polska",
        "server": {"id": 12746, "name": "Orange Polska", "location": "Warsaw", "host": "speedtest.orange.pl", "port": 8080},
        "result": {"url": "https://www.speedtest.net/result/c/abc-124"}
      }
      """
    Then the response code is 201
    And the latest measurement on the connection is recorded as unhealthy

  Scenario: Ingest of a failed Ookla result is still accepted with 201
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333",
        "type": "error",
        "error": "Configuration - Could not retrieve or read configuration"
      }
      """
    Then the response code is 201

  Scenario: Bad probe token is rejected with 401
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "wrong-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333",
        "type": "error",
        "error": "boom"
      }
      """
    Then the response code is 401

  Scenario: Disabled probe is rejected with 403
    Given a disabled probe exists
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333",
        "type": "error",
        "error": "boom"
      }
      """
    Then the response code is 403

  Scenario: Connection owned by another probe is rejected with 403
    Given a probe exists with a connection owned by another probe
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333",
        "type": "error",
        "error": "boom"
      }
      """
    Then the response code is 403

  Scenario: Invalid payload missing connectionId is rejected with 400
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "type": "result"
      }
      """
    Then the response code is 400

  Scenario: Invalid payload missing type is rejected with 400
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "secret-token"
    When I send a POST request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/results" with body:
      """
      {
        "connectionId": "33333333-3333-4333-8333-333333333333"
      }
      """
    Then the response code is 400

  Scenario: Config pull returns the probe connections
    Given an enabled probe with a connection exists
    And I authenticate as probe with token "secret-token"
    When I send a GET request to "/api/v1/probes/22222222-2222-4222-8222-222222222222/config"
    Then the response code is 200
