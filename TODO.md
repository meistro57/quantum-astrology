# Quantum Astrology – TODO

## High Priority
- [x] Configure CI to run `composer test` and lint PHP files on every push.
- [x] Add regression fixtures for `TransitService::calculate()` covering aspect detection and house mapping.
- [x] Finish SVG wheel enhancements (zodiac wedges, glyph placement, responsive scaling) and expose them in the UI.
- [x] Implement PDF templates in `classes/Reports` using mPDF and wire them to downloadable chart reports.
- [x] Add end-to-end regression tests for chart create/list/delete/export flows.
- [x] Add validation tests for profile password change and location auto-fill paths.
- [x] Add integration tests for AI summary report generation and markdown download flows.
- [x] Add integration test for `/api/reports/generate.php` response envelope contract (no duplicated top-level report fields).
- [x] Add integration test for admin Redis dashboard action payload/auth/CSRF flow.
- [x] Add `/admin` system panel with operational controls and guard it behind admin-only access.
- [x] Add admin user management actions (create user, reset password, grant/revoke admin).
- [x] Make login sessions persistent for beta users with configurable cookie/session lifetimes.

## Medium Priority
- [x] Expand REST API to support chart deletion/pagination endpoints and synchronize UI flows.
- [x] Provide admin tooling for managing cached ephemeris data and clearing stale chart artifacts.
- [x] Document environment variables (`.env`) required for database, swetest path, and cache configuration.
- [ ] Add chart library search/filter/sort for large datasets.
- [ ] Standardize API response envelopes across legacy and newer endpoints.
- [x] Cache AI summary markdown outputs by chart/config to reduce repeated generation.
- [ ] Add Laravel-style request validation layer (FormRequest-equivalent) for API endpoints.
- [ ] Add Laravel-style API resource/transformer layer for response envelope consistency.
- [ ] Add policy/gate-style authorization service to centralize ownership/admin checks.
- [ ] Add event/listener hooks for chart/report lifecycle side effects (analytics/cache invalidation).

## Low Priority
- [ ] Evaluate AI interpretation roadmap and draft data requirements for multilingual support.
- [ ] Explore mobile companion app feasibility (React Native) once core web workflows stabilize.
- [ ] Add Sanctum-compatible token auth path for future mobile/API clients.
- [ ] Add scheduler/command registry for backup/cache/health automation.
- [ ] Add notification pipeline for report completion and low-credit alerts.
- [ ] Evaluate feature-flag framework for controlled rollouts (Pennant-style).
