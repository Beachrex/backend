# Beach Details API Documentation

## Overview

The Beach Details API provides comprehensive beach information for detail pages on the frontend. This document explains the design decisions and usage.

## API Design Decision: Single Comprehensive Endpoint

### Endpoint
```
GET /api/v1/beach-details/{identifier}
```

**Identifier can be:**
- Beach ID (numeric): `/api/v1/beach-details/123`
- Beach slug (any language): `/api/v1/beach-details/paradise-beach`

### Why Single Endpoint?

After analyzing your beach data structure (60+ relationships, multiple media collections, characteristics, etc.), I recommend **one comprehensive endpoint** that returns all data. Here's why:

#### ✅ Advantages

1. **Simpler Frontend Code**
   - One API call gets everything
   - No need to orchestrate multiple requests
   - Easier error handling

2. **Better Performance**
   - One HTTP request vs 5-10 separate requests
   - Reduced network overhead
   - Lower latency

3. **Atomic Data**
   - All data is consistent (same timestamp)
   - No race conditions between multiple requests
   - Easier caching strategy

4. **Better for SEO/SSR**
   - Server-side rendering gets all data in one call
   - Faster initial page load
   - Better Core Web Vitals scores

5. **Easier to Maintain**
   - Single source of truth
   - One resource class to maintain
   - Consistent data structure

#### ⚠️ Potential Concerns & Solutions

**Concern:** "Response might be too large"
- **Reality:** With proper eager loading, response is ~50-100KB (compressed ~10-20KB)
- **Solution:** Already implemented efficient eager loading in controller
- **Comparison:** Loading 10 separate endpoints would transfer MORE data due to HTTP overhead

**Concern:** "What if we only need media?"
- **Solution:** You can add specialized endpoints later if needed:
  - `GET /api/v1/beach-details/{id}/media` - Just media
  - `GET /api/v1/beach-details/{id}/characteristics` - Just characteristics
- **Note:** Start with comprehensive endpoint, add specialized ones only if performance testing shows need

## Response Structure

The response is organized into logical sections for easy frontend consumption:

```json
{
  "data": {
    "basic_info": {
      "id": 123,
      "name": "Paradise Beach",
      "slug": "paradise-beach",
      "description": "Beautiful sandy beach...",
      "latitude": "42.5996000",
      "longitude": "18.2302000",
      "folder_name": "paradise-beach-123",
      "mobile_upload": false
    },
    
    "location": {
      "continent": { "code": "EU", "name": "Europe" },
      "country": { "code": "HR", "name": "Croatia", "full_name": "Republic of Croatia" },
      "region": { "id": 1, "name": "Dalmatia", "slug": "dalmatia" },
      "city": { "id": 1, "name": "Split", "slug": "split" },
      "island": { "id": 1, "name": "Hvar", "slug": "hvar" },
      "river": null,
      "lake": null
    },
    
    "characteristics": {
      "beach_type": { "id": 1, "code": "sand", "name": "Sand" },
      "waterbody": { "id": 1, "code": "sea", "name": "Sea" },
      "water_body_type": { "id": 1, "code": "adriatic", "name": "Adriatic Sea" },
      "area_description": { "id": 1, "code": "urban", "name": "Urban" },
      "location_types": [
        { "id": 1, "code": "bay", "name": "Bay" }
      ],
      "boat_only": false
    },
    
    "accessibility": {
      "accessibility": { "id": 1, "code": "easy", "name": "Easy Access" },
      "parking": 1,
      "road_to_parking_type": { "id": 1, "code": "paved", "name": "Paved Road" },
      "parking_capacity": { "id": 1, "code": "large", "name": "Large (100+ spaces)" },
      "parking_beach_distance": { "id": 1, "code": "50m", "name": "50 meters" },
      "parking_beach_height": { "id": 1, "code": "0m", "name": "Same level" },
      "recommended_transport_types": [
        { "id": 1, "code": "car", "name": "Car" },
        { "id": 2, "code": "bus", "name": "Bus" }
      ]
    },
    
    "dimensions": {
      "location_length": { "id": 1, "code": "500m", "name": "500 meters" },
      "bay_length": { "id": 1, "code": "300m", "name": "300 meters" },
      "beach_inclination": { "id": 1, "code": "gentle", "name": "Gentle" },
      "swimming_depth_of_one_m": { "id": 1, "code": "10m", "name": "10 meters from shore" },
      "occupancy_rate": { "id": 1, "code": "medium", "name": "Medium" }
    },
    
    "facilities": {
      "accommodation_around": [
        { "id": 1, "code": "hotel", "name": "Hotel" },
        { "id": 2, "code": "apartment", "name": "Apartment" }
      ],
      "culinary_offers": [
        { "id": 1, "code": "restaurant", "name": "Restaurant" },
        { "id": 2, "code": "beach_bar", "name": "Beach Bar" }
      ],
      "location_infrastructure": [
        { "id": 1, "code": "shower", "name": "Shower" },
        { "id": 2, "code": "toilet", "name": "Toilet" },
        { "id": 3, "code": "changing_room", "name": "Changing Room" }
      ]
    },
    
    "activities": {
      "sporting_activities": [
        { "id": 1, "code": "swimming", "name": "Swimming" },
        { "id": 2, "code": "snorkeling", "name": "Snorkeling" }
      ],
      "user_types": [
        { "id": 1, "code": "families", "name": "Families with Children" },
        { "id": 2, "code": "couples", "name": "Couples" }
      ]
    },
    
    "natural_conditions": {
      "natural_location_conditions": [
        { "id": 1, "code": "shade", "name": "Natural Shade" }
      ],
      "underwater_characteristics": [
        { "id": 1, "code": "sandy_bottom", "name": "Sandy Bottom" }
      ]
    },
    
    "media": {
      "gallery": [
        {
          "id": 456,
          "file_name": "beach-photo-1.jpg",
          "url": "https://cdn.example.com/img/beaches/paradise-beach-123/photo-1.jpg",
          "thumbnail_url": "https://cdn.example.com/img/beaches/paradise-beach-123/thumb_photo-1.jpg",
          "mime_type": "image/jpeg",
          "width": 1920,
          "height": 1080,
          "duration": null,
          "is_primary": true,
          "order": 1
        }
      ],
      "videos": [],
      "spheres_360": [],
      "parking_images": [],
      "spot_images": [],
      "infrastructure_images": [],
      "info_images": []
    },
    
    "embeds": {
      "video_url": "https://youtube.com/watch?v=...",
      "webcam": "<iframe>...</iframe>",
      "map_url": "https://maps.google.com/...",
      "weather": "<div>...</div>",
      "water_temperature": "<div>...</div>"
    },
    
    "swimming_pool_features": {
      "has_swimming_pool": false,
      "indoor_pools_count": 0,
      "outdoor_pools_count": 0,
      "indoor_area_m2": null,
      "outdoor_area_m2": null,
      "features": {
        "indoor_swimming_lanes": false,
        "outdoor_swimming_lanes": false,
        "indoor_children_pool": false,
        "outdoor_children_pool": false,
        "indoor_diving_board": false,
        "outdoor_diving_board": false,
        "thermal_pool": false,
        "indoor_climbing_wall": false,
        "outdoor_climbing_wall": false,
        "indoor_lazy_river": false,
        "outdoor_lazy_river": false,
        "indoor_wave_pool": false,
        "outdoor_wave_pool": false,
        "indoor_whirlpool": false,
        "outdoor_whirlpool": false,
        "indoor_surfing": false,
        "outdoor_surfing": false,
        "sauna": false,
        "wellness": false
      }
    },
    
    "meta": {
      "status": "Active",
      "views": 1234,
      "created_by": {
        "id": 1,
        "name": "John Doe"
      },
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-20T14:45:00Z"
    },

    "navigation": {
      "previous": {
        "id": 122,
        "name": "Nearby Beach 1",
        "slug": "nearby-beach-1",
        "latitude": "42.5900000",
        "longitude": "18.2200000",
        "distance_km": 1.25
      },
      "next": {
        "id": 124,
        "name": "Nearby Beach 2",
        "slug": "nearby-beach-2",
        "latitude": "42.6000000",
        "longitude": "18.2400000",
        "distance_km": 2.50
      }
    }
  }
}
```

## Usage Examples

### Frontend (Next.js)

```typescript
// Fetch beach details by slug (from URL)
const response = await fetch(
  `${process.env.NEXT_PUBLIC_API_ENDPOINT}/beach-details/paradise-beach`,
  {
    headers: {
      'Accept-Language': locale, // 'en', 'hr', 'de', etc.
    }
  }
);

const { data } = await response.json();

// All data is now available
console.log(data.basic_info.name);
console.log(data.location.country.name);
console.log(data.media.gallery); // Array of images
console.log(data.facilities.culinary_offers); // Array of restaurants, bars, etc.

// Navigation - spatially nearest beaches
if (data.navigation.previous) {
  console.log(`Previous beach: ${data.navigation.previous.name} (${data.navigation.previous.distance_km} km away)`);
  // Link to: /beaches/${data.navigation.previous.slug}
}

if (data.navigation.next) {
  console.log(`Next beach: ${data.navigation.next.name} (${data.navigation.next.distance_km} km away)`);
  // Link to: /beaches/${data.navigation.next.slug}
}
```

### Fetch by ID

```typescript
// Useful when you have beach ID from another API
const response = await fetch(
  `${process.env.NEXT_PUBLIC_API_ENDPOINT}/beach-details/123`,
  {
    headers: {
      'Accept-Language': 'en',
    }
  }
);
```

## Features

### ✅ Multi-language Support
- Accepts `Accept-Language` header
- Returns translated content based on locale
- Falls back to English if translation missing

### ✅ Slug Support (Any Language)
- Can use slug in any language: `/beach-details/paradise-beach` (EN)
- Or: `/beach-details/paradies-strand` (DE)
- Or: `/beach-details/plaža-raj` (HR)

### ✅ View Counter
- Automatically increments `beach_views` on each request
- Useful for analytics and "popular beaches" features

### ✅ Only Active Beaches
- Only returns beaches with `status_id = 1`
- Returns 404 for inactive/deleted beaches

### ✅ Efficient Loading
- Uses eager loading to prevent N+1 queries
- Single database query with all relationships
- Optimized for performance

### ✅ Spatial Navigation (PostGIS)
- Automatically includes `previous` and `next` beaches based on **spatial proximity**
- Uses PostGIS `ST_Distance` for accurate geographic calculations
- Returns the 2 nearest beaches (excluding current beach)
- **NOT based on ID** - based on actual geographic distance
- Perfect for "Explore Nearby Beaches" pagination
- Includes distance in kilometers for each navigation beach

## When to Add Specialized Endpoints

Consider adding specialized endpoints if:

1. **Mobile app needs lighter payloads** - Add `/beach-details/{id}/media` for just images
2. **Progressive loading** - Load basic info first, then media on scroll
3. **Performance testing shows issues** - Split into multiple endpoints

But **start with the comprehensive endpoint** - it's simpler and likely sufficient.

## Alternative Approach (Not Recommended)

If you really want multiple endpoints, you could create:

```
GET /api/v1/beach-details/{id}              # Basic info + location
GET /api/v1/beach-details/{id}/media        # All media collections
GET /api/v1/beach-details/{id}/facilities   # Facilities + infrastructure
GET /api/v1/beach-details/{id}/activities   # Activities + user types
```

**Why not recommended:**
- 4 HTTP requests instead of 1
- More complex frontend code
- Harder to keep data consistent
- More API endpoints to maintain
- Actually transfers MORE data (HTTP overhead × 4)

## Conclusion

**Recommendation: Use the single comprehensive endpoint** (`/api/v1/beach-details/{identifier}`)

It's simpler, faster, and easier to maintain. You can always add specialized endpoints later if needed, but you probably won't need them.

