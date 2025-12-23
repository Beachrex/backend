# Task 1.5: MVP Stabilization & Launch Readiness

**Date**: 2025-12-23  
**Status**: NOT_STARTED  
**Priority**: P0 (CRITICAL - blocks Task #2)  
**Estimated Effort**: 3-5 days  
**Purpose**: Validate AI core implementation before building revenue features

---

## Overview

**Goal**: Ensure the AI core (Task #1) is production-ready, stable, and observable before investing 2-3 weeks in Trip Itinerary Generation (Task #2).

**Why This Matters**:
- Task #2 (Itineraries) is the PRIMARY REVENUE feature ($1.08M/year projected)
- Building on unstable foundation = wasted effort + technical debt
- Need baseline metrics to measure itinerary feature impact
- Must validate cost controls work before scaling usage

**Non-Goals**:
- ❌ Build new features
- ❌ Optimize performance
- ❌ Add new endpoints
- ❌ Refactor existing code (unless critical bugs found)

---

## Responsibility Model

**This validation phase is HUMAN-LED.**

**AI agents may assist by**:
- Executing tests
- Collecting metrics
- Generating reports
- Summarizing logs and trends

**AI agents may NOT**:
- Declare validation success on their own
- Make Go / No-Go decisions
- Override human judgment
- Interpret business readiness autonomously

**All final validation, approval, and Go / No-Go decisions must be made by a Human Product Owner or Tech Lead.**

---

## 1. Human Validation Plan

### 1.1. Functional Validation (Manual Testing)

**Objective**: Verify all happy paths and error paths work as documented

**Test Cases** (30 minutes):

1. **Happy Path - Beach Search**
   - Send: "Show me beaches near Split, Croatia"
   - Verify: 200 OK, 1-3 beach recommendations, valid JSON structure
   - Verify: Database records created (ai_sessions, ai_messages, ai_usage)
   - Verify: Logs contain request_id and session_id
   - Verify: Cache metrics incremented

2. **Happy Path - Multi-Turn Conversation**
   - Send: "Show me beaches near Split"
   - Send: "Tell me more about the first one" (with session_id)
   - Verify: Session persists, context includes previous messages
   - Verify: tokens_used incremented in ai_sessions table

3. **Error Path - Rate Limit (429)**
   - Send 101 requests in quick succession
   - Verify: First 100 succeed, 101st returns 429
   - Verify: Response includes X-RateLimit-Limit and X-RateLimit-Remaining headers
   - Verify: Error logged with rate_limit_exceeded code

4. **Error Path - Token Budget (413)**
   - Temporarily set max_tokens_per_request to 10
   - Send long message (88+ characters)
   - Verify: 413 response with estimated_tokens and max_tokens
   - Verify: No LLM call made (middleware blocked it)

5. **Error Path - Payment Required (402)**
   - Send: "Create a 7-day itinerary for Croatia"
   - Verify: 402 response with upgrade_url
   - Verify: No LLM call made (plan enforcement blocked it)

6. **Error Path - Circuit Breaker (503)**
   - Manually set daily cost to $501 in cache
   - Send any request
   - Verify: 503 response with circuit breaker message
   - Verify: No LLM call made

7. **Error Path - Invalid Request (400)**
   - Send message with 501 characters
   - Verify: 400 response with validation error
   - Send invalid session_id (not UUID)
   - Verify: 400 response with validation error

8. **GDPR Compliance Check**
   - Send request from IP 1.2.3.4
   - Verify: ai_usage.ip_address is SHA-256 hash (64 chars), not raw IP
   - Verify: Logs do NOT contain raw IP address
   - Verify: Logs do NOT contain full user message

**Pass Criteria**: All 8 test cases pass

---

### 1.2. Automated Test Validation

**Objective**: Ensure test suite is comprehensive and passing

**Actions** (10 minutes):

```bash
# Run all AI tests
./vendor/bin/sail artisan test --filter=AI

# Expected: 100% pass rate
# Current: 9 tests, 65 assertions (ChatControllerTest)
# TODO: Add tests for ContextBuilderService, LLMService if not already covered
```

**Pass Criteria**: 
- All tests pass (100% pass rate)
- Minimum 60 assertions across all AI tests
- No skipped or incomplete tests

---

### 1.3. Performance Validation

**Objective**: Verify response times are acceptable for user experience

**Actions** (20 minutes):

1. **Measure Average Latency**
   - Send 10 requests with different queries
   - Record latency_ms from response logs
   - Calculate average, min, max, p95

2. **Measure LLM Call Time**
   - Check logs for LLM provider response time
   - Verify it's within acceptable range (< 5 seconds)

3. **Measure Database Query Time**
   - Enable query logging temporarily
   - Send request with location context (triggers beach search)
   - Verify beach queries complete in < 500ms

**Pass Criteria**:
- Average end-to-end latency < 3 seconds
- LLM call time < 5 seconds
- Database queries < 500ms
- No N+1 query issues

---

### 1.4. Cost Control Validation

**Objective**: Verify cost tracking and circuit breakers work correctly

**Actions** (30 minutes):

1. **Verify Cost Calculation**
   - Send 10 requests
   - Check ai_usage.cost_usd for each request
   - Verify costs are reasonable (< $0.01 per request for free tier)
   - Calculate total cost for 10 requests

2. **Verify Circuit Breaker - Free Tier**
   - Manually set cache key `ai:circuit_breaker:free_tier_cost:{date}` to $499
   - Send request (should succeed)
   - Set to $501
   - Send request (should fail with 503)
   - Verify cache key `ai:circuit_breaker:free_tier_disabled` is set

3. **Verify Circuit Breaker - Emergency**
   - Set cache key `ai:circuit_breaker:total_cost:{date}` to $5001
   - Send request (should fail with 503)
   - Verify cache key `ai:circuit_breaker:emergency_shutdown` is set

4. **Verify Token Budget Enforcement**
   - Check ai_sessions.tokens_used after 5 requests
   - Verify it increments correctly
   - Verify per-session limit enforced (send requests until limit hit)

**Pass Criteria**:
- Cost per request < $0.01 (free tier)
- Circuit breakers trigger at correct thresholds
- Token budgets enforced correctly
- No cost leakage (all requests tracked in ai_usage)

---

### 1.5. Observability Validation

**Objective**: Verify logging and metrics are working correctly

**Actions** (20 minutes):

1. **Verify Structured Logging**
   - Send 5 requests (3 success, 2 errors)
   - Check Laravel logs for structured log entries
   - Verify all required fields present: request_id, session_id, tier, latency_ms, tokens, cost_usd
   - Verify no PII in logs (no raw IP, no full messages)

2. **Verify Metrics Collection**
   - Send 10 requests
   - Query cache metrics:
     - `ai:metrics:requests:total:{date}`
     - `ai:metrics:requests:success:{date}`
     - `ai:metrics:latency:sum:{date}` and `ai:metrics:latency:count:{date}`
     - `ai:metrics:cost:sum:{date}`
   - Verify counts match expected values

3. **Verify Database Persistence**
   - Send 5 requests
   - Query ai_sessions, ai_messages, ai_usage tables
   - Verify all records created correctly
   - Verify relationships work (session → messages, session → usage)

**Pass Criteria**:
- All logs contain required fields
- No PII in logs
- Metrics match actual request counts
- Database records complete and accurate

---

### 1.6. Error Handling Validation

**Objective**: Verify graceful degradation and error recovery

**Actions** (30 minutes):

1. **Simulate LLM Provider Failure**
   - Temporarily set invalid OpenAI API key
   - Send request
   - Verify: 500 response with safe error message (no API key exposed)
   - Verify: Error logged with LLM provider error details
   - Verify: AiRequestFailed event fired

2. **Simulate LLM Timeout**
   - Mock LLM provider to delay 30+ seconds
   - Send request
   - Verify: Timeout handled gracefully (500 response)
   - Verify: No hanging requests

3. **Simulate Invalid LLM Response**
   - Mock LLM provider to return invalid JSON
   - Send request
   - Verify: Safe fallback response returned (200 OK with generic message)
   - Verify: Error logged with schema validation failure

4. **Simulate Database Failure**
   - Temporarily stop database
   - Send request
   - Verify: 500 response with safe error message
   - Verify: No database credentials exposed in error

**Pass Criteria**:
- All error scenarios handled gracefully
- No sensitive data exposed in error responses
- All errors logged with sufficient detail for debugging
- No unhandled exceptions

---

## 2. Metrics to Observe Before Starting Itineraries

### 2.1. Baseline Metrics (7-Day Observation Period)

**Objective**: Establish baseline performance before adding itinerary feature

**Metrics to Track**:

1. **Request Volume**
   - Total requests per day
   - Success rate (%)
   - Error rate by code (400, 402, 413, 429, 500, 503)

2. **Performance**
   - Average latency (ms)
   - P95 latency (ms)
   - LLM call time (ms)
   - Database query time (ms)

3. **Cost**
   - Total cost per day (USD)
   - Average cost per request (USD)
   - Cost by tier (free, basic, premium, enterprise)
   - Projected monthly cost at current usage

4. **User Behavior**
   - Average session length (messages per session)
   - Session duration (time between first and last message)
   - Intent distribution (beach_search, weather_query, etc.)
   - Context truncation rate (%)

5. **System Health**
   - Circuit breaker trips per day
   - Rate limit hits per day
   - Token budget exceeded per day
   - LLM provider errors per day

**Collection Method**:
- Query cache metrics daily
- Export to spreadsheet or monitoring dashboard
- Calculate trends (day-over-day change)

**Target Baseline** (after 7 days):
- Success rate > 95%
- Average latency < 3 seconds
- Average cost per request < $0.01
- Circuit breaker trips < 5 per day
- LLM provider errors < 1% of requests

---

### 2.2. Critical Thresholds (Go/No-Go Decision)

**Objective**: Define minimum acceptable performance before proceeding to Task #2

**Hard Requirements** (must meet ALL):

1. **Stability**
   - ✅ Success rate ≥ 95% over 7 days
   - ✅ No unhandled exceptions in production logs
   - ✅ All automated tests passing (100% pass rate)

2. **Performance**
   - ✅ Average latency ≤ 3 seconds
   - ✅ P95 latency ≤ 5 seconds
   - ✅ No timeout errors (< 1% of requests)

3. **Cost Control**
   - ✅ Average cost per request ≤ $0.01 (free tier)
   - ✅ Circuit breakers functioning correctly (tested manually)
   - ✅ Daily cost < $50 (at current usage levels)

4. **Observability**
   - ✅ All metrics collecting correctly (verified in cache)
   - ✅ All logs structured and GDPR-compliant (no PII)
   - ✅ Database records complete (ai_sessions, ai_messages, ai_usage)

5. **Error Handling**
   - ✅ All error paths tested and working (400, 402, 413, 429, 500, 503)
   - ✅ Graceful degradation on LLM provider failure
   - ✅ No sensitive data exposed in error responses

**If ANY threshold not met**: 
- ❌ DO NOT proceed to Task #2
- 🔧 Fix issues first
- 🔁 Re-run validation plan

---

## 3. Success Criteria for Moving to Task #2

### 3.1. Technical Readiness Checklist

**All items must be checked before starting Task #2**:

- [ ] **Functional Validation**: All 8 test cases pass
- [ ] **Automated Tests**: 100% pass rate, ≥60 assertions
- [ ] **Performance**: Average latency ≤3s, P95 ≤5s
- [ ] **Cost Control**: Avg cost ≤$0.01/request, circuit breakers working
- [ ] **Observability**: Metrics collecting, logs GDPR-compliant
- [ ] **Error Handling**: All error paths tested and working
- [ ] **Baseline Metrics**: 7-day baseline established
- [ ] **Critical Thresholds**: All 5 hard requirements met
- [ ] **Documentation**: All docs up-to-date (STATE.md, TASKS.md, operational-controls.md)
- [ ] **Deployment**: AI core deployed to staging environment (if applicable)

**Approval Required (Human)**:
- Product Owner
- Tech Lead

---

### 3.2. Business Readiness Checklist

**All items must be checked before starting Task #2**:

- [ ] **Revenue Model Validated**: Pricing confirmed ($19/$39/$59 for itineraries)
- [ ] **Payment Integration Ready**: Stripe account set up, API keys configured
- [ ] **Legal Compliance**: Terms of Service updated for AI features
- [ ] **GDPR Compliance**: Data retention policy reviewed by legal
- [ ] **Customer Support Ready**: Support team trained on AI features
- [ ] **Marketing Ready**: Landing page copy written, pricing page updated

**Approval Required (Human)**:
- Product Owner
- Tech Lead

---

### 3.3. Risk Assessment

**Risks to Consider Before Proceeding**:

1. **Cost Overrun Risk**
   - Current daily cost: $X (from baseline metrics)
   - Projected daily cost with itineraries: $Y (estimate 5-10x increase)
   - Mitigation: Circuit breakers set to $500/day (free tier), $5,000/day (emergency)

2. **Performance Degradation Risk**
   - Itinerary generation may take 10-30 seconds (vs 2-3s for chat)
   - Mitigation: Consider async processing for itineraries (queue job)

3. **LLM Provider Risk**
   - OpenAI rate limits or outages could block revenue
   - Mitigation: Implement retry logic, consider backup provider (Anthropic)

4. **User Experience Risk**
   - Complex itinerary requests may fail or produce poor results
   - Mitigation: Start with simple 3-day itineraries, iterate based on feedback

**Risk Acceptance**: Product Owner must acknowledge and accept risks before proceeding

---

## 4. Implementation Timeline

**Total Duration**: 3-5 days

### Day 1: Functional & Automated Test Validation
- Morning: Run all 8 manual test cases
- Afternoon: Run automated test suite, fix any failures
- Output: Test results documented, all tests passing

### Day 2: Performance & Cost Control Validation
- Morning: Measure latency, LLM call time, database query time
- Afternoon: Test circuit breakers, token budgets, cost tracking
- Output: Performance metrics documented, cost controls verified

### Day 3: Observability & Error Handling Validation
- Morning: Verify logging, metrics collection, database persistence
- Afternoon: Test all error scenarios (LLM failure, timeout, invalid response, etc.)
- Output: Observability verified, error handling tested

### Day 4-10: Baseline Metrics Collection (7 days)
- Daily: Query cache metrics, export to spreadsheet
- Daily: Monitor logs for errors or anomalies
- Output: 7-day baseline metrics established

### Day 11: Go/No-Go Decision
- Morning: Review all validation results
- Morning: Check all success criteria
- Afternoon: Product Owner / Tech Lead approval
- Output: Decision to proceed to Task #2 or fix issues

---

## 5. Deliverables

1. **Validation Report** (`docs/ai/implementation/MVP_VALIDATION_REPORT.md`)
   - Test results for all 8 functional test cases
   - Automated test results (pass rate, assertion count)
   - Performance metrics (latency, LLM time, DB time)
   - Cost metrics (per request, daily total, projections)
   - Observability verification (logs, metrics, database)
   - Error handling verification (all scenarios tested)

2. **Baseline Metrics Dashboard** (spreadsheet or monitoring tool)
   - 7-day request volume, success rate, error rate
   - 7-day latency trends (avg, p95)
   - 7-day cost trends (total, per request)
   - 7-day user behavior (session length, intent distribution)

3. **Go/No-Go Decision Document** (`docs/ai/implementation/GO_NOGO_DECISION.md`)
   - Technical readiness checklist (all items checked)
   - Business readiness checklist (all items checked)
   - Risk assessment (risks acknowledged and accepted)
   - Approval signatures (Product Owner, Tech Lead)

4. **Updated Documentation**
   - `docs/ai/implementation/STATE.md` (Task 1.5 status)
   - `docs/ai/implementation/TASKS.md` (Task 1.5 marked complete)

---

## 6. Exit Criteria

**Task 1.5 is COMPLETE when**:

✅ All validation tests pass (functional, automated, performance, cost, observability, error handling)  
✅ 7-day baseline metrics collected and analyzed  
✅ All critical thresholds met (stability, performance, cost, observability, error handling)  
✅ All success criteria checklists completed (technical, business)  
✅ Risk assessment completed and accepted  
✅ Go/No-Go decision made and documented  
✅ Product Owner / Tech Lead approval obtained  
✅ Documentation updated (STATE.md, TASKS.md)

**Only then** can we proceed to Task #2: Trip Itinerary Generation.

