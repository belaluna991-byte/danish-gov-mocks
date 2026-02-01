<?php

/**
 * Example Drupal settings for Danish Government Mock Services.
 *
 * Copy relevant sections to your sites/default/settings.local.php
 */

// OpenID Connect configuration for MitID mock
$config['openid_connect.settings.generic']['enabled'] = TRUE;
$config['openid_connect.settings.generic']['settings'] = [
  'client_id' => 'aabenforms-backend',
  'client_secret' => 'aabenforms-backend-secret-change-in-production',
  'authorization_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/auth',
  'token_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/token',
  'userinfo_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/userinfo',
  'end_session_endpoint' => 'http://localhost:8080/realms/danish-gov-test/protocol/openid-connect/logout',
];

// If using with DDEV, adjust URLs to use service names
// $config['openid_connect.settings.generic']['settings']['authorization_endpoint'] =
//   'http://ddev-projectname-keycloak:8080/realms/danish-gov-test/protocol/openid-connect/auth';

// Custom service endpoints for Serviceplatformen mocks
$config['serviceplatformen.settings']['cpr_endpoint'] = 'http://localhost:8081/soap/sf1520';
$config['serviceplatformen.settings']['cvr_endpoint'] = 'http://localhost:8081/soap/sf1530';
$config['serviceplatformen.settings']['digital_post_endpoint'] = 'http://localhost:8081/soap/sf1601';
