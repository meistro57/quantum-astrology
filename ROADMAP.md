# Roadmap

This document outlines the current development milestones for Quantum Astrology.

## Milestones (2026)

### Phase 1: Core Platform Stabilization
- Finalize and validate progressions + solar returns calculations.
- Add end-to-end tests for critical user journeys (auth, chart CRUD, report generation).
- Normalize API responses and tighten validation consistency.
- Harden newly shipped admin workflows (`/admin`) with broader coverage and audit logging.
- Introduce request validation and API resource transformers (Laravel FormRequest/Resource pattern).
- Centralize authorization via policy-style checks for chart/report/admin actions.

### Phase 2: Advanced Forecasting & Reports
- Expand report templates with richer narrative and interpretation detail.
- Improve transit timeline and forecasting UX.
- Add chart library quality-of-life features (search/filter/sort at scale).
- Add OpenRouter settings UX (per-user API key + live model discovery) in profile/admin tools.
- Add queue-backed processing for heavy report/AI jobs and admin visibility (Horizon-equivalent).
- Add Redis-backed caching strategy for chart/report/timeline acceleration.

### Phase 3: Intelligence & Expansion
- Introduce AI-assisted interpretation workflows.
- Prepare multilingual data model and localization strategy.
- Evaluate companion/mobile experience once web workflows are stable.
- Add token-based API auth for mobile clients (Sanctum pattern).
- Add scheduler + notifications + feature flags for reliable ops and safe rollout cadence.

## Legacy Notes
- [Legacy MVP notes](./roadmaps/mvp.md)
- [Legacy future ideas](./roadmaps/future.md)
- [Laravel extras adoption plan](./roadmaps/laravel-extras-adoption.md)
