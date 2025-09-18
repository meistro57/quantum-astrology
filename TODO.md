# Quantum Astrology – TODO

## High Priority
- [ ] Configure CI to run `composer test` and lint PHP files on every push.
- [ ] Add regression fixtures for `TransitService::calculate()` covering aspect detection and house mapping.
- [ ] Finish SVG wheel enhancements (zodiac wedges, glyph placement, responsive scaling) and expose them in the UI.
- [ ] Implement PDF templates in `classes/Reports` using mPDF and wire them to downloadable chart reports.

## Medium Priority
- [ ] Expand REST API to support chart deletion/pagination endpoints and synchronize UI flows.
- [ ] Provide admin tooling for managing cached ephemeris data and clearing stale chart artifacts.
- [ ] Document environment variables (`.env`) required for database, swetest path, and cache configuration.

## Low Priority
- [ ] Evaluate AI interpretation roadmap and draft data requirements for multilingual support.
- [ ] Explore mobile companion app feasibility (React Native) once core web workflows stabilize.
