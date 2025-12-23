# AI Operational Controls

**Last Updated**: 2025-12-23  
**Status**: APPROVED  
**Purpose**: Production operations and emergency controls

---

## 1. Overview

This document defines operational controls for Beachrex AI Agent to ensure safe, cost-effective, and reliable operation in production.

**Key Controls**:
- **Kill switches**: Global and per-tier service disabling
- **Circuit breakers**: Cost-based automatic shutdowns
- **Rate limits**: Per-tier request throttling
- **Token budgets**: Per-request and per-session limits
- **Monitoring**: Real-time metrics and alerts

---

## 2. Kill Switches

### 2.1 Global Kill Switch

**Purpose**: Disable AI service entirely (emergency shutdown)

**Environment Variable**: `AI_ENABLED`

**Config**: `config/ai.php`
```php
'features' => [
    'enabled' => env('AI_ENABLED', true),
],
```

**How to disable**:
```bash
# Set in .env
AI_ENABLED=false

# Clear config cache
php artisan config:clear
```

**Effect**:
- All AI endpoints return 503 Service Unavailable
- No LLM API calls made
- No costs incurred

**Use cases**:
- Emergency cost overrun
- LLM provider outage
- Security incident
- Maintenance window

### 2.2 Free Tier Kill Switch

**Purpose**: Disable free tier only (preserve paid tiers)

**Environment Variable**: `AI_FREE_TIER_ENABLED`

**Config**: `config/ai.php`
```php
'features' => [
    'free_tier_enabled' => env('AI_FREE_TIER_ENABLED', true),
],
```

**How to disable**:
```bash
# Set in .env
AI_FREE_TIER_ENABLED=false

# Clear config cache
php artisan config:clear
```

**Effect**:
- Free tier users get 503 Service Unavailable
- Paid tier users (basic, premium, enterprise) continue to work
- Reduces cost exposure while maintaining revenue

**Use cases**:
- Free tier cost overrun
- Abuse prevention
- Capacity management

---

## 3. Circuit Breakers

### 3.1 Free Tier Daily Limit

**Purpose**: Automatically disable free tier when daily cost exceeds threshold

**Threshold**: $500 USD/day (configurable)

**Environment Variable**: `AI_FREE_TIER_DAILY_LIMIT`

**Config**: `config/ai.php`
```php
'circuit_breakers' => [
    'free_tier_daily_limit' => env('AI_FREE_TIER_DAILY_LIMIT', 500.00),
],
```

**How it works**:
1. CircuitBreakerMiddleware checks daily cost for free tier
2. If cost >= $500, return 503 for free tier users
3. Paid tier users continue to work
4. Resets at midnight UTC

**Cache key**: `ai:circuit_breaker:daily_cost:free:{date}`

**Monitoring**:
```bash
# Check current free tier daily cost
php artisan tinker
>>> Cache::get('ai:circuit_breaker:daily_cost:free:' . now()->format('Y-m-d'))
```

**Manual override** (disable circuit breaker):
```bash
# Set very high limit
AI_FREE_TIER_DAILY_LIMIT=999999.00
php artisan config:clear
```

### 3.2 Emergency Shutdown Limit

**Purpose**: Disable ALL tiers when total daily cost exceeds threshold

**Threshold**: $5,000 USD/day (configurable)

**Environment Variable**: `AI_EMERGENCY_SHUTDOWN_LIMIT`

**Config**: `config/ai.php`
```php
'circuit_breakers' => [
    'emergency_shutdown_limit' => env('AI_EMERGENCY_SHUTDOWN_LIMIT', 5000.00),
],
```

**How it works**:
1. CircuitBreakerMiddleware checks total daily cost (all tiers)
2. If cost >= $5,000, return 503 for ALL users
3. Resets at midnight UTC

**Cache key**: `ai:circuit_breaker:daily_cost:all:{date}`

**Monitoring**:
```bash
# Check current total daily cost
php artisan tinker
>>> Cache::get('ai:circuit_breaker:daily_cost:all:' . now()->format('Y-m-d'))
```

**Manual override** (disable circuit breaker):
```bash
# Set very high limit
AI_EMERGENCY_SHUTDOWN_LIMIT=999999.00
php artisan config:clear
```

### 3.3 Circuit Breaker State

**States**:
- **CLOSED**: Normal operation, requests allowed
- **OPEN**: Circuit breaker triggered, requests blocked
- **HALF-OPEN**: Not implemented in MVP (future: gradual recovery)

**Current state**:
```php
// Check if free tier circuit breaker is open
$freeTierCost = Cache::get('ai:circuit_breaker:daily_cost:free:' . now()->format('Y-m-d'), 0);
$freeTierLimit = config('ai.circuit_breakers.free_tier_daily_limit');
$freeTierOpen = $freeTierCost >= $freeTierLimit;

// Check if emergency circuit breaker is open
$totalCost = Cache::get('ai:circuit_breaker:daily_cost:all:' . now()->format('Y-m-d'), 0);
$emergencyLimit = config('ai.circuit_breakers.emergency_shutdown_limit');
$emergencyOpen = $totalCost >= $emergencyLimit;
```

**Last trip time**:
```php
// Log when circuit breaker trips
Log::critical('AI circuit breaker triggered', [
    'tier' => 'free', // or 'all'
    'daily_cost_usd' => $dailyCost,
    'limit_usd' => $limit,
    'timestamp' => now()->toIso8601String(),
]);
```

**Monitoring dashboard** (future):
- Current state (OPEN/CLOSED)
- Last trip time
- Current daily cost
- Remaining budget
- Estimated time to trip

---

## 4. Rate Limits

### 4.1 Per-Tier Rate Limits

**Purpose**: Prevent abuse and ensure fair usage

**Config**: `config/ai.php`
```php
'tiers' => [
    'free' => [
        'rate_limit' => [
            'requests_per_hour' => 100,
            'requests_per_day' => 500,
        ],
    ],
    'basic' => [
        'rate_limit' => [
            'requests_per_hour' => 500,
            'requests_per_day' => 2000,
        ],
    ],
    // ... premium, enterprise
],
```

**How it works**:
1. RateLimitMiddleware checks request count
2. Anonymous users: rate limit by hashed IP
3. Authenticated users: rate limit by user ID
4. If limit exceeded, return 429 Too Many Requests

**Cache keys**:
- Anonymous: `ai:rate_limit:ip:{hashedIp}`
- Authenticated: `ai:rate_limit:user:{userId}`

**Monitoring**:
```bash
# Check rate limit for IP
php artisan tinker
>>> $hashedIp = hash('sha256', '192.168.1.1');
>>> Cache::get("ai:rate_limit:ip:{$hashedIp}")
```

### 4.2 Manual Rate Limit Override

**Temporarily increase rate limit for specific user**:
```php
// In tinker or admin panel
$userId = 123;
Cache::forget("ai:rate_limit:user:{$userId}");
```

**Temporarily ban user** (set rate limit to 0):
```php
// Not implemented in MVP - future feature
// Would require custom rate limit per user in database
```

---

## 5. Token Budgets

### 5.1 Per-Request Token Limits

**Purpose**: Prevent single request from consuming excessive tokens

**Config**: `config/ai.php`
```php
'tiers' => [
    'free' => [
        'token_budget' => [
            'max_tokens_per_request' => 4000,
        ],
    ],
    // ... other tiers
],
```

**How it works**:
1. TokenBudgetMiddleware estimates tokens from message length
2. Estimation: 1 token ≈ 4 characters
3. If estimated tokens > limit, return 413 Payload Too Large

### 5.2 Per-Session Token Limits

**Purpose**: Prevent session from consuming excessive tokens over time

**Config**: `config/ai.php`
```php
'tiers' => [
    'free' => [
        'token_budget' => [
            'max_tokens_per_session' => 16000,
        ],
    ],
    // ... other tiers
],
```

**How it works**:
1. TokenBudgetMiddleware checks session's total tokens_used
2. If tokens_used + estimated_tokens > limit, return 413

**Monitoring**:
```bash
# Check session token usage
php artisan tinker
>>> $session = \App\Models\AiSession::find('session-uuid');
>>> $session->tokens_used
```

---

## 6. Monitoring & Alerts

### 6.1 Real-Time Metrics

**Metrics tracked** (in cache, 7-day retention):
- Total requests (success + failure)
- Success count
- Error count by HTTP status (400, 402, 413, 429, 503, 500)
- Error count by error code
- Average latency
- Average cost
- Total daily cost

**Cache keys**:
- `ai:metrics:requests:total:{date}`
- `ai:metrics:requests:success:{date}`
- `ai:metrics:requests:error:{httpStatus}:{date}`
- `ai:metrics:requests:error_code:{errorCode}:{date}`
- `ai:metrics:latency:sum:{date}`
- `ai:metrics:latency:count:{date}`
- `ai:metrics:cost:sum:{date}`

**Query metrics**:
```bash
php artisan tinker
>>> $date = now()->format('Y-m-d');
>>> $total = Cache::get("ai:metrics:requests:total:{$date}", 0);
>>> $success = Cache::get("ai:metrics:requests:success:{$date}", 0);
>>> $errors = $total - $success;
>>> $errorRate = $total > 0 ? ($errors / $total) * 100 : 0;
>>> echo "Total: {$total}, Success: {$success}, Errors: {$errors}, Error Rate: {$errorRate}%";
```

### 6.2 Alerts (Future Implementation)

**Critical alerts** (immediate action required):
- Emergency circuit breaker triggered (total cost > $5,000/day)
- Error rate > 50% for 5 minutes
- Average latency > 10 seconds for 5 minutes
- LLM provider API down

**Warning alerts** (investigate soon):
- Free tier circuit breaker triggered (free tier cost > $500/day)
- Error rate > 25% for 15 minutes
- Average latency > 5 seconds for 15 minutes
- Daily cost > $1,000 (approaching emergency limit)

**Info alerts** (FYI):
- Daily cost > $500
- Rate limit hit > 100 times/hour
- Token budget exceeded > 50 times/hour

---

## 7. Emergency Procedures

### 7.1 Cost Overrun

**Symptoms**: Daily cost exceeding budget

**Actions**:
1. Check current daily cost:
   ```bash
   php artisan tinker
   >>> Cache::get('ai:circuit_breaker:daily_cost:all:' . now()->format('Y-m-d'))
   ```

2. If free tier is the issue, disable free tier:
   ```bash
   AI_FREE_TIER_ENABLED=false
   php artisan config:clear
   ```

3. If all tiers are the issue, disable AI entirely:
   ```bash
   AI_ENABLED=false
   php artisan config:clear
   ```

4. Investigate root cause (check logs, usage patterns)

5. Re-enable service once issue resolved

### 7.2 LLM Provider Outage

**Symptoms**: High error rate, 500 errors, timeout errors

**Actions**:
1. Check LLM provider status page
2. If provider is down, disable AI service:
   ```bash
   AI_ENABLED=false
   php artisan config:clear
   ```
3. Monitor provider status
4. Re-enable service once provider is back online

### 7.3 Abuse Detection

**Symptoms**: Unusual traffic patterns, high rate limit hits

**Actions**:
1. Identify abusive IPs/users from logs
2. Temporarily disable free tier if abuse is from anonymous users:
   ```bash
   AI_FREE_TIER_ENABLED=false
   php artisan config:clear
   ```
3. Investigate and block abusive IPs at firewall level
4. Re-enable free tier once abuse stopped

---

## 8. Operational Checklist

**Daily**:
- [ ] Check daily cost (should be < $500 for free tier, < $5,000 total)
- [ ] Check error rate (should be < 5%)
- [ ] Check average latency (should be < 3 seconds)

**Weekly**:
- [ ] Review cost trends (increasing/decreasing?)
- [ ] Review error patterns (any new error types?)
- [ ] Review rate limit hits (any abuse patterns?)

**Monthly**:
- [ ] Review circuit breaker trips (how many times? why?)
- [ ] Review retention policy compliance (data cleanup working?)
- [ ] Review user redaction requests (how many? any issues?)

---

## 9. References

- **config/ai.php**: AI service configuration
- **app/Http/Middleware/AI/CircuitBreakerMiddleware.php**: Circuit breaker implementation
- **app/Http/Middleware/AI/RateLimitMiddleware.php**: Rate limit implementation
- **app/Http/Middleware/AI/TokenBudgetMiddleware.php**: Token budget implementation
- **docs/ai/architecture/data-retention-policy.md**: Data retention and redaction
- **docs/ai/implementation/STEP6_COMPLETION_REPORT.md**: Logging & observability

