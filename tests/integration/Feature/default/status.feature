Feature: Status of API

  Scenario: Get status of API
    When I send a GET request to "/api/v1"
    Then the response code is 200
