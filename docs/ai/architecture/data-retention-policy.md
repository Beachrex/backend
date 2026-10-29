# AI Data Retention & Redaction Policy

**Last Updated**: 2025-12-23  
**Status**: APPROVED  

> **Note 2026-10-29:** the cleanup jobs below were never implemented; nothing deletes `ai_sessions`, `ai_messages`
> or `ai_usage` today. The retention period of chat conversations is an open owner decision (ADR 2026-10-29
> "AI chat history in the admin").
**GDPR Compliance**: YES

---

## 1. Overview

This document defines data retention and redaction policies for Beachrex AI Agent to ensure GDPR compliance and operational efficiency.

**Key Principles**:
- **Minimal retention**: Keep data only as long as needed for business purposes
- **User control**: Users can request deletion of their AI history
- **Privacy by design**: No PII in logs, hashed IPs only
- **Audit trail**: Maintain compliance audit logs

---

## 2. Data Storage

### 2.1 Database Tables

#### `ai_sessions`
**Purpose**: Track AI conversation sessions  
**Retention**: 90 days  
**PII**: Hashed IP address (SHA-256), user_agent  
**Redaction**: Delete on user request or after 90 days

**Fields**:
- `id` (UUID) - Session identifier
- `user_id` (nullable) - User ID if authenticated
- `tier` - User tier (free, basic, premium, enterprise)
- `locale` - User locale (en, hr, sl, etc.)
- `ip_address` - **SHA-256 hashed IP** (not raw IP)
- `user_agent` - Browser user agent
- `messages_count` - Number of messages in session
- `tokens_used` - Total tokens consumed
- `last_activity_at` - Last activity timestamp
- `created_at`, `updated_at`

#### `ai_messages`
**Purpose**: Store conversation history for context  
**Retention**: 30 days  
**PII**: User message content (justification: required for multi-turn conversations)  
**Redaction**: Delete on user request or after 30 days

**Fields**:
- `id` - Message ID
- `session_id` - Session UUID
- `role` - Message role (user, assistant, system)
- `content` - **Message content** (user input or AI response)
- `intent` - Detected intent (beach_search, etc.)
- `context` - JSONB context (beaches returned, filters applied)
- `tokens_input`, `tokens_output`, `tokens_total`
- `created_at`, `updated_at`

**Justification for storing message content**:
- Required for multi-turn conversations (context from previous messages)
- Required for debugging and improving AI responses
- Required for user history (users can view their past conversations)
- Retention limited to 30 days (not indefinite)

#### `ai_usage`
**Purpose**: Cost tracking and analytics  
**Retention**: 365 days (1 year for financial records)  
**PII**: Hashed IP address (SHA-256)  
**Redaction**: Anonymize user_id on user request (keep aggregated data)

**Fields**:
- `id` - Usage record ID
- `user_id` (nullable) - User ID if authenticated
- `session_id` - Session UUID
- `tier` - User tier
- `event_type` - Event type (chat, beach_search, etc.)
- `llm_provider`, `llm_model` - LLM provider and model
- `tokens_input`, `tokens_output`, `tokens_total`
- `cost_usd` - Cost in USD
- `latency_ms` - Request latency
- `cache_hit` - Whether response was cached
- `beaches_fetched`, `beaches_returned`
- `context_truncated` - Whether context was truncated
- `locale` - User locale
- `user_agent` - Browser user agent
- `ip_address` - **SHA-256 hashed IP** (not raw IP)
- `request_id` - Request UUID for tracing
- `created_at`, `updated_at`

**Justification for 1-year retention**:
- Required for financial records and cost tracking
- Required for B2B analytics product (beach search trends)
- Anonymized on user request (user_id set to NULL, keep aggregated data)

---

## 3. Retention Periods

| Data Type | Retention Period | Justification |
|-----------|------------------|---------------|
| **ai_sessions** | 90 days | Session context for debugging and user history |
| **ai_messages** | 30 days | Conversation history for multi-turn context |
| **ai_usage** | 365 days | Financial records and analytics |
| **Application logs** | 30 days | Debugging and monitoring |
| **Metrics cache** | 7 days | Real-time monitoring dashboards |

---

## 4. Automated Cleanup

### 4.1 Database Cleanup Jobs

**Schedule**: Daily at 2:00 AM UTC

**Jobs**:
1. **Delete expired sessions** (> 90 days)
   ```sql
   DELETE FROM ai_sessions WHERE created_at < NOW() - INTERVAL '90 days';
   ```

2. **Delete expired messages** (> 30 days)
   ```sql
   DELETE FROM ai_messages WHERE created_at < NOW() - INTERVAL '30 days';
   ```

3. **Delete expired usage records** (> 365 days)
   ```sql
   DELETE FROM ai_usage WHERE created_at < NOW() - INTERVAL '365 days';
   ```

**Implementation**: Laravel scheduled command

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('ai:cleanup-expired-data')->dailyAt('02:00');
}
```

### 4.2 Log Rotation

**Application logs**: Rotate daily, keep 30 days

**Laravel logging config** (`config/logging.php`):
```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 30, // Keep 30 days
],
```

---

## 5. User Data Redaction (GDPR Right to Erasure)

### 5.1 User Request Process

When a user requests deletion of their AI history:

1. **Verify user identity** (authenticated request or email verification)
2. **Execute redaction script** (see below)
3. **Confirm deletion** (email confirmation to user)
4. **Log redaction event** (audit trail)

### 5.2 Redaction Script

**Command**: `php artisan ai:redact-user-data {userId}`

**Actions**:
1. Delete all sessions for user:
   ```sql
   DELETE FROM ai_sessions WHERE user_id = ?;
   -- Cascades to ai_messages via foreign key
   ```

2. Anonymize usage records (keep aggregated data for analytics):
   ```sql
   UPDATE ai_usage 
   SET user_id = NULL, 
       ip_address = 'REDACTED',
       user_agent = 'REDACTED'
   WHERE user_id = ?;
   ```

3. Log redaction event:
   ```php
   Log::info('User data redacted', [
       'user_id' => $userId,
       'timestamp' => now()->toIso8601String(),
       'redacted_sessions' => $sessionCount,
       'anonymized_usage_records' => $usageCount,
   ]);
   ```

**Implementation**:
```php
// app/Console/Commands/RedactUserData.php
public function handle()
{
    $userId = $this->argument('userId');
    
    DB::transaction(function () use ($userId) {
        // Delete sessions (cascades to messages)
        $sessionCount = AiSession::where('user_id', $userId)->count();
        AiSession::where('user_id', $userId)->delete();
        
        // Anonymize usage records
        $usageCount = AiUsage::where('user_id', $userId)
            ->update([
                'user_id' => null,
                'ip_address' => 'REDACTED',
                'user_agent' => 'REDACTED',
            ]);
        
        // Log redaction
        Log::info('User data redacted', [
            'user_id' => $userId,
            'redacted_sessions' => $sessionCount,
            'anonymized_usage_records' => $usageCount,
        ]);
    });
    
    $this->info("User data redacted successfully.");
}
```

---

## 6. GDPR Compliance Checklist

- ✅ **Minimal data collection**: Only collect data necessary for service operation
- ✅ **No raw IP addresses**: All IPs hashed with SHA-256
- ✅ **No PII in logs**: Logs contain only request_id, session_id, metrics
- ✅ **User consent**: Terms of Service include AI data collection disclosure
- ✅ **Right to access**: Users can view their AI conversation history
- ✅ **Right to erasure**: Users can request deletion of their AI data
- ✅ **Data retention limits**: Automatic cleanup after retention periods
- ✅ **Audit trail**: All redaction events logged
- ✅ **Encryption at rest**: Database encrypted (PostgreSQL encryption)
- ✅ **Encryption in transit**: HTTPS/TLS for all API requests

---

## 7. Monitoring & Alerts

### 7.1 Retention Monitoring

**Metrics to track**:
- Total sessions in database
- Total messages in database
- Total usage records in database
- Oldest session age
- Oldest message age
- Oldest usage record age

**Alerts**:
- Alert if oldest session > 95 days (cleanup job may be failing)
- Alert if oldest message > 35 days (cleanup job may be failing)
- Alert if oldest usage record > 370 days (cleanup job may be failing)

### 7.2 Redaction Monitoring

**Metrics to track**:
- Total redaction requests per month
- Average redaction time
- Failed redaction attempts

**Alerts**:
- Alert if redaction fails (manual intervention required)

---

## 8. Implementation Checklist

- [ ] Create `ai:cleanup-expired-data` command
- [ ] Create `ai:redact-user-data` command
- [ ] Schedule daily cleanup job
- [ ] Add redaction endpoint to user settings
- [ ] Add "View AI History" page to user dashboard
- [ ] Add "Delete AI History" button to user settings
- [ ] Update Terms of Service with AI data collection disclosure
- [ ] Update Privacy Policy with retention periods
- [ ] Add monitoring dashboard for retention metrics
- [ ] Add alerts for cleanup job failures

---

## 9. References

- **GDPR Article 17**: Right to erasure ("right to be forgotten")
- **GDPR Article 5**: Principles relating to processing of personal data
- **GDPR Article 25**: Data protection by design and by default
- **docs/ai/architecture/api-contracts.md**: API contracts and privacy requirements
- **docs/ai/implementation/STEP6_COMPLETION_REPORT.md**: Logging & observability implementation

