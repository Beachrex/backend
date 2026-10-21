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

