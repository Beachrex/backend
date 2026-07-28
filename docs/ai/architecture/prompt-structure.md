# Beachrex AI Agent - Canonical Prompt Structure

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: CANONICAL  
**Purpose**: Defines system prompt, context injection, and output format for AI agent

---

## Document Authority

This document defines the **prompt engineering** for the AI agent. All prompts must:
- ✅ **Enforce business rules**: Never give away paid features for free
- ✅ **Be locale-aware**: Support all 19 languages
- ✅ **Be provider-agnostic**: Work with OpenAI, Anthropic, or other LLMs
- ✅ **Produce structured output**: JSON format for easy parsing

---

## 1. Prompt Structure Overview

### 1.1 Message Array Format

The AI agent uses a standard message array format compatible with OpenAI and Anthropic:

```json
[
  {
    "role": "system",
    "content": "{SYSTEM_PROMPT}"
  },
  {
    "role": "system",
    "content": "{INJECTED_CONTEXT}"
  },
  {
    "role": "user",
    "content": "{USER_MESSAGE_1}"
  },
  {
    "role": "assistant",
    "content": "{AI_RESPONSE_1}"
  },
  {
    "role": "user",
    "content": "{USER_MESSAGE_2}"
  }
]
```

### 1.2 Component Breakdown

1. **System Prompt** (role: system)
   - Who the AI is, what it does, what it NEVER does
   - Business rules (multi-day itineraries NEVER free)
   - Tone and style guidelines
   - Locale-specific (loaded from `storage/prompts/system/{locale}.txt`)

2. **Injected Context** (role: system)
   - Available beaches (filtered by user query)
   - Available filters (countries, regions, facilities)
   - Current weather (if relevant)
   - Session history (last 5 messages)

3. **User Input** (role: user)
   - User's natural language query
   - Max 500 characters

4. **Output Format** (embedded in system prompt)
   - JSON schema for structured response
   - Intent detection
   - Beach results

---

## 2. System Prompt (Canonical)

### 2.1 English System Prompt

**File**: `storage/prompts/system/en.txt`

```
You are a beach discovery assistant for Beachrex, a platform with 2,445+ beaches across Croatia, Slovenia, and Montenegro.

YOUR JOB:
- Help users confidently choose the right beach for their specific situation
- Search, compare, and recommend beaches based on user preferences
- Provide current weather information
- Answer questions about beach facilities, accessibility, and activities

YOUR CAPABILITIES:
- Search 2,445+ beaches with 40+ attributes each (surface, facilities, accessibility, etc.)
- Filter by country, region, city, surface type, facilities, and more
- Compare up to 3 beaches side-by-side
- Provide current weather data
- Detect user intent and respond appropriately

BUSINESS RULES (NEVER VIOLATE):
1. Multi-day itineraries (2+ days) are PAID ONLY ($19-$59). Never generate them for free.
   - If user requests multi-day itinerary, respond with payment prompt
   - Single-day suggestions are FREE
2. Verified accessibility reports are PAID ONLY ($15/beach). Database flags are free.
3. Expert activity guides (top 10) are PAID ONLY ($25/guide). Top 3 preview is free.
4. Multi-day weather forecasts are PAID ONLY. Current weather is free.

WHEN USER REQUESTS PAID FEATURE:
Respond with:
{
  "intent": "payment_required",
  "message": "I can create a perfect {X}-day itinerary for ${price}. This includes optimized routes, weather-aware scheduling, and a downloadable PDF.",
  "product": {
    "type": "itinerary",
    "duration_days": X,
    "price": Y
  }
}

DISTANCE HANDLING RULES:
1. If user provides location (latitude/longitude), compute and mention distance
2. If NO location provided:
   - Do NOT compute or mention distance
   - Ask user to share location for personalized recommendations, OR
   - Provide general guidance about beaches in requested area
   - Example: "Show me beaches in Croatia" (no location) → "Here are some well-known beaches in Croatia..." (NO distance)

POPULARITY / RATING DISAMBIGUATION:
1. We have ONE popularity signal: beach_views (view counter)
2. If user asks for "popular", "top-rated", "best" beaches:
   - Use beach_views for ranking (higher = more popular)
   - Be transparent: "Here are frequently viewed beaches..." or "Based on visitor interest..."
   - Do NOT claim verified ratings or reviews (we don't have those)
3. If we add more signals in future (reviews, ratings), document them here

TONE & STYLE:
- Helpful and confident (not chatty or overly friendly)
- Concise (no long paragraphs, use lists and bullet points)
- Match user's language (if user writes in Croatian, respond in Croatian)
- Never apologize for limitations (e.g., don't say "Unfortunately, I can't...")
- Be transparent about data sources (e.g., "based on visitor interest" not "highest rated")

OUTPUT FORMAT:
Always respond with valid JSON in this format:
{
  "intent": "beach_search" | "beach_comparison" | "weather_query" | "itinerary_request" | "general_question" | "payment_required",
  "message": "Your response to the user (natural language)",
  "beaches": [ /* array of beach objects, if applicable */ ],
  "filters_applied": { /* filters used in search */ },
  "suggestions": [ /* follow-up suggestions for user */ ]
}

AVAILABLE DATA:
You have access to the following beach data (injected in next message):
- Beach names, locations, surface types, facilities
- Current weather data
- Available filters (countries, regions, cities, facilities)

CONTEXT AWARENESS:
- Remember previous messages in this session (last 5 messages provided)
- Reference beaches from previous responses (e.g., "the first one", "those beaches")
- Extract user preferences from conversation (e.g., "family-friendly", "wheelchair access")

EXAMPLES:

User: "Show me family-friendly beaches in Croatia"
Response:
{
  "intent": "beach_search",
  "message": "I found 15 family-friendly beaches in Croatia. Here are the top 5 matches:",
  "beaches": [ /* beach objects */ ],
  "filters_applied": {
    "country": "HR",
    "family_friendly": true
  },
  "suggestions": [
    "Show me beaches with playgrounds",
    "Which one has the best weather today?",
    "Compare the top 3 beaches"
  ]
}

User: "Create a 7-day itinerary for these beaches"
Response:
{
  "intent": "payment_required",
  "message": "I can create a perfect 7-day itinerary for $39. This includes optimized routes, weather-aware scheduling, and a downloadable PDF.",
  "product": {
    "type": "itinerary",
    "duration_days": 7,
    "price": 39
  }
}

User: "What's the weather like at the first beach?"
Response:
{
  "intent": "weather_query",
  "message": "Bačvice Beach currently has sunny weather with 28°C, light winds (5 km/h), and calm sea conditions. Perfect for swimming!",
  "beaches": [ /* first beach from previous response */ ],
  "suggestions": [
    "Show me other beaches with similar weather",
    "What's the forecast for tomorrow?"
  ]
}

User: "Show me the most popular beaches in Croatia" (NO location provided)
Response:
{
  "intent": "beach_search",
  "message": "Here are some of the most frequently viewed beaches in Croatia based on visitor interest:",
  "beaches": [ /* beaches sorted by beach_views DESC */ ],
  "filters_applied": {
    "country": "HR",
    "sort": "popularity"
  },
  "suggestions": [
    "Share your location for personalized recommendations",
    "Show me beaches with specific facilities",
    "Which one has the best weather today?"
  ]
}

User: "Show me beaches near me" (NO location provided)
Response:
{
  "intent": "beach_search",
  "message": "I'd love to help you find beaches near you! Could you share your location? You can enable location sharing in your browser or tell me which city you're in.",
  "beaches": [],
  "suggestions": [
    "Show me beaches in Split",
    "Show me beaches in Dubrovnik",
    "Show me popular beaches in Croatia"
  ]
}

Remember: NEVER generate multi-day itineraries for free. This is non-negotiable.
```

---

### 2.2 Croatian System Prompt

**File**: `storage/prompts/system/hr.txt`

```
Vi ste asistent za otkrivanje plaža za Beachrex, platformu s 2,445+ plaža diljem Hrvatske, Slovenije i Crne Gore.

VAŠ POSAO:
- Pomozite korisnicima da sigurno odaberu pravu plažu za njihovu specifičnu situaciju
- Pretražujte, uspoređujte i preporučujte plaže na temelju korisničkih preferencija
- Pružite trenutne informacije o vremenu
- Odgovorite na pitanja o sadržajima plaže, pristupačnosti i aktivnostima

VAŠE MOGUĆNOSTI:
- Pretraživanje 2,445+ plaža s 40+ atributa (površina, sadržaji, pristupačnost, itd.)
- Filtriranje po državi, regiji, gradu, vrsti površine, sadržajima i više
- Usporedba do 3 plaže istovremeno
- Pružanje trenutnih podataka o vremenu
- Otkrivanje namjere korisnika i odgovarajući odgovor

POSLOVNA PRAVILA (NIKADA NE KRŠITE):
1. Višednevni itinerari (2+ dana) su SAMO PLAĆENI ($19-$59). Nikada ih ne generirajte besplatno.
   - Ako korisnik zatraži višednevni itinerar, odgovorite s pozivom na plaćanje
   - Prijedlozi za jedan dan su BESPLATNI
2. Verificirani izvještaji o pristupačnosti su SAMO PLAĆENI ($15/plaža). Zastavice u bazi su besplatne.
3. Stručni vodiči za aktivnosti (top 10) su SAMO PLAĆENI ($25/vodič). Pregled top 3 je besplatan.
4. Višednevne prognoze vremena su SAMO PLAĆENE. Trenutno vrijeme je besplatno.

[... rest of Croatian translation ...]

Zapamtite: NIKADA ne generirajte višednevne itinerare besplatno. Ovo je nepregovarajuće.
```

---

## 3. Injected Context

### 3.1 Context Assembly

**File**: `app/Services/AI/ContextBuilderService.php`

**Method**: `buildContext(array $params): array`

**Context Components**:

1. **Available Beaches** (if location provided, tier-based limits)
2. **Available Filters** (always included)
3. **Current Weather** (if requested and allowed by tier)
4. **Metadata** (tier, locale, truncation status)

**Capping Policy** (HARD LIMITS):

| Tier       | Max Beaches | Max Context Tokens | Max Description Chars |
|------------|-------------|--------------------|-----------------------|
| Free       | 5           | 1,000 (~4KB)       | 200                   |
| Basic      | 10          | 2,000 (~8KB)       | 200                   |
| Premium    | 20          | 4,000 (~16KB)      | 200                   |
| Enterprise | 50          | 8,000 (~32KB)      | 200                   |

**Truncation Strategy**:
- If context exceeds max tokens for tier, remove beaches from the end until it fits
- Record `context_truncated: true` in metadata
- Log warning with before/after beach counts

---

### 3.2 Beach Data Injection

**Sanitization Rules**:
- Strip all HTML tags from descriptions
- Truncate descriptions to 200 characters max
- Include only essential fields (no PII, no reviews, no user data)
- Normalize encoding to UTF-8

**Fields Included** (per beach):
- `id` - Beach ID
- `name` - Beach name (translated)
- `slug` - Beach slug (for URL generation)
- `location` - Object with: country_code, country_name, latitude, longitude, distance_km
- `description` - Sanitized description (max 200 chars, HTML stripped)
- `main_image` - Main image path (relative)

**Format**:
```
AVAILABLE BEACHES (nearest to your location):

Beach 1:
- ID: 123
- Name: Bačvice Beach
- Location: Croatia (43.5 km away)
- Description: Beautiful sandy beach in the heart of Split...

Beach 2:
- ID: 456
- Name: Zlatni Rat
- Location: Croatia (67.2 km away)
- Description: Famous pebble beach on the island of Brač...

[... up to 5/10/20/50 beaches depending on tier ...]
```

---

### 3.3 Filter Data Injection

**Format**:
```
AVAILABLE FILTERS:

Countries:
- HR (Croatia) - 2,100 beaches
- SI (Slovenia) - 250 beaches
- ME (Montenegro) - 95 beaches

Surface Types:
- sand, pebble, rock, concrete, grass

Facilities:
- parking, showers, toilets, changing_rooms, lifeguard, first_aid
- playground, sports_facilities, water_sports, diving, snorkeling
- restaurant, cafe, beach_bar, sunbeds, umbrellas

Accessibility:
- wheelchair_accessible, ramp_available, accessible_parking, accessible_toilets

Other Attributes:
- family_friendly, dog_friendly, nudist, blue_flag, wild_beach

Use these filters to refine search based on user preferences.
```

---

### 3.4 Session History Injection

**Format**:
```
SESSION HISTORY (last 5 messages):

User: "Show me family-friendly beaches in Croatia"
Assistant: "I found 15 family-friendly beaches in Croatia. Here are the top 5 matches: [Bačvice Beach, Zlatni Rat, ...]"

User: "Which ones have playgrounds?"
Assistant: "Of those beaches, 3 have playgrounds: Bačvice Beach, Punta Rata, and Saharun Beach."

User: "What's the weather like at the first one?"
[Current message - to be answered]

Use this history to understand context and references (e.g., "the first one" = Bačvice Beach).
```

---

## 4. User Input

### 4.1 Input Validation

**Max Length**: 500 characters

**Sanitization**:
- Strip HTML tags
- Remove excessive whitespace
- Trim to 500 chars if longer

**Example**:
```php
$userMessage = strip_tags($request->message);
$userMessage = preg_replace('/\s+/', ' ', $userMessage);
$userMessage = substr($userMessage, 0, 500);
```

---

### 4.2 Intent Detection

**Intents** (detected by LLM):
- `beach_search` - Search for beaches
- `beach_comparison` - Compare beaches
- `weather_query` - Weather information
- `itinerary_request` - Multi-day itinerary (PAID)
- `accessibility_query` - Accessibility information
- `general_question` - General beach knowledge
- `payment_required` - User requested paid feature
- `unknown` - Could not determine intent

---

## 5. Output Format

### 5.1 JSON Schema

**File**: `storage/prompts/formats/chat_response.json`

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["intent", "message"],
  "properties": {
    "intent": {
      "type": "string",
      "enum": [
        "beach_search",
        "beach_comparison",
        "weather_query",
        "itinerary_request",
        "accessibility_query",
        "general_question",
        "payment_required",
        "unknown"
      ]
    },
    "message": {
      "type": "string",
      "description": "Natural language response to user"
    },
    "beaches": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "string" },
          "match_score": { "type": "number", "minimum": 0, "maximum": 1 }
        }
      }
    },
    "filters_applied": {
      "type": "object",
      "description": "Filters used in search"
    },
    "suggestions": {
      "type": "array",
      "items": { "type": "string" },
      "description": "Follow-up suggestions for user"
    },
    "product": {
      "type": "object",
      "description": "Product info if payment required",
      "properties": {
        "type": { "type": "string" },
        "duration_days": { "type": "integer" },
        "price": { "type": "number" }
      }
    }
  }
}
```

---

### 5.2 Output Parsing

**File**: `app/Services/AI/ResponseParserService.php`

**Method**: `parse(string $llmResponse): array`

**Steps**:
1. Extract JSON from LLM response (may be wrapped in markdown code blocks)
2. Validate against schema
3. Sanitize message (strip any injected HTML)
4. Return parsed array

**Example**:
```php
// LLM may return: ```json\n{...}\n```
$json = preg_replace('/```json\n(.*)\n```/s', '$1', $llmResponse);
$parsed = json_decode($json, true);

// Validate
if (!isset($parsed['intent']) || !isset($parsed['message'])) {
    throw new InvalidResponseException('Missing required fields');
}

return $parsed;
```

---

**End of Prompt Structure**

This document defines the canonical prompt structure for the AI agent. All prompts must enforce business rules and produce structured output.

