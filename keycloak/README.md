# Keycloak - MitID Mock

This directory contains the Keycloak realm configuration for mocking Danish MitID authentication.

## Overview

Keycloak provides an OpenID Connect (OIDC) identity provider that simulates MitID, Denmark's national digital identity system.

## Contents

- `realms/danish-gov-test.json` - Pre-configured realm with 10 Danish test personas

## Configuration Details

### Realm: danish-gov-test

- **Default Locale**: Danish (da)
- **Supported Locales**: Danish, English
- **Token Lifespan**: 3600 seconds (1 hour)
- **Signature Algorithm**: RS256

### Pre-configured Clients

#### aabenforms-backend
- **Client ID**: `aabenforms-backend`
- **Client Secret**: `aabenforms-backend-secret-change-in-production`
- **Type**: Confidential (server-side applications)
- **Allowed Redirect URIs**: `http://localhost:3000/*`, `http://aabenforms.ddev.site/*`
- **Protocol Mappers**: CPR, CVR, birthdate, organization_name

#### aabenforms-frontend
- **Client ID**: `aabenforms-frontend`
- **Type**: Public (browser-based applications)
- **Allowed Redirect URIs**: `http://localhost:3000/*`, `http://localhost:3001/*`

## Test Users

All users have password: `test1234`

| Username | CPR | Type | CVR |
|----------|-----|------|-----|
| freja.nielsen | 0101904521 | Personal | - |
| mikkel.jensen | 1502856234 | Personal | - |
| karen.christensen | 1205705432 | Business | 12345678 |
| protected.person | 0101804321 | Protected | - |

See [../docs/test-data.md](../docs/test-data.md) for complete list.

## Custom Claims

Danish-specific claims included in ID tokens:

- `cpr` - CPR number (personnummer)
- `cvr` - CVR number (business users only)
- `organization_name` - Company name (business users only)
- `birthdate` - ISO 8601 date
- `acr` - eIDAS assurance level

## Usage

### Access Admin Console

```bash
# URL
http://localhost:8080/admin

# Credentials
Username: admin
Password: admin
```

### OIDC Endpoints

```bash
# Discovery
http://localhost:8080/realms/danish-gov-test/.well-known/openid-configuration

# Authorization
http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth

# Token
http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token

# UserInfo
http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo

# Logout
http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout
```

## Customization

### Add New Test Users

Edit `realms/danish-gov-test.json` and add to the `users` array:

```json
{
  "username": "new.user",
  "firstName": "New",
  "lastName": "User",
  "email": "new.user@example.dk",
  "emailVerified": true,
  "enabled": true,
  "credentials": [
    {
      "type": "password",
      "value": "test1234",
      "temporary": false
    }
  ],
  "attributes": {
    "cpr": ["0101951234"],
    "birthdate": ["1995-01-01"],
    "given_name": ["New"],
    "family_name": ["User"],
    "assurance_level": ["http://eidas.europa.eu/LoA/substantial"]
  },
  "realmRoles": ["user"]
}
```

Restart Keycloak to import changes:

```bash
docker compose restart keycloak
```

### Add New Client

Add to the `clients` array in the realm file:

```json
{
  "clientId": "my-app",
  "name": "My Application",
  "enabled": true,
  "protocol": "openid-connect",
  "publicClient": false,
  "clientAuthenticatorType": "client-secret",
  "secret": "my-app-secret",
  "redirectUris": ["http://localhost:8000/*"],
  "webOrigins": ["http://localhost:8000"],
  "standardFlowEnabled": true
}
```

## Troubleshooting

### Realm Not Imported

Check container logs:

```bash
docker compose logs keycloak | grep import
```

Verify file is mounted:

```bash
docker compose exec keycloak ls /opt/keycloak/data/import/
```

### User Cannot Login

1. Verify user exists in admin console
2. Check user is enabled
3. Confirm password is set (not temporary)
4. Verify realm name in URL matches `danish-gov-test`

## References

- [Keycloak Documentation](https://www.keycloak.org/documentation)
- [OpenID Connect Core](https://openid.net/specs/openid-connect-core-1_0.html)
- [Keycloak Realm Import](https://www.keycloak.org/server/importExport)
