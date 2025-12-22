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

**End of DECISIONS.md**

This file is updated by: All AI agents making architectural decisions
This file must be updated: When making any significant design decision
This file is read by: All new AI agents to understand past decisions

