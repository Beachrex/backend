# Beachrex AI Agent - Task List

**Last Updated**: 2025-12-22  
**Current Phase**: MVP Implementation  
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

### [ ] 1. Trip Itinerary Generation (PRIMARY REVENUE)
**Priority**: P0 (CRITICAL)  
**Status**: NOT_STARTED  
**Estimated Effort**: 2-3 weeks  
**Revenue Impact**: $1.08M/year  
**Blockers**: None  

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

### [ ] 2. Payment Integration (Stripe)
**Priority**: P0 (CRITICAL)  
**Status**: NOT_STARTED  
**Estimated Effort**: 1 week  
**Revenue Impact**: Enables all B2C revenue ($1.8M/year)  
**Blockers**: None  

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

### [ ] 3. Beach Search Analytics (B2B)
**Priority**: P0 (CRITICAL)  
**Status**: NOT_STARTED  
**Estimated Effort**: 1-2 weeks  
**Revenue Impact**: $100K/year  
**Blockers**: None  

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

### [ ] 4. Promoted Beach Listings (B2B)
**Priority**: P0 (CRITICAL)  
**Status**: NOT_STARTED  
**Estimated Effort**: 1 week  
**Revenue Impact**: $150K/year  
**Blockers**: None  

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
- B2B tenant system (Task #7)
- Beach search analytics (Task #3)

**Acceptance Criteria**:
- B2B customers can select beaches to promote (up to 200)
- Promoted beaches appear in top 3 AI recommendations
- Promoted beaches are clearly labeled as "Promoted"
- System tracks impressions and clicks
- System enforces B2B tier validation ($15K/year minimum)

---

## P1: Important (Needed for MVP, Not Blocking)

### [ ] 5. PDF Generation (Itineraries, Reports)
**Priority**: P1  
**Status**: NOT_STARTED  
**Estimated Effort**: 3-5 days  
**Blockers**: None  

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

### [ ] 6. AI Agent Logging (Query Tracking)
**Priority**: P1  
**Status**: NOT_STARTED  
**Estimated Effort**: 2-3 days  
**Blockers**: None  

**Description**: Log all AI queries for analytics, cost tracking, and debugging.

**Subtasks**:
- [ ] 6.1. Create `ai_usage` table and migration (if not created in Task #3)
- [ ] 6.2. Log all AI queries (user_id, session_id, query, intent, beaches_returned)
- [ ] 6.3. Log LLM API calls (tokens_used, cost, latency)
- [ ] 6.4. Create cost tracking dashboard (admin only)
- [ ] 6.5. Add cost alerts (email CTO if daily cost > $500)
- [ ] 6.6. Write unit tests (logging service)

**Dependencies**: None

**Acceptance Criteria**:
- System logs all AI queries to `ai_usage` table
- System logs LLM API calls (tokens, cost, latency)
- Admin can view cost tracking dashboard
- System sends alerts if daily cost exceeds $500

---

### [ ] 7. B2B Tenant System (Multi-Tenancy)
**Priority**: P1  
**Status**: NOT_STARTED  
**Estimated Effort**: 1 week  
**Blockers**: None  

**Description**: Implement multi-tenancy for B2B customers (DMOs, hotels, municipalities).

**Subtasks**:
- [ ] 7.1. Create `tenants` table and migration
- [ ] 7.2. Create `Tenant` model with relationships
- [ ] 7.3. Add `tenant_id` to `users` table (B2B users belong to tenant)
- [ ] 7.4. Add tenant scoping middleware (all queries scoped by tenant_id)
- [ ] 7.5. Create tenant admin panel (Filament resource)
- [ ] 7.6. Add tenant tier validation (Analytics, Promotion, White-Label, Enterprise)
- [ ] 7.7. Write unit tests (tenant scoping)
- [ ] 7.8. Write integration tests (multi-tenant isolation)

**Dependencies**: None

**Acceptance Criteria**:
- System supports multiple B2B tenants
- Each tenant has isolated data (cannot access other tenants' data)
- System enforces tier validation (Analytics, Promotion, White-Label, Enterprise)
- Admin can manage tenants via Filament panel

---

### [ ] 8. AI Service Implementation (Microservice)
**Priority**: P1  
**Status**: NOT_STARTED  
**Estimated Effort**: 2-3 weeks  
**Blockers**: None  

**Description**: Implement AI service as dedicated microservice (separate Laravel app).

**Subtasks**:
- [ ] 8.1. Create new Laravel app (`laravel new ai-service`)
- [ ] 8.2. Configure shared database (PostgreSQL)
- [ ] 8.3. Configure shared Redis (sessions, cache, rate limiting)
- [ ] 8.4. Configure shared Meilisearch (search indexes)
- [ ] 8.5. Create `ChatController` (handle chat requests)
- [ ] 8.6. Create `AIAgentService` (LLM integration, context assembly)
- [ ] 8.7. Implement session memory (Redis, 30-min TTL)
- [ ] 8.8. Implement rate limiting (100/hour per IP for free tier)
- [ ] 8.9. Implement token budget enforcement (4K tokens for free tier)
- [ ] 8.10. Implement circuit breakers ($500/day → disable free tier)
- [ ] 8.11. Write unit tests (AIAgentService)
- [ ] 8.12. Write integration tests (ChatController)

**Dependencies**: 
- LLM API integration (OpenAI or Anthropic)

**Acceptance Criteria**:
- AI service runs on port 8001 (separate from main API on port 8000)
- AI service shares PostgreSQL, Redis, Meilisearch with main API
- AI service handles chat requests (natural language queries)
- AI service enforces rate limiting (100/hour per IP)
- AI service enforces token budgets (4K tokens for free tier)
- AI service implements circuit breakers (cost control)

---

## P2: Nice-to-Have (Phase 2)

### [ ] 9. Verified Accessibility Reports
**Priority**: P2 (Phase 2)  
**Status**: NOT_STARTED  
**Estimated Effort**: 4-6 weeks  
**Revenue Impact**: $450K/year  
**Blockers**: Requires human verification process  

**Description**: Human-verified accessibility reports with photos and guarantees.

**Subtasks**:
- [ ] 9.1. Create `verified_accessibility_reports` table
- [ ] 9.2. Create verification workflow (photographer network, QA)
- [ ] 9.3. Create `AccessibilityReportController` (purchase, download)
- [ ] 9.4. Create PDF template for accessibility reports
- [ ] 9.5. Add money-back guarantee logic
- [ ] 9.6. Write tests

**Dependencies**: 
- Human verification process (not yet defined)
- Photographer network (not yet established)

---

### [ ] 10. Activity-Specific Guides
**Priority**: P2 (Phase 2)  
**Status**: NOT_STARTED  
**Estimated Effort**: 6-8 weeks  
**Revenue Impact**: $270K/year  
**Blockers**: Requires expert curation  

**Description**: Expert-curated top 10 beaches for specific activities (snorkeling, diving, surfing).

**Subtasks**:
- [ ] 10.1. Create `activity_guides` table
- [ ] 10.2. Create expert curation workflow
- [ ] 10.3. Create `ActivityGuideController` (purchase, download)
- [ ] 10.4. Create PDF template for activity guides
- [ ] 10.5. Add quarterly update process
- [ ] 10.6. Write tests

**Dependencies**: 
- Expert curation process (not yet defined)
- Content creation workflow (not yet established)

---

### [ ] 11. White-Label AI Agent
**Priority**: P2 (Phase 2)  
**Status**: NOT_STARTED  
**Estimated Effort**: 4-6 weeks  
**Revenue Impact**: $150K/year  
**Blockers**: Requires multi-tenancy infrastructure  

**Description**: Branded AI agent for B2B customers (custom logo, colors, domain).

**Subtasks**:
- [ ] 11.1. Create tenant branding configuration (logo, colors, domain)
- [ ] 11.2. Create isolated session storage (per tenant)
- [ ] 11.3. Create custom domain routing
- [ ] 11.4. Create white-label admin panel
- [ ] 11.5. Write tests

**Dependencies**: 
- B2B tenant system (Task #7)

---

### [ ] 12. API Access (REST + GraphQL)
**Priority**: P2 (Phase 2)  
**Status**: NOT_STARTED  
**Estimated Effort**: 2-3 weeks  
**Revenue Impact**: Included in White-Label tier  
**Blockers**: Requires rate limiting per tenant  

**Description**: API access for B2B customers (integrate beach data into their platforms).

**Subtasks**:
- [ ] 12.1. Create API key management
- [ ] 12.2. Create rate limiting per tenant (10,000/day)
- [ ] 12.3. Create GraphQL endpoint (optional)
- [ ] 12.4. Create API documentation (Swagger/OpenAPI)
- [ ] 12.5. Write tests

**Dependencies**: 
- B2B tenant system (Task #7)

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

