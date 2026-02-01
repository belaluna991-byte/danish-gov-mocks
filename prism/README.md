# Prism - DAWA Address API Mock

This directory will contain the OpenAPI specification for mocking the Danish DAWA (Danmarks Adressers Web API).

## Status

**Coming in v1.1** - OpenAPI specification is currently in development.

## Overview

DAWA is the official Danish address database providing REST API endpoints for:
- Address autocomplete
- Address validation
- Geocoding (address to coordinates)
- Reverse geocoding (coordinates to address)

## Planned Features

When complete, this mock will provide:

### Address Autocomplete
```bash
GET http://localhost:8082/adresser/autocomplete?q=Rådhus
```

### Address Lookup
```bash
GET http://localhost:8082/adresser/{id}
```

### Address Search
```bash
GET http://localhost:8082/adresser?vejnavn=Rådhuspladsen&husnr=1
```

### Geocoding
```bash
GET http://localhost:8082/adgangsadresser?x=12.5681&y=55.6761
```

## Using Without Prism

Until the OpenAPI spec is ready, you can:

1. **Use real DAWA API** - It's public and requires no authentication:
   ```bash
   curl https://dawa.aws.dk/adresser/autocomplete?q=Rådhus
   ```

2. **Stub with WireMock** - Add DAWA endpoints to WireMock:
   ```json
   {
     "request": {
       "method": "GET",
       "urlPathPattern": "/adresser/autocomplete"
     },
     "response": {
       "status": 200,
       "jsonBody": [
         {
           "tekst": "Rådhuspladsen 1, 1550 København V",
           "adresse": {
             "id": "0a3f5095-45ec-32b8-e044-0003ba298018"
           }
         }
       ]
     }
   }
   ```

## Starting Prism (When Ready)

```bash
# Start with full profile
docker compose --profile full up -d

# Or start just Prism
docker compose up -d prism
```

Access at: `http://localhost:8082`

## Contributing

Help us build the DAWA OpenAPI specification:

1. Reference the official DAWA documentation: https://dawadocs.dataforsyningen.dk/
2. Create `dawa-openapi.yaml` based on actual API responses
3. Test with Prism: `prism mock dawa-openapi.yaml`
4. Submit pull request

## Resources

- [DAWA Documentation](https://dawadocs.dataforsyningen.dk/)
- [DAWA REST API](https://dawa.aws.dk/)
- [Prism Documentation](https://docs.stoplight.io/docs/prism)
- [OpenAPI 3.0 Specification](https://swagger.io/specification/)
