# Beachrex AI Agent - Task List

**Last Updated**: 2025-12-23
**Current Phase**: Implementation (IN PROGRESS) - Task #1: AI Core Implementation (Step 6/6 COMPLETE)
**Priority**: P0 (Critical) → P1 (Important) → P2 (Nice-to-have)

---

## How to Use This File

**Task States**:
- `[ ]` NOT_STARTED - Task not yet begun
- `[/]` IN_PROGRESS - Task currently being worked on
- `[x]` DONE - Task completed
- `[-]` BLOCKED - Task blocked by dependency or decision

**Priority Levels**:
- **P0**: Critical path, blocks MVP launch, revenue-generating
- **P1**: Important, needed for MVP, but not blocking
- **P2**: Nice-to-have, can wait for Phase 2

**Update Rules**:
- Mark tasks as `[x]` DONE when completed
- Mark tasks as `[/]` IN_PROGRESS when starting work
- Mark tasks as `[-]` BLOCKED if blocked (add blocker reason)
- Add new tasks as needed (keep prioritized)

---

## P0: Critical Path (MVP Launch Blockers)

### [x] AI Governance Documentation Bootstrap
**Priority**: P0  
**Status**: DONE  
**Completed**: 2025-12-22  
**Effort**: 1 day  

**Deliverables**:
- [x] `AI_INDEX.md` - Canonical entry point
- [x] `ai-monetization.md` - Pricing strategy
- [x] `ai-business-rules.md` - Hard constraints
- [x] `agent-execution-model.md` - Execution model
- [x] `ai-capability-alignment.md` - Capability mapping
- [x] `STATE.md` - Current state tracking
- [x] `DECISIONS.md` - ADR log
- [x] `TASKS.md` - This file
- [x] `PROMPTS.md` - System prompts

**Impact**: Any new AI agent can now start by reading `AI_INDEX.md`

---

### [x] 0. AI Architecture & API Contract Definition
**Priority**: P0 (CRITICAL)
**Status**: DONE
**Completed**: 2025-12-22
**Effort**: 1 day

**Description**: Define minimal AI architecture and API contracts before implementation.

**Deliverables**:
- [x] Answer blocking questions (Q1-Q4)
- [x] Create `mvp-integration.md` (minimal AI architecture)
- [x] Create `api-contracts.md` (AI interaction API)
- [x] Create `prompt-structure.md` (canonical prompt structure)
- [x] Update `DECISIONS.md` (4 new architecture decisions)
- [x] Update `STATE.md` (mark architecture phase complete)
- [x] Update `TASKS.md` (this file)

**Architecture Decisions**:
1. AI service in existing Laravel API (not microservice) for MVP
2. Provider-agnostic LLM abstraction layer (OpenAI initial implementation)
3. Hybrid session management (anonymous for free, auth for paid)
4. Synchronous request/response (not async) for MVP

**Impact**: Architecture is defined, API contracts are stable, ready for implementation

---

### [x] 1. AI Core Implementation (Foundation)
**Priority**: P0 (CRITICAL)
**Status**: DONE (Step 6/6 COMPLETE)
**Completed**: 2025-12-23
**Estimated Effort**: 1 week
**Revenue Impact**: Enables all AI features
**Blockers**: None

**Description**: Implement core AI infrastructure (controllers, services, middleware) based on architecture defined in `mvp-integration.md`.

**Implementation Steps** (in order):
- [x] **Step 1: Database Layer** (COMPLETE 2025-12-22)
  - Created 3 migrations: ai_sessions, ai_messages, ai_usage
  - Created 3 models: AiSession, AiMessage, AiUsage
  - Ran migrations successfully
  - Verified tables and relationships
- [x] **Step 2: Plan Enforcement + Rate Limiting** (COMPLETE 2025-12-22)
  - Created config/ai.php with tier configuration (free, basic, premium, enterprise)
  - Created 4 middleware: OptionalAuthMiddleware, CircuitBreakerMiddleware, RateLimitMiddleware, TokenBudgetMiddleware
  - Created PlanEnforcementService for feature gating (402 responses)
  - Created TestController for testing (temporary, will be replaced in Step 5)
  - Added AI routes (/api/v1/ai/test) with middleware stack
  - Tested 402 Payment Required responses (multi-day itineraries, accessibility reports, etc.)
  - Tested 429 Rate Limit responses (100/hour anonymous, 500/hour authenticated)
  - Tested rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining)
  - Updated .env.example with AI configuration variables
- [x] **Step 3: LLM Provider Abstraction** (COMPLETE 2025-12-23)
  - Created LLMProviderInterface (provider-agnostic contract)
  - Created OpenAIProvider (concrete implementation using openai-php/laravel)
  - Created LLMService (facade for provider selection)
  - Created DTOs (ChatMessage, ChatCompletionRequest, ChatCompletionResponse)
  - Created LLM exceptions (LLMException, LLMRateLimitException, LLMQuotaExceededException, LLMTimeoutException)
  - Implemented token counting (rough estimation: 1 token ≈ 4 characters)
  - Implemented cost calculation (OpenAI pricing per 1M tokens)
  - Updated TestController to use real LLM integration
  - Created 6 automated tests (100% pass rate)
  - Tested real LLM API calls (successful responses with token/cost tracking)
- [x] **Step 4: Context Assembly** (COMPLETE 2025-12-23)
  - Created ContextBuilderService with strict capping and sanitization
  - Implemented tier-based beach limits (free: 5, basic: 10, premium: 20, enterprise: 50)
  - Implemented tier-based token limits (free: 1K, basic: 2K, premium: 4K, enterprise: 8K)
  - Implemented beach data sanitization (HTML stripping, description truncation to 200 chars)
  - Implemented weather data inclusion (only if tier allows)
  - Implemented deterministic ordering (same input => same context)
  - Implemented context truncation strategy (remove beaches from end until fits)
  - Created formatContextAsText() method for prompt injection
  - Created 10 automated tests (100% pass rate)
  - Updated docs/ai/architecture/prompt-structure.md with capping policies
- [x] **Step 5: AI Endpoint Controller** (DONE 2025-12-23)
  - Created ChatRequest FormRequest with validation
  - Created ChatController with full request/response cycle
  - Implemented POST /api/v1/ai/chat endpoint with middleware stack
  - Implemented intent detection (6 intents)
  - Implemented output shaping (JSON validation, safe fallback)
  - Implemented error handling (400, 402, 413, 429, 503, 500)
  - Implemented observability (request_id, session_id, logging)
  - Created 9 automated tests (100% pass rate, 65 assertions)
  - Fixed database schema (ip_address column length)
- [x] **Step 6: Logging & Observability** (DONE 2025-12-23)
  - Created LoggingService with GDPR-compliant structured logging
  - Created AiRequestCompleted and AiRequestFailed events
  - Created TrackAiMetrics listener for real-time metrics (cache-based, 7-day TTL)
  - Registered event listeners in AppServiceProvider
  - Created data-retention-policy.md (90/30/365 day retention, GDPR compliance)
  - Created operational-controls.md (kill switches, circuit breakers, emergency procedures)
  - Integrated LoggingService into ChatController
  - All tests pass (9 tests, 65 assertions, 100% pass rate)
  - Metrics tracked: requests, success/error counts, latency, cost, tier distribution

**Original Subtasks** (mapped to steps above):
- [ ] 1.1. Create AI configuration file (`config/ai.php`) - Step 2
- [ ] 1.2. Add AI environment variables to `.env.example` - Step 2
- [ ] 1.3. Create `AI/ChatController` (POST /api/v1/ai/chat) - Step 5
- [ ] 1.4. Create `AI/SessionController` (GET/DELETE /api/v1/ai/session) - Step 5
- [ ] 1.5. Create `AI/LLMService` interface - Step 3
- [ ] 1.6. Create `AI/Providers/OpenAIProvider` implementation - Step 3
- [ ] 1.7. Create `AI/ContextAssemblyService` - Step 4
- [ ] 1.8. Create `AI/PromptService` - Step 4
- [ ] 1.9. Create `AI/CostTrackingService` - Step 6
- [ ] 1.10. Create `AI/RateLimitMiddleware` - Step 2
- [ ] 1.11. Create `AI/TokenBudgetMiddleware` - Step 2
- [ ] 1.12. Create `AI/CircuitBreakerMiddleware` - Step 2
- [ ] 1.13. Add AI routes to `routes/api.php` - Step 5
- [ ] 1.14. Create system prompts (`storage/prompts/system/en.txt`, `hr.txt`) - Step 4
- [ ] 1.15. Write unit tests (services) - Step 6
- [ ] 1.16. Write integration tests (controllers) - Step 6

**Dependencies**: None (architecture is defined, Step 1 complete)

**Acceptance Criteria**:
- User can send message to `POST /api/v1/ai/chat` and get AI response
- Rate limiting enforced (100/hour for free tier)
- Token budget enforced (4K tokens for free tier)
- Circuit breakers active (disable at $500/day)
- System prompts enforce business rules (multi-day itineraries NEVER free)
- All tests passing

---

### [ ] 2. Trip Itinerary Generation (PRIMARY REVENUE)
**Priority**: P0 (CRITICAL)
**Status**: NOT_STARTED
**Estimated Effort**: 2-3 weeks
**Revenue Impact**: $1.08M/year
**Blockers**: Task #1 (AI Core Implementation)

**Description**: Generate optimized multi-day beach itineraries (3-7-14 days) with route optimization, weather-aware scheduling, and PDF delivery.

**Subtasks**:
- [ ] 1.1. Create `ItineraryController` (generate, show, download endpoints)
- [ ] 1.2. Create `ItineraryService` (optimization algorithm)
- [ ] 1.3. Create `itineraries` database table and migration
- [ ] 1.4. Implement route optimization (PostGIS distance calculations)
- [ ] 1.5. Integrate multi-day weather forecast (OpenWeatherMap API)
- [ ] 1.6. Create PDF generation (Laravel DomPDF)
- [ ] 1.7. Add watermarking (user email + date)
- [ ] 1.8. Add download limit enforcement (max 5 downloads)
- [ ] 1.9. Add payment validation (check purchase before generation)
- [ ] 1.10. Write unit tests (ItineraryService)
- [ ] 1.11. Write integration tests (ItineraryController)

**Dependencies**: 
- Payment integration (Task #2)
- PDF generation (Task #5)

**Acceptance Criteria**:
- User can request 3-day, 7-day, or 14-day itinerary
- System generates optimized route (minimize driving, maximize beach time)
- System includes weather forecast for each day
- System generates PDF with watermark
- System enforces payment ($19/$39/$59)
- System limits downloads to 5 per purchase

---

### [ ] 3. Payment Integration (Stripe)
**Priority**: P0 (CRITICAL)
**Status**: NOT_STARTED
**Estimated Effort**: 1 week
**Revenue Impact**: Enables all B2C revenue ($1.8M/year)
**Blockers**: Task #1 (AI Core Implementation)

**Description**: Integrate Stripe for one-time payments (itineraries, reports, guides).

**Subtasks**:
- [ ] 2.1. Install Stripe SDK (`composer require stripe/stripe-php`)
- [ ] 2.2. Create `PaymentController` (create checkout session, handle webhook)
- [ ] 2.3. Create `purchases` table and migration
- [ ] 2.4. Create `Purchase` model with relationships
- [ ] 2.5. Implement checkout session creation (Stripe Checkout)
- [ ] 2.6. Implement webhook handler (payment.succeeded, payment.failed)
- [ ] 2.7. Add payment validation middleware (`hasPurchased()`)
- [ ] 2.8. Add refund handling (30-day money-back guarantee)
- [ ] 2.9. Write unit tests (PaymentController)
- [ ] 2.10. Write integration tests (mock Stripe API)

**Dependencies**: None

**Acceptance Criteria**:
- User can purchase itinerary ($19/$39/$59)
- User can purchase accessibility report ($15)
- User can purchase activity guide ($25)
- System handles payment success (create purchase record)
- System handles payment failure (show error)
- System validates payment before delivering product
- System supports refunds (within 30 days)

---

### [ ] 4. Beach Search Analytics (B2B)
**Priority**: P0 (CRITICAL)
**Status**: NOT_STARTED
**Estimated Effort**: 1-2 weeks
**Revenue Impact**: $100K/year
**Blockers**: Task #1 (AI Core Implementation)

**Description**: Track AI search queries and provide analytics dashboard for B2B customers (DMOs, municipalities).

**Subtasks**:
- [ ] 3.1. Create `ai_usage` table and migration
- [ ] 3.2. Create `beach_analytics` table and migration
- [ ] 3.3. Create `AnalyticsController` (dashboard, export)
- [ ] 3.4. Implement query logging (log all AI searches to `ai_usage`)
- [ ] 3.5. Create aggregation job (daily cron, aggregate to `beach_analytics`)
- [ ] 3.6. Create analytics dashboard (charts, tables, filters)
- [ ] 3.7. Add CSV export
- [ ] 3.8. Add PDF export
- [ ] 3.9. Add B2B tier validation (only Analytics/Promotion/White-Label/Enterprise)
- [ ] 3.10. Write unit tests (AnalyticsController)
- [ ] 3.11. Write integration tests (aggregation job)

**Dependencies**: 
- AI agent logging (Task #6)
- B2B tenant system (Task #7)

**Acceptance Criteria**:
- System logs all AI search queries (beach names, filters, locations)
- System aggregates data daily (beach_id, search_count, click_count)
- B2B customers can view analytics dashboard
- B2B customers can export to CSV/PDF
- System enforces B2B tier validation ($5K/year minimum)

---

### [ ] 5. Promoted Beach Listings (B2B)
**Priority**: P0 (CRITICAL)
**Status**: NOT_STARTED
**Estimated Effort**: 1 week
**Revenue Impact**: $150K/year
**Blockers**: Task #1 (AI Core Implementation), Task #4 (Beach Search Analytics)

**Description**: Allow B2B customers to promote specific beaches in AI recommendations.

**Subtasks**:
- [ ] 4.1. Create `promoted_beaches` table and migration
- [ ] 4.2. Create `PromotionController` (CRUD for promoted beaches)
- [ ] 4.3. Update AI recommendation algorithm (weighted scoring with boost)
- [ ] 4.4. Add "Promoted" label in AI responses
- [ ] 4.5. Track impressions/clicks in `beach_analytics`
- [ ] 4.6. Add B2B tier validation (only Promotion/White-Label/Enterprise)
- [ ] 4.7. Add admin approval workflow (prevent spam)
- [ ] 4.8. Write unit tests (PromotionController)
- [ ] 4.9. Write integration tests (AI recommendation algorithm)

**Dependencies**:
- B2B tenant system (Task #8)
- Beach search analytics (Task #4)

**Acceptance Criteria**:
- B2B customers can select beaches to promote (up to 200)
- Promoted beaches appear in top 3 AI recommendations
- Promoted beaches are clearly labeled as "Promoted"
- System tracks impressions and clicks
- System enforces B2B tier validation ($15K/year minimum)

---

## P1: Important (Needed for MVP, Not Blocking)

### [ ] 6. PDF Generation (Itineraries, Reports)
**Priority**: P1
**Status**: NOT_STARTED
**Estimated Effort**: 3-5 days
**Blockers**: Task #2 (Trip Itinerary Generation)

**Description**: Generate PDF documents for itineraries, accessibility reports, and activity guides.

**Subtasks**:
- [ ] 5.1. Install PDF library (`composer require barryvdh/laravel-dompdf`)
- [ ] 5.2. Create PDF template for itineraries (Blade view)
- [ ] 5.3. Create PDF template for accessibility reports (Blade view)
- [ ] 5.4. Create PDF template for activity guides (Blade view)
- [ ] 5.5. Add watermarking (user email + date)
- [ ] 5.6. Add download tracking (increment download_count)
- [ ] 5.7. Add download limit enforcement (max 5 for itineraries)
- [ ] 5.8. Write unit tests (PDF generation)

**Dependencies**: None

**Acceptance Criteria**:
- System generates PDF for itineraries (with watermark)
- System generates PDF for accessibility reports (with watermark)
- System generates PDF for activity guides (with watermark)
- System tracks download count
- System enforces download limits

---

### [ ] 7. AI Agent Logging (Query Tracking)
**Priority**: P1
**Status**: NOT_STARTED
**Estimated Effort**: 2-3 days
**Blockers**: Task #1 (AI Core Implementation)

**Description**: Log all AI queries for analytics, cost tracking, and debugging.

**Subtasks**:
- [ ] 7.1. Create `ai_usage` table and migration (if not created in Task #4)
- [ ] 7.2. Log all AI queries (user_id, session_id, query, intent, beaches_returned)
- [ ] 7.3. Log LLM API calls (tokens_used, cost, latency)
- [ ] 7.4. Create cost tracking dashboard (admin only)
- [ ] 6.5. Add cost alerts (email CTO if daily cost > $500)
- [ ] 6.6. Write unit tests (logging service)

**Dependencies**: None

**Acceptance Criteria**:
- System logs all AI queries to `ai_usage` table
- System logs LLM API calls (tokens, cost, latency)
- Admin can view cost tracking dashboard
- System sends alerts if daily cost exceeds $500

---

### [ ] 8. B2B Tenant System (Multi-Tenancy)
**Priority**: P1
**Status**: NOT_STARTED
**Estimated Effort**: 1 week
**Blockers**: None

**Description**: Implement multi-tenancy for B2B customers (DMOs, hotels, municipalities).

**Subtasks**:
- [ ] 8.1. Create `tenants` table and migration
- [ ] 8.2. Create `Tenant` model with relationships
- [ ] 8.3. Add `tenant_id` to `users` table (B2B users belong to tenant)
- [ ] 8.4. Add tenant scoping middleware (all queries scoped by tenant_id)
- [ ] 8.5. Create tenant admin panel (Filament resource)
- [ ] 8.6. Add tenant tier validation (Analytics, Promotion, White-Label, Enterprise)
- [ ] 8.7. Write unit tests (tenant scoping)
- [ ] 8.8. Write integration tests (multi-tenant isolation)

**Dependencies**: None

**Acceptance Criteria**:
- System supports multiple B2B tenants
- Each tenant has isolated data (cannot access other tenants' data)
- System enforces tier validation (Analytics, Promotion, White-Label, Enterprise)
- Admin can manage tenants via Filament panel

---

### [ ] 9. AI Service Migration to Microservice (Phase 2)
**Priority**: P1 (Phase 2 only, NOT MVP)
**Status**: NOT_STARTED
**Estimated Effort**: 2-3 weeks
**Blockers**: Task #1 (AI Core Implementation), MVP launch, usage/cost signals

**Description**: Migrate AI service from existing Laravel API to dedicated microservice (separate Laravel app). This is Phase 2 work, NOT needed for MVP.

**Subtasks**:
- [ ] 9.1. Create new Laravel app (`laravel new ai-service`)
- [ ] 9.2. Configure shared database (PostgreSQL)
- [ ] 9.3. Configure shared Redis (sessions, cache, rate limiting)
- [ ] 9.4. Configure shared Meilisearch (search indexes)
- [ ] 9.5. Extract AI controllers from main API to new app
- [ ] 9.6. Extract AI services from main API to new app
- [ ] 9.7. Extract AI middleware from main API to new app
- [ ] 9.8. Update frontend to call new AI service URL
- [ ] 9.9. Set up API gateway routing (optional)
- [ ] 9.10. Deploy AI service to separate server/container
- [ ] 9.11. Write unit tests (migration)
- [ ] 9.12. Write integration tests (end-to-end)

**Dependencies**:
- MVP launch (validate AI value first)
- Usage/cost signals (determine if microservice is needed)

**Acceptance Criteria**:
- AI service runs as separate app (separate from main API)
- AI service shares PostgreSQL, Redis, Meilisearch with main API
- Frontend sees no difference (API contracts unchanged)
- AI service can scale independently
- Migration is seamless (no downtime)

---

## P2: Nice-to-Have (Phase 2)

### [ ] 10. Verified Accessibility Reports
**Priority**: P2 (Phase 2)
**Status**: NOT_STARTED
**Estimated Effort**: 4-6 weeks
**Revenue Impact**: $450K/year
**Blockers**: Requires human verification process

**Description**: Human-verified accessibility reports with photos and guarantees.

**Subtasks**:
- [ ] 10.1. Create `verified_accessibility_reports` table
- [ ] 10.2. Create verification workflow (photographer network, QA)
- [ ] 10.3. Create `AccessibilityReportController` (purchase, download)
- [ ] 10.4. Create PDF template for accessibility reports
- [ ] 10.5. Add money-back guarantee logic
- [ ] 10.6. Write tests

**Dependencies**: 
- Human verification process (not yet defined)
- Photographer network (not yet established)

---

### [ ] 11. Activity-Specific Guides
**Priority**: P2 (Phase 2)
**Status**: NOT_STARTED
**Estimated Effort**: 6-8 weeks
**Revenue Impact**: $270K/year
**Blockers**: Requires expert curation

**Description**: Expert-curated top 10 beaches for specific activities (snorkeling, diving, surfing).

**Subtasks**:
- [ ] 11.1. Create `activity_guides` table
- [ ] 11.2. Create expert curation workflow
- [ ] 11.3. Create `ActivityGuideController` (purchase, download)
- [ ] 11.4. Create PDF template for activity guides
- [ ] 11.5. Add quarterly update process
- [ ] 11.6. Write tests

**Dependencies**: 
- Expert curation process (not yet defined)
- Content creation workflow (not yet established)

---

### [ ] 12. White-Label AI Agent
**Priority**: P2 (Phase 2)
**Status**: NOT_STARTED
**Estimated Effort**: 4-6 weeks
**Revenue Impact**: $150K/year
**Blockers**: Requires multi-tenancy infrastructure

**Description**: Branded AI agent for B2B customers (custom logo, colors, domain).

**Subtasks**:
- [ ] 12.1. Create tenant branding configuration (logo, colors, domain)
- [ ] 12.2. Create isolated session storage (per tenant)
- [ ] 12.3. Create custom domain routing
- [ ] 12.4. Create white-label admin panel
- [ ] 12.5. Write tests

**Dependencies**:
- B2B tenant system (Task #8)

---

### [ ] 13. API Access (REST + GraphQL)
**Priority**: P2 (Phase 2)
**Status**: NOT_STARTED
**Estimated Effort**: 2-3 weeks
**Revenue Impact**: Included in White-Label tier
**Blockers**: Requires rate limiting per tenant

**Description**: API access for B2B customers (integrate beach data into their platforms).

**Subtasks**:
- [ ] 13.1. Create API key management
- [ ] 13.2. Create rate limiting per tenant (10,000/day)
- [ ] 13.3. Create GraphQL endpoint (optional)
- [ ] 13.4. Create API documentation (Swagger/OpenAPI)
- [ ] 13.5. Write tests

**Dependencies**:
- B2B tenant system (Task #8)

---

## Blocked Tasks

**No blocked tasks currently.**

---

## Completed Tasks (Last 30 Days)

### [x] AI Governance Documentation Bootstrap
**Completed**: 2025-12-22  
**Effort**: 1 day  
**Impact**: Any new AI agent can now start by reading `AI_INDEX.md`

---

## Next Steps for AI Agent

**If you are a new AI agent reading this**:

1. ✅ You have read `AI_INDEX.md`
2. ✅ You have read `STATE.md`
3. ✅ You have read this file (`TASKS.md`)
4. ⏭️ Ask user: "What should I work on?"

**Recommended next task**: Trip Itinerary Generation (Task #1, P0, PRIMARY REVENUE)

---

**End of TASKS.md**

This file is updated by: All AI agents working on implementation  
This file must be updated: When starting, completing, or blocking tasks  
This file is read by: All new AI agents to understand what needs to be done

