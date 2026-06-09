Feature: Probe due-work polling

  Scenario: A probe polling due work gets only the due connection
    Given an enabled probe with a due connection and a recently-measured connection exists
    And I authenticate as probe with token "due-secret-token"
    When I send a GET request to "/api/v1/probes/55555555-5555-4555-8555-555555555555/due"
    Then the response code is 200
    And the response content is:
      """
      {
        "tasks": [
          {"connectionId": "66666666-6666-4666-8666-666666666666", "serverId": "12746"}
        ],
        "pollAfterSeconds": 60
      }
      """

  Scenario: A degraded connection is densified and retested on a different server
    Given an enabled probe with a degraded connection and a healthy recent connection exists
    And I authenticate as probe with token "due-secret-token"
    When I send a GET request to "/api/v1/probes/55555555-5555-4555-8555-555555555555/due"
    Then the response code is 200
    And the response content is:
      """
      {
        "tasks": [
          {"connectionId": "88888888-8888-4888-8888-888888888888", "serverId": "srv-b"}
        ],
        "pollAfterSeconds": 60
      }
      """

  Scenario: Bad probe token is rejected with 401
    Given an enabled probe with a due connection and a recently-measured connection exists
    And I authenticate as probe with token "wrong-token"
    When I send a GET request to "/api/v1/probes/55555555-5555-4555-8555-555555555555/due"
    Then the response code is 401

  Scenario: Disabled probe is rejected with 403
    Given a disabled probe with a due connection exists
    And I authenticate as probe with token "due-secret-token"
    When I send a GET request to "/api/v1/probes/55555555-5555-4555-8555-555555555555/due"
    Then the response code is 403
