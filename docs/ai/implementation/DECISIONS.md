# Beachrex AI Agent - Architectural Decision Records (ADR)

**Purpose**: Document all significant architectural and design decisions  
**Format**: ADR (Architectural Decision Record)  
**Audience**: Future AI agents, developers, product owners

---

## How to Use This File

**When to add an entry**:
- You make a significant architectural decision
- You choose between multiple implementation approaches
- You decide NOT to implement something (and why)
- You change an existing decision

**Entry format**:
```markdown
## [YYYY-MM-DD] Decision Title

**Status**: Accepted | Rejected | Superseded | Deprecated

**Context**: [Why this decision was needed]

**Decision**: [What was decided]

**Alternatives Considered**:
1. [Alternative 1] - Rejected because [reason]
2. [Alternative 2] - Rejected because [reason]

**Reasoning**: [Why this decision is best]

**Impact**:
- [Impact on codebase]
- [Impact on users]
- [Impact on business]

**Related**: [Link to code, docs, or tasks]
```

---

## [2025-12-22] AI Agent as Paid Product (Not Free Feature)

**Status**: Accepted (CANONICAL)

**Context**: 
Initial product discussions considered making the AI agent a free feature to drive engagement, similar to how Google and TripAdvisor offer free search. However, this would not generate revenue and would incur significant LLM costs.

**Decision**: 
The AI agent is a **paid product** with trip-based pricing, not a free feature. Search is free (table stakes), but decision outcomes (itineraries, verified reports, guides) are paid.

**Alternatives Considered**:
1. **Free AI with ads** - Rejected because beach discovery is not high-frequency (users plan 1-2 trips/year, not daily), so ad revenue would be minimal
2. **Monthly subscription** ($9-29/month) - Rejected because users won't pay monthly for infrequent use (subscription fatigue)
3. **Freemium with feature limits** (block weather, map, comparisons) - Rejected because blocking table-stakes features degrades experience and reduces conversion

**Reasoning**: 
- Users have high willingness to pay at decision point ($39 for 7-day itinerary vs $5K vacation)
- Trip-based pricing aligns with user behavior (1-2 vacations/year)
- No subscription fatigue (one-time purchase)
- Clear value proposition (saves 5+ hours research, avoids $500 wasted day)

**Impact**:
- **Codebase**: All itinerary generation must validate payment before execution
- **Users**: Free users get unlimited search, paid users get itineraries/reports
- **Business**: Primary revenue driver ($1.08M/year projected from itineraries)

**Related**: `docs/ai/product/ai-monetization.md`, `docs/ai/product/ai-business-rules.md`

---

## [2025-12-22] B2B Pricing Based on Visitor Acquisition (Not AI Features)

**Status**: Accepted (CANONICAL)

**Context**:
Initial B2B pricing focused on "AI-powered analytics" and "AI-powered recommendations" as the value proposition. However, B2B customers (DMOs, hotels, municipalities) don't care about AI technology—they care about business outcomes.

**Decision**:
B2B pricing is based on **visitor acquisition value** (more tourists to their beaches), not AI features. Pricing tiers are justified by ROI (e.g., $5K investment → 1% increase in visitors → $500K+ tourism revenue).

**Alternatives Considered**:
1. **Feature-based pricing** (pay per AI query, pay per recommendation) - Rejected because customers don't understand or value AI queries
2. **Flat SaaS pricing** ($99/month for all features) - Rejected because undervalues enterprise customers (national tourism boards worth $50K+/year)
3. **Usage-based pricing** (pay per visitor driven) - Rejected because attribution is difficult (can't prove AI drove the visit)

**Reasoning**:
- B2B customers measure success in visitor numbers and tourism revenue, not AI metrics
- Value-based pricing captures more revenue from high-value customers (national tourism boards)
- Clear ROI justification makes sales easier ($5K → $500K return)

**Impact**:
- **Codebase**: B2B analytics focus on visitor intent, search trends, geographic distribution (not AI accuracy metrics)
- **Users**: B2B customers get analytics dashboard, promoted listings, white-label AI
- **Business**: B2B revenue potential $600K/year (37 customers across 4 tiers)

**Related**: `docs/ai/product/ai-monetization.md` (Section 3: B2B Monetization)

---

## [2025-12-22] AI Service as Dedicated Microservice (Not Monolith)

**Status**: Superseded by [2025-12-22] AI Service in Existing Laravel API for MVP

**Context**:
The AI agent could be integrated into the existing Laravel API (monolith) or deployed as a separate microservice. Monolith is simpler, but microservice provides isolation and independent scaling.

**Decision**:
AI agent is a **dedicated microservice** (separate Laravel app on port 8001), isolated from main API (port 8000). Shares PostgreSQL, Redis, Meilisearch.

**Alternatives Considered**:
1. **Monolith** (integrate into existing Laravel API) - Rejected because AI costs and scaling are unpredictable, could impact main API performance
2. **Serverless** (AWS Lambda, Google Cloud Functions) - Rejected because cold start latency (2-5 seconds) is too high for real-time chat
3. **Separate infrastructure** (different database, different Redis) - Rejected because unnecessary complexity and cost for MVP

**Reasoning**:
- **Isolation**: AI failures don't impact main API (beach search still works if AI is down)
- **Scaling**: AI service can scale independently (more traffic → more AI servers, not more API servers)
- **Cost control**: Easy to monitor and limit AI costs (separate service, separate metrics)
- **Deployment**: Can deploy AI updates without touching main API (reduces risk)

**Impact**:
- **Codebase**: Two Laravel apps (main API, AI service), shared database/Redis/Meilisearch
- **Users**: Transparent (frontend calls AI service via proxy or direct)
- **Business**: Better cost control, easier to scale, lower risk

**Related**: `docs/ai/architecture/integration.md` (Section 1: Where AI Lives)

**NOTE**: This decision was superseded for MVP. See next decision for MVP approach.

---

## [2025-12-22] No Long-Term User Memory (Privacy-First)

**Status**: Accepted (CANONICAL)

**Context**:
AI agents often store long-term user memory (preferences, past searches, behavior patterns) to provide personalized recommendations. However, this raises privacy concerns and GDPR compliance issues.

**Decision**:
AI agent has **NO long-term user memory**. Only session memory (Redis, 30-minute TTL, max 10 messages). After session expires, all user data is deleted.

**Alternatives Considered**:
1. **Long-term memory** (store user preferences, past searches) - Rejected because GDPR compliance is complex (right to be forgotten, data portability, consent management)
2. **Opt-in memory** (users choose to save preferences) - Rejected because adds complexity and most users won't opt in (privacy concerns)
3. **Anonymous memory** (store patterns without user ID) - Rejected because still requires GDPR compliance (IP addresses are personal data)

**Reasoning**:
- **Privacy**: No long-term storage = no privacy risk, no GDPR compliance burden
- **Simplicity**: No user accounts on frontend, no login required, no consent forms
- **Trust**: Users trust AI more when they know data isn't stored permanently
- **Cost**: No long-term storage = lower database costs

**Impact**:
- **Codebase**: Session memory only (Redis, 30-min TTL), no user profile storage
- **Users**: Must re-enter preferences each session (acceptable for infrequent use)
- **Business**: Lower compliance risk, lower storage costs, higher user trust

**Related**: `docs/ai/architecture/agent-execution-model.md` (Section 3: Memory Model)

---

## [2025-12-22] Rate Limiting by IP (Not by User)

**Status**: Accepted (CANONICAL)

**Context**:
Rate limiting can be enforced per user (requires login) or per IP (anonymous). Per-user is more accurate, but per-IP is simpler and doesn't require authentication.

**Decision**:
Rate limiting is **per IP** (100 queries/hour for free tier), not per user. Paid tier uses per-user rate limiting (requires authentication).

**Alternatives Considered**:
1. **Per-user rate limiting** (requires login for all users) - Rejected because adds friction (most users won't create account for free search)
2. **No rate limiting** (unlimited free tier) - Rejected because enables scraping and DDoS attacks
3. **CAPTCHA-based** (show CAPTCHA after 50 queries) - Rejected because degrades user experience

**Reasoning**:
- **Simplicity**: No login required for free tier (lower friction, higher engagement)
- **Abuse prevention**: 100 queries/hour per IP is enough for legitimate users, blocks scrapers
- **Cost control**: Prevents runaway LLM costs from bots
- **Upgrade path**: Paid users get higher limits (500/hour) and per-user tracking

**Impact**:
- **Codebase**: Redis sliding window per IP, middleware enforcement
- **Users**: Free users can search without login (up to 100/hour), paid users get higher limits
- **Business**: Lower friction for free tier, better abuse prevention

**Related**: `docs/ai/product/ai-business-rules.md` (Section 4.1: Rate Limiting)

---

## [2025-12-22] Circuit Breakers for Cost Control (Automatic Shutdown)

**Status**: Accepted (CANONICAL)

**Context**:
LLM costs can spike unexpectedly (bot attack, viral traffic, bug in code). Without automatic shutdowns, costs could reach $10K+/day and bankrupt the company.

**Decision**:
Implement **circuit breakers** that automatically disable AI when costs exceed thresholds:
- $500/day → Disable free tier
- $5,000/day → Emergency shutdown (all tiers)

**Alternatives Considered**:
1. **Manual monitoring** (alert CTO, manually disable) - Rejected because response time is too slow (could cost $1K+ before CTO responds)
2. **Hard budget cap** (OpenAI API budget limit) - Rejected because disables AI immediately (no graceful degradation)
3. **Per-user budget** (limit each user to $1/day) - Rejected because legitimate paid users could hit limit

**Reasoning**:
- **Cost protection**: Automatic shutdown prevents runaway costs
- **Graceful degradation**: Disable free tier first (preserve paid tier revenue)
- **Fast response**: Automatic (no human in loop), triggers in seconds
- **Reversible**: CTO can re-enable after investigating spike

**Impact**:
- **Codebase**: Cost tracking per query, Redis flag for global enable/disable, middleware enforcement
- **Users**: Free tier may be temporarily disabled during cost spikes (paid tier continues)
- **Business**: Protects against bankruptcy from cost spikes

**Related**: `docs/ai/product/ai-business-rules.md` (Section 4.6: Circuit Breakers)

---

## [2025-12-22] Trip-Based Pricing (Not Subscription)

**Status**: Accepted (CANONICAL)

**Context**:
SaaS products typically use monthly subscriptions ($9-29/month). However, beach vacation planning is infrequent (1-2 times/year), so monthly subscriptions don't align with user behavior.

**Decision**:
Use **trip-based pricing** ($19-$59 per itinerary), not monthly subscriptions. One-time purchase, trip-specific.

**Alternatives Considered**:
1. **Monthly subscription** ($9/month for unlimited itineraries) - Rejected because users won't pay monthly for infrequent use (subscription fatigue)
2. **Annual subscription** ($99/year for unlimited itineraries) - Rejected because users plan 1-2 trips/year (would pay $99 for 2 itineraries vs $78 trip-based)
3. **Pay-per-query** ($0.10/query) - Rejected because users don't understand or accept per-query pricing

**Reasoning**:
- **Aligns with user behavior**: Users plan 1-2 beach vacations/year (not monthly)
- **Higher willingness to pay**: $39 for 7-day itinerary vs $5K vacation is easy decision
- **No subscription fatigue**: One-time purchase, no recurring charges
- **Clear value**: "Pay $39, get perfect itinerary" is clearer than "Pay $9/month for unlimited itineraries"

**Impact**:
- **Codebase**: One-time payment validation (not recurring billing)
- **Users**: Pay once per trip (no monthly charges)
- **Business**: Higher revenue per transaction ($39 vs $9), lower churn (no subscriptions to cancel)

**Related**: `docs/ai/product/ai-monetization.md` (Section 2.2: B2C Pricing Models)

---

## [2025-12-22] Multi-Day Itineraries NEVER Free (Hard Constraint)

**Status**: Accepted (CANONICAL, NON-NEGOTIABLE)

**Context**:
Multi-day itineraries are the PRIMARY REVENUE source ($1.08M/year projected). Giving them away for free would destroy the business model.

**Decision**:
Multi-day itineraries (2+ days) are **NEVER free**. This is a hard constraint enforced at code level. Single-day suggestions are free.

**Alternatives Considered**:
1. **Free preview** (show first day, pay for full itinerary) - Rejected because users could just ask for multiple single-day suggestions
2. **Freemium** (1 free itinerary/month, then paid) - Rejected because users would create multiple accounts
3. **Free with ads** (show ads in itinerary PDF) - Rejected because ad revenue is minimal for infrequent use

**Reasoning**:
- **Primary revenue**: Itineraries are 60% of B2C revenue ($1.08M/year)
- **High value**: Users save 5+ hours research, avoid $500 wasted day (worth $39)
- **Clear differentiation**: Free = search, Paid = decision outcomes
- **Enforceable**: Code-level validation (402 Payment Required response)

**Impact**:
- **Codebase**: All itinerary generation validates payment before execution (no exceptions)
- **Users**: Free users get search and single-day suggestions, paid users get multi-day itineraries
- **Business**: Protects primary revenue source

**Related**: `docs/ai/product/ai-business-rules.md` (Section 1.1: Multi-Day Itineraries)

**Penalty for Violation**: Immediate termination of developer who bypasses this rule

---

## [2025-12-22] AI Governance Documentation Bootstrap

**Status**: Accepted (COMPLETE)

**Context**:
The project is 80% complete, but there was no canonical documentation for AI agents to understand the project, business model, and constraints. Each new AI agent would need extensive briefing.

**Decision**:
Create a **canonical AI governance system** with a single entry point (`AI_INDEX.md`) that all future AI agents must read first.

**Alternatives Considered**:
1. **Scattered documentation** (README, wiki, comments) - Rejected because AI agents wouldn't know where to start
2. **Verbal briefing** (explain to each AI agent) - Rejected because not scalable, inconsistent
3. **Code comments only** (document in code) - Rejected because doesn't cover business logic and constraints

**Reasoning**:
- **Single entry point**: AI agents read ONE file (`AI_INDEX.md`) and understand entire project
- **Consistency**: All AI agents get same information (no drift)
- **Scalability**: Can onboard unlimited AI agents without human briefing
- **Handoff**: AI agents can hand off work to each other (update STATE.md, TASKS.md, DECISIONS.md)

**Impact**:
- **Codebase**: New `docs/ai/` directory with 9 canonical documents
- **Users**: No direct impact (internal documentation)
- **Business**: Faster AI agent onboarding, consistent implementation, lower risk

**Related**: `docs/ai/AI_INDEX.md` (canonical entry point)

**Files Created**:
- `AI_INDEX.md` - Canonical entry point
- `product/ai-monetization.md` - Pricing strategy
- `product/ai-business-rules.md` - Hard constraints
- `architecture/agent-execution-model.md` - Execution model
- `architecture/ai-capability-alignment.md` - Capability mapping
- `implementation/STATE.md` - Current state
- `implementation/DECISIONS.md` - This file (ADR log)
- `implementation/TASKS.md` - Task list
- `implementation/PROMPTS.md` - System prompts

---

---

## [2025-12-22] AI Service in Existing Laravel API for MVP

**Status**: Accepted (CANONICAL for MVP)

**Context**:
During architecture phase, we need to decide between integrating AI into the existing Laravel API (monolith) or creating a separate microservice. Previous decision favored microservice, but for MVP we need to validate value and contracts first before adding infrastructure complexity.

**Decision**:
For MVP, AI agent is **part of the existing Laravel API** (same app, new routes under `/api/v1/ai/*`). Can be split into microservice in Phase 2 once we have real usage and cost signals.

**Alternatives Considered**:
1. **Dedicated microservice** (separate Laravel app on port 8001) - Rejected for MVP because adds infrastructure complexity, deployment overhead, and inter-service communication before validating product-market fit
2. **Serverless** (AWS Lambda, Vercel Edge Functions) - Rejected because cold start latency (2-5s) is too high for real-time chat
3. **Separate infrastructure** (different database, Redis) - Rejected because unnecessary for MVP

**Reasoning**:
- **Fastest to market**: No new infrastructure, no inter-service communication, no deployment complexity
- **Simplest**: Single codebase, single deployment, single monitoring dashboard
- **Lowest risk**: Validate AI value proposition before investing in microservice architecture
- **Easy migration**: Can extract to microservice later (routes already namespaced under `/api/v1/ai/*`)
- **Cost visibility**: Still track AI costs separately (middleware logging), just in same app

**Impact**:
- **Codebase**: New controllers/services in existing Laravel app under `app/Http/Controllers/AI/` and `app/Services/AI/`
- **Users**: Transparent (same API domain, just new endpoints)
- **Business**: Faster MVP launch, lower infrastructure costs, easier to iterate

**Related**: `docs/ai/architecture/mvp-integration.md` (new file to be created)

**Migration Path**: Phase 2 can extract AI routes to separate microservice without changing API contracts (frontend sees no difference)

---

## [2025-12-22] Provider-Agnostic LLM Abstraction Layer

**Status**: Accepted (CANONICAL)

**Context**:
AI agent needs to call LLM APIs (OpenAI, Anthropic, etc.). We could integrate directly with one provider or create an abstraction layer. Direct integration is faster but creates vendor lock-in.

**Decision**:
Implement **provider-agnostic abstraction layer** with initial implementation for OpenAI. Design API contracts without provider-specific fields.

**Alternatives Considered**:
1. **Direct OpenAI integration** - Rejected because creates vendor lock-in, hard to switch providers if pricing changes or service degrades
2. **Direct Anthropic integration** - Rejected for same reason as OpenAI
3. **Multi-provider from day 1** (OpenAI + Anthropic + local models) - Rejected because over-engineering for MVP, adds complexity without immediate value

**Reasoning**:
- **Avoid lock-in**: Can switch providers if OpenAI raises prices or service degrades
- **Future-proof**: Can add Anthropic, local models, or other providers without changing application code
- **Cost optimization**: Can route queries to cheapest provider based on complexity
- **Practical**: Start with OpenAI (most mature API), add others as needed

**Impact**:
- **Codebase**: `LLMService` interface with `OpenAIProvider` implementation, easy to add `AnthropicProvider` later
- **Users**: Transparent (don't know or care which LLM is used)
- **Business**: Lower risk of vendor lock-in, easier to optimize costs

**Related**: `docs/ai/architecture/llm-abstraction.md` (new file to be created)

**Interface Design**:
```php
interface LLMProvider {
    public function chat(array $messages, array $options): LLMResponse;
    public function streamChat(array $messages, array $options): Generator;
}
```

---

## [2025-12-22] Hybrid Session Management (Anonymous + Authenticated)

**Status**: Accepted (CANONICAL)

**Context**:
AI agent needs to track sessions for conversation context. We could require authentication for all users, allow anonymous for all, or use a hybrid approach.

**Decision**:
Use **hybrid session management**:
- **Anonymous allowed** for free tier (IP-based rate limiting, very limited)
- **Authentication required** for paid features and higher limits (Laravel Sanctum)

**Alternatives Considered**:
1. **Authentication required for all** - Rejected because adds friction, most users won't create account for free search
2. **Anonymous for all** - Rejected because can't enforce payment or track paid users
3. **CAPTCHA-based** (show CAPTCHA after N queries) - Rejected because degrades UX

**Reasoning**:
- **Best conversion funnel**: Free users can try AI without signup (lower friction)
- **Enforceable**: Paid features require auth (can validate payment)
- **Abuse prevention**: IP-based rate limiting for anonymous (100/hour), user-based for authenticated (500/hour)
- **Upgrade path**: Clear incentive to create account (higher limits, paid features)

**Impact**:
- **Codebase**: Middleware checks for auth token (optional for free tier, required for paid)
- **Users**: Can use AI without signup (up to 100 queries/hour), must signup for paid features
- **Business**: Lower friction for free tier, clear upgrade path to paid

**Related**: `docs/ai/product/ai-business-rules.md` (Section 4.1: Rate Limiting)

**Session Identification**:
- Anonymous: `session_id` generated client-side, stored in Redis with IP validation
- Authenticated: `session_id` tied to `user_id`, stored in Redis with auth token validation

---

## [2025-12-22] Synchronous Request/Response for MVP

**Status**: Accepted (CANONICAL for MVP)

**Context**:
AI responses can be delivered synchronously (HTTP request/response) or asynchronously (job queue + webhook). Sync is simpler, async is better for long-running tasks.

**Decision**:
Use **synchronous request/response** for MVP, with optional streaming support (not required in v1). Async can be added in Phase 2 for long-running features (itineraries).

**Alternatives Considered**:
1. **Asynchronous** (job queue + webhook callback) - Rejected for MVP because adds complexity (job workers, webhook infrastructure, client polling)
2. **Both** (sync for simple, async for complex) - Rejected because over-engineering for MVP
3. **Streaming only** (SSE or WebSocket) - Rejected because not all clients support streaming (mobile apps, older browsers)

**Reasoning**:
- **Simplest UX**: User sends query, gets response (no polling, no webhooks)
- **Simplest implementation**: Standard HTTP request/response, no job queue infrastructure
- **Good enough for MVP**: Most queries complete in <2 seconds (acceptable for sync)
- **Streaming optional**: Can add SSE streaming later for better UX (progressive response)

**Impact**:
- **Codebase**: Standard Laravel controllers (no job queue, no webhooks)
- **Users**: Immediate response (no waiting for job to complete)
- **Business**: Faster MVP launch, simpler infrastructure

**Related**: `docs/ai/architecture/api-contracts.md` (new file to be created)

**Timeout Handling**:
- Client timeout: 30 seconds (if LLM takes longer, return error and suggest retry)
- Server timeout: 25 seconds (fail fast, don't let client wait indefinitely)

**Migration Path**: Phase 2 can add async endpoints (`POST /api/v1/ai/chat/async`) for long-running tasks without breaking existing sync endpoints

---

## [2025-12-29] Dedicated Endpoint for Trip Itineraries (POST /api/v1/ai/itineraries)

**Status**: Accepted (CANONICAL)

**Context**:
Task #2 (Trip Itinerary Generation - PRIMARY REVENUE) requires an API endpoint. Two options were considered: (1) extend existing `POST /api/v1/ai/chat` with `intent=itinerary_request`, or (2) create dedicated endpoint `POST /api/v1/ai/itineraries`.

**Decision**:
Create **dedicated endpoint `POST /api/v1/ai/itineraries`** for trip itinerary generation, separate from the chat endpoint.

**Alternatives Considered**:
1. **Extend POST /api/v1/ai/chat** with `intent=itinerary_request` - Rejected because:
   - Mixes two very different response models (conversational chat vs structured itinerary)
   - Increases risk of leaking paid content through chat responses
   - Makes OpenAPI typing and frontend client generation more complex
   - Blurs monetization boundary (chat is free, itineraries are paid)
2. **Separate microservice** - Rejected for MVP because adds infrastructure complexity (can extract later if needed)

**Reasoning**:
- **Clear monetization boundary**: Dedicated endpoint makes it explicit that itineraries are paid (always 402 unless entitled)
- **Easier OpenAPI typing**: Request/response schemas are completely different from chat (structured itinerary vs conversational message)
- **Better frontend DX**: Generated TypeScript client has clear separation between `chatWithAI()` and `generateItinerary()`
- **Reduced risk**: No chance of accidentally returning paid itinerary content through free chat endpoint
- **Reuses AI core**: Still uses same middleware stack (OptionalAuth, CircuitBreaker, RateLimit, TokenBudget), same LLM abstraction, same session/message/usage tables

**Impact**:
- **Codebase**: New route `POST /api/v1/ai/itineraries`, new controller `ItineraryController`, new service `ItineraryService`, new request/response schemas
- **Users**: Clear separation between free chat (discovery, Q&A) and paid itineraries (trip planning)
- **Business**: Protects primary revenue source ($1.08M/year) with explicit payment gate

**Related**:
- `docs/ai/implementation/PHASE_1_PROPOSAL.md` (Section 1: Endpoint Decision)
- `docs/ai/implementation/TASKS.md` (Task #2: Trip Itinerary Generation)

**OpenAPI Schemas**:
- `AiItineraryRequest` - Request schema with destination, duration_days, preferences, constraints
- `AiItineraryResponse` - Response schema with structured day-by-day itinerary
- Error responses: 400, 402, 413, 429, 500, 503

**Middleware Stack** (reused from AI Core):
1. `OptionalAuth` - Allow anonymous for free tier, require auth for paid
2. `CircuitBreaker` - Protect against cost spikes
3. `RateLimit` - Tier-based limits (100/hour free, 500/hour paid)
4. `TokenBudget` - Prevent oversized requests (4K free, 16K paid)

---

## Template for Future Decisions

**Copy this template when adding new decisions**:

```markdown
## [YYYY-MM-DD] Decision Title

**Status**: Accepted | Rejected | Superseded | Deprecated

**Context**: [Why this decision was needed]

**Decision**: [What was decided]

**Alternatives Considered**:
1. [Alternative 1] - Rejected because [reason]
2. [Alternative 2] - Rejected because [reason]

**Reasoning**: [Why this decision is best]

**Impact**:
- [Impact on codebase]
- [Impact on users]
- [Impact on business]

**Related**: [Link to code, docs, or tasks]
```

---

## [2025-12-30] Credit-Based Entitlement Model for Itinerary Purchases

**Status**: Accepted

**Context**:
Task #3 (Payment Integration) required a decision on how to manage user entitlements for purchased itineraries. Two primary models were considered: time-based access (purchase grants access for X days) vs credit-based (purchase grants N credits, each itinerary consumes 1 credit).

**Decision**:
Implement a **credit-based entitlement model** where:
- Each purchase grants 1 credit for a specific itinerary type (3-day, 7-day, or 14-day)
- Each itinerary generation consumes 1 credit
- Credits do not expire (MVP)
- Refunds revoke credits (can go negative, blocking future generation until balance >= 0)

**Alternatives Considered**:
1. **Time-based access** (e.g., purchase grants 30-day access to generate unlimited itineraries)
   - Rejected because: Revenue leakage risk (users could generate unlimited itineraries for one payment), difficult to price fairly, abuse potential
2. **Subscription model** (monthly/annual recurring payments)
   - Rejected because: Adds complexity (billing cycles, cancellations, prorations), not aligned with one-time purchase pricing strategy for MVP

**Reasoning**:
- **Revenue protection**: One purchase = one itinerary prevents abuse and revenue leakage
- **Pricing alignment**: Directly maps to existing pricing ($19/$39/$59 per itinerary)
- **Simplicity**: Easy to understand for users ("buy 1 credit, get 1 itinerary")
- **Scalability**: Can add bundles/promotions later ("buy 5, get 1 free") without changing core model
- **Refund handling**: Clear policy (revoke unused credits first, allow negative balance if consumed)

**Impact**:
- **Database**: Two new tables (`user_entitlements`, `purchases`)
- **Services**: EntitlementService for credit management (grant, check, decrement, revoke)
- **Enforcement**: PlanEnforcementService checks credits before allowing itinerary generation
- **Atomicity**: Credit decrement happens AFTER successful generation (prevents double-charge on retry)
- **User experience**: Clear credit balance display, transparent pricing

**Known Limitations** (MVP):
- **Race condition**: There is a small window between credit check (in `PlanEnforcementService`) and credit decrement (in `ItineraryController`) where two concurrent requests could both pass the check before either decrements. The `decrementCredits` method uses `lockForUpdate()` to prevent concurrent decrements, but the check itself is not locked. In practice, this is unlikely due to LLM generation latency (5-10 seconds). Post-MVP: Implement atomic check-and-decrement using database-level locking or move both operations into a single transaction.

**Related**:
- ADR document: `docs/ai/implementation/TASK_3_ENTITLEMENT_MODEL_ADR.md`
- Proposal: `docs/ai/implementation/TASK_3_PHASE_1_PROPOSAL.md`
- Implementation: Task #3 Phase 2.5 (COMPLETE - PRODUCTION READY)
- Test evidence: `docs/ai/implementation/TASK_3_MERGE_EVIDENCE.md`
- Stripe guide: `docs/ai/implementation/TASK_3_STRIPE_TEST_MODE_GUIDE.md`

---

## [2026-01-02] Bearer Tokens (Sanctum) Instead of Cookies for Frontend Auth

**Status**: Accepted

**Context**:
Task #5 (Google OAuth Authentication) required a decision on how to authenticate frontend requests to the Laravel API. Two primary approaches were considered: cookie-based authentication (Laravel's default with Sanctum SPA authentication) vs Bearer token authentication (Sanctum Personal Access Tokens).

**Decision**:
Use **Bearer tokens (Sanctum Personal Access Tokens)** stored in browser localStorage and sent via `Authorization: Bearer {token}` header for all authenticated API requests.

**Alternatives Considered**:
1. **Cookie-based authentication (Sanctum SPA mode)**
   - Rejected because:
     - Requires same-domain or complex CORS configuration
     - Requires CSRF token management
     - More complex setup for separate frontend/backend domains
     - Harder to debug (cookies are opaque in network inspector)
2. **Session-based authentication (Laravel sessions)**
   - Rejected because:
     - Not suitable for stateless API architecture
     - Requires sticky sessions in load-balanced environments
     - Poor mobile app compatibility (future requirement)

**Reasoning**:
- **Simplicity**: Bearer tokens are straightforward to implement and debug
- **Cross-domain**: Works seamlessly with separate frontend (localhost:3000) and backend (localhost:8000) domains
- **Mobile-ready**: Same token approach works for future mobile apps
- **Stateless**: No server-side session storage required (tokens are self-contained)
- **Debugging**: Tokens visible in network inspector, easy to test with curl/Postman
- **Standard**: Industry-standard approach for SPA + API architecture

**Impact**:
- **Frontend**: Store token in localStorage (`beachrex_auth_token` key)
- **API Client**: Inject `Authorization: Bearer {token}` header on all requests
- **Backend**: Use `auth:sanctum` middleware for protected routes
- **Security**: Tokens are revocable (logout deletes token from database)
- **XSS Risk**: Tokens in localStorage are vulnerable to XSS attacks (mitigated by CSP headers and input sanitization)

**Trade-offs**:
- ✅ **Pros**: Simple, debuggable, cross-domain, mobile-ready, stateless
- ⚠️ **Cons**: Vulnerable to XSS (but so are cookies without httpOnly flag), requires manual token refresh logic (future)

**Related**:
- Implementation: `src/contexts/AuthContext.tsx`, `src/components/ApiClientProvider.tsx`
- Backend: `app/Http/Controllers/Api/V1/Auth/GoogleAuthController.php`
- Documentation: `docs/ai/implementation/AUTH_GOOGLE_SETUP.md`

---

## [2026-01-02] OAuth One-Time Exchange Codes Instead of Tokens in URL

**Status**: Accepted

**Context**:
During Task #5 Phase 3 implementation, a **critical security vulnerability** was discovered: the OAuth callback was passing Sanctum tokens via URL parameters (`?token=...`). This exposes tokens in browser history, server logs, and analytics tools.

**Decision**:
Implement a **one-time exchange code pattern** where:
1. Backend generates a random 64-character exchange code
2. Backend stores the token in cache with 5-minute TTL (key: `google_oauth_exchange:{code}`)
3. Backend redirects to frontend with exchange code (not token): `/auth/callback?code={code}`
4. Frontend calls `/api/v1/auth/google/exchange` with the code
5. Backend returns the token and deletes the code from cache (one-time use)

**Alternatives Considered**:
1. **Pass token in URL fragment** (`#token=...`)
   - Rejected because: Still visible in browser, not sent to server, but can be logged by client-side analytics
2. **Use POST redirect with token in body**
   - Rejected because: Browsers don't support POST redirects from server-side (would require intermediate page with form submission)
3. **Use encrypted token in URL**
   - Rejected because: Still logged in server access logs, encryption adds complexity, doesn't solve the root problem

**Reasoning**:
- **Security**: Tokens never appear in URLs (not logged anywhere)
- **One-time use**: Exchange codes can only be used once (deleted after exchange)
- **Short-lived**: Exchange codes expire after 5 minutes (reduces attack window)
- **Standard pattern**: Used by OAuth 2.0 Authorization Code flow (industry best practice)
- **Auditability**: Can log exchange code usage without exposing tokens

**Impact**:
- **Backend**: New endpoint `POST /api/v1/auth/google/exchange`
- **Frontend**: Must call exchange endpoint after OAuth callback
- **Security**: Eliminates token exposure in URLs, browser history, and server logs
- **User experience**: No visible change (redirect flow is transparent)

**Attack Vectors Mitigated**:
- ✅ **Browser history**: Tokens no longer stored in browser history
- ✅ **Server logs**: Tokens no longer logged in access logs
- ✅ **Analytics**: Tokens no longer sent to Google Analytics, Mixpanel, etc.
- ✅ **Referrer headers**: Tokens no longer leaked via Referer header
- ✅ **Shoulder surfing**: Tokens no longer visible in address bar

**Related**:
- Implementation: `app/Http/Controllers/Api/V1/Auth/GoogleAuthController.php` (lines 199-210)
- Frontend: `src/app/[locale]/auth/callback/page.tsx`
- Documentation: `docs/ai/implementation/SECURITY_FIX_OAUTH_TOKEN.md`
- ADR: This decision supersedes any previous decision to pass tokens in URLs

---

## [2026-01-02] Stripe Checkout (Hosted) Instead of Stripe.js for Payment Integration

**Status**: Accepted

**Context**:
Task #3 (Payment Integration) required a decision on how to integrate Stripe for itinerary purchases. Two primary approaches were considered: Stripe Checkout (hosted payment page) vs Stripe.js (embedded payment form).

**Decision**:
Use **Stripe Checkout (hosted payment page)** where:
1. Backend creates a Checkout Session via Stripe API
2. Backend returns the session URL to frontend
3. Frontend redirects user to Stripe-hosted payment page
4. User completes payment on Stripe's domain
5. Stripe redirects back to frontend success/cancel page
6. Backend receives webhook event (`checkout.session.completed`) to grant credits

**Alternatives Considered**:
1. **Stripe.js (embedded payment form)**
   - Rejected because:
     - Requires PCI compliance (even with Stripe Elements)
     - More complex frontend implementation (card validation, error handling)
     - Requires custom payment form UI design
     - More maintenance burden (Stripe.js version updates)
2. **Stripe Payment Links (no-code solution)**
   - Rejected because:
     - Less control over user experience (can't customize branding)
     - Can't pass custom metadata (user_id, product_type)
     - Can't programmatically create links (requires manual setup)

**Reasoning**:
- **Security**: Stripe handles all payment data (no PCI compliance required)
- **Simplicity**: Minimal frontend code (just redirect to URL)
- **Reliability**: Stripe's hosted page is battle-tested and optimized
- **Mobile-optimized**: Stripe Checkout works seamlessly on mobile
- **Conversion-optimized**: Stripe's UX is optimized for conversion (A/B tested by millions of merchants)
- **Maintenance**: No need to update payment form UI when Stripe changes

**Impact**:
- **Backend**: Create Checkout Session endpoint (`POST /api/v1/payments/stripe/checkout`)
- **Frontend**: Redirect to Stripe Checkout URL (no payment form UI needed)
- **Webhooks**: Handle `checkout.session.completed` event to grant credits
- **User experience**: User leaves site briefly to complete payment (industry-standard flow)

**Trade-offs**:
- ✅ **Pros**: Secure, simple, reliable, mobile-optimized, conversion-optimized, low maintenance
- ⚠️ **Cons**: User leaves site (but this is standard for payment flows), less UI customization

**Related**:
- Implementation: `app/Http/Controllers/Api/V1/Payments/StripeController.php`
- Frontend: `src/app/[locale]/ai/itinerary/page.tsx` (handlePurchase function)
- Webhooks: `app/Http/Controllers/Api/V1/Webhooks/StripeWebhookController.php`
- Documentation: `docs/ai/implementation/TASK_3_STRIPE_TEST_MODE_GUIDE.md`

---

## [2026-10-07] Project-wide Development Rules and Working Mode B

**Status**: Accepted

**Context**: The owner shared a general rules file from another project and asked to apply what makes sense for Beachrex. Until now, agent work had no single entry point, no handoff file, and no PR CI.

**Decision**:
- `docs/DEVELOPMENT-RULES.md` (backend repo, covers both repos) is the rules file. `CLAUDE.md` in each repo points to it.
- `docs/HANDOFF.md` holds the current state.
- **Mode B:** the agent merges green PRs into `dev`. Promotion `dev → uat → main` happens only via PRs and only when the owner asks.
- This file stays the project-wide ADR log, so there is no separate `03-DECISIONS.md`.
- `DEV_HANDOFF.md` stays the change log, so the full `docs/` restructure from the general rules is not adopted.

**Alternatives Considered**:
1. Copy the general rules file verbatim and restructure `docs/` into 00-MANIFEST/01-SPEC/02-ARCHITECTURE. Rejected: ~140 existing docs would have to move, and it adds churn without value.
2. Mode A, where the owner commits. Rejected: the owner explicitly asked the agent to open and merge PRs.

**Related**: `docs/DEVELOPMENT-RULES.md`, `docs/HANDOFF.md`, `CLAUDE.md`, `.claude/settings.json` (deny list for ssh/scp/rsync, force-push, env files)

---

## [2026-10-07] Production Database Is Created from UAT (No Laravel Cloud)

**Status**: Accepted (supersedes the "final pg_dump from Laravel Cloud" step in `docs/deployment/VPS_MIGRATION_PLAN.md` and `DEPLOYMENT_GUIDE.md` §9)

**Context**: Laravel Cloud no longer exists for this project. `beachrex.com` still serves an old site from another host. Only dev and uat run on the VPS.

**Decision**: At prod launch, `beachrex_prod` is loaded from a `pg_dump` of `beachrex_uat`, which holds the curated content. The procedure, including dry run and verification, is in the prod launch runbook. Test users, AI sessions and purchases created on UAT must be reviewed and cleaned before or after the load. That is an owner decision at launch time.

**Related**: `docs/runbooks/prod-launch.md`

---

## [2026-10-07] Itineraries: Code Plans, LLM Narrates. pgvector Deferred

**Status**: Accepted

**Context**: The owner reported that itineraries are of very poor quality and suggested pgvector. The diagnosis (TASK-001) found the main causes outside semantic search:
- filters on nonexistent columns;
- the model received almost no beach data and no coordinates;
- there was no geographic logic;
- no JSON mode, a fixed token limit, gpt-3.5-turbo.

Only 21% of beaches have descriptions (legacy data), so embeddings would have little text to work with.

**Decision**:
1. The **code** builds the plan: candidate beaches from real attributes (beach type, facilities, accessibility, user types, activities), k-means day clusters, nearest-neighbour order and haversine distances.
2. The **LLM** only writes the narrative around the fixed skeleton, in JSON mode. Beach facts in the response always come from the database.
3. Default model `gpt-4o-mini`, which is both cheaper and better than `gpt-3.5-turbo`. It can be overridden with `OPENAI_MODEL`.
4. **pgvector is deferred.** Semantic re-ranking of free-text preferences is a later, optional step. It will be evaluated against Meilisearch hybrid search, which is already deployed, once descriptions are filled in for more beaches.

**Alternatives Considered**:
1. Add pgvector now. Rejected: it fixes none of the root causes above. It also needs a custom Postgres image (PostGIS + pgvector) on the VPS and in CI, while there is too little description text to embed.
2. Let the LLM keep choosing and grouping beaches with better prompts. Rejected: the model has no reliable geographic sense and invents distances.

**Related**: `docs/tasks/TASK-001-itinerary-quality.md`

---

## [2026-10-07] ASCII-only Storage Paths: Convert New Values Only; ULID Upload Names

**Status**: Accepted

**Context**: TASK-002 prevention (§4) must stop new non-ASCII paths before the legacy keys are migrated. Legacy rows such as `beach.folder_name = "hr-114-o-brač"` still point to non-ASCII keys on Spaces. If a re-save rewrote them to ASCII, the images would break until the copy step runs.

**Decision**:
1. Models with storage paths use the `HasAsciiPaths` trait:
   - `Beach.folder_name`;
   - `Media.file_path` and `Media.file_name`;
   - `main_image` on City, Region, Island and Continent;
   - `Country.image_path`.

   On `saving`, it applies `AsciiPath::fromPath` **only to new or changed values**. An unchanged legacy value is left as is; `storage:ascii-paths` migrates keys and DB together.
2. Every Filament `FileUpload` names files `AsciiPath::uploadFileName()`: a ULID plus the ASCII, lowercase extension. This is Filament's default naming made explicit, so unique names stay and readable names are not reintroduced. A source-scan test fails if a `FileUpload` lacks it.
3. `article.feature_image`, `badges.icon` and `beach.path_to_beach_map_url` are free-text fields that may hold full URLs, which `AsciiPath` would break (`:` → `-`). They are not converted. The inventory still reports them.
4. The Filament `folder_name` field converts silently instead of rejecting input. The helper text explains the rule.

**Alternatives Considered**:
1. A mutator that always converts. Rejected: it would break images of not-yet-migrated beaches on any admin edit.
2. Readable upload names (`Plaza-Ciovo.jpg`). Rejected: name collisions would overwrite files, and today's names are ULIDs anyway.

**Related**: `docs/tasks/TASK-002-ascii-storage-paths.md`, `app/Models/Traits/HasAsciiPaths.php`

---

## [2026-10-07] Sea Temperature from Open-Meteo Marine

**Status**: Accepted (owner chose option B)

**Context**: `sea_temperature` was the OpenWeatherMap air temperature, shown on every beach page. The options were to show nothing ("--°") or to use a real sea surface temperature source.

**Decision**:
- Use the Open-Meteo Marine API (`sea_surface_temperature`) through `WeatherService`, cached for 3 h.
- An optional `OPEN_METEO_API_KEY` switches to the commercial endpoint. The free endpoint is licensed for non-commercial use only, so production needs a key (owner action).

**Alternatives Considered**:
1. `null` everywhere: honest, but an empty widget on every beach page.
2. Copernicus Marine or NOAA OISST directly: free for any use, but much heavier (gridded datasets, accounts, or ERDDAP queries) for one number per beach.

---

## [2026-10-07] Chat: Code Queries, LLM Understands and Narrates (TASK-003)

**Status**: Accepted

**Context**: Chat intent and filters came from English keyword matching. Without GPS, most questions got 0 beaches. Places, surface and facilities were never queried, and `filters_applied` was whatever the model claimed.

**Decision**:
1. A small first LLM call extracts the query with **strict structured output** (`response_format: json_schema`). Attribute codes and countries are enums built from the DB, so the model cannot invent codes.
2. Place names are resolved by the code (`PlaceResolver`) against city, island and region translations in every locale, with simple inflection handling. An unknown place becomes an assumption, never a guess.
3. Candidates come from the DB (`ItineraryContextBuilderService::fetchChatCandidates`, shared with TASK-001):
   - attribute filters are relaxed only when nothing matches;
   - accessibility is never relaxed.
4. The answer call only narrates the candidates. `filters_applied` comes from the code.
5. If the extraction fails, the old keyword detection (now with word boundaries) is the fallback; `metadata.understanding = fallback`.

Structured output was chosen over a tool-calling loop: one predictable extra call, easy to test and to fall back from.

**Alternatives Considered**:
1. Tool calling with the model choosing searches in a loop. Rejected: more calls and latency, harder to test, and the DB query is deterministic anyway.
2. Translating the keyword lists into 19 languages. Rejected: brittle, misses inflections and paraphrases.

**Related**: `docs/tasks/TASK-003-chat-query-understanding.md`

---

## [2026-10-08] TASK-002: Migrate the DB on Dev Only; uat Gets the dev DB

**Status**: Accepted (owner, 2026-10-08: "popisa na uat ne potrebujemo ker je identično kot dev. In bomo naredili promote dev baze na uat, da uredimo zapise. ne rabimo kaj ločeno početi.")

**Context**: The runbook planned an inventory and `--update-db` per environment (dev, then uat). The Spaces bucket is shared, and the owner says the uat data is the same as dev.

**Decision**:
1. The inventory runs on dev only (done 2026-10-08: 0 collisions).
2. `--copy` (shared bucket) and `--update-db` run on dev only.
3. uat gets its paths by a promotion of the dev DB to uat (owner action). Anything that exists only in the uat DB is overwritten by that promotion.
4. `--delete-old` runs only after the uat DB holds the migrated dev data. Prod is created from uat, as in the prod launch runbook.

**Alternatives Considered**: A separate `--update-db` on uat. Not needed, because the dev DB replaces the uat DB.

**Related**: `docs/runbooks/ascii-storage-paths.md`, `docs/tasks/TASK-002-ascii-storage-paths.md`

---

## [2026-10-08] TASK-002 Cleanup: No Spaces Bypass for Non-ASCII Image Paths

**Status**: Accepted (agent's reading of TASK-002 §5 "keep a test that rejects non-ASCII paths"; owner decision 2026-10-07: all images only from `s3.beachrex.com`)

**Context**: Step 6 deleted the 10 685 old non-ASCII keys on 2026-10-08. The control inventory shows 0 non-ASCII keys and 0 non-ASCII DB values. The Imagor bypass in web-client `buildCdnUrl` and MCP `cdn()` linked non-ASCII paths directly to Spaces; those links can no longer resolve.

**Decision**: Both builders always use Imagor and never link Spaces for a non-ASCII path. MCP: such a path gets no URL (empty string), and empty gallery entries are dropped. web-client: such a path is percent-encoded and sent to Imagor (it will 404). `buildCdnUrl` keeps returning a string for a non-empty path, because callers use `!` and a `null` would crash the page (QA #55/#56). Tests guard both.

**Alternatives Considered**: Transliterating the path on the client (would duplicate `App\Support\AsciiPath` in TypeScript and hide data errors); sending the path to Imagor URL-encoded (always 404 now).

**Related**: `docs/tasks/TASK-002-ascii-storage-paths.md` §5, `docs/runbooks/ascii-storage-paths.md`

---

## [2026-10-08] TASK-004: What Visitors See at Launch

**Status**: Accepted (owner, 2026-10-08, answers to the TASK-004 audit)

**Context**: The pre-launch audit (`docs/tasks/TASK-004-audit.md`) found features that are only shells: dead buttons, 404 links and fake data.

**Decision**:
1. Hide every unfinished feature for launch: accommodation (menu, search bar, home, footer, page sections), favorites, my account, shop, blog, the GetYourGuide section. Each hidden item is listed in `docs/features/hidden-for-launch.md` so it can be built later.
2. `/beach-type` and `/user-type` stay as routes but are `noindex` and leave the sitemap.
3. Footer `/destinations` → `/beaches`. "About us" (`/about` and the quick menu) → the CMS page `about-us`.
4. The burger menu is built from API data (continents) plus the beach map and the AI pages.
5. Home counts come from the API; the fake accommodation count is removed. A short SEO site description is written for 19 locales.
6. Stripe is live at launch, so the payment pages are fixed and translated.
7. AI crawlers: search/answer bots are allowed (e.g. `OAI-SearchBot`, `ChatGPT-User`, `PerplexityBot`, `Claude-SearchBot`, `Claude-User`); training bots are disallowed (e.g. `GPTBot`, `ClaudeBot`, `Google-Extended`, `CCBot`).
8. Analytics and ads load only through CookieYes consent (Google Consent Mode v2, default denied): Google Analytics `G-WK9LGF32VW`, AdSense `ca-pub-5133469552990533` (owner sent both), the CookieYes banner script. IDs are public client IDs and live in `NEXT_PUBLIC_*` vars. The Facebook Pixel snippet the owner sent is commented out on the old site and is not added unless the owner asks. The cookie policy page embeds the CookieYes cookie table shortcode.

**Alternatives Considered**: Building the missing features before launch (not possible in days); leaving the buttons visible (dead ends hurt trust and SEO).

**Related**: `docs/tasks/TASK-004-pre-launch-review.md`, `docs/tasks/TASK-004-audit.md`, `docs/features/hidden-for-launch.md`

---

## [2026-10-09] Mobile: App Stack and Scope (TASK-005)

**Status**: Accepted (owner, 2026-10-09: "React Native, NativeWind, EAS – Expo dev … bistvo je iskanje plaž … grafika po našem CGP-ju, dober UX"; details are the agent's reading, owner may change them)

**Context**: Beachrex needs an iOS/Android app focused on finding beaches. The web (Next.js) and the API (`/api/v1`) exist; a second agent ("mobile agent") will build the app while the web agent continues the website.

**Decision**:
1. Separate repo `Beachrex/mobile-app`; Expo (latest stable SDK at start), Expo Router, TypeScript strict, NativeWind v4, TanStack Query, API client generated from the backend OpenAPI, `use-intl` with the web's ICU message format, MMKV, expo-image, react-native-maps + supercluster, EAS Build/Submit/Update. Plan: `docs/mobile/MOBILE-PLAN.md`; tasks: `docs/mobile/MOBILE-TASKS.md`; roles: `docs/AGENT-ROLES.md`.
2. v1 = public data only (discover, search, map, filters, beach and place pages, saved on device, 19 languages). No accounts and no payments in v1: paid digital content in the app must use In-App Purchase, and Google sign-in requires Sign in with Apple; both are phase 2 with backend work.
3. CGP v1.0 tokens; text in Deep Sea because white on teal/orange fails WCAG contrast (2.6:1).
4. `/api/v1` becomes additive-only once the app is in a store (`DEVELOPMENT-RULES.md` §9).

**Alternatives Considered**: Bare React Native (more native maintenance, no EAS Update convenience); a WebView wrapper of the website (poor UX, store rejection risk); Flutter (no code/knowledge reuse with the web's React/TypeScript).

**Related**: `docs/mobile/MOBILE-PLAN.md`, `docs/mobile/MOBILE-TASKS.md`, `docs/AGENT-ROLES.md`

---

## [2026-10-09] AI chat and itinerary: plan to make them sellable (AI-1 … AI-4)

**Status**: Accepted (owner, 2026-10-09: "AI chat mora delat v nulo … AI načrtovalec potovanj mora delat 100% … strinjam se z vsem kar si napisal, nadaljuj, uredi")

**Context**: Audit 2026-10-09: the chat gave no working beach links (the model's invented `slug` overwrote the real one), had no memory (the page never sent `session_id`), a stale prompt ("2,445+ beaches in 3 countries"), no images, refused accessibility questions (402 for everyone), and answered from 2-3 beaches. The MCP server answers much better because the model calls tools that return ready links. The paid itinerary flow is not sellable yet (free-text destination silently becomes HR, 1-30 days vs 3/7/14, no saved plans, webhook/credit races, 60 s timeout risk, USD without VAT).

**Decision** (owner accepted all proposals):
1. **AI-1** quick chat fixes: database fields only (model gives ids + match_score), page-language names/slugs, photo, sliding 30-min session tied to the user, beaches shown kept in the history, JSON mode, current prompt, accessibility answered for everyone, 503 `ai_unavailable` for provider errors; web page sends session/locale/token and links beach names.
2. **AI-2** chat as a tool-calling agent with the MCP tools (ready links), streaming, and a fixed evaluation set (~40 questions, several languages). Compare OpenAI (stronger model) with Claude on that set and pick by quality and cost; Claude needs an Anthropic API key in the server env files (owner).
3. **AI-3** sellable itinerary: destination/region picker from the API, 3/7/14 with prices, interest chips; webhook purchase+grant in one transaction, refund idempotency, credit reserved atomically, no credit for a fallback result; generation in the background with progress; saved plans ("My plans" + credits); links, map, photos, PDF/share.
4. **AI-4** selling in the EU with the owner: prices in EUR (19/39/59 kept unless the owner changes them), separate 3/7/14 packages shown simply, Stripe Tax/VAT and invoices, withdrawal-right waiver for digital content, pricing page, terms review.
5. The chat stays free for everyone, protected by the daily cost circuit breaker.

**Related**: `DEV_HANDOFF.md` (2026-10-09 AI chat entries), `docs/HANDOFF.md` (AI plan), `docs/tasks/TASK-001*`, `docs/tasks/TASK-003*`

## [2026-10-09] Paid itineraries run in a queue worker (AI-3)

**Status**: Accepted (part of the AI plan the owner accepted on 2026-10-09).

**Context**: A 7- or 14-day plan takes 20-120 s (candidate search, planner, LLM narrative with one retry). PHP-FPM stops every web request at 60 s (`request_terminate_timeout`, Dockerfile.prod) and nginx waits 60 s (`fastcgi_read_timeout`), so long plans could die after the credit was taken. `defer()` does not help: deferred work runs in the same FPM request and the same 60 s limit applies. There was no queue worker on the server.

**Decision**:
1. A `worker_<env>` container per environment (deploy/docker-compose.vps.yml) runs `php artisan queue:work itineraries --queue=itineraries --tries=1 --timeout=240`. It reuses the api image, like `scheduler_<env>`. The deploy recreates it after `migrate`/`config:cache`.
2. A dedicated `itineraries` queue connection (database driver, `retry_after` 300 s > worker timeout 240 s). The job sets that connection itself, so `QUEUE_CONNECTION` in the server env files does not matter.
3. `itinerary_plans` table: a plan is queued with its credit reserved; the job writes the result. One attempt only, because a retry would call the LLM again.
4. The credit goes back exactly once (row lock + `credit_charged`) when the plan fails, has too few beaches, has no AI narrative, or is stuck. A plan is stuck when it has been running for more than 10 minutes or queued for more than 30. The scheduler checks for stuck plans every 5 minutes, and so does `GET /me/itinerary-plans/{id}`.
5. The synchronous `POST /ai/itineraries` stays for the 1-day/free path and for older clients.

**Consequences**: One more PHP process per environment (one plan at a time per environment). More workers can be added later if plans queue up.

**Related**: `app/Jobs/GenerateItineraryPlan.php`, `app/Services/AI/Itinerary/ItineraryPlanService.php`, `tests/Feature/AI/ItineraryPlanTest.php`

---

---

**End of DECISIONS.md**

This file is updated by: All AI agents making architectural decisions
This file must be updated: When making any significant design decision
This file is read by: All new AI agents to understand past decisions

---

## [2026-10-09] Data Quality Pass with Claude Batches (`content:ai`)

**Context:** Most beach names carry an English "Beach" added by the import, in every language; region names are English in most languages; about 21% of beaches have a description. The owner asked to fix this with AI ("z AI prevajaj").

**Decision:**
- One artisan command, `content:ai`, with four parts (names, regions, descriptions, generate). One Claude request per item, sent through the Message Batches API (half price, parallel, results keyed by `custom_id` = `<part>-<id>`, so no local state between submit and apply).
- Model `claude-opus-5-5` at effort `low` by default, configurable (`AI_CONTENT_MODEL`, `AI_CONTENT_EFFORT`); structured JSON output per request.
- Names: proper names stay in the local language and spelling for Latin-script languages and are transliterated for ua/ru/bg/el; names are not translated.
- Generated descriptions use only database facts (no distances, history, ratings).
- Writes keep slugs (no URL changes), never overwrite human text, and run only with `apply --write` after a dry run; runs on dev only, uat/prod get the data by DB promotion.

**Consequences:** One-off cost (rough estimate printed by `content:ai plan`). The owner reviews `preview` output before submitting. Descriptions written by AI are marked nowhere; if that becomes a legal or editorial need, add a column.


## [2026-10-14] Windy webcams: local copy, local matching, direct iframe (TASK-006)

**Context:** `beach.webcam` holds pasted embed HTML from the old site. Of 3 774 rows, 250 carry a Windy/Lookr webcam ID (237 unique); on 2026-10-14 only 73 of those IDs still render, 95 are dead and 69 have embedding disabled. The web never embedded them (it opens the `href`, mostly `lookr.com`). Several cams show a neighbouring place.

**Decision (owner, 2026-10-14):**
- Windy Webcams API v3 is used **only by batch commands** (import, monthly refresh, legacy ID lookup). Metadata goes into `windy_webcams` (never image URLs); assignments into `place_webcams` with score, breakdown, source and review status. The public site never calls the API.
- Matching is local (PostGIS), **max radius 3 km**, weights and thresholds in one config file (auto ≥ 70 and +15 over the runner-up, pending 40–69). One webcam is assigned to at most one place. Approved, rejected and manual rows are never overwritten; rejected pairs are never re-proposed.
- A webcam must pass the public player check (`embed_status = ok`) before it is auto-approved or rendered, because cameras with embedding disabled still exist in the API.
- The web renders the **iframe directly** (`https://webcams.windy.com/webcams/public/embed/player?webcamId=…&playerType=day`), the same iframe Windy's `player.js` builds, lazy-loaded, with the Windy link kept. No third-party script.
- Webcams from other providers (WhatsUpCams, Webcamgalore) are **not shown** any more.
- `beach.webcam` stays (API field and DB column) until the owner approves its removal; old app builds keep working.

**Consequences:** The first import needs `WINDY_API_KEY` on dev and runs as an artisan command there (the agent sandbox cannot reach `api.windy.com`). Free tier caps one filter at 1 050 results, so the importer slices by region/bbox. Professional tier only if that or player ads become a problem.

**Related:** `docs/tasks/TASK-006-windy-webcams.md`, `docs/technical/windy-webcams-api.md`

## [2026-10-14] Admin guides in Slovenian, updated with every feature

**Context:** The admin had developer docs only (implementation notes, runbooks in English). The owner wants user instructions for every admin function, to be collected into one admin manual at the end.

**Decision (owner, 2026-10-14: "ja uredi to za admina ja. prvo naredimo slovenščina ja, če bomo kdaj rabili bomo kasneje angleško"):**
- `docs/guides/admin/` holds one Slovenian page per admin feature (admin page, action or owner command) and an index (`README.md`) that lists every feature and the status of its guide.
- Every PR that adds or changes an admin page, admin action or owner command updates its guide in the same PR (DEVELOPMENT-RULES §7 and the before-merge checklist). This is the one exception to "docs in English".
- Runbooks stay in English for one-off technical procedures; admin guides link to them instead of copying commands.

**Consequences:** The manual can be generated from the index at any time. An English version is a later, separate task.


## [2026-10-21] Beach texts for search engines and AI assistants: snippet, intro, FAQ (TASK-007)

**Context:** The owner previewed `content:ai --part=generate` (Livade i Obatnice, Ušće Neretve): every text listed the database fields in the same order, repeated the page icons (parking, road, transport), and gave search engines and AI assistants nothing to quote as a direct answer. On ~2 800 beaches that is one template.

**Decision (owner, 2026-10-21, "za opise se strinjam, čimveč"):**
- `generate` returns per language a search snippet (≤ 155 characters), an intro without logistics and 3–5 FAQs (question names the beach, answer starts with the direct answer, only questions the facts answer). New part `seo` writes snippet + FAQ for beaches that already have a description (the description is a source, never changed).
- Facts gain computed context from our own data: distance to the town centre, number of beaches in the town, the nearest 3 active beaches within 10 km. Nothing else about distances may be invented.
- The opening angle rotates by beach id (4 angles), so neighbouring pages do not read alike.
- Two tiers: the `AI_CONTENT_FEATURED` (default 200) most viewed beaches get 90–150 words and 5 FAQs; the rest 50–80 words and 3–4 FAQs.
- Storage: `beach_translation.meta_description`, `beach_translation.faq` (jsonb `[{q, a}]`), written only where empty, editable in the admin. The API returns them for the requested language only (no English fallback).
- Web: FAQ section + `FAQPage` JSON-LD; the snippet is the meta description.

**Consequences:** About 2.5× the token cost of the old `generate` (FAQ in 19 languages); the owner checks `plan` and can switch to Sonnet. A wrong-language fallback is avoided at the price of empty FAQ for languages the pass has not reached.



## [2026-10-22] Lake beaches from the old database: fix in place, no re-import

**Context:** In the old database (`database/sql/beachrex_old.sql`) every beach has `waterbody_id = 3` (sea). Lake beaches are marked only by `location_id = 3` (`l_lake`) and `lake_name`. `beaches:migrate-from-old-db` copied `waterbody_id` 1:1 and ignored `location_id`, so 42 lake beaches (33 NL, 7 BE, 1 FR, 1 GR) are "Sea" on dev/uat/prod and missing from the lake filter. Checked on dev via MCP: 4023 Strand Schildmeer and 4166 Strand Nieuwdonk report "Waterbody: Sea".

**Decision (owner, 2026-10-22: "ker ne bomo ponovno uvažali cele stare baze, moramo posodobiti obstoječo našo bazo"):**
- A one-off owner command `beaches:fix-lake-waterbody` updates the existing rows; the importer is not changed and the old DB is not re-imported.
- The list of beaches is a reviewed file in the repo (`database/legacy/lake-beaches.json`: the 42 rows with `location_id = 3` **and** a `lake_name`). The two `location_id = 3` rows without a lake name (2546 Playa Bahía Azul, 2649 Praia de Ribeira d'Ilhas) are sea beaches and stay out.
- Agent's reading: a command, not a migration, because a data migration would run on prod automatically at promotion, while DEVELOPMENT-RULES §9 requires dry run → real run → verification with the owner. Dry run is the default; it writes only with `--apply`. Idempotent.
- It sets the `lake` waterbody (looked up by code), links a missing `lake_id` to the lake with the same English name (or the old slug as code), creating the lake if needed. An existing `lake_id` with another name is reported, never changed. Saves go through Eloquent (model cache + Scout), then `CacheService::clearAll()`.

**Consequences:** The owner runs it once per environment. The 4 old river beaches (`location_id = 4`: two Drava test beaches, two on the Scheldt estuary) are out of scope. The old DB spells one lake two ways (Grevelingenmeer / Gravelingenmeer), so two lakes can exist for it; merging needs a lake field in the beach admin form (not there today).


## [2026-10-23] After the prod launch: code moves by merges, data by migrations, no DB promotion

**Status**: Accepted (owner, 2026-10-23: "Na zacetku prenesemo bazo na vsa okolja pol pa se tk z mergi v dev uat in main deploya tam pa tk mamo pol migracije ki se morajo pognati ob deploju" — "tako ja")

**Context**: Until the launch, uat and prod got their data by copying the dev/uat database (ADR 2026-10-08, TASK-002; prod launch runbook Phase B). After the launch the prod database holds real users, favorites and purchases, so it can never be overwritten again. An admin "promote" button was considered and dropped: branching already moves the code.

**Decision**:
1. The database is copied to the environments once, at the launch (prod from uat, runbook Phase B). After that, no environment's database is copied over another one's.
2. Code moves only by PRs `dev → uat → main`; each deploy runs `php artisan migrate --force`.
3. **Data changes that every environment needs are migrations** (idempotent, matching current values, never overwriting text a person edited), e.g. `2026_10_23_060000_fix_hero_banner_title`.
4. **Commands and admin edits write only the environment they run on.** Content work after the launch (`content:ai`, `translations:ai`, `webcams:import`, admin edits) runs on **prod**, by the owner: dry run first, then `--write`/`--apply`, then `cache:clear-api --type=all` and `search:index`. dev and uat may lag behind prod's content; that is acceptable. When a test environment needs fresh content, it may be refreshed *from* prod (never the other way), after removing personal data.

**Consequences**: Runbooks that said "runs on dev only, uat/prod get it by DB promotion" are updated (`content-ai.md`, `prod-launch.md`). Agents write data fixes as migrations, not as one-off commands on dev.

## [2026-10-28] Homepage media example: one real beach, chosen in the section config (TASK-008)

**Status**: Accepted (agent's reading of the owner's request 2026-10-28: "da dejansko vidiš na primeru ene plaže kako zgleda"; owner may pick another beach or presentation)

**Context**: "View beaches from all angles" was four static cards. The owner wants visitors to see the media on one real beach. The beach must be changeable without a deploy; the homepage must stay fast and never lose the section.

**Decision**:
1. The example beach is `config.showcase_beach_id` of `first_page_content` `beach_views` (existing admin KeyValue field). Default 989 (Svetilnik, Izola) by an idempotent migration that never overwrites an admin value.
2. The web loads that beach's details server-side (ISR 300 s, `NOT_A_VIEW_HEADERS`) and renders cards from its own photos; clicks open the beach page's `MediaOverlay` with the same content components. No iframe before a click.
3. Any failure (no id, unknown beach, no media) falls back to the static cards from admin Items.
4. Saving a `FirstPageContent` row clears the homepage API cache in every locale (as a translation save already did), so config edits are visible within the web's 5-minute ISR.

**Consequences**: Admin guide `prva-stran.md` explains how to change the beach. The static Items stay as fallback content and are still edited in admin.



## [2026-10-28] Search counts are real totals; a place name scopes the result

**Status**: Accepted (agent's reading of the owner's report 2026-10-28: "za Francijo, ki ima ~400 plaž, kaže 10 plaž … ne prikazuje dejanskega stanja")

**Context**: The search dropdown showed per-type counts equal to the items returned (`limit` 10), so "France" read as
10 beaches and 10 towns. The owner expects "France" to mean the beaches in France. Plain full-text matching also
counts and lists documents that only mention a similar word (an Italian beach describing the church of San Francesco
matches "france" as a prefix).

**Decision**:
1. Every type returns its **total** matches (`meta.totals_by_type`, `meta.total_matches`) from the search engine's
   exhaustive count for the same query and filters (`page` + `hitsPerPage`), never by loading the documents.
   `pagination.maxTotalHits` is raised so counts above 1000 are exact.
2. **Place scope**: types are searched top-down (country, region, island, city, beach). The first type with exactly
   one result whose name in any language equals the query (case, diacritics, extra spaces ignored) becomes
   `meta.place`; the types below it are filtered to that place by id/code. "France" → beaches, towns, islands and
   regions in France; "Toscana" → beaches, towns, islands in Toscana. Prefixes ("fran") and ambiguous names (two towns
   with the same name) do not scope; the result is a plain search.
3. Names and places rank above descriptions in the beaches index (`searchableAttributes` order).
4. Only the requested `types` take part (a caller asking `types=beach` gets a plain beach search).
5. `/api/v1` stays additive: `results_by_type` and `total_results` keep their meaning (items returned).

**Alternatives**: DB `COUNT` per place (exact even with a stale index, but list and count could disagree and it needs a
second query path per type); counting full-text hits only (counts the San Francesco noise); always showing "10+"
(not what the owner asked).

**Consequences**: The web shows `totals_by_type` in the tabs; "All" is `total_matches`. Counts follow the index; the
deploy re-applies settings and re-indexes, and new place-id fields need that re-index before scoped searches work.


## [2026-10-28] Search in a named place counts like the place pages (amends "Search counts are real totals")

**Status**: Accepted (follow-up on dev 2026-10-28: "toscana" showed 43 beaches but 0 towns; the Toscana page shows 21 towns)

**Context**: Point 2 of the previous ADR filtered the lower types through the search index (`region_id`, `island_id`,
`city_id`). On dev the city and island documents did not carry those new fields, so every region or island scope
counted 0 towns and 0 islands (Toscana 0/21 towns, Kvarner 0/4 islands, Krk 0/19 towns) while beaches were right.
A count that silently depends on a re-index is fragile, and the place pages count from the database.

**Decision**: Once the query names a place (detection unchanged, via the index), the types below it are read from the
database with the place pages' rules: active beaches (`status_id = 1`), and towns/islands/regions with at least one
active beach, filtered by `country_code` / `region_id` / `island_id` / `city_id`. Beaches are listed most viewed first
(as "Browse all beaches"), places with the most active beaches first. Plain queries still use the index.

**Consequences**: Search totals equal the page numbers (`/regions/{slug}` `beaches_count`, `/cities?region_code=`,
`/islands?region_code=`, `/cities?island_code=`). The index fields from the previous ADR stay (harmless, filterable)
but nothing depends on them. On dev, the region page's "All beaches (20)" button was the popular-beaches carousel
expander labelled with the number of carousel cards; the web fix shows the region's real total there.

## [2026-10-29] AI guide on/off is an admin setting per environment (TASK-009)

**Status**: Accepted (owner 2026-10-29: "bi ga na prod izklopil" until Stripe is on prod and the guide is tested)

**Context**: The only switch was `AI_ENABLED` in the server `.env` — a server action, and it did not hide the guide on the website.

**Decision**: Table `site_settings` with key `ai_assistant_enabled` (default on), edited in admin (System → Site settings). Off: website hides the menu entry and `llms.txt` line and answers 404 on `/ai/*` and `/payment/*`; API answers 503 `ai_disabled` on `/api/v1/ai/*` and Stripe checkout. Public read: `GET /api/v1/site-settings`. `AI_ENABLED` stays as the server-level kill switch.

**Consequences**: Future site switches go into the same table and page. The mobile app should read `/site-settings` and hide its AI entry when off.

**Amendment (2026-10-29, owner: "želel sem skriti trip planner, ker je plačljiv, ne pa chat … da lahko v adminu izklopim eno ali drugo ali pa oboje")**: two independent switches. `ai_assistant_enabled` now means the **AI chat** only (chat API, chat page, chat widget; 503 `ai_disabled` on `POST /ai/chat` and `/ai/test`). New `ai_trip_planner_enabled` (default on, idempotent migration) covers the **paid trip planner**: `POST /ai/itineraries`, `POST /ai/itinerary-plans`, `GET /payments/products`, `POST /payments/stripe/checkout` answer 503 `ai_trip_planner_disabled`; website planner pages (`/ai/itinerary/*`), `/payment/*`, the menu entry and links to the planner are hidden. The Stripe webhook, `/me/entitlements` and `/me/itinerary-plans` are never switched off (payments already started must complete, bought plans stay readable through the API). `/ai` and the "AI Beach Assistant" menu group disappear only when both are off. `GET /api/v1/site-settings` returns both keys (additive; old clients reading only `ai_assistant_enabled` now see the chat state).

**Amendment (2026-10-29, owner: "prosim izklopi mi Google ads na produkciji, pa dobro bi bilo, da bi jih lahko prek admina vklapljali ali izklapljali")**: a third, independent switch `ads_enabled` for Google ads (AdSense) on the website. Unlike the AI switches it defaults **off**: the idempotent migration inserts `0` when the key is missing (keeps a value set in admin), and a missing key reads as off, so ads stop on prod with the deploy and the owner switches them on in admin. `GET /api/v1/site-settings` returns `ads_enabled` (additive). The website uses AdSense Auto ads (no ad slots in our markup), so the switch only decides whether the AdSense script is loaded: prod only (unchanged) **and** `ads_enabled` true; consent handling (Consent Mode defaults + CookieYes) unchanged; an unreadable API means no ads (fail closed). Read server-side with the same 5-minute revalidation as the other switches. `public/ads.txt` stays published regardless (harmless without the script, and AdSense needs it to verify the publisher when ads are switched back on).


## [2026-10-29] Watermark mobile-app photos on the fly (supersedes the 2026-10-08 "no watermark" note for these photos)

**Status**: Accepted (owner 2026-10-29: most beaches have photos with our logo, but not all, e.g. Plage du Port Vieux, Biarritz; approved adding the watermark to the photos that lack it)

**Context**: Photos uploaded in admin (`img/beaches/…`) have the Beachrex logo baked in at the bottom right. Photos
uploaded from the mobile app (`img/beaches_mobile/…`, 839 of 2 876 beaches on dev) have none. On 2026-10-08 the owner
had said "no watermark" (recorded in HANDOFF / DEV_HANDOFF, not as an ADR); this request reverses it for these photos.
Images are served by Imagor (`https://s3.beachrex.com/unsafe/<ops>/<path>`), which supports a `watermark` filter that
loads the logo from the same bucket.

**Decision**:
1. Imagor draws the watermark when a mobile-app photo is shown; the originals on the bucket are not changed.
   The website adds `filters:watermark(img/watermark/beachrex-watermark-v1.png,-3p,-4p,0,20,30)` (bottom right,
   3 %/4 % margin, at most 20 % of the width) in its one URL builder (`buildCdnUrl`, plus the share image URL).
2. Only raster photos under `img/beaches_mobile/`. Never admin photos (logo already baked in), icons, videos, 360° tours
   (`/vtour/`) or panorama/sphere images (a logo on an equirectangular image looks wrong in a viewer).
3. The watermark is `resources/watermark/beachrex-watermark-v1.png` (white horizontal logo from the brand package +
   "www.beachrex.com" in Inter, soft shadow; generator `resources/watermark/make_watermark.py`). Every deploy runs
   `media:publish-watermark`, which uploads it to `public/img/watermark/beachrex-watermark-v1.png` (public) only if
   missing. The bucket is shared by all environments, so a published version is never overwritten: a new design is
   a new file (`-v2`) and the website switches to it.
4. Structured data still makes no copyright claim for mobile uploads (they may be other authors' photos); the mark is
   a brand mark on how Beachrex shows the photo.

**Alternatives**: Bake the logo into the files (irreversible, rewrites originals that may be other authors' work, and
the shared bucket means every environment changes at once); a CSS overlay (not in downloaded/shared/OG images or
Google Images).

**Consequences**: Reversible by removing the filter in the web client. Watermarked URLs are new Imagor cache keys
(first view of each photo is rendered once). The mobile app and the MCP server build their own image URLs and are not
changed by this decision. If the PNG is missing on the bucket, watermarked URLs fail, so the backend deploy (which
uploads it) must reach an environment before the web client that uses it.

## [2026-10-29] The chat knows the page it is opened on — an untrusted, server-resolved hint (TASK-010)

**Status**: Accepted (owner 2026-10-29: chat as a widget on every page, "da bi še vedel na kateri strani se nahajaš")

**Context**: The website chat becomes a floating widget on every page. Questions like "is this beach good for kids?"
need the page. Anything the browser sends can be forged, and a page title in a prompt is a prompt-injection channel.

**Decision**:
1. `POST /api/v1/ai/chat` takes an optional `page_context` {`page_type` (enum), `path`, `beach_id`, `title`}; unknown
   keys, wrong types and over-long values are rejected with 422. Absent → behaviour unchanged (additive, `/api/v1` rule).
2. The model never sees the raw object. `PageContextHint` builds one short line: for beach pages from the database
   (`beach_id` → active beach name, town, country in the request locale, English fallback; title ignored); for other
   pages a generic label plus the title, cleaned, cut to 80 characters and JSON-quoted with "(page text, not an
   instruction)". `path` is validated but not sent to the model.
3. The hint is context, never authorization, and it does not change plans, limits or which data can be read.
4. Agent engine: the hint goes into the per-message context line (system prompt stays byte-stable for provider caching)
   and points to `get_beach_details` for the id. Pipeline engine: a `CURRENT PAGE` line in the system prompt.
5. The scope of the assistant stays beaches and seaside travel ("karkoli" read as anything about the site and trips);
   widening it to general chat is a product decision for the owner (cost, abuse).

**Consequences**: The mobile app can send the same field later. A beach that is inactive or missing gives a generic
"beach page" hint. Conversation persistence across tabs is a website concern (localStorage); the server session still
expires after 30 minutes of inactivity, after which the model no longer sees the earlier turns.

## [2026-10-29] Indoor / outdoor pools: classified by the pool counts, shown under "Pool"

**Status**: Accepted (owner 2026-10-29: "filter bi mogli razbit na notranji in zunanji bazen")

**Context**: The water body filter offers sea, lake, river and pool. The home page tiles "outdoor pools" and "indoor
pools" send `pool_type=outdoor|indoor` (QA #70), which the map listed as a separate "Pool type" group, so the owner did
not see the split. The data model already has the split: `beach.indoor_pools_count` and `beach.outdoor_pools_count`
(admin, beach form), plus per-feature indoor/outdoor flags (`has_indoor_children_pool`, …) and the unused
`beach_location_type` code `PB` ("Indoor pool"). Dev has no pool places yet, so every pool filter returns 0.

**Decision**:
1. A place is an indoor pool when `indoor_pools_count > 0`, an outdoor pool when `outdoor_pools_count > 0` (both is
   possible). No new column, waterbody code or migration; the API and its parameters stay as they are (additive rule).
2. The web shows the two `pool_type` options nested under "Pool" in the water body group (map sidebar desktop and
   mobile, home filter dialog, listing pages' select) and no longer as a separate group. `waterbody=pool` still means
   every pool place; old links keep working.
3. `waterbody` and `pool_type` combine with AND, like every other pair of filters: "Sea" + "Indoor pool" finds sea
   beaches with an indoor pool (a hotel), not "sea beaches or indoor pools". Changing that would change the meaning of
   an existing API parameter (the mobile app uses `pool_type` too) and needs an owner decision.
4. Classification is content work: whoever imports or edits pool places (the planned OSM/official-site pool importer,
   or the admin) sets the two counts. A pool place without counts is found by "Pool" but by neither pool type.
   The agent does not guess counts from names or location types.

**Consequences**: The tiles and the nested options show results as soon as pool places with counts exist. The
importer must map indoor/outdoor (e.g. OSM `indoor=yes`, `covered=yes`, `building`) to the counts.

## [2026-10-29] AI chat daily cost cap and Turnstile (supersedes the limits in the 2025-12-22 circuit breaker ADR)

**Status**: Accepted (owner 2026-10-29: "Anthropic uporabljamo … brezplačni uporabniki 5 $ na dan; če je preseženo,
se chat izklopi in se naslednji dan avtomatsko vklopi … Turnstile ja")

**Context**: The circuit breaker limits were $500 (free) / $5,000 (all) per UTC day, far above what a small site
should spend. The live chat runs on Anthropic (`AI_CHAT_ENGINE=agent`, `AI_CHAT_PROVIDER=anthropic`, Claude Opus
5.5). A limit is only as good as the cost it counts, and the chat had no bot protection.

Audit of the cost before this change: the agent engine summed every tool round with correct Opus 5.5 prices ($4 /
$20 per MTok) but priced cache reads at 0.1x instead of 0.05x (over-count), priced every round by the configured
model even when a server-side fallback served it, priced an unknown Claude model as Opus without a warning, and lost
the cost of rounds already billed when a later round failed. The pipeline engine and the trip planner (still OpenAI
via `LLMService`) lost the understanding call's cost when the answer call failed, counted only the last of two
itinerary narration attempts, and priced an unknown OpenAI model as gpt-3.5-turbo (cheaper than most). The public
`POST /ai/test` called the LLM without recording any cost. The trip planner's paid plans counted toward the free
limit and were stopped by it.

**Decision**:
1. Limits: free chat **$5** per day (`AI_FREE_TIER_DAILY_LIMIT`), everything **$20** per day
   (`AI_EMERGENCY_SHUTDOWN_LIMIT`, the agent's suggestion; owner can change by env). Free bucket = free-tier usage
   except `event_type=itinerary`. The trip planner routes check only the emergency limit.
2. Day = calendar day in `AI_DAILY_LIMIT_TIMEZONE` (default **Europe/Ljubljana**, the owner's day; `app.timezone`
   stays UTC). Every key and query is per local date, so the chat is back on at local midnight without any job.
3. One price table (`ModelPricing`) for every LLM call: Anthropic prices from the published table (checked
   2026-10-29), cache read/write multipliers per model, Haiku 5.5 long-prompt tier, each round priced by the model
   that served it; an unknown model gets the most expensive tier of its provider and a logged warning, never $0.
4. Every LLM call adds its cost to a request/job-scoped `AiSpendMeter`; the chat records it also when the request
   fails after billed calls, and the itinerary records all narration attempts. `POST /ai/test` records its cost.
5. The 5-minute cache of the daily sums is dropped on every new `ai_usage` row, so only requests already running
   when the limit is crossed can overshoot (a few cents).
6. A tripped limit answers 503 `{error: service_unavailable, reason: daily_limit, retry_after: <next local midnight>}`
   (+ `Retry-After` header); the website shows "resting until tomorrow". Once per bucket and day: a log warning and,
   if `AI_ALERT_EMAIL` is set, an e-mail (sent after the response).
7. **Cloudflare Turnstile** on `POST /ai/chat` and `/ai/test` (`TURNSTILE_SECRET_KEY`; empty = off): the website
   sends `turnstile_token` with the first message of a conversation (site key `NEXT_PUBLIC_TURNSTILE_SITE_KEY`,
   build time; empty = off). After one successful siteverify the session is remembered as verified for the session
   lifetime (sliding 30 min), bound to the visitor's IP hash. Missing/rejected token → 403 `turnstile_required` /
   `turnstile_failed`; the website re-runs the widget and resends once. Cloudflare unreachable or our secret rejected
   → the request passes (logged): an outage on their side must not take the chat down.
8. **Mobile app**: `TURNSTILE_REQUIRED_FOR=web` (default) checks only browser requests (they always send `Origin` or
   `Sec-Fetch-*`; React Native sends neither). `all` checks every client.
9. The trip planner is not behind Turnstile: it needs sign-in and a bought credit.

**Security trade-off (mobile)**: in `web` mode a script that sends no `Origin`/`Sec-Fetch-*` headers is not
challenged. Turnstile then stops browser abuse and naive bots only; a determined script is still bounded by the
per-IP rate limit and, finally, by the $5 daily limit (which also switches the chat off for real visitors until
midnight). To close the gap later: the mobile app gets its own proof (Turnstile in a WebView, or App Attest / Play
Integrity verified by the API), then `TURNSTILE_REQUIRED_FOR=all` (or a per-client rule).

**Consequences**: With Opus 5.5 an answer costs about $0.02–0.05 (evaluation 2026-10-09: ~$0.036), so $5 is
roughly 100–250 chat answers per day for all visitors together. When it trips, every visitor sees the resting
message until midnight. Cookie consent: Turnstile is a strictly necessary security service (no consent banner
category needed); the privacy policy should name Cloudflare Turnstile. The site sends no Content-Security-Policy
header today; if one is added, it must allow `https://challenges.cloudflare.com` in `script-src` and `frame-src`.

## [2026-10-29] AI chat history in the admin; retention is an owner decision

**Status**: Accepted (owner 2026-10-29: "v adminu vidimo, kaj vse se ljudje pogovarjajo v chatu in kakšne odgovore
daje — zgodovina, da se lahko izboljšujemo"). Clarifies the 2025-12-22 "No Long-Term User Memory" ADR, see below.

**Context**: The chat already stored every answered turn (`ai_messages`: question and answer text, intent, tool calls
or beaches in `context`, tokens) for both engines, anonymous and signed-in, with the session (`ai_sessions`) and the
cost row (`ai_usage`). Nothing deletes these rows: `expires_at` (30 min, sliding) only ends the conversation for the
model, and the cleanup jobs in `docs/ai/architecture/data-retention-policy.md` (30 days messages / 90 days sessions,
"APPROVED" 2025-12-23) were never implemented. Missing for a useful history: the engine, the page the chat was opened
on, a link from a turn to its cost/model, and turns that got no answer (the question was lost).

**Decision**:
1. Admin-only, read-only page **AI → Chat conversations** (`AiConversationResource`) over the existing tables; no
   create, edit or delete; the public API response is unchanged.
2. Store additively: `ai_sessions.engine`, `ai_sessions.page_context` (first page), `ai_messages.page_context`,
   `ai_messages.request_id` (= `ai_usage.request_id`, gives model and cost per turn), and failed turns as a user
   message with `ai_messages.error` (`ai_unavailable`, `internal_error`, `payment_required`). Failed turns are never
   sent to the model as history. Requests rejected before the controller (AI switched off, daily cost limit, rate
   limit, Turnstile) are not stored.
3. Privacy: chat text is shown only on this page (no global search, not logged anywhere else); the IP hash and user
   agent are not shown.
4. No automatic deletion is added. The retention period (proposal: 180 days, then delete the session and its
   messages; `ai_usage` keeps the cost without the session link) and the privacy policy wording are owner decisions.
   The repo's policy text (`database/content/static-pages/*.json`, "AI beach assistant") says questions and answers
   are "processed to generate a reply" and names only OpenAI; it does not say that conversations are kept and read
   to improve the service, and does not name Anthropic. Until decided, rows are kept indefinitely, as before.

**Consequences**: The model's memory rule (current session only) is unchanged; the 2025-12-22 "all user data is
deleted after the session expires" statement never matched the code and does not hold. A retention job, once decided,
is a scheduled command deleting old `ai_sessions` (messages cascade). Deleting one visitor's conversation on request
is a developer task until then.

**Amendment (2026-10-29, owner: "Hramba chatov ok", "Dopolni politiko")**: retention decided — **180 days** after a
conversation's last activity (`AI_CHAT_RETENTION_DAYS`, default 180; 0 = scheduled job off). `ai:prune-conversations`
runs daily at 01:40 Europe/Ljubljana: deletes expired `ai_sessions` and their `ai_messages` in chunks; their `ai_usage`
rows keep time, model, tokens and cost for statistics and the daily limits but lose `session_id`, and — the agent's
reading of data minimisation — also the IP hash and user agent (nothing reads them after the day is over). A session
with a message newer than the cutoff is kept even if `last_activity_at` is stale. Trip-planner data (purchases,
credits, itinerary plans) is not touched. The privacy and cookie policies (19 languages, static pages) now say that
chats are stored and reviewed by staff to improve the service (legitimate interest), kept 180 days, deletable earlier
on request to info@beachrex.com, and name Anthropic (OpenAI for some features such as the trip planner), Cloudflare
Turnstile (necessary security check) and Microsoft Clarity (analytics, consent only). The texts reach a database only
when the owner runs `static-pages:sync` on that environment (ADR 2026-10-23: no DB promotion). The legal wording was
written by the agent and should be reviewed by someone qualified. Database backups keep a deleted conversation up to
`BACKUP_KEEP_DAYS` (14) longer. `docs/ai/architecture/data-retention-policy.md` rewritten to match.


## [2026-10-29] Closed dev/uat websites behind an e-mail magic link

**Status**: Accepted (owner 2026-10-29: "dev in uat stran bi morali zapreti — vpišem svoj mail, dobim magic link,
potem se v sejo shrani, da lahko gledam; da ne bo kdorkoli vtipkal dev/uat in prišel na stran")

**Context**: dev.beachrex.com and uat.beachrex.com are reachable by anyone who types the address (only `noindex`
keeps them out of search engines). They show unreleased work and spend AI budget.

**Decision**:
1. Only the **web frontends** dev/uat are closed. Not production (`beachrex.com`, live), not the APIs (the mobile dev
   builds use dev-api; the API is not secret), not the admin (dock-dev/dock-uat have their own login).
2. Off unless `PREVIEW_GATE_ENABLED=true`: in `.env.front.<env>` (website) and `.env.<env>` (API). **Never** active
   on production, whatever the env says: the website checks `isProductionSite(NEXT_PUBLIC_BASE_URL)`, the API checks
   `FRONTEND_URL` against `beachrex.com` / `www.beachrex.com`.
3. Who may enter: the admins of that environment (`users.is_admin`) plus `PREVIEW_ALLOWED_EMAILS` (API env, comma
   list). `POST /api/v1/preview-access/request` answers the same for every address (no enumeration) and sends the
   e-mail after the response (same timing). One-time token: 64 random characters, only its SHA-256 stored
   (`preview_access_tokens`), 15 minutes, single use (atomic `UPDATE … WHERE used_at IS NULL`). Limits: 5 requests
   per minute per IP; per address 1 e-mail per minute and 5 per hour (silently skipped). `return_path` must be a
   relative path (422 otherwise). Both endpoints answer 404 when the gate is off for that environment.
4. The e-mail link opens `/preview-access/callback?token=…` on the website, a page with a button; the button POSTs
   the token to the website's `/preview-access/verify`, which calls `POST /api/v1/preview-access/verify` **server
   side** and sets its own cookie. The return path is stored with the token on the API (not in the link) and
   sanitized again by the website.
   *Deviation from the first sketch (link → route handler that verifies on GET):* mail link scanners (Outlook Safe
   Links, corporate gateways) open every URL in an e-mail and would use up a single-use token before the person
   clicks. A GET that only shows a button cannot be spent that way.
5. The cookie `beachrex_preview` = `v1.<expires>.<nonce>.<HMAC-SHA256>`, signed with the server-only
   `PREVIEW_GATE_SECRET` (≥ 32 characters; `openssl rand -hex 32`), HttpOnly, Secure, SameSite=Lax, 30 days, host-only.
   The Next.js middleware verifies it with Web Crypto before the locale handling; no valid cookie → 307
   `/preview-access?return=<path>`. Exempt: `/preview-access*`, `/_next/`, `robots.txt`, favicon, static files (and
   everything the middleware matcher already skips: `/api`, `/images`, `/brand/`, `/vendor/`, sitemaps, `ads.txt`,
   `llms.txt`).
6. Enabled without a usable secret → every gated page answers 503 "This test website is closed" (fail closed, no
   crash loop).
7. Env plumbing: non-`NEXT_PUBLIC_` variables are **not** inlined into the Next 14.2 middleware bundle (checked in
   the build output: the bundle reads `process.env.PREVIEW_GATE_*`, the middleware manifest `env` lists only Next's
   own keys; the edge sandbox copies the server's `process.env` per request). The front containers already get
   `.env.front.<env>` through `env_file`, so no build args and no compose change. Changing the values needs only a
   container restart (the deploy recreates it).

**Consequences**: Revoking all cookies = change `PREVIEW_GATE_SECRET` and redeploy the website. The gate does not
protect the API (by design), nor the public files the middleware never sees (sitemaps list dev URLs, but the pages
behind them are closed). Testers without an admin account need their address in `PREVIEW_ALLOWED_EMAILS`. The
sandbox cannot reach dev/uat: the live check (mail arrives, link opens the site) is the owner's.

---

## [2026-10-29] Zero-downtime deploys: website blue/green, API rolling replacement

**Context**: beachrex.com went live on 2026-10-29. Every website deploy recreated `front_<env>` in place
(`up -d --force-recreate`), so Next.js did not listen for a few seconds and Cloudflare showed "502 Bad Gateway"
(owner got one at 20:27:23 UTC during prod run 40948577676). The API deploy recreated `web_<env>` (nginx) and,
through `depends_on`, `api_<env>` (PHP-FPM): the same gap for the API. The image was already built before the
recreate, so the gap was the container swap itself. Owner: "uredi".

**Decision**:
1. **Website: blue/green per environment.** `front_<env>` (blue, ports 3001/3002/3003) stays the normal container;
   new `front_<env>_green` (3011/3012/3013, compose profile `green`, same image) runs only during a deploy.
   `deploy/scripts/front-deploy.sh` builds, starts the idle color, waits for the new `GET /api/health` (web-client),
   warms the main pages (ISR cache is per container), switches host nginx with a graceful reload, refreshes blue the
   same way and stops green. **The end state is always blue**, so every older nginx config and compose file (which
   only know blue) stays correct — important because `deploy/` is shared by all branches.
2. **Switch = state file + reload**, not a stateless two-server upstream: `/opt/beachrex/nginx-state/front_<env>.conf`
   (outside the `--delete`-synced `deploy/`) sets `$beachrex_front_pool` to the green upstream; it is included with a
   glob, so a missing file/dir is valid nginx and means blue. A reload drains the old color precisely; a stateless
   pool would also send requests to a container that is still starting (docker-proxy accepts, then resets) and would
   log a refused connection every few seconds in steady state. Each upstream still has the other color as `backup`,
   so a wrong or stale state file degrades to the other color instead of a 502.
3. **API: rolling replacement, not blue/green.** `deploy/scripts/api-rollout.sh` scales `api_<env>` to two
   containers (`--no-recreate`, new one has the new image), runs migrations/caches in the new one, waits out
   web's 10 s DNS cache and stops the old one gracefully. `web_<env>` already resolves `api_<env>` per request;
   new `fastcgi_connect_timeout 1s` + `fastcgi_next_upstream error timeout` retry the other address. `web_<env>` is
   no longer recreated: `api.<env>.conf` is synced `--inplace` and reloaded (recreated only if the mounted file is
   stale). No second port, no host nginx change for the API.
4. **Proof built in:** both scripts probe their health URL through host nginx every 0.2 s and fail the run if any
   request got no answer or a 5xx.
5. Rollback = revert + redeploy (zero-downtime too); no previous-version container is kept (memory on the 8 GB VPS,
   and a simpler state model). Emergency color switch by hand is in the runbook.

**Consequences**: a website deploy takes ~30–60 s longer (two starts + warm-ups) and briefly runs two Next.js
servers per environment. API container names become `deploy-api_<env>-N`. The backend must be promoted to all
branches before the web-client change (runbook "Promotion order"). Runbook: `docs/runbooks/zero-downtime-deploys.md`.
Local proof (sandbox, real nginx 1.24 + Docker, fake servers with the same start/stop behaviour, 12 concurrent
GET/POST clients): blue/green 0 failed of 625; API rollout 0 failed of 744; the old in-place recreate under the
same load: ~100 000 × 502 (website), 1 782 × 502 (API).

## [2026-10-30] Deploys prune old Docker images (amends "Zero-downtime deploys" 2026-10-29, point 5)

**Context**: the VPS disk (75 GB) was 71 % full. Every deploy builds `beachrex/api:<env>` / `beachrex/front:<env>`;
the replaced image stayed as a dangling `<none>` image and BuildKit cache grew. Owner 2026-10-30: "da deploy
samodejno čisti stare Docker slike — ja".

**Decision**:
1. Both deploy workflows end with a step that runs `deploy/scripts/docker-prune.sh` (after "Reload Nginx",
   `continue-on-error`, the script always exits 0). It covers all environments with one rule.
2. **Retention per building service** (`api_<env>`, `front_<env>`): the current image, **one previous** (the newest
   other image of the service, tagged `<tag>-previous` so it is never dangling) and every image any container uses
   (running or stopped, incl. `front_<env>_green`). Older ones: `docker image rm` without `-f`. Selected by the
   compose build label `com.docker.compose.project=deploy` or the name `beachrex/*`; base images, other projects,
   volumes and networks are never touched; no `docker system prune`. Unlabelled dangling images are only reported.
   "Previous" is the image the build step tagged `<tag>-previous` right before `docker compose build` (the VPS uses
   Docker's containerd image store, which deletes an untagged unused image at once, so it must be tagged before the
   build); without that tag, the newest other image by created date.
3. BuildKit cache: entries unused > 72 h removed, total capped at 8 GB.
4. No overlap with a build of the other repo: builds hold `/opt/beachrex/.docker-build.lock` shared, the cleanup
   exclusively (waits ≤ 10 min, else skips; a build waits ≤ 10 min, else builds anyway).
5. Disk numbers before/after and other space users (container logs and their limits, Laravel logs, `*.dump` from
   manual backups, volumes, host folders) go into one `::notice`. Dumps are never deleted automatically.

**Amends** the zero-downtime ADR point 5: still no previous-version *container*, but the previous *image* is kept
on disk (`<tag>-previous`) for a quick manual rollback. Runbook: `docs/runbooks/disk-cleanup.md`.

## [2026-10-30] AI models are picked in admin per feature; the trip planner defaults to Claude Opus 5.5

**Status**: Accepted (owner 2026-10-30: "Kateri model zdaj uporabljamo za AI chat? Damo enega cenejšega …? … v adminu
seznam modelov in približne cene … za chat posebej, za AI itinerary posebej"; later: "AI trip planner mora biti
najdražji/najboljši model, ali GPT Astra mogoče")

**Context**: The chat model was set only in the server `.env` (prod: agent engine, Anthropic, no
`AI_CHAT_ANTHROPIC_MODEL` → code default `claude-opus-5-5`). The trip planner narration called `LLMService`, which
supported OpenAI only (`OPENAI_MODEL`, `gpt-4o-mini`); `AI_PROVIDER=anthropic` threw.

**Decision**:
1. New admin page **AI → AI models** (`AiModels`), two selects stored as site settings `ai_chat_model` and
   `ai_itinerary_model` (idempotent migration inserts NULL; NULL = env/config default, so the chat does not change on
   deploy). Order: admin choice → env/config → code default. A saved model that is no longer usable (not in the list,
   or its provider's key is missing) is ignored with a log warning; the feature keeps running.
2. The supported list is `AiModelCatalog::MODELS`: Claude Opus 5.5, Sonnet 5.5, Haiku 5.5 (`claude-opus-5-5`,
   `claude-sonnet-5-5`, `claude-haiku-5-5`), OpenAI GPT-6 Astra (`gpt-6-astra`), GPT-4o, GPT-4o mini. Only models whose
   provider has an API key in that environment can be saved (validated in the select and again in `save()`).
3. Prices come only from `ModelPricing` (also used by the daily cost cap). Added `gpt-6-astra`: $10 input / $50 output
   per MTok, cached input $1 (counted as normal input, a small over-count), prompts over 272K input tokens 2x input and
   1.5x output — sources https://developers.openai.com/api/docs/pricing and
   https://developers.openai.com/api/docs/models/gpt-6-astra (checked 2026-10-30). Claude prices re-checked against
   https://platform.claude.com/docs/en/about-claude/pricing (unchanged). The OpenAI unknown-model fallback output price
   was raised to $75 so it stays above every listed model.
4. Estimated cost per answer = `ModelPricing` × a token profile: the 30-day average of this environment's `ai_usage`
   when it has ≥ 20 answered requests, else an assumption (chat: 6,000 input + 4,000 cached + 600 output, calibrated
   to the 2026-10-09 evaluation's ~$0.036 per Opus 5.5 answer; planner: 7-day plan 4,000 input + 3,500 output, scaled
   by days for 3/14). The planner table shows the AI cost as a share of the Stripe price of the plan.
5. **Chat**: both engines use the choice. A Claude model always runs on the agent engine (the pipeline engine is
   OpenAI JSON mode), so picking one on a pipeline environment switches it to agent. GPT-6 Astra is not offered for the
   chat: OpenAI's Chat Completions API, which our agent uses, has no function calling for it (Responses API only).
6. **Trip planner**: `LLMService` now has an `AnthropicProvider` (official SDK, same output contract: text, tokens,
   cost, `finishReason` "length" on `max_tokens`). Claude has no JSON mode without a schema: the JSON requirement goes
   into the system prompt and the outermost JSON object is returned. Claude thinks adaptively, so effort `low` and
   `max_tokens` = answer budget + 4,000. OpenAI reasoning models (gpt-6/gpt-5/o-series) get `max_completion_tokens`
   (+4,000), `reasoning_effort: low`, no temperature, and a client with the longer timeout. Timeout per narration call
   `AI_ITINERARY_TIMEOUT` (110 s; two attempts fit the worker's 240 s; no SDK retries).
7. **Planner default** (no admin choice): `AI_ITINERARY_MODEL`, default **`claude-opus-5-5`** (owner: best model). If
   that provider has no key in the environment, the planner stays on `AI_PROVIDER` / `OPENAI_MODEL` as before.
8. Admin-only "Test the chat model" / "Test the trip planner model" buttons send one fixed question to the selected
   (even unsaved) model and show answer, time and cost; the cost is recorded in `ai_usage` (`event_type=test`,
   tier free), so it counts toward the daily limits; refused when the $20 emergency limit is reached.

**Consequences**: no public API change. A plan costs about $0.04–0.16 with Opus 5.5 and $0.11–0.40 with GPT-6 Astra
(< 1 % of the $19–59 price). Slower models: a 14-day narration can take 60–100 s; if both attempts time out the buyer
gets the deterministic text and the credit back (existing rule). The synchronous `POST /ai/itineraries` (not used by
the website, which uses the queued `/ai/itinerary-plans`) runs under PHP-FPM's 60 s and can time out on long plans
with these models. Token counts for new models (thinking, extra tool rounds) may be higher than the profile; the page
shows measured averages once there is traffic. Guide: `docs/guides/admin/ai-modeli.md`.

## [2026-10-30] Bounded Next.js cache: pages in a memory LRU, scanner URLs 404 before rendering

**Status**: Accepted (owner 2026-10-30, third disk-filling issue: "stran mora biti hitra, light in strežnik prazen;
najprej raziskava, kaj je narobe")

**Context**: the `front_prod` container's writable layer reached 16.3 GB about 16 h after the prod deploy
(dev/uat: 32 MB). On the server: `.next/server/app` 13.9 GB in 178 057 files, `.next/cache` 1.2 GB (fetch-cache
139 955 files); the newest files were scanner paths cached as pages (`api/proc/self/environ`, `api/public/.env.*`,
`api/..;/.env.*`, `api/graphql`, ...). Causes, reproduced locally (production build, standalone server, mock API):
1. Next 14's default cache handler writes **every** ISR render (HTML + RSC: ~260 KB per beach page and language,
   ~125 KB per 404) to `.next/server/app` and every cached `fetch` to `.next/cache/fetch-cache`, and never deletes.
   Next also stores **404 and redirect renders** of ISR routes.
2. The URL space is unbounded: the middleware matcher skipped every path starting with `api`, so `/api/<anything>`
   rendered `[locale]/layout` with locale `api` (no locale check) and `[locale]/[...rest]` (ISR, 1 day), which also
   called the legacy-redirect API with the path (one fetch-cache file per path). Other junk (`/.env`, `/wp-login.php`)
   was redirected to `/en/...` and cached the same way. Legitimate pages alone are 19 × (3 774 beaches + places).
Nothing secret was served: these paths rendered the normal 404 page.

**Decision**:
1. **Custom cache handler** (web-client `cache-handler.cjs`, `next.config.js` `cacheHandler`,
   `cacheMaxMemorySize: 0`): ISR pages, route handlers and the fetch Data Cache live in one in-memory LRU with a byte
   budget (`NEXT_CACHE_MAX_MB`: prod 256, uat/dev 64; default 128), entries gzip-compressed (a beach page ~260 KB →
   ~60 KB). It never writes to disk; build-time files are read from the image read-only. Non-200 pages/routes are
   not stored. `revalidateTag` is honoured in memory. Each process (blue/green) has its own cache, empty after a
   deploy — as before, when every deploy recreated the container.
2. **Scanner URLs get a 9-byte 404 in the middleware** before anything renders (`src/lib/utils/junk-paths.ts`:
   `/api/*` except `/api/health`, dot segments, `..`, `;`, `/proc/self`, `/etc/passwd`, script/config/dump
   extensions, `wp-*`, `cgi-bin`, `phpmyadmin`, ...). The matcher now skips only `api/health`.
3. `[locale]/layout` returns 404 for a locale that is not one of the 19; `[locale]/[...rest]` (the catch-all for
   unknown URLs and old-site redirects) is `force-dynamic`: no ISR file, no cached API response per invented URL.
4. **Safety net**: `/app/.next/cache` is a 256 MB tmpfs in every `front_*` service (empty while the handler works).
5. **Visibility**: `/api/health` returns the cache numbers (`entries`, `mb`, `maxMb`, `evictions`, `hits`,
   `misses`, `skipped`) and `rssMb`; the deploy "Disk report" lists per website container its writable layer,
   `.next/cache` and `.next/server/app` size/files and those numbers, with a WARNING when a layer reaches GBs.

**Consequences**: disk use of the website no longer depends on traffic. A page evicted from the LRU is rendered again
on its next visit (locally ~65 ms vs ~16 ms from cache, same as before the change); popular pages stay cached.
Memory: the budget plus Next's normal render heap (RSS ~1.1–1.5 GB under a crawl both with a 1 MB and a 128 MB
budget). Measured locally: 950 beach pages, 38 place pages and 500 unknown/redirect URLs wrote 318 MB (1 489 pages ×
html/rsc/meta) + 15 MB (2 505 fetch files) before; after, the same plus 480 scanner probes wrote 0 bytes; TTFB unchanged (miss 64 → 67 ms, hit 15.3 → 15.9 ms avg).
Runbook: `docs/runbooks/disk-cleanup.md` "Website cache".
