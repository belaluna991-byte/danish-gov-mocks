# Test Data Reference

This document lists all test personas available in the Danish Government Mock Services.

## Overview

The mock services include 10 realistic Danish test personas with valid CPR number formats, covering diverse scenarios:

- **Personal users**: Standard MitID Privat logins
- **Business users**: MitID Erhverv with CVR numbers
- **Protected persons**: Name/address protection scenarios
- **Age diversity**: Young adults to seniors

**Important**: All credentials are `test1234`. These are test accounts only - never use in production.

## Test Personas

### 1. Freja Nielsen
**Type**: Personal (Standard Citizen)

| Attribute | Value |
|-----------|-------|
| Username | `freja.nielsen` |
| Password | `test1234` |
| CPR | `0101904521` |
| Birthdate | 1990-01-01 |
| Email | freja.nielsen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Typical Copenhagen resident
- Common Danish name (Freja is popular in Denmark)
- Standard OIDC authentication flow testing
- General purpose testing persona

---

### 2. Mikkel Jensen
**Type**: Personal (Standard Citizen)

| Attribute | Value |
|-----------|-------|
| Username | `mikkel.jensen` |
| Password | `test1234` |
| CPR | `1502856234` |
| Birthdate | 1985-02-15 |
| Email | mikkel.jensen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Most common Danish surname (Jensen)
- Middle-aged user scenario
- Testing with typical Danish demographics

---

### 3. Sofie Hansen
**Type**: Personal (Young Parent)

| Attribute | Value |
|-----------|-------|
| Username | `sofie.hansen` |
| Password | `test1234` |
| CPR | `2506924015` |
| Birthdate | 1992-06-25 |
| Email | sofie.hansen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Young parent demographic
- Family-related services testing
- Childcare/education service workflows

---

### 4. Lars Andersen
**Type**: Personal (Middle-Aged)

| Attribute | Value |
|-----------|-------|
| Username | `lars.andersen` |
| Password | `test1234` |
| CPR | `0803755210` |
| Birthdate | 1975-03-08 |
| Email | lars.andersen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Aarhus resident (second largest city)
- Middle-aged demographic
- Employment/tax services testing

---

### 5. Emma Pedersen
**Type**: Personal (Young Adult)

| Attribute | Value |
|-----------|-------|
| Username | `emma.pedersen` |
| Password | `test1234` |
| CPR | `1010005206` |
| Birthdate | 2000-10-10 |
| Email | emma.pedersen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Recent graduate/young professional
- Education services (SU - student grants)
- First-time service user scenarios

---

### 6. Karen Christensen
**Type**: Business (MitID Erhverv)

| Attribute | Value |
|-----------|-------|
| Username | `karen.christensen` |
| Password | `test1234` |
| CPR | `1205705432` |
| CVR | `12345678` |
| Birthdate | 1970-05-12 |
| Email | karen@test-aps.dk |
| Organization | Test ApS |
| Assurance Level | High |
| Roles | user, business |

**Use Cases**:
- Business owner authentication
- MitID Erhverv testing
- CVR-based service access
- Company registration/reporting workflows
- Higher assurance level (eIDAS High)

**Notes**:
- OIDC token includes both CPR and CVR claims
- Use for testing business user journeys
- Organization name included in claims

---

### 7. Protected Person
**Type**: Personal (Name/Address Protection)

| Attribute | Value |
|-----------|-------|
| Username | `protected.person` |
| Password | `test1234` |
| CPR | `0101804321` |
| Birthdate | 1980-01-01 |
| First Name | [BESKYTTET] |
| Last Name | [BESKYTTET] |
| Email | protected@example.dk |
| Assurance Level | Substantial |
| Special Attribute | `protected: true` |

**Use Cases**:
- Name and address protection (navne- og adressebeskyttelse)
- Testing with concealed personal data
- Politicians, domestic violence victims, witnesses
- Systems must handle missing/redacted data

**Notes**:
- Name appears as "[BESKYTTET]" (Protected)
- Address information should be hidden
- CPR lookup should return limited data
- Critical for GDPR compliance testing

---

### 8. Morten Rasmussen
**Type**: Personal (Senior Citizen)

| Attribute | Value |
|-----------|-------|
| Username | `morten.rasmussen` |
| Password | `test1234` |
| CPR | `2209674523` |
| Birthdate | 1967-09-22 |
| Email | morten.rasmussen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Senior citizen demographic
- Retirement/pension services
- Healthcare system access
- Accessibility testing

---

### 9. Ida Mortensen
**Type**: Personal (Young Professional)

| Attribute | Value |
|-----------|-------|
| Username | `ida.mortensen` |
| Password | `test1234` |
| CPR | `0507985634` |
| Birthdate | 1998-07-05 |
| Email | ida.mortensen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Odense resident (third largest city)
- Young professional demographic
- Job seeking/career services
- Relocation/moving services

---

### 10. Peter Larsen
**Type**: Personal (Standard Citizen)

| Attribute | Value |
|-----------|-------|
| Username | `peter.larsen` |
| Password | `test1234` |
| CPR | `1811826547` |
| Birthdate | 1982-11-18 |
| Email | peter.larsen@example.dk |
| Assurance Level | Substantial |

**Use Cases**:
- Common male Danish name
- General purpose testing
- Standard citizen workflows

---

## OIDC Token Claims

When a user authenticates, the following claims are included in the ID token and access token:

### Standard Claims
- `sub`: Subject (username)
- `given_name`: First name
- `family_name`: Last name
- `name`: Full name
- `email`: Email address
- `email_verified`: Always true
- `birthdate`: ISO 8601 format (YYYY-MM-DD)

### Danish-Specific Claims
- `cpr`: CPR number (personnummer)
- `cvr`: CVR number (only for business users)
- `organization_name`: Company name (only for business users)
- `acr`: Assurance level (eIDAS substantial or high)
- `protected`: Boolean flag for protected persons

### Example Token Payload

```json
{
  "sub": "freja.nielsen",
  "given_name": "Freja",
  "family_name": "Nielsen",
  "name": "Freja Nielsen",
  "email": "freja.nielsen@example.dk",
  "email_verified": true,
  "birthdate": "1990-01-01",
  "cpr": "0101904521",
  "acr": "http://eidas.europa.eu/LoA/substantial",
  "iss": "http://localhost:8080/realms/danish-gov-test",
  "aud": "aabenforms-backend",
  "exp": 1706789012,
  "iat": 1706785412
}
```

## CPR Number Format

All CPR numbers in this test dataset follow the standard format:

**Format**: `DDMMYY-XXXX` (where dash is optional in some contexts)

- **DD**: Day of birth (01-31)
- **MM**: Month of birth (01-12)
- **YY**: Year of birth (last 2 digits)
- **XXXX**: Sequence number (determines century and gender)

**Note**: These are realistic formats but fictitious numbers. Do not use real CPR numbers in test data.

## Serviceplatformen Test Data

The WireMock service includes matching SOAP responses for these personas:

### SF1520 (CPR Lookup)

Configured CPR numbers with full person data:
- `0101904521` (Freja Nielsen)
- `1502856234` (Mikkel Jensen)
- `2506924015` (Sofie Hansen)
- `0803755210` (Lars Andersen)
- `1010005206` (Emma Pedersen)
- `1205705432` (Karen Christensen)
- `0101804321` (Protected Person)
- `2209674523` (Morten Rasmussen)
- `0507985634` (Ida Mortensen)
- `1811826547` (Peter Larsen)

### SF1530 (CVR Lookup)

Configured CVR numbers:
- `12345678` (Test ApS)
- `61126228` (Københavns Kommune - Copenhagen Municipality)
- `25313763` (Aarhus Kommune - Aarhus Municipality)
- `28291035` (Odense Kommune - Odense Municipality)
- `14773908` (Aalborg Kommune - Aalborg Municipality)

### SF1601 (Digital Post)

Three test scenarios:
1. **Success**: Message delivered successfully
2. **Recipient Not Found**: CPR not registered for digital post
3. **Message Too Large**: Attachment exceeds size limit

## Recommended Test Scenarios

### Basic Authentication Flow
**Persona**: Freja Nielsen
**Reason**: Standard user, no special cases

### Business User Flow
**Persona**: Karen Christensen
**Reason**: Tests CVR claims and business role

### Protected Data Handling
**Persona**: Protected Person
**Reason**: Tests redacted data handling

### Multi-Generation Testing
**Personas**: Emma Pedersen (young), Lars Andersen (middle-aged), Morten Rasmussen (senior)
**Reason**: Tests age-based service eligibility

### Geographic Distribution
- Copenhagen: Freja Nielsen
- Aarhus: Lars Andersen
- Odense: Ida Mortensen

## Security Notes

### NEVER Use in Production
- These personas are publicly documented
- Credentials are well-known (`test1234`)
- CPR numbers are fictional but realistic
- No encryption or real security measures

### Appropriate Use
- Local development environments
- CI/CD automated testing
- Integration testing
- Proof of concepts
- Developer training

### GDPR Compliance
Even with test data:
- Document why you're processing "CPR-like" data
- Don't mix test and production databases
- Clear test data retention policies
- Ensure test data doesn't leak to analytics

## Adding Custom Test Personas

To add your own test personas:

1. Edit `keycloak/realms/danish-gov-test.json`
2. Add user to `users` array
3. Generate valid CPR format (avoid real numbers)
4. Set password to `test1234` for consistency
5. Add appropriate attributes
6. Restart Keycloak container

Example:
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

## References

- [CPR Number Information (Danish)](https://cpr.dk/)
- [MitID User Guide](https://www.mitid.dk/)
- [eIDAS Assurance Levels](https://ec.europa.eu/digital-building-blocks/wikis/display/DIGITAL/eIDAS+Levels+of+Assurance)
