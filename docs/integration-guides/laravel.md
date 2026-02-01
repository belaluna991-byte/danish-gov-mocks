# Laravel Integration Guide

This guide shows how to integrate the Danish Government Mock Services with Laravel 10/11.

## Prerequisites

- Laravel 10 or 11
- PHP 8.2+
- Composer

## MitID Authentication (Keycloak OIDC)

### 1. Install Socialite

```bash
composer require laravel/socialite
composer require socialiteproviders/keycloak
```

### 2. Configure Service Provider

Add to `config/app.php`:

```php
'providers' => [
    // Other providers...
    \SocialiteProviders\Manager\ServiceProvider::class,
],
```

### 3. Add Event Listener

Create `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        \SocialiteProviders\Keycloak\KeycloakExtendSocialite::class.'@handle',
    ],
];
```

### 4. Configure Environment Variables

Add to `.env`:

```env
# MitID Mock (Keycloak)
KEYCLOAK_CLIENT_ID=aabenforms-backend
KEYCLOAK_CLIENT_SECRET=aabenforms-backend-secret-change-in-production
KEYCLOAK_REDIRECT_URI=http://localhost:8000/auth/callback
KEYCLOAK_BASE_URL=http://localhost:8080
KEYCLOAK_REALMS=danish-gov-test

# Serviceplatformen Mock (WireMock)
WIREMOCK_BASE_URL=http://localhost:8081
```

### 5. Update config/services.php

```php
'keycloak' => [
    'client_id' => env('KEYCLOAK_CLIENT_ID'),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    'redirect' => env('KEYCLOAK_REDIRECT_URI'),
    'base_url' => env('KEYCLOAK_BASE_URL'),
    'realms' => env('KEYCLOAK_REALMS'),
],
```

### 6. Create Authentication Routes

Add to `routes/web.php`:

```php
use App\Http\Controllers\Auth\MitIdController;

Route::get('/auth/mitid', [MitIdController::class, 'redirect'])->name('mitid.redirect');
Route::get('/auth/callback', [MitIdController::class, 'callback'])->name('mitid.callback');
Route::post('/auth/logout', [MitIdController::class, 'logout'])->name('mitid.logout');
```

### 7. Create MitID Controller

```php
<?php
// app/Http/Controllers/Auth/MitIdController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class MitIdController extends Controller
{
    /**
     * Redirect to MitID login.
     */
    public function redirect()
    {
        return Socialite::driver('keycloak')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle MitID callback.
     */
    public function callback()
    {
        try {
            $keycloakUser = Socialite::driver('keycloak')->user();

            // Extract Danish-specific claims
            $cpr = $keycloakUser->user['cpr'] ?? null;
            $cvr = $keycloakUser->user['cvr'] ?? null;
            $birthdate = $keycloakUser->user['birthdate'] ?? null;

            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $keycloakUser->getEmail()],
                [
                    'name' => $keycloakUser->getName(),
                    'keycloak_id' => $keycloakUser->getId(),
                    'cpr' => $cpr,
                    'cvr' => $cvr,
                    'birthdate' => $birthdate,
                ]
            );

            // Assign business role if CVR present
            if ($cvr) {
                $user->assignRole('business');
            }

            // Login user
            Auth::login($user);

            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['error' => 'Authentication failed']);
        }
    }

    /**
     * Logout user.
     */
    public function logout()
    {
        Auth::logout();

        $logoutUrl = config('services.keycloak.base_url')
            . '/realms/' . config('services.keycloak.realms')
            . '/protocol/openid-connect/logout'
            . '?redirect_uri=' . urlencode(url('/'));

        return redirect($logoutUrl);
    }
}
```

### 8. Update User Migration

Add columns to users table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('keycloak_id')->unique()->nullable();
    $table->string('cpr')->nullable();
    $table->string('cvr')->nullable();
    $table->date('birthdate')->nullable();
});
```

### 9. Encrypt CPR Field

Install encryption package:

```bash
composer require paragonie/ciphersweet-laravel
```

Update User model:

```php
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;

class User extends Authenticatable
{
    protected $casts = [
        'cpr' => 'encrypted',
        'birthdate' => 'date',
    ];

    protected $hidden = [
        'cpr',
        'keycloak_id',
    ];
}
```

---

## Serviceplatformen Integration (SOAP)

### 1. Install SOAP Client

```bash
composer require phpro/soap-client
```

### 2. Create CPR Lookup Service

```php
<?php
// app/Services/Serviceplatformen/CprLookupService.php

namespace App\Services\Serviceplatformen;

use Illuminate\Support\Facades\Http;

class CprLookupService
{
    protected string $endpoint;

    public function __construct()
    {
        $this->endpoint = config('services.serviceplatformen.cpr_endpoint',
            'http://localhost:8081/soap/sf1520'
        );
    }

    /**
     * Look up person by CPR number.
     *
     * @param string $cpr
     * @return array|null
     */
    public function lookup(string $cpr): ?array
    {
        $xml = $this->buildSoapRequest($cpr);

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '',
        ])->send('POST', $this->endpoint, [
            'body' => $xml,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $this->parseResponse($response->body());
    }

    protected function buildSoapRequest(string $cpr): string
    {
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

    protected function parseResponse(string $xml): array
    {
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

### 3. Create CVR Lookup Service

```php
<?php
// app/Services/Serviceplatformen/CvrLookupService.php

namespace App\Services\Serviceplatformen;

use Illuminate\Support\Facades\Http;

class CvrLookupService
{
    protected string $endpoint;

    public function __construct()
    {
        $this->endpoint = config('services.serviceplatformen.cvr_endpoint',
            'http://localhost:8081/soap/sf1530'
        );
    }

    public function lookup(string $cvr): ?array
    {
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

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '',
        ])->send('POST', $this->endpoint, [
            'body' => $xml,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $this->parseResponse($response->body());
    }

    protected function parseResponse(string $xml): array
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cvr', 'http://serviceplatformen.dk/xml/schemas/CvrLookup/1/');

        return [
            'cvr' => $xpath->evaluate('string(//cvr:CVR)'),
            'name' => $xpath->evaluate('string(//cvr:CompanyName)'),
            'address' => $xpath->evaluate('string(//cvr:Address)'),
            'city' => $xpath->evaluate('string(//cvr:City)'),
        ];
    }
}
```

### 4. Register Services

Add to `app/Providers/AppServiceProvider.php`:

```php
public function register()
{
    $this->app->singleton(CprLookupService::class);
    $this->app->singleton(CvrLookupService::class);
}
```

### 5. Usage in Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Services\Serviceplatformen\CprLookupService;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function show(Request $request, CprLookupService $cprService)
    {
        $cpr = $request->user()->cpr;

        $personData = $cprService->lookup($cpr);

        return view('person.show', [
            'person' => $personData,
        ]);
    }
}
```

---

## Middleware for CPR Access Logging

Create audit middleware:

```php
<?php
// app/Http/Middleware/AuditCprAccess.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class AuditCprAccess
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->user() && $request->user()->cpr) {
            Log::channel('audit')->info('CPR accessed', [
                'user_id' => $request->user()->id,
                'cpr' => substr($request->user()->cpr, 0, 6) . '****',
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);
        }

        return $response;
    }
}
```

Register in `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \App\Http\Middleware\AuditCprAccess::class,
    ],
];
```

---

## Testing

### Feature Test for MitID Login

```php
<?php
// tests/Feature/MitIdAuthTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Laravel\Socialite\Facades\Socialite;

class MitIdAuthTest extends TestCase
{
    public function test_mitid_redirect()
    {
        $response = $this->get('/auth/mitid');

        $response->assertRedirect();
        $this->assertStringContainsString('localhost:8080', $response->headers->get('Location'));
    }

    public function test_mitid_callback_creates_user()
    {
        $mockUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $mockUser->shouldReceive('getId')->andReturn('keycloak-123');
        $mockUser->shouldReceive('getEmail')->andReturn('test@example.dk');
        $mockUser->shouldReceive('getName')->andReturn('Test User');
        $mockUser->user = [
            'cpr' => '0101904521',
            'birthdate' => '1990-01-01',
        ];

        Socialite::shouldReceive('driver->user')->andReturn($mockUser);

        $response = $this->get('/auth/callback');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.dk',
            'keycloak_id' => 'keycloak-123',
        ]);
    }
}
```

### Unit Test for CPR Lookup

```php
<?php
// tests/Unit/CprLookupServiceTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Serviceplatformen\CprLookupService;

class CprLookupServiceTest extends TestCase
{
    public function test_lookup_returns_person_data()
    {
        $service = new CprLookupService();
        $result = $service->lookup('0101904521');

        $this->assertEquals('Freja', $result['first_name']);
        $this->assertEquals('Nielsen', $result['last_name']);
        $this->assertNotEmpty($result['address']);
    }
}
```

---

## Environment Configuration

Add to `config/services.php`:

```php
'serviceplatformen' => [
    'cpr_endpoint' => env('SERVICEPLATFORMEN_CPR_ENDPOINT', 'http://localhost:8081/soap/sf1520'),
    'cvr_endpoint' => env('SERVICEPLATFORMEN_CVR_ENDPOINT', 'http://localhost:8081/soap/sf1530'),
    'digital_post_endpoint' => env('SERVICEPLATFORMEN_DIGITALPOST_ENDPOINT', 'http://localhost:8081/soap/sf1601'),
],
```

---

## Production Checklist

When deploying to production:

1. Update `.env` with production Keycloak URL
2. Use secure client secret (not test secret)
3. Enable HTTPS for all endpoints
4. Configure proper CPR encryption keys
5. Set up audit logging to dedicated channel
6. Add rate limiting to authentication routes
7. Configure session security

---

## Resources

- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)
- [Keycloak Socialite Provider](https://socialiteproviders.com/Keycloak/)
- [PHP SOAP Client](https://www.php.net/manual/en/class.soapclient.php)
