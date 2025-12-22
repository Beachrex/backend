# Beachrex AI Agent - Business Rules (Non-Negotiable)

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: **CANONICAL** - Hard business rules for AI usage  
**Authority**: These rules are NON-NEGOTIABLE and enforced at code level

---

## Document Purpose

This document defines **hard business rules** that protect revenue and prevent abuse.

**These rules are enforced by**:
1. **Code-level validation** (cannot be bypassed)
2. **Database constraints** (foreign keys, check constraints)
3. **API middleware** (rate limiting, authentication)
4. **Circuit breakers** (automatic shutdowns on abuse)

**Violations of these rules**:
- ❌ Are NOT allowed under any circumstances
- ❌ Cannot be overridden by customer support
- ❌ Cannot be bypassed by admin users
- ✅ Require code changes and deployment to modify

---

## 1. What the AI Agent is NEVER Allowed to Give Away for Free

### Rule 1.1: Multi-Day Itineraries (NEVER FREE)

**What is Protected**:
- Any itinerary spanning 2+ days
- Optimized routes between beaches
- Day-by-day scheduling with weather
- Downloadable PDF itineraries

**What is Allowed for Free**:
- ✅ Single-day suggestions ("Visit Beach A today")
- ✅ List of beaches matching criteria (no route optimization)
- ✅ Verbal recommendations (no PDF download)

**Enforcement**:
```php
// In ItineraryController::generate()
if ($request->days >= 2 && !$user->hasPurchased('itinerary', $tripHash)) {
    return response()->json([
        'error' => 'Multi-day itineraries require payment',
        'price' => $this->calculatePrice($request->days),
        'purchase_url' => route('purchase.itinerary', $tripHash)
    ], 402); // Payment Required
}
```

**Rationale**: Multi-day itineraries are PRIMARY REVENUE ($1.08M/year). Giving them away destroys business model.

**Penalty for Violation**: Immediate termination of developer who bypasses this rule.

---

### Rule 1.2: Verified Accessibility Data (NEVER FREE)

**What is Protected**:
- Human-verified accessibility scores (1-10)
- Photos of access points, ramps, parking
- Beach operator contact information
- Accessibility guarantees (money-back)

**What is Allowed for Free**:
- ✅ Database accessibility flags (wheelchair=yes/no)
- ✅ Filter by accessibility attributes
- ✅ Show accessibility icons in search results

**Enforcement**:
```php
// In AccessibilityReportController::show()
if (!$user->hasPurchased('accessibility_report', $beachId)) {
    return response()->json([
        'error' => 'Verified accessibility reports require payment',
        'price' => 15,
        'preview' => [
            'database_flags' => $beach->accessibility, // Free
            'verified_score' => null, // Paid
            'photos' => null, // Paid
            'contact_info' => null // Paid
        ]
    ], 402);
}
```

**Rationale**: Human verification costs money. Database flags are free, verification is paid.

**Penalty for Violation**: Immediate termination + legal action (breach of contract with verification partners).

---

### Rule 1.3: Expert-Curated Activity Guides (NEVER FREE)

**What is Protected**:
- Top 10 beaches for specific activities (curated by experts)
- Insider tips (local secrets, avoid crowds)
- Equipment rental information
- Best time of year, water conditions

**What is Allowed for Free**:
- ✅ Filter beaches by activity (e.g., snorkeling=yes)
- ✅ Show top 3 beaches (not full top 10)
- ✅ Generic activity information (no insider tips)

**Enforcement**:
```php
// In ActivityGuideController::show()
if (!$user->hasPurchased('activity_guide', $activityType)) {
    $beaches = $guide->beaches->take(3); // Free preview: 3 of 10
    return response()->json([
        'error' => 'Full activity guide requires payment',
        'price' => 25,
        'preview' => $beaches, // 3 beaches
        'full_count' => 10,
        'purchase_url' => route('purchase.guide', $activityType)
    ], 402);
}
```

**Rationale**: Expert curation is expensive. Free users get database search, paid users get expert insights.

**Penalty for Violation**: Immediate termination + repayment of expert curation costs.

---

### Rule 1.4: Multi-Day Weather Forecasts (NEVER FREE)

**What is Protected**:
- 3-day, 7-day, 14-day weather forecasts
- Weather-aware itinerary scheduling
- Historical weather patterns

**What is Allowed for Free**:
- ✅ Current weather (today only)
- ✅ Current temperature, conditions, wind

**Enforcement**:
```php
// In WeatherController::forecast()
if ($request->days > 1 && !$user->hasPurchased('itinerary', $tripHash)) {
    return response()->json([
        'error' => 'Multi-day forecasts are included in paid itineraries',
        'current_weather' => $this->getCurrentWeather($beachId), // Free
        'forecast' => null, // Paid
        'purchase_url' => route('purchase.itinerary')
    ], 402);
}
```

**Rationale**: Multi-day forecasts are part of paid itineraries. Current weather is free (table stakes).

**Penalty for Violation**: Immediate termination.

---

### Rule 1.5: Bulk Beach Data Export (NEVER FREE)

**What is Protected**:
- Export of 100+ beaches to CSV/PDF
- API access to bulk beach data
- Automated scraping of beach database

**What is Allowed for Free**:
- ✅ View up to 100 beaches per query (paginated)
- ✅ Manual copy-paste of individual beach data
- ✅ Share individual beach links

**Enforcement**:
```php
// In BeachController::index()
$maxLimit = $user->tier === 'enterprise' ? 10000 : 100;

if ($request->limit > $maxLimit) {
    return response()->json([
        'error' => 'Bulk exports require Enterprise tier',
        'max_limit' => $maxLimit,
        'requested' => $request->limit
    ], 403);
}
```

**Rationale**: Prevents competitors from cloning database. Protects proprietary data.

**Penalty for Violation**: Immediate termination + legal action (theft of proprietary data).

---

### Rule 1.6: Raw Analytics Data (NEVER FREE, B2B ONLY)

**What is Protected**:
- Individual user search queries
- Raw click-through data
- User demographic information
- Real-time analytics (not aggregated)

**What is Allowed for Free**:
- ✅ Public aggregated statistics (e.g., "Top 10 most searched beaches")
- ✅ User's own search history (their data only)

**Enforcement**:
```php
// In AnalyticsController::rawData()
if ($user->tier !== 'analytics' && $user->tier !== 'enterprise') {
    return response()->json([
        'error' => 'Raw analytics data requires Analytics tier ($5K/year)',
        'tier' => $user->tier,
        'upgrade_url' => route('upgrade.analytics')
    ], 403);
}
```

**Rationale**: Analytics data is B2B product ($5K/year). Free users don't get access.

**Penalty for Violation**: Immediate termination + GDPR violation fines (exposing user data).

---

## 2. Data Scopes That Require Payment

### Rule 2.1: Geographic Scope Limits (Free vs Paid)

**Free Tier**:
- ✅ Search within 1 country at a time
- ✅ Search within 1 region/island/city at a time
- ✅ View up to 100 beaches per query

**Paid Tier (Itinerary Purchase)**:
- ✅ Multi-country itineraries (e.g., Croatia + Slovenia)
- ✅ Cross-region optimization (e.g., Istria + Dalmatia)
- ✅ Unlimited beaches in itinerary

**Enforcement**:
```php
// In BeachFilterService::apply()
if (count($request->countries) > 1 && !$user->hasPurchased('itinerary')) {
    throw new PaymentRequiredException(
        'Multi-country searches require paid itinerary'
    );
}
```

**Rationale**: Single-country search is free. Multi-country trip planning is paid.

---

### Rule 2.2: Time Scope Limits (Current vs Future)

**Free Tier**:
- ✅ Current weather (today only)
- ✅ Current beach conditions (today only)

**Paid Tier (Itinerary Purchase)**:
- ✅ 3-14 day weather forecasts
- ✅ Future beach conditions (seasonal patterns)
- ✅ Historical weather data

**Enforcement**:
```php
// In WeatherService::getForecast()
$maxDays = $user->hasPurchased('itinerary') ? 14 : 1;

if ($request->days > $maxDays) {
    throw new PaymentRequiredException(
        "Forecasts beyond {$maxDays} days require paid itinerary"
    );
}
```

**Rationale**: Current data is free. Future planning is paid.

---

### Rule 2.3: Detail Scope Limits (Summary vs Full)

**Free Tier**:
- ✅ Beach summary (name, location, surface, basic facilities)
- ✅ Up to 3 photos per beach
- ✅ Database accessibility flags (yes/no)

**Paid Tier (Accessibility Report)**:
- ✅ Full beach details (40+ attributes)
- ✅ All photos, videos, 360° tours
- ✅ Verified accessibility data with photos

**Enforcement**:
```php
// In BeachDetailController::show()
$detail = $beach->getSummary(); // Free

if ($user->hasPurchased('accessibility_report', $beach->id)) {
    $detail = $beach->getFullDetails(); // Paid
    $detail->verified_accessibility = $this->getVerifiedData($beach->id);
}

return response()->json($detail);
```

**Rationale**: Summary is free. Full details require payment.

---

### Rule 2.4: Comparison Scope Limits (3 vs Unlimited)

**Free Tier**:
- ✅ Compare up to 3 beaches side-by-side
- ✅ Basic comparison (facilities, accessibility, weather)

**Paid Tier (Pro/Enterprise)**:
- ✅ Compare up to 10 beaches (Pro)
- ✅ Compare unlimited beaches (Enterprise)
- ✅ Advanced comparison (historical data, trends)

**Enforcement**:
```php
// In ComparisonController::compare()
$maxBeaches = match($user->tier) {
    'free' => 3,
    'pro' => 10,
    'enterprise' => PHP_INT_MAX,
    default => 3
};

if (count($request->beach_ids) > $maxBeaches) {
    throw new PaymentRequiredException(
        "Comparing {$request->beach_ids} beaches requires {$requiredTier} tier"
    );
}
```

**Rationale**: Basic comparison is free. Advanced comparison is paid.

---

## 3. AI Actions That Are Always Gated

### Rule 3.1: Itinerary Generation (ALWAYS REQUIRES PAYMENT)

**Gated Action**: Generate multi-day beach itinerary

**Payment Required**: $19-$59 (based on trip duration)

**Enforcement**:
```php
// In AI Agent prompt
if ($intent === 'generate_itinerary' && $days >= 2) {
    if (!$user->hasPurchased('itinerary', $tripHash)) {
        return $this->showPaymentPrompt([
            'message' => "I can create a perfect {$days}-day itinerary for \${$price}",
            'price' => $price,
            'purchase_url' => route('purchase.itinerary', $tripHash)
        ]);
    }
}
```

**No Exceptions**: Even admin users must purchase itineraries (for testing, use test mode).

---

### Rule 3.2: Accessibility Verification (ALWAYS REQUIRES PAYMENT)

**Gated Action**: Provide verified accessibility report

**Payment Required**: $15 per beach

**Enforcement**:
```php
// In AI Agent prompt
if ($intent === 'verify_accessibility') {
    if (!$user->hasPurchased('accessibility_report', $beachId)) {
        return $this->showPaymentPrompt([
            'message' => "I can provide a verified accessibility report for \$15",
            'preview' => $beach->accessibility, // Database flags only
            'purchase_url' => route('purchase.accessibility', $beachId)
        ]);
    }
}
```

**No Exceptions**: Database flags are free, verification is always paid.

---

### Rule 3.3: Expert Recommendations (ALWAYS REQUIRES PAYMENT)

**Gated Action**: Provide expert-curated activity guide

**Payment Required**: $25 per guide

**Enforcement**:
```php
// In AI Agent prompt
if ($intent === 'activity_guide') {
    if (!$user->hasPurchased('activity_guide', $activityType)) {
        return $this->showPaymentPrompt([
            'message' => "I can provide the ultimate {$activityType} guide for \$25",
            'preview' => $guide->beaches->take(3), // 3 of 10 beaches
            'purchase_url' => route('purchase.guide', $activityType)
        ]);
    }
}
```

**No Exceptions**: Database search is free, expert curation is always paid.

---

### Rule 3.4: B2B Analytics Access (ALWAYS REQUIRES B2B TIER)

**Gated Action**: Access beach search analytics, visitor intent data

**Payment Required**: $5K/year (Analytics tier)

**Enforcement**:
```php
// In AnalyticsController::index()
if (!in_array($user->tier, ['analytics', 'promotion', 'white_label', 'enterprise'])) {
    throw new UnauthorizedException(
        'Analytics require B2B tier (starting at $5K/year)'
    );
}
```

**No Exceptions**: Analytics are B2B-only. No free tier access.

---

### Rule 3.5: Promoted Beach Listings (ALWAYS REQUIRES PROMOTION TIER)

**Gated Action**: Boost beaches in AI recommendations

**Payment Required**: $15K/year (Promotion tier)

**Enforcement**:
```php
// In PromotionController::create()
if (!in_array($user->tier, ['promotion', 'white_label', 'enterprise'])) {
    throw new UnauthorizedException(
        'Promoted listings require Promotion tier ($15K/year)'
    );
}
```

**No Exceptions**: Promotion is B2B-only. No free tier access.

---

## 4. Abuse and Overuse Prevention

### Rule 4.1: Rate Limiting (IP-Based, Non-Negotiable)

**Limits**:
- **Free Tier**: 100 queries per hour per IP
- **Paid Tier**: 500 queries per hour per user
- **B2B Tier**: 10,000 queries per hour per tenant
- **Enterprise**: Unlimited (dedicated infrastructure)

**Enforcement**:
```php
// In RateLimitMiddleware::handle()
$key = "rate_limit:ip:{$ip}:hour";
$limit = $this->getLimit($user);

if (Redis::get($key) >= $limit) {
    return response()->json([
        'error' => 'Rate limit exceeded',
        'limit' => $limit,
        'reset_at' => now()->addHour()->toIso8601String()
    ], 429); // Too Many Requests
}

Redis::incr($key);
Redis::expire($key, 3600);
```

**Rationale**: Prevents scraping, DDoS attacks, infrastructure abuse.

**No Exceptions**: Even admin users are rate-limited (use test mode for testing).

---

### Rule 4.2: Concurrent Session Limits

**Limits**:
- **Free Tier**: 1 concurrent session per IP
- **Paid Tier**: 3 concurrent sessions per user
- **B2B Tier**: 10 concurrent sessions per tenant
- **Enterprise**: Unlimited

**Enforcement**:
```php
// In SessionMiddleware::handle()
$activeSessions = Redis::get("sessions:{$userId}:count") ?? 0;
$maxSessions = $this->getMaxSessions($user);

if ($activeSessions >= $maxSessions) {
    return response()->json([
        'error' => 'Concurrent session limit exceeded',
        'max_sessions' => $maxSessions,
        'active_sessions' => $activeSessions
    ], 429);
}

Redis::incr("sessions:{$userId}:count");
Redis::expire("sessions:{$userId}:count", 1800); // 30 min
```

**Rationale**: Prevents account sharing, infrastructure abuse.

---

### Rule 4.3: Token Budget Enforcement (LLM Cost Control)

**Limits**:
- **Free Tier**: 4K tokens per query (max 10 queries/hour)
- **Paid Tier**: 16K tokens per query (max 100 queries/hour)
- **B2B Tier**: 32K tokens per query (max 1,000 queries/hour)
- **Enterprise**: 128K tokens per query (unlimited queries)

**Enforcement**:
```php
// In AIAgentService::execute()
$maxTokens = $this->getMaxTokens($user);

if ($estimatedTokens > $maxTokens) {
    return response()->json([
        'error' => 'Query too complex for your tier',
        'estimated_tokens' => $estimatedTokens,
        'max_tokens' => $maxTokens,
        'suggestion' => 'Simplify your query or upgrade tier'
    ], 413); // Payload Too Large
}
```

**Rationale**: Prevents LLM cost explosion. Complex queries require paid tier.

---

### Rule 4.4: Download Limits (Prevent Reselling)

**Limits**:
- **Itineraries**: Download up to 5 times (prevent sharing)
- **Accessibility Reports**: Download up to 10 times (legitimate re-downloads)
- **Activity Guides**: Download unlimited (valid for 1 year)

**Enforcement**:
```php
// In DownloadController::download()
$downloadCount = Purchase::where('id', $purchaseId)
    ->value('download_count');

$maxDownloads = $this->getMaxDownloads($productType);

if ($downloadCount >= $maxDownloads) {
    return response()->json([
        'error' => 'Download limit exceeded',
        'max_downloads' => $maxDownloads,
        'contact_support' => 'If you need more downloads, contact support'
    ], 403);
}

Purchase::where('id', $purchaseId)->increment('download_count');
```

**Rationale**: Prevents users from buying once and sharing with 100 friends.

---

### Rule 4.5: IP Blocking (Automated Abuse Detection)

**Triggers**:
- 1,000+ queries in 1 hour (scraping)
- 100+ failed authentication attempts (brute force)
- 50+ payment failures (fraud)
- 10+ CAPTCHA failures (bot)

**Enforcement**:
```php
// In AbuseDetectionMiddleware::handle()
$abuseScore = $this->calculateAbuseScore($ip);

if ($abuseScore >= 100) {
    Redis::setex("blocked_ip:{$ip}", 86400, 1); // Block for 24 hours
    
    return response()->json([
        'error' => 'IP blocked due to abuse',
        'reason' => $this->getAbuseReason($ip),
        'unblock_at' => now()->addDay()->toIso8601String()
    ], 403);
}
```

**Rationale**: Automated protection against bots, scrapers, fraudsters.

**Manual Override**: Only CEO or CTO can unblock IPs (requires database update).

---

### Rule 4.6: Circuit Breakers (Cost Control)

**Triggers**:
- LLM costs exceed $500/day (disable free tier)
- LLM costs exceed $5,000/day (disable all tiers, emergency shutdown)
- Error rate > 50% for 5 minutes (disable AI, fallback to search)
- LLM API down (5 consecutive failures, fallback to cache)

**Enforcement**:
```php
// In CircuitBreakerService::check()
$dailyCost = $this->getDailyCost();

if ($dailyCost > 5000) {
    Redis::set('ai:global_enabled', 0);
    $this->alertCEO('AI disabled: Daily cost exceeded $5K');
    
    throw new ServiceUnavailableException(
        'AI temporarily disabled due to cost spike'
    );
}

if ($dailyCost > 500) {
    Redis::set('ai:free_tier_enabled', 0);
    $this->alertCTO('Free tier disabled: Daily cost exceeded $500');
}
```

**Rationale**: Prevents runaway LLM costs from bankrupting company.

**Manual Override**: Only CEO can re-enable after cost spike investigation.

---

## 5. Enforcement Mechanisms

### 5.1 Code-Level Validation

**All business rules are enforced in code**:
- Middleware: Rate limiting, authentication, tier validation
- Controllers: Payment validation, download limits
- Services: Token budgets, data scope limits
- Database: Foreign keys, check constraints

**No bypasses allowed**: Even admin users must follow rules (use test mode for testing).

---

### 5.2 Database Constraints

**Examples**:
```sql
-- Prevent free itineraries
ALTER TABLE itineraries ADD CONSTRAINT check_payment
CHECK (payment_status = 'paid' OR days < 2);

-- Prevent bulk exports
ALTER TABLE api_keys ADD CONSTRAINT check_rate_limit
CHECK (rate_limit <= 10000);

-- Prevent unlimited downloads
ALTER TABLE purchases ADD CONSTRAINT check_downloads
CHECK (download_count <= max_downloads);
```

---

### 5.3 Monitoring and Alerts

**Real-time alerts for**:
- Rate limit violations (>100/hour per IP)
- Payment bypasses (free itineraries generated)
- Cost spikes (>$500/day LLM costs)
- Abuse patterns (scraping, bots)

**Alert recipients**: CTO (technical), CEO (business), on-call engineer (immediate)

---

### 5.4 Audit Logs

**All gated actions are logged**:
- Itinerary generation (paid/free, user, trip hash)
- Accessibility report access (paid/free, user, beach)
- Analytics access (B2B tier, user, query)
- Rate limit violations (IP, user, timestamp)

**Retention**: 90 days (GDPR compliance)

---

## 6. Exceptions and Overrides

### Rule 6.1: Test Mode (Developers Only)

**Allowed**:
- ✅ Generate free itineraries (marked as TEST)
- ✅ Bypass rate limits (test environment only)
- ✅ Access all tiers (test user accounts)

**Enforcement**:
```php
if (app()->environment('testing') || $user->is_test_user) {
    return; // Skip payment validation
}
```

**Restrictions**:
- ❌ Test mode NEVER enabled in production
- ❌ Test users NEVER have access to real user data
- ❌ Test purchases NEVER counted in revenue metrics

---

### Rule 6.2: Customer Support Overrides (Limited)

**Allowed**:
- ✅ Refund purchases (within 30 days)
- ✅ Reset download limits (legitimate cases)
- ✅ Unblock IPs (false positives)

**NOT Allowed**:
- ❌ Generate free itineraries for users
- ❌ Bypass payment for accessibility reports
- ❌ Grant B2B tier access without payment

**Enforcement**: Customer support has limited admin panel access (refunds only, no free products).

---

### Rule 6.3: CEO/CTO Overrides (Emergency Only)

**Allowed**:
- ✅ Disable AI globally (cost spike, outage)
- ✅ Unblock IPs (false positives)
- ✅ Adjust rate limits (DDoS attack)

**NOT Allowed**:
- ❌ Generate free itineraries (even for VIPs)
- ❌ Bypass payment rules (even for partners)

**Enforcement**: CEO/CTO overrides require database update + audit log entry.

---

## 7. Penalties for Rule Violations

### 7.1 Developer Violations

**Offense**: Bypassing payment rules, disabling rate limits, exposing paid content for free

**Penalty**:
- 1st offense: Written warning + code review
- 2nd offense: Suspension + mandatory training
- 3rd offense: Immediate termination

---

### 7.2 User Violations

**Offense**: Scraping, account sharing, payment fraud, reselling content

**Penalty**:
- 1st offense: Warning + temporary suspension (24 hours)
- 2nd offense: Permanent account ban + IP block
- 3rd offense: Legal action (theft of proprietary data)

---

### 7.3 B2B Customer Violations

**Offense**: Exceeding tier limits, sharing API keys, reselling data

**Penalty**:
- 1st offense: Warning + usage audit
- 2nd offense: Tier downgrade + contract renegotiation
- 3rd offense: Contract termination + legal action

---

**End of Business Rules Document**

These rules are **non-negotiable** and protect the business model. All violations are logged, monitored, and enforced automatically. Manual overrides require CEO/CTO approval and audit trail.

