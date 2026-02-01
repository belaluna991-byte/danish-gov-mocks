# Generic HTTP Integration Guide

This guide shows how to integrate with the Danish Government Mock Services using raw HTTP requests. Use this as a reference for any programming language or framework.

## MitID Authentication (OpenID Connect Flow)

### 1. Discover OIDC Configuration

```bash
curl http://localhost:8080/realms/danish-gov-test/.well-known/openid-configuration
```

**Response**:
```json
{
  "issuer": "http://localhost:8080/realms/danish-gov-test",
  "authorization_endpoint": "http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth",
  "token_endpoint": "http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token",
  "userinfo_endpoint": "http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo",
  "end_session_endpoint": "http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout",
  "jwks_uri": "http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/certs",
  "grant_types_supported": ["authorization_code", "refresh_token"],
  "response_types_supported": ["code"],
  "subject_types_supported": ["public"],
  "id_token_signing_alg_values_supported": ["RS256"]
}
```

### 2. Authorization Request

Redirect user to authorization endpoint:

```
http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth?
  client_id=aabenforms-backend&
  redirect_uri=http://localhost:3000/callback&
  response_type=code&
  scope=openid%20profile%20email&
  state=random_state_string&
  nonce=random_nonce_string
```

**Parameters**:
- `client_id`: Your client ID (aabenforms-backend)
- `redirect_uri`: Where to send user after authentication
- `response_type`: Must be "code" for authorization code flow
- `scope`: Requested scopes (openid is required)
- `state`: Random string to prevent CSRF
- `nonce`: Random string to prevent replay attacks

**User Experience**:
1. User sees Keycloak login page
2. User enters username: `freja.nielsen` and password: `test1234`
3. User is redirected back to your `redirect_uri` with authorization code

### 3. Handle Callback

After successful login, Keycloak redirects to:

```
http://localhost:3000/callback?code=AUTH_CODE&state=random_state_string
```

**Extract the code** from query parameters and verify the state matches.

### 4. Exchange Code for Tokens

```bash
curl -X POST http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code" \
  -d "client_id=aabenforms-backend" \
  -d "client_secret=aabenforms-backend-secret-change-in-production" \
  -d "code=AUTH_CODE" \
  -d "redirect_uri=http://localhost:3000/callback"
```

**Response**:
```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 3600,
  "refresh_expires_in": 1800,
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "id_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
  "not-before-policy": 0,
  "session_state": "session-id",
  "scope": "openid profile email"
}
```

### 5. Decode ID Token

The `id_token` is a JWT containing user information. Decode it to access Danish claims:

**Example ID Token Payload**:
```json
{
  "sub": "freja.nielsen",
  "email_verified": true,
  "name": "Freja Nielsen",
  "preferred_username": "freja.nielsen",
  "given_name": "Freja",
  "family_name": "Nielsen",
  "email": "freja.nielsen@example.dk",
  "cpr": "0101904521",
  "birthdate": "1990-01-01",
  "acr": "http://eidas.europa.eu/LoA/substantial",
  "iss": "http://localhost:8080/realms/danish-gov-test",
  "aud": "aabenforms-backend",
  "exp": 1706789012,
  "iat": 1706785412
}
```

**Danish-Specific Claims**:
- `cpr`: CPR number (personnummer)
- `cvr`: CVR number (for business users only)
- `organization_name`: Company name (for business users only)
- `birthdate`: ISO 8601 date
- `acr`: Assurance level (eIDAS)

### 6. Get User Info

Alternatively, call the UserInfo endpoint with the access token:

```bash
curl http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

### 7. Refresh Access Token

When access token expires, use refresh token:

```bash
curl -X POST http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token" \
  -d "client_id=aabenforms-backend" \
  -d "client_secret=aabenforms-backend-secret-change-in-production" \
  -d "refresh_token=REFRESH_TOKEN"
```

### 8. Logout

Redirect user to end session endpoint:

```
http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout?
  redirect_uri=http://localhost:3000
```

---

## Serviceplatformen CPR Lookup (SF1520)

### 1. Build SOAP Request

Create XML SOAP envelope:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>0101904521</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>
```

### 2. Send SOAP Request

```bash
curl -X POST http://localhost:8081/soap/sf1520 \
  -H "Content-Type: text/xml; charset=utf-8" \
  -H "SOAPAction: \"\"" \
  -d @cpr-request.xml
```

Or inline:

```bash
curl -X POST http://localhost:8081/soap/sf1520 \
  -H "Content-Type: text/xml; charset=utf-8" \
  -H "SOAPAction: \"\"" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>0101904521</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>'
```

### 3. Parse SOAP Response

**Example Response**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
  <soapenv:Body>
    <per:PersonLookupResponse xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
      <per:CPR>0101904521</per:CPR>
      <per:FirstName>Freja</per:FirstName>
      <per:LastName>Nielsen</per:LastName>
      <per:Address>Hovedgaden 1</per:Address>
      <per:PostalCode>1000</per:PostalCode>
      <per:City>København K</per:City>
      <per:Birthdate>1990-01-01</per:Birthdate>
    </per:PersonLookupResponse>
  </soapenv:Body>
</soapenv:Envelope>
```

**Extract values** using XML parser in your language.

---

## Serviceplatformen CVR Lookup (SF1530)

### 1. SOAP Request

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cvr="http://serviceplatformen.dk/xml/schemas/CvrLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <cvr:CvrLookupRequest>
      <cvr:CVR>12345678</cvr:CVR>
    </cvr:CvrLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>
```

### 2. Send Request

```bash
curl -X POST http://localhost:8081/soap/sf1530 \
  -H "Content-Type: text/xml; charset=utf-8" \
  -H "SOAPAction: \"\"" \
  -d @cvr-request.xml
```

### 3. Response

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
  <soapenv:Body>
    <cvr:CvrLookupResponse xmlns:cvr="http://serviceplatformen.dk/xml/schemas/CvrLookup/1/">
      <cvr:CVR>12345678</cvr:CVR>
      <cvr:CompanyName>Test ApS</cvr:CompanyName>
      <cvr:Address>Testvej 42</cvr:Address>
      <cvr:PostalCode>2000</cvr:PostalCode>
      <cvr:City>Frederiksberg</cvr:City>
    </cvr:CvrLookupResponse>
  </soapenv:Body>
</soapenv:Envelope>
```

---

## Serviceplatformen Digital Post (SF1601)

### 1. Send Message Request

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:dp="http://serviceplatformen.dk/xml/schemas/DigitalPost/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <dp:SendMessageRequest>
      <dp:RecipientCPR>0101904521</dp:RecipientCPR>
      <dp:Subject>Test Message</dp:Subject>
      <dp:MessageBody>This is a test message</dp:MessageBody>
    </dp:SendMessageRequest>
  </soapenv:Body>
</soapenv:Envelope>
```

### 2. Send Request

```bash
curl -X POST http://localhost:8081/soap/sf1601 \
  -H "Content-Type: text/xml; charset=utf-8" \
  -H "SOAPAction: \"\"" \
  -d @digital-post-request.xml
```

### 3. Possible Responses

**Success**:
```xml
<dp:SendMessageResponse>
  <dp:Status>SUCCESS</dp:Status>
  <dp:MessageId>msg-12345</dp:MessageId>
</dp:SendMessageResponse>
```

**Recipient Not Found**:
```xml
<dp:SendMessageResponse>
  <dp:Status>ERROR</dp:Status>
  <dp:ErrorCode>RECIPIENT_NOT_FOUND</dp:ErrorCode>
</dp:SendMessageResponse>
```

**Message Too Large**:
```xml
<dp:SendMessageResponse>
  <dp:Status>ERROR</dp:Status>
  <dp:ErrorCode>MESSAGE_TOO_LARGE</dp:ErrorCode>
</dp:SendMessageResponse>
```

---

## WireMock Admin API

### View All Stub Mappings

```bash
curl http://localhost:8081/__admin/mappings
```

### View Requests Log

```bash
curl http://localhost:8081/__admin/requests
```

### Reset Request Log

```bash
curl -X DELETE http://localhost:8081/__admin/requests
```

### Add New Stub Dynamically

```bash
curl -X POST http://localhost:8081/__admin/mappings \
  -H "Content-Type: application/json" \
  -d '{
    "request": {
      "method": "POST",
      "urlPathPattern": "/soap/sf1520",
      "bodyPatterns": [
        {
          "matchesXPath": "//cpr[text()=\"1234567890\"]"
        }
      ]
    },
    "response": {
      "status": 200,
      "body": "<response>Custom response</response>",
      "headers": {
        "Content-Type": "text/xml"
      }
    }
  }'
```

---

## Testing with Different Personas

### Personal User (Standard)

```bash
# Login
Username: freja.nielsen
Password: test1234

# Expected claims
cpr: 0101904521
cvr: (not present)
```

### Business User

```bash
# Login
Username: karen.christensen
Password: test1234

# Expected claims
cpr: 1205705432
cvr: 12345678
organization_name: Test ApS
```

### Protected Person

```bash
# Login
Username: protected.person
Password: test1234

# Expected behavior
name: [BESKYTTET]
cpr: 0101804321
protected: true
```

---

## Language-Specific Examples

### Python

```python
import requests

# OIDC Token Request
response = requests.post(
    'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token',
    data={
        'grant_type': 'authorization_code',
        'client_id': 'aabenforms-backend',
        'client_secret': 'aabenforms-backend-secret-change-in-production',
        'code': auth_code,
        'redirect_uri': 'http://localhost:3000/callback'
    }
)

tokens = response.json()
id_token = tokens['id_token']

# CPR Lookup
soap_request = '''<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>0101904521</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>'''

response = requests.post(
    'http://localhost:8081/soap/sf1520',
    data=soap_request,
    headers={'Content-Type': 'text/xml; charset=utf-8'}
)

print(response.text)
```

### Go

```go
package main

import (
    "bytes"
    "net/http"
    "io/ioutil"
)

func lookupCPR(cpr string) (string, error) {
    soapRequest := `<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>` + cpr + `</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>`

    resp, err := http.Post(
        "http://localhost:8081/soap/sf1520",
        "text/xml; charset=utf-8",
        bytes.NewBufferString(soapRequest),
    )
    if err != nil {
        return "", err
    }
    defer resp.Body.Close()

    body, err := ioutil.ReadAll(resp.Body)
    return string(body), err
}
```

### Ruby

```ruby
require 'net/http'
require 'uri'

# SOAP Request
uri = URI.parse('http://localhost:8081/soap/sf1520')
request = Net::HTTP::Post.new(uri)
request.content_type = 'text/xml; charset=utf-8'
request.body = <<-XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>0101904521</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML

response = Net::HTTP.start(uri.hostname, uri.port) do |http|
  http.request(request)
end

puts response.body
```

---

## Common Headers

### OIDC Requests
```
Content-Type: application/x-www-form-urlencoded
```

### SOAP Requests
```
Content-Type: text/xml; charset=utf-8
SOAPAction: ""
```

### Authenticated API Requests
```
Authorization: Bearer ACCESS_TOKEN
```

---

## Error Handling

### OIDC Errors

**Invalid Client**:
```json
{
  "error": "invalid_client",
  "error_description": "Invalid client credentials"
}
```

**Invalid Grant**:
```json
{
  "error": "invalid_grant",
  "error_description": "Code not valid"
}
```

### SOAP Faults

```xml
<soapenv:Fault>
  <faultcode>soapenv:Server</faultcode>
  <faultstring>Person not found</faultstring>
</soapenv:Fault>
```

---

## Resources

- [OpenID Connect Specification](https://openid.net/specs/openid-connect-core-1_0.html)
- [OAuth 2.0 RFC 6749](https://tools.ietf.org/html/rfc6749)
- [SOAP 1.1 Specification](https://www.w3.org/TR/2000/NOTE-SOAP-20000508/)
- [JWT Decoder](https://jwt.io/)
- [XML Pretty Print](https://www.freeformatter.com/xml-formatter.html)
