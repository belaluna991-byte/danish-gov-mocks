# Drupal Integration Guide

This guide shows how to integrate the Danish Government Mock Services with Drupal 10/11.

## Prerequisites

- Drupal 10 or 11
- PHP 8.1+ with SOAP extension
- Composer

## Required Modules

```bash
composer require drupal/openid_connect
composer require drupal/externalauth
composer require php-http/guzzle7-adapter
```

## MitID Authentication (Keycloak OIDC)

### 1. Install OpenID Connect Module

```bash
composer require drupal/openid_connect
drush pm:enable openid_connect openid_connect_generic
```

### 2. Configure Generic OpenID Connect Client

Navigate to: **Configuration → People → OpenID Connect**

Or via Drush:
```bash
drush config:edit openid_connect.settings.generic
```

#### Configuration Values

```yaml
enabled: true
settings:
  client_id: 'aabenforms-backend'
  client_secret: 'aabenforms-backend-secret-change-in-production'
  authorization_endpoint: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth'
  token_endpoint: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token'
  userinfo_endpoint: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo'
  end_session_endpoint: 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout'
settings_mapping:
  userinfo_mappings:
    name: 'preferred_username'
    mail: 'email'
```

### 3. Update settings.php

Add OpenID Connect configuration:

```php
// settings.php or settings.local.php

$config['openid_connect.settings.generic']['enabled'] = TRUE;
$config['openid_connect.settings.generic']['settings'] = [
  'client_id' => 'aabenforms-backend',
  'client_secret' => 'aabenforms-backend-secret-change-in-production',
  'authorization_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth',
  'token_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token',
  'userinfo_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo',
  'end_session_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout',
];
```

### 4. Test Login

1. Navigate to `/user/login`
2. Click "Login with Generic"
3. Use test credentials: `freja.nielsen` / `test1234`
4. User should be created automatically in Drupal

### 5. Access CPR and CVR Claims

Create a custom module to extract Danish claims from the ID token:

```php
<?php
// modules/custom/danish_auth/danish_auth.module

use Drupal\openid_connect\OpenIDConnectSession;

/**
 * Implements hook_openid_connect_userinfo_save().
 */
function danish_auth_openid_connect_userinfo_save($account, array $context) {
  $userinfo = $context['userinfo'];

  // Store CPR in user field
  if (!empty($userinfo['cpr'])) {
    $account->set('field_cpr', $userinfo['cpr']);
  }

  // Store CVR for business users
  if (!empty($userinfo['cvr'])) {
    $account->set('field_cvr', $userinfo['cvr']);
    $account->addRole('business_user');
  }

  // Store birthdate
  if (!empty($userinfo['birthdate'])) {
    $account->set('field_birthdate', $userinfo['birthdate']);
  }

  $account->save();
}
```

**Note**: Add corresponding fields to user entity:
- `field_cpr` (Text, encrypted)
- `field_cvr` (Text)
- `field_birthdate` (Date)

---

## Serviceplatformen Integration (WireMock SOAP)

### 1. Install SOAP Client

```bash
# Ensure PHP SOAP extension is installed
php -m | grep soap

# If not installed (Ubuntu/Debian):
sudo apt-get install php-soap
```

### 2. Create CPR Lookup Service

Create a custom module with SOAP client:

```php
<?php
// modules/custom/danish_services/src/Service/CprLookupService.php

namespace Drupal\danish_services\Service;

use SoapClient;
use SoapFault;

class CprLookupService {

  protected string $wsdlUrl;
  protected string $endpoint;

  public function __construct() {
    // In production, these would come from config
    $this->endpoint = 'http://localhost:8081/soap/sf1520';
  }

  /**
   * Look up person data by CPR number.
   *
   * @param string $cpr
   *   CPR number (10 digits).
   *
   * @return array|null
   *   Person data or NULL if not found.
   */
  public function lookupPerson(string $cpr): ?array {
    try {
      // Create SOAP envelope
      $xml = $this->buildSoapRequest($cpr);

      // Send request
      $response = $this->sendSoapRequest($xml);

      // Parse response
      return $this->parseSoapResponse($response);
    }
    catch (\Exception $e) {
      \Drupal::logger('danish_services')->error('CPR lookup failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  protected function buildSoapRequest(string $cpr): string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:per="http://serviceplatformen.dk/xml/schemas/PersonLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <per:PersonLookupRequest>
      <per:CPR>{$cpr}</per:CPR>
    </per:PersonLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;
  }

  protected function sendSoapRequest(string $xml): string {
    $ch = curl_init($this->endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: text/xml; charset=utf-8',
      'SOAPAction: ""',
    ]);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
      throw new \Exception("SOAP request failed with HTTP $httpCode");
    }

    return $response;
  }

  protected function parseSoapResponse(string $xml): array {
    $doc = new \DOMDocument();
    $doc->loadXML($xml);

    $xpath = new \DOMXPath($doc);
    $xpath->registerNamespace('per', 'http://serviceplatformen.dk/xml/schemas/PersonLookup/1/');

    return [
      'cpr' => $xpath->evaluate('string(//per:CPR)'),
      'first_name' => $xpath->evaluate('string(//per:FirstName)'),
      'last_name' => $xpath->evaluate('string(//per:LastName)'),
      'address' => $xpath->evaluate('string(//per:Address)'),
      'postal_code' => $xpath->evaluate('string(//per:PostalCode)'),
      'city' => $xpath->evaluate('string(//per:City)'),
    ];
  }
}
```

### 3. Register Service

```yaml
# modules/custom/danish_services/danish_services.services.yml
services:
  danish_services.cpr_lookup:
    class: Drupal\danish_services\Service\CprLookupService
```

### 4. Use in Code

```php
// Example: Lookup CPR after MitID login
$cpr_service = \Drupal::service('danish_services.cpr_lookup');
$person_data = $cpr_service->lookupPerson('0101904521');

if ($person_data) {
  \Drupal::logger('danish_services')->info('Person found: @name', [
    '@name' => $person_data['first_name'] . ' ' . $person_data['last_name'],
  ]);
}
```

---

## CVR Lookup Integration

Similar to CPR lookup, create a CVR service:

```php
<?php
// modules/custom/danish_services/src/Service/CvrLookupService.php

namespace Drupal\danish_services\Service;

class CvrLookupService {

  protected string $endpoint = 'http://localhost:8081/soap/sf1530';

  public function lookupCompany(string $cvr): ?array {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cvr="http://serviceplatformen.dk/xml/schemas/CvrLookup/1/">
  <soapenv:Header/>
  <soapenv:Body>
    <cvr:CvrLookupRequest>
      <cvr:CVR>{$cvr}</cvr:CVR>
    </cvr:CvrLookupRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;

    // Send SOAP request and parse response
    // Similar to CPR lookup implementation
  }
}
```

---

## Complete Example Module

See the [examples/drupal/](../../examples/drupal/) directory for a complete working module with:
- OpenID Connect configuration
- CPR/CVR SOAP clients
- User field mappings
- Webform integration

---

## Security Considerations

### Encrypt CPR Fields

Use the Encrypt module to protect CPR numbers in the database:

```bash
composer require drupal/encrypt drupal/key drupal/real_aes
drush pm:enable encrypt key real_aes field_encrypt
```

Configure encryption:
1. Create encryption key: `/admin/config/system/keys`
2. Create encryption profile: `/admin/config/system/encryption/profiles`
3. Encrypt CPR field: Edit field settings → Enable encryption

### Access Control

Restrict CPR access to authorized roles:

```php
/**
 * Implements hook_entity_field_access().
 */
function danish_auth_entity_field_access($operation, $field_definition, $account, $field_item_list) {
  if ($field_definition->getName() === 'field_cpr' && $operation === 'view') {
    // Only caseworkers can view CPR
    return AccessResult::forbiddenIf(!$account->hasRole('caseworker'));
  }
  return AccessResult::neutral();
}
```

---

## Testing

### Test with Multiple Personas

```php
// Test script: test-mitid.php
use GuzzleHttp\Client;

$personas = [
  'freja.nielsen',
  'karen.christensen', // Business user
  'protected.person',  // Protected
];

foreach ($personas as $username) {
  // Simulate OIDC flow
  // Test CPR lookup
  // Verify user creation
}
```

### Automated Testing

Use PHPUnit with Drupal Test Traits:

```php
<?php

namespace Drupal\Tests\danish_services\Kernel;

use Drupal\KernelTests\KernelTestBase;

class CprLookupTest extends KernelTestBase {

  public function testCprLookup() {
    $service = \Drupal::service('danish_services.cpr_lookup');
    $result = $service->lookupPerson('0101904521');

    $this->assertEquals('Freja', $result['first_name']);
    $this->assertEquals('Nielsen', $result['last_name']);
  }
}
```

---

## DDEV Integration

If using DDEV, the mock services need to be accessible from the Drupal container:

```yaml
# .ddev/docker-compose.mocks.yaml
services:
  keycloak:
    networks:
      - ddev_default

  wiremock:
    networks:
      - ddev_default

networks:
  ddev_default:
    external: true
```

Then use internal hostnames:
```php
$config['openid_connect.settings.generic']['settings']['authorization_endpoint'] =
  'http://ddev-projectname-keycloak:8080/realms/danish-gov-test/protocol/openid-connect/auth';
```

---

## Production Configuration

When moving to production:

1. **Update endpoints** to real Serviceplatformen URLs
2. **Add certificates** for WS-Security
3. **Use real client credentials** (not test secrets)
4. **Enable HTTPS** for all OIDC endpoints
5. **Configure proper encryption keys**
6. **Enable audit logging** for CPR access

---

## Troubleshooting

### OIDC Login Redirects to 404

Check redirect URIs in Keycloak client match your Drupal URL exactly.

### CPR Field Not Saving

Ensure field exists and hook is running:
```bash
drush cr
drush php:eval "print_r(\Drupal::moduleHandler()->getImplementations('openid_connect_userinfo_save'));"
```

### SOAP Request Returns 404

Verify WireMock endpoint and check logs:
```bash
docker compose logs wiremock
```

---

## Resources

- [Drupal OpenID Connect Module](https://www.drupal.org/project/openid_connect)
- [Drupal Encrypt Module](https://www.drupal.org/project/encrypt)
- [PHP SOAP Documentation](https://www.php.net/manual/en/book.soap.php)
