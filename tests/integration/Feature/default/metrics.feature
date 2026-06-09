Feature: Prometheus /metrics exposition

  Scenario: Scrape exposes metric families and connection labels
    Given a probe "home" with site "home-lab" exists
    And a connection "wan1" on probe "home" with isp "Acme ISP" expecting 1000000000 down and 500000000 up
    And a completed measurement on connection "wan1" was recorded 60 seconds ago with download 950000000 and ping 12 ms
    And a stale completed measurement on connection "wan1" was recorded 100000 seconds ago with download 500000000 and ping 30 ms
    And a failed measurement on connection "wan1" was recorded 90 seconds ago
    When the metrics endpoint is scraped
    Then the metrics response code is 200
    And the metrics response is Prometheus exposition with content type
    And the metrics response body contains:
      """
      # TYPE netpulse_up gauge
      """
    And the metrics response body contains:
      """
      netpulse_up{probe="home",connection="wan1"} 1
      """
    And the metrics response body contains:
      """
      netpulse_download_bits_per_second{probe="home",connection="wan1",server_name=
      """
    And the metrics response body contains:
      """
      netpulse_connection_expected_download_bits_per_second{probe="home",connection="wan1"} 1000000000
      """
    And the metrics response body contains:
      """
      netpulse_speedtest_runs_total{probe="home",connection="wan1",status="completed"} 2
      """
    And the metrics response body contains:
      """
      netpulse_speedtest_runs_total{probe="home",connection="wan1",status="failed"} 1
      """
    And the metrics response body contains:
      """
      netpulse_connection_healthy{probe="home",connection="wan1",site="home-lab",server_name="Acme Speedtest",server_id="12345",isp="Acme ISP"} 1
      """
    And the metrics response body contains:
      """
      netpulse_connection_degraded{probe="home",connection="wan1"} 0
      """

  Scenario: Scrape reports a below-threshold connection as unhealthy and degraded
    Given a probe "home" with site "home-lab" exists
    And a connection "wan9" on probe "home" with isp "Acme ISP" expecting 1000000000 down and 500000000 up
    And an unhealthy measurement on connection "wan9" was recorded 60 seconds ago with download 100000000 and ping 250 ms
    When the metrics endpoint is scraped
    Then the metrics response code is 200
    And the metrics response body contains:
      """
      netpulse_connection_healthy{probe="home",connection="wan9",site="home-lab",server_name="Acme Speedtest",server_id="12345",isp="Acme ISP"} 0
      """
    And the metrics response body contains:
      """
      netpulse_speedtest_unhealthy_total{probe="home",connection="wan9"} 1
      """
    And the metrics response body contains:
      """
      netpulse_connection_degraded{probe="home",connection="wan9"} 1
      """

  Scenario: Scrape exposes the notifications-sent counter after a notification was dispatched
    Given a notification send was recorded for kind "alert" channel "webhook" status "sent"
    And a notification send was recorded for kind "recovery" channel "webhook" status "sent"
    When the metrics endpoint is scraped
    Then the metrics response code is 200
    And the metrics response body contains:
      """
      # TYPE netpulse_notifications_sent_total counter
      """
    And the metrics response body contains:
      """
      netpulse_notifications_sent_total{kind="alert",channel="webhook",status="sent"} 1
      """
    And the metrics response body contains:
      """
      netpulse_notifications_sent_total{kind="recovery",channel="webhook",status="sent"} 1
      """
