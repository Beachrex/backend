# Staging Server Configuration

## Overview

The staging server `stage-api.beachrex.com` has been added to the Swagger API documentation configuration.

## Current Server Configuration

The Swagger documentation now includes **4 server environments**:

1. **Local Development**: `http://localhost:8000`
2. **Development Server**: `https://dev-api.beachrex.com`
3. **Staging Server**: `https://stage-api.beachrex.com` ✨ **NEW**
4. **Production Server**: `https://api.beachrex.com`

## Environment Variables for Staging

When deploying to staging environment, use these environment variables:

```bash
APP_URL=https://stage-api.beachrex.com
L5_SWAGGER_CONST_HOST=https://stage-api.beachrex.com
```

## Swagger UI Server Dropdown

Users will now see a dropdown in Swagger UI with these options:

- **Beachrex API Server** (environment-specific)
- **Development Server** (https://dev-api.beachrex.com)
- **Staging Server** (https://stage-api.beachrex.com) ✨ **NEW**
- **Production Server** (https://api.beachrex.com)

## Testing Workflow

### Development → Staging → Production

1. **Development Testing**:
   - Test on `dev-api.beachrex.com`
   - Validate new features and bug fixes

2. **Staging Testing** ✨ **NEW**:
   - Test on `stage-api.beachrex.com`
   - Final validation before production
   - User acceptance testing
   - Performance testing

3. **Production Deployment**:
   - Deploy to `api.beachrex.com`
   - Live environment

## Benefits of Staging Environment

✅ **Pre-production Testing**: Test in production-like environment
✅ **Risk Reduction**: Catch issues before production deployment
✅ **User Acceptance**: Stakeholders can test features before release
✅ **Performance Validation**: Test under production-like conditions
✅ **Integration Testing**: Test with external services safely

## Files Updated

1. **`app/Swagger/Swagger.php`**: Added staging server annotation
2. **`docs/swagger-configuration.md`**: Updated documentation
3. **`storage/api-docs/api-docs.json`**: Regenerated with staging server

## Next Steps

1. **Deploy to Staging**: Set up staging environment with `stage-api.beachrex.com`
2. **Configure Environment**: Use staging-specific environment variables
3. **Test APIs**: Use Swagger UI to test against staging server
4. **Validate Features**: Ensure all functionality works in staging

## Quick Test

Once staging is deployed, test the API documentation:

```bash
# Visit staging Swagger documentation
https://stage-api.beachrex.com/api/documentation

# Test spatial API endpoint
curl "https://stage-api.beachrex.com/api/v1/spatial/beaches/statistics"
```

The staging server is now fully integrated into the API documentation system! 🚀
