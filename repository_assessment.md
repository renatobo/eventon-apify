# Repository Quality Assessment

Assessment date: 2026-07-18  
Version reviewed: 2.2.1 (`f8dcad3`)  
Scope: repository quality, architecture, performance, security, test/release posture, and documentation.

## Executive summary

EventON APIfy is a good, actively maintained WordPress plugin with unusually strong release discipline and a clear recent architectural improvement. It has centralized authorization, restrictive REST schemas, compensating rollback for multi-store writes, current WordPress 7.0.2 CI coverage, a dependency-free unit suite, and synchronized release tooling. The local suite passed 65/65 tests and every PHP file passed syntax validation.

It is not yet a high-confidence, production-at-scale codebase. The largest concrete defect is RSVP scalability: listing, delta sync, and summary paths retrieve and normalize every RSVP for an event before filtering or paginating. The architecture is documented but remains largely procedural and has several very large modules. Test breadth is good for pure normalization logic but limited at the real EventON boundary. The admin documentation also contradicts the implemented authentication policy for MCP schema routes.

Overall: **7.5/10 — solid and releasable for controlled deployments, with clear scale and verification gaps.**

| Area | Score | Assessment |
|---|---:|---|
| Repository quality | 8/10 | Clean release process, useful documentation, small dependency surface, and passing checks; missing automated standards/static-analysis gates and measurable coverage. |
| Architecture | 7/10 | Explicit composition root and sensible boundaries, but procedural coupling and 600–886 line modules make changes harder to reason about. |
| Performance | 5/10 | Event collection queries are bounded, but RSVP collection work is unbounded and pagination occurs in PHP after full hydration. No benchmark or query-budget regression gate exists. |
| Security | 8/10 | No concrete critical/high/medium plugin vulnerability found. Strong authorization and validation posture; residual confidence is limited by shallow dynamic coverage and broad admin privilege requirements. |
| Reliability/testing | 7/10 | Good unit matrix and WordPress smoke test, including rollback; no real EventON/RSVP, multisite, concurrency, or compatibility-matrix exercise. |
| Release/operations | 8/10 | Version synchronization and GitHub Release packaging are well designed; workflow actions are version-tag pinned rather than commit-SHA pinned. |

## Findings

### PERF-001 — High priority: RSVP pagination does not bound work

- Files: `includes/rest-rsvp.php:174-238`, `includes/rest-rsvp.php:270-303`
- Evidence: both attendee listing and summary call `eventon_apify_get_event_rsvp_attendees()`. That query uses `posts_per_page => -1`, then formats every post. Filtering, delta selection, sorting, totals, and `array_slice()` pagination occur afterward in PHP.
- Impact: latency and memory grow linearly with total RSVPs, even when the client requests one page of 50. Delta sync also performs an in-memory sort. Large events can exhaust request time or memory, and summary requests pay the full normalization cost even though they only need counts.
- Action: create query-level read models. Push event ID, RSVP/status, checkpoint, ordering, offset, and limit into `WP_Query`/metadata queries where semantics allow; fetch `per_page + 1` for cursor-style delta detection. Build the summary from a count-oriented query. Preserve the existing response contract and add scale fixtures before replacing the implementation.

### ARCH-001 — Medium priority: module boundaries are better than file boundaries

- Files: `includes/class-plugin.php:35-73`; notably `rest-event-meta.php` (886 lines), `rest-rsvp.php` (776), `mcp-field-definitions.php` (759), `rest-events-read.php` (658), and `rest-event-terms.php` (631).
- Evidence: the composition root loads almost every runtime module on every WordPress request. Most modules expose global procedural functions, so dependencies are implicit and namespace collision risk is managed by prefixing rather than interfaces or explicit collaborators.
- Impact: maintenance cost and regression risk rise as feature modules accumulate. Large files mix normalization, persistence, formatting, and orchestration concerns despite the high-level architecture document describing separate boundaries.
- Action: refactor incrementally around stable seams, not wholesale. Start with RSVP query/formatting, then event meta mapping. Keep existing global functions as thin compatibility adapters while moving new behavior into namespaced, focused classes. Add tests before each extraction.

### TEST-001 — Medium priority: integration confidence stops before the proprietary boundary

- Files: `.github/workflows/php-tests.yml:10-72`, `tests/integration/wp-rest-smoke.php:14-99`
- Evidence: CI covers PHP 8.0/8.3/8.5 unit tests and installs WordPress 7.0.2, but the integration test registers substitute post types/taxonomies rather than loading EventON or its RSVP addon. It checks authorization, route shape, required schema fields, and a forced rollback path, not end-to-end CRUD/RSVP behavior.
- Impact: changes can pass CI while breaking against actual EventON metadata conventions, helpers, addon versions, or lifecycle hooks. Current risk is concentrated in the largest and most coupled modules.
- Action: add an opt-in licensed/private integration job or reproducible local harness with supported EventON fixtures. Cover create/read/update/delete round trips, term metadata, RSVP list/summary/delta, redaction, and rollback. If CI cannot legally install EventON, run the suite in a private release gate and document that limitation.

### DOC-001 — Low effort, user-visible: MCP authentication copy is contradictory

- Files: `includes/admin.php:310-312`, `includes/admin.php:348-352`, `includes/rest-routes.php:7-28`
- Evidence: the settings UI says schema routes “remain public” and are “safe to expose,” while both routes use `eventon_apify_admin_only`. The architecture and readme describe administrator authentication.
- Impact: operators and client authors receive incorrect setup guidance. This can cause failed integrations and weakens confidence in security documentation.
- Action: update the settings copy to say MCP schema routes require administrator credentials/Application Passwords, then update `ui.md` in the same change per repository policy. Add a text/contract regression assertion if practical.

### TEST-002 — Medium priority: no automated code-standard, static-analysis, or coverage gate

- Files: `.github/workflows/php-tests.yml:10-33`, `tests/php/run.php:1-14`
- Evidence: CI lints syntax and runs a custom assertion harness, but has no PHPCS/WordPress Coding Standards, PHPStan/Psalm, coverage threshold, mutation testing, or duplicate/dead-code check.
- Impact: the suite proves selected behavior but does not quantify untested paths or catch signature/type mistakes and WordPress-standard violations consistently. This matters more as procedural modules grow.
- Action: add PHPCS with WordPress standards first, configured to reflect the existing PHP 8.0 floor. Add PHPStan at a permissive baseline and ratchet it upward. Generate coverage for trend visibility before imposing a threshold; set thresholds only after integration-critical paths are covered.

### PERF-002 — Medium priority: no performance budget or realistic scale test

- Files: test and CI configuration generally; `includes/class-plugin.php:35-73`
- Evidence: no test records query counts, peak memory, response time, or bootstrap cost. Roughly 30 runtime PHP modules are loaded eagerly; this may be acceptable, but it is unmeasured.
- Impact: performance regressions cannot be distinguished from intuition. Optimization work risks targeting file loading while missing database/hydration costs.
- Action: benchmark representative event-list, single-event, write, RSVP summary, and 100-item/delta-list requests at 100/1,000/10,000 RSVP scale. Record database query count, wall time, and peak memory. Optimize only against measured budgets.

### SEC-001 — Low residual risk: strong static controls, incomplete adversarial verification

- Files: `includes/rest-routes.php:7-189`, `includes/rest-access-control.php:10-11`, existing `wp_plugin_security_assessment.md`
- Evidence: every custom route has the centralized `manage_options` permission callback; schemas bound collection sizes and validate structured inputs; there is no direct raw SQL, upload, arbitrary filesystem write, dynamic include, or shell execution surface in plugin runtime code. Admin output is contextually escaped. The current dedicated security report found no unresolved vulnerabilities.
- Residual gaps: no authenticated dynamic scan, low-privilege user matrix, REST batch regression suite, multisite verification, concurrent-write stress test, or real EventON/RSVP security test.
- Action: retain the current low-risk rating, but add negative integration tests for anonymous/subscriber/editor access to every custom and `wp/v2` compatibility route, including `/wp/v2/batch` and search/discovery paths. Test that sensitive virtual, organizer, venue, and RSVP fields remain redacted where promised.

### REL-001 — Medium priority: compensating rollback is thoughtful but not concurrency-safe

- Files: `includes/event-write-coordinator.php:20-42`, `includes/event-write-coordinator.php:50-64`, `includes/event-write-coordinator.php:89-134`, `docs/architecture.md:33-40`
- Evidence: writes snapshot the whole post, all metadata, all taxonomy assignments, and the shared `evo_tax_meta` option, then restore the snapshot after a later failure. The architecture correctly documents that this is not database isolation.
- Impact: a concurrent legitimate write between snapshot and rollback can be overwritten, particularly because the shared taxonomy option is restored wholesale. This is a known design constraint, not a hidden defect.
- Action: document an idempotency/concurrency contract for clients. Consider optimistic concurrency using `modified_gmt`/ETag preconditions for event updates and narrower compare-and-swap handling for shared option updates. Add a concurrency test before changing persistence semantics.

### SUPPLY-001 — Low priority: GitHub Actions are not immutable-pinned

- Files: `.github/workflows/php-tests.yml:18-23`, `.github/workflows/package-plugin.yml:14-16`, `.github/workflows/package-plugin.yml:64-70`
- Evidence: actions use mutable major/version tags such as `actions/checkout@v6`, `shivammathur/setup-php@v2`, and `softprops/action-gh-release@v3`.
- Impact: upstream tag compromise or retargeting could affect builds and, for the release workflow, a token with `contents: write`.
- Action: pin third-party actions to reviewed commit SHAs and use Dependabot to update GitHub Actions references. Keep job permissions minimal; the current release job appropriately scopes `contents: write` to the job that needs it.

## Strengths worth preserving

- A small bootstrap delegates to an explicit composition root, with admin code conditionally loaded.
- Authorization is centralized and consistently applied to custom REST routes.
- Inputs use REST schemas, validation, sanitization, allowlists, and bounded page sizes.
- No runtime third-party Composer/npm dependency chain is shipped.
- The write coordinator explicitly handles partial-failure recovery and has a real WordPress smoke assertion.
- The GitHub-first release flow keeps plugin header, constant, stable tag, OpenAPI version, tag, notes, and packaged asset aligned.
- Distribution copy correctly identifies GitHub Releases and Git Updater as the live channel in the readme and primary settings copy.
- `.env` files and generated zip artifacts are ignored; no local secret file or zip is tracked.
- The architecture document states real tradeoffs instead of claiming impossible transaction guarantees.

## Recommended action plan

### Phase 0 — Immediate, less than one day

1. Fix MCP schema authentication copy in `includes/admin.php` and `ui.md`.
2. Add regression assertions that anonymous and non-admin users cannot access schema, event, RSVP, or enabled `wp/v2` compatibility surfaces.
3. Open a tracked performance issue with acceptance data: RSVP counts and target response/memory budgets.

### Phase 1 — Performance and evidence, 2–5 days

1. Build repeatable RSVP fixtures at 100, 1,000, and 10,000 records.
2. Capture baseline query count, wall time, and peak memory for summary, page 1/page N, filtered listing, and delta sync.
3. Replace full hydration with query-level paging/checkpoint selection while preserving API responses.
4. Split summary into a count-focused path.
5. Add performance regression thresholds based on the measured baseline and target environment.

### Phase 2 — Test and security confidence, 3–7 days

1. Add real EventON/RSVP end-to-end tests in a private or local release gate.
2. Exercise CRUD, taxonomy metadata, RSVP payloads, redaction, application-password authentication, REST batch, and rollback.
3. Add PHPCS/WordPress Coding Standards and an initial PHPStan baseline.
4. Produce coverage reports and prioritize missing write/rollback/access-control paths; do not chase a vanity percentage.
5. Pin GitHub Actions to commit SHAs.

### Phase 3 — Incremental architecture hardening, ongoing

1. Extract an RSVP repository/query service and formatter behind existing procedural callbacks.
2. Split event meta definitions, validation, and persistence into focused collaborators.
3. Introduce explicit data-transfer shapes at transport/use-case boundaries where they reduce ambiguous arrays.
4. Add optimistic concurrency semantics before promising safe simultaneous updates.
5. Re-measure bootstrap cost before considering conditional/lazy module loading.

## Verification performed

- `php tests/php/run.php`: **65 passed, 0 failed**.
- PHP syntax check across all plugin and test PHP files: **passed**.
- `bash -n` for `build.sh`, `release.sh`, and `scripts/test-production.sh`: **passed**.
- Git status was clean before this report was added.
- Targeted static review covered REST authorization, request schemas, admin escaping, raw input, SQL/filesystem/process/deserialization patterns, release workflows, packaging, and ignored secret/artifact files.

## Limits of this assessment

- The production test was not run because it requires configured external credentials and a live site.
- The GitHub-hosted WordPress/MySQL integration job was inspected but not reproduced locally.
- No licensed EventON or RSVP addon instance, production dataset, load test, penetration test, multisite, or concurrent client was available.
- Therefore “no demonstrated vulnerability” should not be read as proof of absence, and performance severity is based on clear algorithm/query behavior rather than measured production timings.
