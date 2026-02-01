# Node.js Integration Guide

This guide shows how to integrate the Danish Government Mock Services with Node.js applications.

## Prerequisites

- Node.js 18+ or 20+
- npm or yarn

## MitID Authentication (Keycloak OIDC)

### 1. Install Dependencies

```bash
npm install express passport passport-openidconnect express-session
# or
yarn add express passport passport-openidconnect express-session
```

### 2. Configure Passport

Create `config/passport.js`:

```javascript
const passport = require('passport');
const OpenIDConnectStrategy = require('passport-openidconnect').Strategy;

passport.use('mitid', new OpenIDConnectStrategy({
  issuer: 'http://localhost:8080/realms/danish-gov-test',
  authorizationURL: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth',
  tokenURL: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token',
  userInfoURL: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo',
  clientID: 'aabenforms-backend',
  clientSecret: 'aabenforms-backend-secret-change-in-production',
  callbackURL: 'http://localhost:3000/auth/callback',
  scope: ['openid', 'profile', 'email']
}, (issuer, profile, done) => {
  // Extract Danish-specific claims
  const user = {
    id: profile.id,
    username: profile.username,
    email: profile.emails[0].value,
    name: profile.displayName,
    cpr: profile._json.cpr,
    cvr: profile._json.cvr,
    birthdate: profile._json.birthdate,
    assuranceLevel: profile._json.acr
  };

  return done(null, user);
}));

passport.serializeUser((user, done) => {
  done(null, user);
});

passport.deserializeUser((user, done) => {
  done(null, user);
});

module.exports = passport;
```

### 3. Setup Express App

Create `app.js`:

```javascript
const express = require('express');
const session = require('express-session');
const passport = require('./config/passport');

const app = express();

// Session configuration
app.use(session({
  secret: 'your-secret-key-change-in-production',
  resave: false,
  saveUninitialized: false,
  cookie: { secure: false } // Set to true in production with HTTPS
}));

// Initialize Passport
app.use(passport.initialize());
app.use(passport.session());

// Routes
app.get('/auth/mitid', passport.authenticate('mitid'));

app.get('/auth/callback',
  passport.authenticate('mitid', { failureRedirect: '/login' }),
  (req, res) => {
    res.redirect('/dashboard');
  }
);

app.get('/logout', (req, res) => {
  const logoutUrl = 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout' +
    '?redirect_uri=' + encodeURIComponent('http://localhost:3000');

  req.logout(() => {
    res.redirect(logoutUrl);
  });
});

// Protected route
app.get('/dashboard', ensureAuthenticated, (req, res) => {
  res.json({
    message: 'Welcome to dashboard',
    user: req.user
  });
});

// Middleware to ensure authentication
function ensureAuthenticated(req, res, next) {
  if (req.isAuthenticated()) {
    return next();
  }
  res.redirect('/auth/mitid');
}

app.listen(3000, () => {
  console.log('Server running on http://localhost:3000');
});
```

---

## Serviceplatformen Integration (SOAP)

### 1. Install SOAP Client

```bash
npm install soap axios
```

### 2. Create CPR Lookup Service

Create `services/cprLookup.js`:

```javascript
const axios = require('axios');
const { DOMParser } = require('xmldom');
const xpath = require('xpath');

class CprLookupService {
  constructor() {
    this.endpoint = process.env.SERVICEPLATFORMEN_CPR_URL ||
      'http://localhost:8081/soap/sf1520';
  }

  async lookup(cpr) {
    const soapRequest = this.buildSoapRequest(cpr);

    try {
      const response = await axios.post(this.endpoint, soapRequest, {
        headers: {
          'Content-Type': 'text/xml; charset=utf-8',
          'SOAPAction': ''
        }
      });

      return this.parseResponse(response.data);
    } catch (error) {
      console.error('CPR lookup failed:', error.message);
      return null;
    }
  }

  buildSoapRequest(cpr) {
    return `<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>${cpr}</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>`;
  }

  parseResponse(xmlString) {
    const doc = new DOMParser().parseFromString(xmlString);
    const select = xpath.useNamespaces({
      'per': 'http://serviceplatformen.dk/xml/schemas/PersonLookup/1/'
    });

    return {
      cpr: select('string(//per:CPR)', doc),
      firstName: select('string(//per:FirstName)', doc),
      lastName: select('string(//per:LastName)', doc),
      address: select('string(//per:Address)', doc),
      postalCode: select('string(//per:PostalCode)', doc),
      city: select('string(//per:City)', doc)
    };
  }
}

module.exports = CprLookupService;
```

### 3. Create CVR Lookup Service

Create `services/cvrLookup.js`:

```javascript
const axios = require('axios');
const { DOMParser } = require('xmldom');
const xpath = require('xpath');

class CvrLookupService {
  constructor() {
    this.endpoint = process.env.SERVICEPLATFORMEN_CVR_URL ||
      'http://localhost:8081/soap/sf1530';
  }

  async lookup(cvr) {
    const soapRequest = `<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cvr="http://serviceplatformen.dk/xml/schemas/CvrLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <cvr:CvrLookupRequest>
      <cvr:CVR>${cvr}</cvr:CVR>
    </cvr:CvrLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>`;

    try {
      const response = await axios.post(this.endpoint, soapRequest, {
        headers: {
          'Content-Type': 'text/xml; charset=utf-8',
          'SOAPAction': ''
        }
      });

      return this.parseResponse(response.data);
    } catch (error) {
      console.error('CVR lookup failed:', error.message);
      return null;
    }
  }

  parseResponse(xmlString) {
    const doc = new DOMParser().parseFromString(xmlString);
    const select = xpath.useNamespaces({
      'cvr': 'http://serviceplatformen.dk/xml/schemas/CvrLookup/1/'
    });

    return {
      cvr: select('string(//cvr:CVR)', doc),
      name: select('string(//cvr:CompanyName)', doc),
      address: select('string(//cvr:Address)', doc),
      city: select('string(//cvr:City)', doc)
    };
  }
}

module.exports = CvrLookupService;
```

### 4. Use Services in Routes

```javascript
const CprLookupService = require('./services/cprLookup');
const CvrLookupService = require('./services/cvrLookup');

const cprService = new CprLookupService();
const cvrService = new CvrLookupService();

// CPR lookup endpoint
app.get('/api/person', ensureAuthenticated, async (req, res) => {
  const cpr = req.user.cpr;

  if (!cpr) {
    return res.status(400).json({ error: 'No CPR number available' });
  }

  const personData = await cprService.lookup(cpr);

  if (!personData) {
    return res.status(404).json({ error: 'Person not found' });
  }

  res.json(personData);
});

// CVR lookup endpoint
app.get('/api/company/:cvr', ensureAuthenticated, async (req, res) => {
  const cvr = req.params.cvr;

  const companyData = await cvrService.lookup(cvr);

  if (!companyData) {
    return res.status(404).json({ error: 'Company not found' });
  }

  res.json(companyData);
});
```

---

## Complete Example with TypeScript

Create a TypeScript Express application:

### Install Dependencies

```bash
npm install --save-dev typescript @types/node @types/express @types/passport
npm install express passport passport-openidconnect express-session axios
```

### tsconfig.json

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "module": "commonjs",
    "outDir": "./dist",
    "rootDir": "./src",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true
  }
}
```

### src/types/user.ts

```typescript
export interface DanishUser {
  id: string;
  username: string;
  email: string;
  name: string;
  cpr?: string;
  cvr?: string;
  birthdate?: string;
  assuranceLevel?: string;
}

export interface PersonData {
  cpr: string;
  firstName: string;
  lastName: string;
  address: string;
  postalCode: string;
  city: string;
}

export interface CompanyData {
  cvr: string;
  name: string;
  address: string;
  city: string;
}
```

### src/services/CprLookupService.ts

```typescript
import axios from 'axios';

export class CprLookupService {
  private endpoint: string;

  constructor() {
    this.endpoint = process.env.SERVICEPLATFORMEN_CPR_URL ||
      'http://localhost:8081/soap/sf1520';
  }

  async lookup(cpr: string): Promise<PersonData | null> {
    const soapRequest = this.buildSoapRequest(cpr);

    try {
      const response = await axios.post(this.endpoint, soapRequest, {
        headers: {
          'Content-Type': 'text/xml; charset=utf-8',
          'SOAPAction': ''
        }
      });

      return this.parseResponse(response.data);
    } catch (error) {
      console.error('CPR lookup failed:', error);
      return null;
    }
  }

  private buildSoapRequest(cpr: string): string {
    return `<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>${cpr}</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>`;
  }

  private parseResponse(xmlString: string): PersonData {
    // Parse XML and extract data
    // Implementation similar to JavaScript version
  }
}
```

---

## Environment Variables

Create `.env`:

```env
# Server
PORT=3000
NODE_ENV=development
SESSION_SECRET=your-secret-key-change-in-production

# Keycloak (MitID Mock)
KEYCLOAK_ISSUER=http://localhost:8080/realms/danish-gov-test
KEYCLOAK_CLIENT_ID=aabenforms-backend
KEYCLOAK_CLIENT_SECRET=aabenforms-backend-secret-change-in-production
KEYCLOAK_CALLBACK_URL=http://localhost:3000/auth/callback

# Serviceplatformen (WireMock)
SERVICEPLATFORMEN_CPR_URL=http://localhost:8081/soap/sf1520
SERVICEPLATFORMEN_CVR_URL=http://localhost:8081/soap/sf1530
```

---

## Testing

### Unit Test with Jest

Install Jest:

```bash
npm install --save-dev jest @types/jest
```

Create `tests/cprLookup.test.js`:

```javascript
const CprLookupService = require('../services/cprLookup');

describe('CprLookupService', () => {
  let service;

  beforeAll(() => {
    service = new CprLookupService();
  });

  test('should lookup person by CPR', async () => {
    const result = await service.lookup('0101904521');

    expect(result).not.toBeNull();
    expect(result.firstName).toBe('Freja');
    expect(result.lastName).toBe('Nielsen');
  });

  test('should return null for invalid CPR', async () => {
    const result = await service.lookup('0000000000');

    expect(result).toBeNull();
  });
});
```

### Integration Test

```javascript
const request = require('supertest');
const app = require('../app');

describe('MitID Authentication', () => {
  test('should redirect to Keycloak', async () => {
    const response = await request(app)
      .get('/auth/mitid');

    expect(response.status).toBe(302);
    expect(response.headers.location).toContain('localhost:8080');
  });
});
```

---

## Audit Logging Middleware

Create `middleware/auditLog.js`:

```javascript
const winston = require('winston');

const logger = winston.createLogger({
  format: winston.format.json(),
  transports: [
    new winston.transports.File({ filename: 'logs/audit.log' })
  ]
});

function auditCprAccess(req, res, next) {
  if (req.user && req.user.cpr) {
    logger.info('CPR accessed', {
      userId: req.user.id,
      cprPartial: req.user.cpr.substring(0, 6) + '****',
      ip: req.ip,
      route: req.path,
      timestamp: new Date().toISOString()
    });
  }

  next();
}

module.exports = auditCprAccess;
```

Usage:

```javascript
const auditCprAccess = require('./middleware/auditLog');

app.use(auditCprAccess);
```

---

## Docker Compose for Development

Create `docker-compose.dev.yml` to run your app with mocks:

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "3000:3000"
    environment:
      - KEYCLOAK_ISSUER=http://keycloak:8080/realms/danish-gov-test
      - SERVICEPLATFORMEN_CPR_URL=http://wiremock:8080/soap/sf1520
    depends_on:
      - keycloak
      - wiremock
    networks:
      - app-network

  keycloak:
    image: quay.io/keycloak/keycloak:23.0
    command:
      - start-dev
      - --import-realm
    volumes:
      - ./danish-gov-mocks/keycloak/realms:/opt/keycloak/data/import
    networks:
      - app-network

  wiremock:
    image: wiremock/wiremock:3.3.1
    volumes:
      - ./danish-gov-mocks/wiremock/mappings:/home/wiremock/mappings
      - ./danish-gov-mocks/wiremock/__files:/home/wiremock/__files
    networks:
      - app-network

networks:
  app-network:
    driver: bridge
```

---

## Production Checklist

1. Update environment variables with production URLs
2. Enable HTTPS and set `cookie.secure = true`
3. Use secure session secret
4. Configure proper error handling
5. Enable CORS for production domains
6. Set up monitoring and logging
7. Implement rate limiting

---

## Resources

- [Passport.js Documentation](http://www.passportjs.org/)
- [Axios Documentation](https://axios-http.com/)
- [Node SOAP](https://github.com/vpulim/node-soap)
