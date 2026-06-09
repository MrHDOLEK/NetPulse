Feature: Security headers and CSP nonce on web pages
  Web HTML responses carry browser-hardening headers and a strict Content-Security-Policy
  whose script-src admits only same-origin scripts and a per-request nonce — no
  'unsafe-inline' for scripts. The agent API and the Prometheus scrape stay headerless.

  Scenario: The login page carries the hardening headers and a nonce'd CSP
    When I request "/login"
    Then the response has header "X-Frame-Options" with value "DENY"
    And the response has header "X-Content-Type-Options" with value "nosniff"
    And the response has header "Referrer-Policy" with value "same-origin"
    And the response header "Content-Security-Policy" contains "default-src 'self'"
    And the response header "Content-Security-Policy" contains "script-src 'self' 'nonce-"
    And the response header "Content-Security-Policy" matches "/script-src 'self' 'nonce-[0-9a-f]{32}'/"
    And the response header "Content-Security-Policy" contains "object-src 'none'"
    And the response header "Content-Security-Policy" contains "frame-ancestors 'none'"

  Scenario: The Prometheus scrape endpoint is left headerless
    When I request "/metrics"
    Then the response does not have header "X-Frame-Options"
    And the response does not have header "X-Content-Type-Options"
    And the response does not have header "Referrer-Policy"
    And the response does not have header "Content-Security-Policy"

  Scenario: The agent API is left headerless
    When I request "/api/v1/ping"
    Then the response does not have header "X-Frame-Options"
    And the response does not have header "X-Content-Type-Options"
    And the response does not have header "Referrer-Policy"
    And the response does not have header "Content-Security-Policy"
