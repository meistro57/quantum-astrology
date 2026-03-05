# Laravel Extras Adoption Plan (Incremental)

Date: 2026-03-02

Goal: adopt high-value Laravel ecosystem patterns in staged increments while current architecture remains operational.

## Scope

This plan targets the following extras and equivalents:

1. Queues + Horizon-like job visibility
2. Redis-backed caching strategy
3. FormRequest-style validation
4. API Resource-style response transformers
5. Policy/Gate-style authorization
6. Sanctum-style token auth for API/mobile
7. Scheduler + command automation
8. Notifications pipeline
9. Event/Listener domain hooks
10. Feature flags (Pennant-style)
11. Telescope-like observability (dev/staging)

## Delivery Phases

## Phase A: Foundation (2-3 weeks)

### A1. Validation Layer
- Build centralized request validation classes for API inputs.
- Remove duplicated inline validation from endpoints.
- Return normalized validation errors in one envelope shape.

Definition of done:
- Critical chart/report/admin endpoints use shared validators.
- Integration tests verify invalid input envelope consistency.

### A2. API Resource Layer
- Introduce response transformers for charts, reports, history, admin payloads.
- Enforce `{ success|error, data|message, meta }` contract.

Definition of done:
- Legacy and new endpoints share one response pattern.
- Frontend uses stable keys; no endpoint-specific parsing branches.

### A3. Policy/Gate Layer
- Move ownership/admin checks into policy services.
- Add reusable guards for chart access, report access, admin mutation actions.

Definition of done:
- Endpoint handlers call policy methods, not inline condition chains.

## Phase B: Performance + Reliability (3-4 weeks)

### B1. Queue Jobs
- Move heavy tasks to background jobs:
  - PDF report generation
  - AI summary generation
  - large batch timeline calculations
- Add job status tracking table.

Definition of done:
- API returns job id for async tasks.
- UI can poll job status and fetch artifacts on completion.

### B2. Cache Strategy
- Move/standardize cache storage with Redis support.
- Cache keys by chart/config/version with predictable invalidation.
- Add cache metrics to admin panel.

Definition of done:
- 40%+ cache hit ratio on repeated heavy workflows.
- Admin can clear scoped caches safely.

### B3. Scheduler/Commands
- Daily/weekly tasks:
  - stale cache cleanup
  - storage maintenance
  - backup rotation
  - health probes

Definition of done:
- Scheduled tasks are idempotent and logged.

## Phase C: Product Expansion (3-4 weeks)

### C1. Sanctum-style Tokens
- Add personal API tokens and scoped permissions for mobile/API clients.
- Keep existing session auth for web.

Definition of done:
- Token auth available for selected endpoints with scope checks.

### C2. Notifications
- Add report completion and low-credit alerts.
- Support email + in-app channels.

Definition of done:
- Queue-backed notifications for async reliability.

### C3. Events/Listeners
- Emit domain events:
  - chart.created
  - report.generated
  - ai.summary.generated
- Attach listeners for analytics, cache invalidation, notifications.

Definition of done:
- Side effects decoupled from request handlers.

### C4. Feature Flags
- Roll out new modules behind feature flags.
- Per-env and per-user override support.

Definition of done:
- At least two major features launched behind flags.

## Phase D: Developer Experience (parallel)

### D1. Telescope-style Observability (non-prod)
- request traces
- query diagnostics
- exception inspection

### D2. CI/QA Enhancements
- Add smoke coverage for async jobs and token-auth flows.

## First 10 Tickets (Recommended Execution Order)

1. Implement shared API response envelope utility.
2. Add validator class for report endpoints.
3. Add validator class for chart create/update endpoints.
4. Add resource transformers for chart/report/history payloads.
5. Extract chart/report/admin policies into dedicated classes.
6. Introduce job table and async report generation job.
7. Add Redis cache adapter with scoped invalidation helpers.
8. Add scheduled maintenance command registration.
9. Add API token tables and token middleware (scoped).
10. Add notification events for report completion + low OpenRouter credits.

## Risks

- Mixed architecture drift during migration.
  - Mitigation: enforce adapters/contracts at boundaries.
- Async job UX confusion.
  - Mitigation: explicit job statuses and frontend polling states.
- Cache inconsistency.
  - Mitigation: key versioning and invalidation tests.

## Non-Goals (for this cycle)

- Full Laravel rewrite in one step.
- Replacing all existing pages with SPA framework immediately.
