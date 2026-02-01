# Terminology Glossary

This glossary explains Danish government service terms and abbreviations used throughout this project.

## Danish Government Systems

### CPR (Personnummer)
**Full Name**: Det Centrale Personregister (Civil Registration System)

**Description**: The Danish civil registration number, similar to a social security number. Format: DDMMYY-XXXX (birthdate + 4 digits).

**Usage**: Used to uniquely identify Danish citizens and residents. Required for most government services.

**Example**: `080495-1236` (person born April 8, 1995)

**Privacy**: CPR numbers are considered sensitive personal data under GDPR Article 9.

---

### CVR (Virksomhedsnummer)
**Full Name**: Det Centrale Virksomhedsregister (Central Business Register)

**Description**: The Danish business registration number. Format: 8 digits.

**Usage**: Identifies companies, organizations, and self-employed individuals.

**Example**: `61126228`

**Related**: P-numbers identify production units (branches) within a CVR entity.

---

### MitID
**Full Name**: MitID (My ID)

**Description**: Denmark's national digital identity solution, replacing NemID in 2021.

**Types**:
- **MitID Privat**: Personal login for citizens
- **MitID Erhverv**: Business login for company representatives

**Standards**: Based on OpenID Connect (OIDC) and OAuth 2.0.

**Usage**: Required for accessing public sector services, banking, healthcare, etc.

---

### Serviceplatformen
**Full Name**: Serviceplatformen

**Description**: The Danish government's central integration platform, operated by KOMBIT (municipal IT cooperation).

**Purpose**: Provides standardized APIs for accessing government registers and services.

**Access**: Requires digital certificates and service agreements. Production access only for authorized organizations.

**Services**: SF1500 series (SF1520, SF1530, SF1601, etc.)

---

### DAWA
**Full Name**: Danmarks Adressers Web API (Denmark's Address Web API)

**Description**: Official Danish address database with REST API.

**Maintained By**: Danish Agency for Data Supply and Infrastructure (SDFI)

**Usage**: Address autocomplete, validation, geocoding.

**Access**: Public API, no authentication required.

**URL**: https://dawa.aws.dk/

---

### NSIS
**Full Name**: Fællesoffentlig Digital Infrastruktur (Common Public Sector Digital Infrastructure)

**Description**: The framework of standards, architectures, and components for Danish government IT systems.

**Scope**: Defines interoperability standards, security requirements, and technical specifications.

---

## Serviceplatformen Services

### SF1520
**Name**: CPR Person Lookup

**Purpose**: Query person data from the CPR register.

**Returns**: Name, address, birthdate, citizenship, family relations.

**Protocol**: SOAP (OIO XML)

**Access Level**: Requires legal basis under GDPR.

---

### SF1530
**Name**: CVR Company Lookup

**Purpose**: Query company data from the CVR register.

**Returns**: Company name, address, industry codes, ownership, P-numbers.

**Protocol**: SOAP (OIO XML)

**Access Level**: Public data, but requires service agreement.

---

### SF1601
**Name**: Digital Post

**Purpose**: Send official letters to citizens' digital mailbox.

**Delivery**: Messages delivered to e-Boks or other approved digital post providers.

**Fallback**: Physical mail for citizens exempt from digital post.

**Protocol**: SOAP (OIO XML)

---

## Technical Terms

### OIDC
**Full Name**: OpenID Connect

**Description**: Authentication protocol built on top of OAuth 2.0.

**Usage**: Standard protocol for MitID and other modern authentication systems.

**Key Concepts**: ID token, access token, authorization code flow.

---

### OAuth 2.0
**Full Name**: OAuth 2.0

**Description**: Authorization framework for delegated access.

**Usage**: Allows applications to access resources on behalf of a user.

**Flows**: Authorization code, implicit, client credentials, etc.

---

### SOAP
**Full Name**: Simple Object Access Protocol

**Description**: XML-based protocol for web services.

**Usage**: Legacy protocol used by Serviceplatformen services.

**Transport**: Typically over HTTP/HTTPS with POST requests.

---

### OIO
**Full Name**: Offentlig Information Online (Public Information Online)

**Description**: Danish government's XML schema standards for data exchange.

**Usage**: Defines XML structure for CPR, CVR, and other government data.

**Examples**: OIO Person, OIO Virksomhed (company).

---

### WS-Security
**Full Name**: Web Services Security

**Description**: SOAP security extension for authentication and message integrity.

**Usage**: Used in production Serviceplatformen, but simplified in mocks.

**Features**: Digital signatures, encryption, timestamps.

---

## Organizations

### KOMBIT
**Description**: Municipal IT cooperation owned by Danish municipalities.

**Role**: Operates Serviceplatformen and other shared IT infrastructure.

**Members**: All Danish municipalities and regions.

---

### Digitaliseringsstyrelsen
**English**: Danish Agency for Digital Government

**Role**: Coordinates digital transformation of the public sector.

**Responsibilities**: MitID, NemLog-in, digital post, standards.

---

### SDFI
**Full Name**: Styrelsen for Dataforsyning og Infrastruktur (Agency for Data Supply and Infrastructure)

**Role**: Maintains DAWA and other geographic/address data.

**Parent**: Ministry of Climate, Energy and Utilities.

---

## Authentication Concepts

### Realm (Keycloak)
**Description**: Isolated set of users, credentials, roles, and clients in Keycloak.

**Usage**: This project uses the `danish-gov-test` realm.

**Analogy**: Like a tenant or workspace in multi-tenant systems.

---

### Client (OIDC)
**Description**: Application that uses OIDC for authentication.

**Examples**: Web app, mobile app, backend service.

**Credentials**: Client ID and client secret (for confidential clients).

---

### Claim (OIDC)
**Description**: Piece of information about the user included in tokens.

**Standard Claims**: `sub` (subject), `name`, `email`, `birthdate`.

**Custom Claims**: `cpr`, `cvr`, `organization_name` (Danish-specific).

---

### Stub (WireMock)
**Description**: Pre-configured mock response for a specific request pattern.

**Usage**: Allows WireMock to simulate API behavior without real backend.

**Components**: Request matcher + response definition.

---

## Test Data Terms

### Test Persona
**Description**: Fictional person with realistic Danish data for testing.

**Attributes**: Name, CPR, address, birthdate, credentials.

**Purpose**: Enables realistic integration testing without real citizen data.

---

### Protected Person (Beskyttet Person)
**Description**: Individual with hidden address in CPR due to threats or safety concerns.

**Testing**: Include test personas with address protection flags.

**Example**: Politicians, domestic violence victims, witnesses.

---

## Related Acronyms

- **NemID**: Former Danish digital identity (replaced by MitID in 2021)
- **NemLog-in**: Unified login solution for public sector employees
- **UNI-Login**: Education sector login system
- **e-Boks**: Digital mailbox provider
- **GDPR**: General Data Protection Regulation (EU privacy law)
- **P-number**: Production unit number (company branch identifier)

## Further Reading

- [Digital Government Strategy](https://en.digst.dk/)
- [Serviceplatformen Documentation](https://digitaliseringskataloget.dk/)
- [DAWA Documentation](https://dawadocs.dataforsyningen.dk/)
- [MitID Information](https://www.mitid.dk/en-gb/)
