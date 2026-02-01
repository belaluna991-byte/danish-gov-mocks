# WireMock - Serviceplatformen Mock

This directory contains WireMock configuration for mocking Danish Serviceplatformen SOAP services.

## Overview

WireMock provides HTTP/SOAP mocks for Serviceplatformen services, enabling local testing of CPR lookups, CVR lookups, and Digital Post without actual government system access.

## Directory Structure

```
wiremock/
├── mappings/           # Request-response stub mappings
│   ├── sf1520/         # CPR person lookup stubs
│   ├── sf1530/         # CVR company lookup stubs
│   └── sf1601/         # Digital Post stubs
└── __files/            # Response templates (XML files)
    ├── sf1520/         # CPR XML responses
    ├── sf1530/         # CVR XML responses
    └── sf1601/         # Digital Post XML responses
```

## Services Mocked

### SF1520 - CPR Person Lookup

**Endpoint**: `http://localhost:8081/soap/sf1520`

**Available CPR numbers**:
- `0101904521` - Freja Nielsen
- `1502856234` - Mikkel Jensen
- `2506924015` - Sofie Hansen
- `0803755210` - Lars Andersen
- `1010005206` - Emma Pedersen
- `1205705432` - Karen Christensen (business user)
- `0101804321` - Protected Person
- `2209674523` - Morten Rasmussen
- `0507985634` - Ida Mortensen
- `1811826547` - Peter Larsen

**Example Request**:
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

**Response**: Returns OIO XML with person details (name, address, birthdate)

### SF1530 - CVR Company Lookup

**Endpoint**: `http://localhost:8081/soap/sf1530`

**Available CVR numbers**:
- `12345678` - Test ApS
- `61126228` - Københavns Kommune
- `25313763` - Aarhus Kommune
- `28291035` - Odense Kommune
- `14773908` - Aalborg Kommune

**Example Request**:
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

**Response**: Returns CVR data (company name, address, industry codes)

### SF1601 - Digital Post

**Endpoint**: `http://localhost:8081/soap/sf1601`

**Test Scenarios**:
1. **Success** - Message delivered successfully
2. **Recipient Not Found** - CPR not registered for digital post
3. **Message Too Large** - Attachment exceeds size limit

## Usage

### Send SOAP Request

```bash
curl -X POST http://localhost:8081/soap/sf1520 \
  -H "Content-Type: text/xml; charset=utf-8" \
  -H "SOAPAction: \"\"" \
  -d @request.xml
```

### Access Admin UI

```bash
# Open in browser
http://localhost:8081/__admin

# View all mappings
curl http://localhost:8081/__admin/mappings

# View request log
curl http://localhost:8081/__admin/requests
```

### Reset Request Log

```bash
curl -X DELETE http://localhost:8081/__admin/requests
```

## Adding New Stubs

### 1. Create Mapping File

Create `mappings/sf1520/sf1520-cpr-1234567890.json`:

```json
{
  "request": {
    "method": "POST",
    "urlPathPattern": "/soap/sf1520",
    "bodyPatterns": [
      {
        "matchesXPath": {
          "expression": "//per:CPR[text()='1234567890']",
          "namespaces": {
            "per": "http://serviceplatformen.dk/xml/schemas/PersonLookup/1/"
          }
        }
      }
    ]
  },
  "response": {
    "status": 200,
    "bodyFileName": "sf1520/person-1234567890.xml",
    "headers": {
      "Content-Type": "text/xml; charset=utf-8"
    }
  }
}
```

### 2. Create Response File

Create `__files/sf1520/person-1234567890.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
  <soapenv:Body>
    <per:PersonLookupResponse xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
      <per:CPR>1234567890</per:CPR>
      <per:FirstName>Test</per:FirstName>
      <per:LastName>Person</per:LastName>
      <per:Address>Test Street 123</per:Address>
      <per:PostalCode>1234</per:PostalCode>
      <per:City>Test City</per:City>
      <per:Birthdate>1990-01-01</per:Birthdate>
    </per:PersonLookupResponse>
  </soapenv:Body>
</soapenv:Envelope>
```

### 3. Reload WireMock

```bash
# Restart container
docker compose restart wiremock

# Or use admin API
curl -X POST http://localhost:8081/__admin/mappings/reset
```

## Response Templating

WireMock supports dynamic responses using Handlebars templates. Use `{{request.body}}` to reference request data.

Example with templating:

```json
{
  "response": {
    "status": 200,
    "body": "{{request.body}}",
    "transformers": ["response-template"]
  }
}
```

## Debugging

### View Matched Requests

```bash
curl http://localhost:8081/__admin/requests | jq '.'
```

### Check Stub Matching

```bash
# List all stubs
curl http://localhost:8081/__admin/mappings | jq '.mappings[].name'

# Find unmatched requests
curl http://localhost:8081/__admin/requests/unmatched
```

### Verify File Loading

```bash
# Check container logs
docker compose logs wiremock

# List loaded files
docker compose exec wiremock ls /home/wiremock/mappings
docker compose exec wiremock ls /home/wiremock/__files
```

## Troubleshooting

### Stub Not Matching

1. Verify XPath expression matches request XML
2. Check namespace declarations
3. Test XPath with online tool
4. View request log to see actual request body

### Response Not Found

1. Verify `bodyFileName` path is correct
2. Check file exists in `__files/` directory
3. Ensure file permissions are readable

### Service Returns 404

1. Verify endpoint URL path matches mapping
2. Check request method (POST vs GET)
3. Review WireMock logs for matching errors

## References

- [WireMock Documentation](https://wiremock.org/docs/)
- [Request Matching](https://wiremock.org/docs/request-matching/)
- [Response Templating](https://wiremock.org/docs/response-templating/)
- [Serviceplatformen Documentation](https://digitaliseringskataloget.dk/)
