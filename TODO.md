# Quantum Astrology – TODO

## High Priority
- [x] Configure CI to run `composer test` and lint PHP files on every push.
- [x] Add regression fixtures for `TransitService::calculate()` covering aspect detection and house mapping.
- [x] Finish SVG wheel enhancements (zodiac wedges, glyph placement, responsive scaling) and expose them in the UI.
- [x] Implement PDF templates in `classes/Reports` using mPDF and wire them to downloadable chart reports.
- [ ] Add end-to-end regression tests for chart create/list/delete/export flows.
- [x] Add validation tests for profile password change and location auto-fill paths.
- [x] Add integration tests for AI summary report generation and markdown download flows.
- [x] Add `/admin` system panel with operational controls and guard it behind admin-only access.
- [x] Add admin user management actions (create user, reset password, grant/revoke admin).
- [x] Make login sessions persistent for beta users with configurable cookie/session lifetimes.

## Medium Priority
- [x] Expand REST API to support chart deletion/pagination endpoints and synchronize UI flows.
- [x] Provide admin tooling for managing cached ephemeris data and clearing stale chart artifacts.
- [x] Document environment variables (`.env`) required for database, swetest path, and cache configuration.
- [ ] Add chart library search/filter/sort for large datasets.
- [ ] Standardize API response envelopes across legacy and newer endpoints.
- [ ] Cache AI summary markdown outputs by chart/config to reduce repeated generation.

## Low Priority
- [ ] Evaluate AI interpretation roadmap and draft data requirements for multilingual support.
- [ ] Explore mobile companion app feasibility (React Native) once core web workflows stabilize.
