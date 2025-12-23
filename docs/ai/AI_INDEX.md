# Beachrex AI Agent - Canonical Entry Point

**⚠️ NEW AI AGENTS MUST START HERE ⚠️**

This is the **ONLY** file you need to read first. Everything else is referenced from here.

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: **AUTHORITATIVE** - This file governs all AI work on this project

---

## What This Project Is

**Beachrex** is a beach discovery platform with a **paid AI agent** as the core business model.

### The Platform (80% Complete)
- **Frontend**: Next.js 14 (TypeScript, React Query, next-intl, 14 languages)
- **Backend**: Laravel 11 API (PostgreSQL + PostGIS, Meilisearch, Redis)
- **Admin**: Filament 3.x for data management
- **Data**: 2,445+ beaches across Croatia, Slovenia, Montenegro (40+ attributes each)
- **Features**: Natural language search, spatial queries, weather integration, multi-language

### The AI Agent (Core Business Model)
**NOT**: A free chatbot or search assistant  
**YES**: A **paid decision engine** that generates trip itineraries and verified reports

**Primary Revenue**: Trip planning packages ($19-$59 per itinerary) = $1.08M/year projected  
**Secondary Revenue**: B2B analytics and white-label AI ($5K-$200K/year per customer)

---

## Who Pays for the AI and Why

### B2C Monetization (Trip-Based, NOT Subscriptions)

**What is FREE** (Unlimited):
- ✅ AI-powered beach search (natural language queries)
- ✅ Beach comparisons (up to 3 beaches)
- ✅ Current weather data
- ✅ Accessibility filters
- ✅ Educational queries
- ✅ Multi-language support (14 languages)

**What is PAID**:
1. **Trip Itinerary Generation** ($19-$59) - PRIMARY REVENUE
   - Multi-day beach itineraries (3-14 days)
   - Optimized routes, weather-aware scheduling
   - Downloadable PDF + mobile access
   
2. **Verified Accessibility Reports** ($15/beach)
   - Human-verified accessibility data with photos
   - Money-back guarantee
   
3. **Activity-Specific Guides** ($25/guide)
   - Expert-curated top 10 beaches for snorkeling, diving, etc.
   - Insider tips, equipment rental info

**Why Trip-Based**: Users plan 1-2 beach vacations per year (not monthly). Willingness to pay is high at decision point ($39 for 7-day itinerary vs $5K vacation). No subscription fatigue.

### B2B Monetization (Visitor Acquisition, NOT AI Features)

**Who Pays**:
- **DMOs** (tourism boards): $5K-$50K/year - Analytics, promoted listings, white-label
- **Hotels/Resorts**: $2K-$10K/year - Concierge tool, guest widget
- **Municipalities**: $1K-$5K/year - Beach promotion, visitor analytics
- **Travel Agencies**: $500-$5K/month - API access, white-label

**What They Buy**: More visitors to their beaches (not "AI-powered search")

**B2B Tiers**:
- **Analytics** ($5K/year): Beach search analytics, visitor intent data
- **Promotion** ($15K/year): Boosted listings, custom beach data
- **White-Label** ($30K/year): Branded AI agent, API access
- **Enterprise** ($50K-$200K/year): Custom prompts, private data, SLA

---

## What the AI Agent Is and Is NOT

### The AI Agent IS:
- ✅ A **decision engine** that helps users choose the right beach
- ✅ A **trip planner** that generates optimized itineraries (PAID)
- ✅ A **recommendation system** based on user constraints
- ✅ A **multi-language interface** to 2,445+ beaches
- ✅ A **B2B analytics tool** for tourism boards and hotels

### The AI Agent is NOT:
- ❌ A free chatbot (search is free, outcomes are paid)
- ❌ A booking platform (no reservations, no payments for beaches)
- ❌ A review aggregator (no UGC, no ratings)
- ❌ A social network (no user accounts on frontend, no favorites)
- ❌ A real-time crowding tracker (no IoT sensors)
- ❌ A navigation app (no turn-by-turn directions)

---

## Current Project Phase

**Phase**: **MVP Implementation** (80% platform complete, AI agent in development)

**What is DONE**:
- ✅ Beach database (2,445+ beaches, 40+ attributes, 14 languages)
- ✅ Search API (natural language via Meilisearch)
- ✅ Spatial queries (PostGIS: radius, nearest, bounding-box)
- ✅ Weather integration (OpenWeatherMap)
- ✅ Admin panel (Filament 3.x)
- ✅ Frontend (Next.js 14, multi-language)
- ✅ AI governance documentation (this file and supporting docs)

**What is NEXT** (MVP Priority):
1. **Trip Itinerary Generation** (PRIMARY REVENUE) - P0
2. **Beach Search Analytics** (B2B, $5K/year) - P0
3. **Promoted Beach Listings** (B2B, $15K/year) - P0
4. Payment integration (Stripe)
5. PDF generation (itineraries, reports)

**What is LATER** (Phase 2):
- Verified Accessibility Reports (requires human verification process)
- Activity-Specific Guides (requires expert curation)
- White-Label AI (requires multi-tenancy infrastructure)
- API Access (requires rate limiting, billing integration)

**See**: `docs/ai/implementation/STATE.md` for detailed status

---

## Hard Non-Negotiable Constraints

### Business Rules (NEVER Violate)

**The AI Agent NEVER Gives Away for Free**:
1. ❌ Multi-day itineraries (2+ days) - PRIMARY REVENUE
2. ❌ Verified accessibility data (human-verified, photos)
3. ❌ Expert-curated activity guides (full top 10)
4. ❌ Multi-day weather forecasts (3-14 days)
5. ❌ Bulk beach data export (100+ beaches)
6. ❌ Raw analytics data (B2B only)

**Enforcement**: Code-level validation, database constraints, middleware, circuit breakers

**See**: `docs/ai/product/ai-business-rules.md` for complete rules

### Technical Constraints

**Rate Limiting** (IP-Based, Non-Negotiable):
- FREE: 100 queries/hour per IP
- PAID: 500 queries/hour per user
- B2B: 10,000 queries/hour per tenant
- ENTERPRISE: Unlimited

**Token Budgets** (LLM Cost Control):
- FREE: 4K tokens/query (max 10/hour)
- PAID: 16K tokens/query (max 100/hour)
- B2B: 32K tokens/query (max 1,000/hour)
- ENTERPRISE: 128K tokens/query (unlimited)

**Circuit Breakers** (Automatic Shutdown):
- $500/day LLM costs → Disable free tier
- $5,000/day LLM costs → Emergency shutdown (all tiers)
- 50% error rate → Fallback to search only

**See**: `docs/ai/architecture/agent-execution-model.md` for execution details

### Architectural Constraints

**AI Service**: Dedicated microservice (separate Laravel app, port 8001)
**Main API**: Existing Laravel app (port 8000)
**Shared**: PostgreSQL, Redis, Meilisearch

**Data Access**: Read-only (AI never writes to beach database)
**Session Memory**: Redis, 30-minute TTL, max 10 messages
**No Long-Term Memory**: Privacy-first, no user profiling

**See**: `docs/ai/architecture/integration.md` for integration design

---

## OpenAPI / Swagger Requirement (NON-NEGOTIABLE)

This project uses OpenAPI as a **single source of truth** for frontend integration.

### Rules

**Every API endpoint MUST be fully documented using OpenAPI / Swagger annotations**:
- ✅ No endpoint is considered "implemented" unless it appears correctly in the OpenAPI spec
- ✅ Request schemas, response schemas, and error responses MUST be explicitly defined
- ✅ Frontend API clients are generated from OpenAPI – undocumented behavior is forbidden
- ✅ All enums (tier, intent, error_code) must be explicitly defined
- ✅ Security schemes (authentication) must be documented

### AI Agent Rules

**If you add or modify an endpoint, you MUST update OpenAPI annotations in the same task**:
- ❌ Do NOT implement endpoints without OpenAPI coverage
- ❌ Do NOT defer OpenAPI updates to a later task
- ❌ Do NOT rely on implicit or inferred schemas
- ✅ Add Swagger annotations to controller methods
- ✅ Define reusable schemas in `app/Swagger/Schemas/`
- ✅ Verify endpoint appears in Swagger UI before marking task complete

### Definition of Done (API Tasks)

For any task involving API changes, ALL of these must be complete:
- [ ] Runtime logic implemented
- [ ] Tests passing (100% pass rate)
- [ ] OpenAPI annotations added/updated
- [ ] Swagger UI renders endpoint correctly (`http://localhost/api/documentation`)
- [ ] Frontend client generation verified (no manual fixes required)

**Failure to comply blocks task completion.**

**See**: `docs/ai/implementation/OPENAPI_COVERAGE_REPORT.md` for current coverage

---

## Instructions for New AI Agents

### Step 1: Read This File First
You are reading it now. This is the authoritative source.

### Step 2: Understand the Business Model
Read in this order:
1. `docs/ai/product/ai-monetization.md` - Who pays and why
2. `docs/ai/product/ai-business-rules.md` - What is NEVER free

**Critical**: The AI agent is a **paid product**, not a free feature. Trip itineraries are PRIMARY REVENUE ($1.08M/year). Never give them away for free.

### Step 3: Understand Current State
Read:
1. `docs/ai/implementation/STATE.md` - What is done, what is next
2. `docs/ai/implementation/TASKS.md` - Prioritized task list
3. `docs/ai/implementation/DECISIONS.md` - Past decisions and rationale

### Step 4: Understand Technical Architecture
Read:
1. `docs/ai/architecture/agent-execution-model.md` - How AI executes
2. `docs/ai/architecture/ai-capability-alignment.md` - What AI can do (B2C vs B2B)
3. `docs/ai/canonical-documentation.md` - Platform architecture and conventions

### Step 5: Check for Active Work
Before starting ANY work:
1. Read `docs/ai/implementation/STATE.md` - Check current phase
2. Read `docs/ai/implementation/TASKS.md` - Check what is in progress
3. Ask user: "What should I work on?" (don't assume)

---

## What You Are Allowed to Do

### ✅ ALLOWED (Safe, Reversible, Expected)

**Documentation**:
- ✅ Update `docs/ai/implementation/STATE.md` (keep current)
- ✅ Add entries to `docs/ai/implementation/DECISIONS.md` (ADR format)
- ✅ Update `docs/ai/implementation/TASKS.md` (mark done, add next)
- ✅ Create technical design docs (in `docs/ai/architecture/`)

**Code Reading**:
- ✅ Use `view` tool to read existing code
- ✅ Use `codebase-retrieval` to find relevant code
- ✅ Analyze existing implementations

**Implementation** (ONLY if user explicitly requests):
- ✅ Implement features from `TASKS.md` (if user assigns)
- ✅ Write tests for new features
- ✅ Fix bugs (if user reports)
- ✅ Update existing code (if user requests)

**Planning**:
- ✅ Break down large tasks into subtasks
- ✅ Estimate complexity and dependencies
- ✅ Propose implementation approaches (ask user to approve)

---

## What You Must NEVER Do

### ❌ FORBIDDEN (Dangerous, Irreversible, Out of Scope)

**Business Logic Changes**:
- ❌ Change pricing ($19/$39/$59 for itineraries is fixed)
- ❌ Make paid features free (violates business model)
- ❌ Remove rate limits (protects infrastructure)
- ❌ Bypass payment validation (destroys revenue)
- ❌ Change B2B tier pricing (contractual)

**Scope Expansion**:
- ❌ Add booking/reservation features (explicitly out of scope)
- ❌ Add user-generated content (reviews, ratings, comments)
- ❌ Add social features (favorites, sharing, profiles)
- ❌ Add real-time crowding (requires IoT, not scalable)
- ❌ Add navigation (use Google Maps links instead)

**Architectural Changes**:
- ❌ Merge AI service into main API (must stay separate)
- ❌ Add long-term user memory (privacy violation)
- ❌ Store user queries indefinitely (GDPR violation)
- ❌ Change database schema without approval (breaking change)

**Deployment Actions** (Without Explicit User Permission):
- ❌ Deploy to production
- ❌ Run database migrations
- ❌ Install dependencies (use package managers, ask first)
- ❌ Commit or push code
- ❌ Merge branches

**Cost-Risky Actions**:
- ❌ Remove circuit breakers (cost protection)
- ❌ Increase token budgets without approval
- ❌ Enable unlimited free tier
- ❌ Disable rate limiting

---

## AI-to-AI Handoff Rules

### When You Finish a Task

**ALWAYS** update these files before ending conversation:
1. `docs/ai/implementation/STATE.md` - Update "What is DONE" and "What is NEXT"
2. `docs/ai/implementation/TASKS.md` - Mark completed tasks as DONE
3. `docs/ai/implementation/DECISIONS.md` - Add ADR entry if you made a design decision

**Handoff Message Template**:
```
I have completed [task name].

Updated files:
- STATE.md: Marked [X] as DONE, [Y] is NEXT
- TASKS.md: Marked task [ID] as DONE
- DECISIONS.md: Added ADR for [decision]

Next AI agent should:
1. Read AI_INDEX.md (this file)
2. Check STATE.md for current phase
3. Check TASKS.md for next priority
4. Ask user: "Should I continue with [next task]?"

No blockers. Ready for next phase.
```

### When You Encounter a Blocker

**ALWAYS** document blockers:
1. Update `STATE.md` - Add to "Blockers / Open Questions"
2. Update `TASKS.md` - Mark task as BLOCKED
3. Ask user for clarification (don't guess)

**Blocker Message Template**:
```
I encountered a blocker on [task name]:

Blocker: [Describe issue]
Impact: [What is blocked]
Options: [List 2-3 alternatives]

Updated STATE.md and TASKS.md to reflect blocker.

Please advise:
1. [Option 1]
2. [Option 2]
3. [Option 3]
```

### When You Make a Design Decision

**ALWAYS** document decisions:
1. Add entry to `DECISIONS.md` (ADR format)
2. Include: Date, Decision, Alternatives, Reasoning, Impact
3. Reference decision in code comments

**Decision Entry Template**:
```markdown
## [YYYY-MM-DD] Decision Title

**Status**: Accepted

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

## Canonical System Prompt for AI Agent

**See**: `docs/ai/implementation/PROMPTS.md` for full system prompt

**Core Directive**:
> "You are a beach discovery assistant. Your job is to help users confidently choose the right beach for their specific situation. You have access to 2,445+ beaches with 40+ attributes each. You can search, compare, and recommend beaches. For multi-day trip planning, you offer paid itinerary generation ($19-$59). You NEVER give away multi-day itineraries for free."

**Tone**: Helpful, confident, concise (not chatty)  
**Language**: Match user's language (14 supported)  
**Output**: Structured (lists, tables, maps), not long paragraphs

---

## Project File Structure

```
Api/
├── docs/
│   ├── ai/
│   │   ├── AI_INDEX.md                    ← YOU ARE HERE (start here)
│   │   ├── product/
│   │   │   ├── ai-monetization.md         ← Who pays and why
│   │   │   ├── ai-business-rules.md       ← What is NEVER free
│   │   │   ├── customer-agent-definition.md
│   │   │   ├── agent-capability-map.md
│   │   ├── architecture/
│   │   │   ├── agent-execution-model.md   ← How AI executes
│   │   │   ├── ai-capability-alignment.md ← B2C vs B2B capabilities
│   │   │   ├── integration.md
│   │   ├── implementation/
│   │   │   ├── STATE.md                   ← Current phase, done/next
│   │   │   ├── DECISIONS.md               ← ADR log
│   │   │   ├── TASKS.md                   ← Prioritized task list
│   │   │   ├── PROMPTS.md                 ← System prompts
│   │   ├── canonical-documentation.md     ← Platform architecture
│   │   ├── agents/
│   │       ├── project-brain.md
├── app/
├── routes/
├── database/
├── tests/
```

---

## Quick Reference: Key Numbers

**Platform**:
- 2,445+ beaches
- 40+ attributes per beach
- 14 languages supported
- 80% implementation complete

**Revenue Model**:
- B2C: $1.8M/year (100K MAU, 5% conversion, $30 avg)
- B2B: $600K/year (20+10+5+2 customers)
- **Total**: $2.4M/year projected

**Pricing**:
- Trip itinerary: $19 (3-day), $39 (7-day), $59 (14-day)
- Accessibility report: $15/beach
- Activity guide: $25/guide
- B2B Analytics: $5K/year
- B2B Promotion: $15K/year
- B2B White-Label: $30K/year
- B2B Enterprise: $50K-$200K/year

**Rate Limits**:
- Free: 100 queries/hour per IP
- Paid: 500 queries/hour per user
- B2B: 10,000 queries/hour per tenant

**Token Budgets**:
- Free: 4K tokens/query
- Paid: 16K tokens/query
- B2B: 32K tokens/query
- Enterprise: 128K tokens/query

---

## Final Instructions

**To the AI agent reading this**:

1. **This file is authoritative**. If anything conflicts with this file, this file wins.

2. **Read the linked documents** in the order specified above. Don't skip them.

3. **Check STATE.md** before starting any work. Don't assume what needs to be done.

4. **Ask the user** before making any changes. Don't assume permission.

5. **Update STATE.md, TASKS.md, DECISIONS.md** before ending your session. The next AI agent depends on this.

6. **Never violate business rules**. Multi-day itineraries are NEVER free. This is non-negotiable.

7. **Never expand scope**. No booking, no UGC, no social features, no real-time crowding.

8. **Never bypass payment validation**. This destroys the business model.

9. **Never remove rate limits or circuit breakers**. This protects infrastructure and costs.

10. **When in doubt, ask the user**. Don't guess. Don't assume.

---

**You are now ready to work on this project.**

**Next steps**:
1. Read `docs/ai/product/ai-monetization.md`
2. Read `docs/ai/product/ai-business-rules.md`
3. Read `docs/ai/implementation/STATE.md`
4. Ask user: "What should I work on?"

**Good luck. Build something great. Protect the revenue model.**

---

**End of AI_INDEX.md**

This file was last updated: 2025-12-22  
This file is maintained by: Project Brain AI  
This file is authoritative for: All AI agents working on Beachrex

