<!--
PR title must start with "- " (a CI check enforces the pattern ^(#\d+ )?- .+), e.g.:
  - Add per-connection alert thresholds
  #42 - Correct the ping unit on the heatmap
-->

## Summary

<!-- What does this change and why? Link any related issue: Closes #123 -->

## Type of change

- [ ] ✨ Feature
- [ ] 🐞 Bug fix
- [ ] ♻️ Refactor
- [ ] 📖 Documentation
- [ ] 🧰 Chore / tooling

## How was it tested?

<!-- Commands you ran, scenarios you checked. -->

## Quality gate

All commands run inside the `app` container (`docker compose exec -T app <cmd>`):

- [ ] `composer cs:check` — code style
- [ ] `composer phpstan` — PHPStan level 10, zero errors
- [ ] `vendor/bin/deptrac --no-progress` — 0 architecture violations
- [ ] `composer db:test` — migrations apply to the test DB
- [ ] `composer test` — PHPUnit passes
- [ ] `vendor/bin/behat` — BDD passes

## Checklist

- [ ] My PR title starts with `- ` (CI-enforced pattern `^(#\d+ )?- .+`).
- [ ] I added/updated tests where it makes sense.
- [ ] I updated the docs (`docs/`) / README where relevant.
- [ ] The change is focused and self-contained.
