# Best-in-Class 90-Day Roadmap

Goal: make Quantum Astrology the highest-trust and most actionable astrology platform for both individual users and professional practitioners.

Date baseline: 2026-03-02

## Success Metrics (By Day 90)

- Chart-to-insight time under 5 seconds for cached flows.
- Forecasting engagement: 40%+ of active users view timeline features weekly.
- Report completion rate: 30%+ lift for multi-report generation.
- Practitioner retention: 20%+ lift from CRM/session tooling.
- Support tickets tied to chart inaccuracies reduced by 50%.

## Prioritization Matrix

Legend: Impact (H/M/L), Effort (H/M/L), Risk (H/M/L)

1. Accuracy & Transparency Layer
- Impact: H
- Effort: M
- Risk: L

2. Relationship Intelligence Suite (Synastry + Composite hardening)
- Impact: H
- Effort: H
- Risk: M

3. Unified Forecasting Timeline
- Impact: H
- Effort: H
- Risk: M

4. AI Copilot with Evidence Mode
- Impact: H
- Effort: M
- Risk: M

5. Practitioner Report Studio + CRM Foundations
- Impact: H
- Effort: H
- Risk: M

## Phase 1 (Days 1-30): Trust + Core Functionality

### 1) Accuracy & Reproducibility
- Add a “Calculation Provenance” block to chart and report payloads:
  - ephemeris source
  - house system
  - orb policy
  - calculation timestamp
  - engine version
- Add deterministic “replay” endpoint:
  - input snapshot -> same output hash
- Add advanced validation fixtures for:
  - timezone boundaries
  - DST transitions
  - high-latitude births

Definition of done:
- New provenance fields shown in UI and API.
- Replay hash is stable across repeated runs.
- New integration tests pass in CI.

### 2) Synastry Module Hardening
- Complete missing UX/API behaviors for:
  - chart-pair selection validation
  - stable compatibility scoring and labels
  - error handling for sparse/malformed chart data
- Add export path for synastry/composite summary.

Definition of done:
- End-to-end relationship flow works without stubs.
- Report export available for synastry result.
- Regression tests cover synastry/composite endpoints.

### 3) OpenRouter Observability (already started)
- Keep credit status panel.
- Add low-credit warning threshold setting and alert state.

Definition of done:
- Admin sees credit status + warnings without manual API probing.

## Phase 2 (Days 31-60): Differentiated User Value

### 4) Unified Forecasting Command Center
- Build one timeline that overlays:
  - transits
  - progressions
  - solar returns
  - key relationship windows (if paired charts exist)
- Add “opportunity/risk” weights by life domain:
  - career
  - relationships
  - health/well-being
  - finance

Definition of done:
- User can filter timeline by domain and event type.
- Timeline performance acceptable with pagination/windowing.

### 5) AI Copilot with Evidence Mode
- Add “Evidence Mode” to AI responses:
  - every guidance section includes source factors
  - links to aspects/houses used
- Add tone modes:
  - concise
  - coach
  - technical

Definition of done:
- AI output can be audited by power users.
- Hallucination-prone generic language reduced.

### 6) Composite “Action Plans”
- Convert chart insights into weekly action cards.
- Add calendar export (ICS) for selected timing windows.

Definition of done:
- One-click export to calendar works.
- Users can save action plans to profile context.

## Phase 3 (Days 61-90): Professional Moat

### 7) Report Studio
- Build configurable report composer:
  - include/exclude sections
  - custom branding
  - multi-report packets (natal + transit + synastry)
- Add reusable templates for practitioners.

Definition of done:
- Admin/practitioner can generate custom branded packet PDFs.

### 8) Practitioner CRM Foundations
- Add client records:
  - session notes
  - follow-up tasks
  - report history + status
- Add secure client portal share links with expiration.

Definition of done:
- Practitioner can run core workflow end-to-end in-app.

### 9) Reliability & Scale
- Expand end-to-end browser tests for:
  - auth
  - chart CRUD
  - reports
  - relationship workflows
- Add background job queue for heavy report generation.

Definition of done:
- Major user journeys guarded by regression suite.
- Long-running generation no longer blocks request lifecycle.

## Suggested Ticket Breakdown (Execution Order)

1. Provenance metadata model + API
2. Synastry endpoint parity + validation
3. Synastry/composite report export
4. Forecast timeline data model
5. Timeline UI + filters
6. AI evidence mode response schema
7. Report composer backend
8. Report composer UI
9. CRM client entities + session notes
10. E2E suite expansion

## Risk Notes

- AI cost growth: mitigate with strict caching and per-user/provider budgets.
- Interpretation trust: mitigate with evidence mode and provenance visibility.
- Feature sprawl: enforce phase gates and completion criteria before advancing.

## Immediate Next 2 Weeks (Recommended)

1. Finish synastry module hardening and ship tests.
2. Add provenance metadata to all report outputs.
3. Start timeline backend schema and event aggregation endpoints.
