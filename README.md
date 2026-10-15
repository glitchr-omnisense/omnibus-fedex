# omnibus/fedex

FedEx for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): rate quotes (Rate API),
shipments and labels (Ship API), tracking (Track API) and drop-off locations (Location API) - the
REST APIs with OAuth2 client credentials.

```php
$gateway = (new FedexGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `glitchr/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        fedex:
            factory: fedex
            options:
                client_id: '%env(FEDEX_CLIENT_ID)%'
                client_secret: '%env(FEDEX_CLIENT_SECRET)%'
                account_number: '%env(FEDEX_ACCOUNT)%'
                sandbox: true
                rates: [...]        # optional: configured prices instead of the Rate API
```

Shipment options: `label_format` (PDF, PNG, ZPLII), `pickup_type` (DROPOFF_AT_FEDEX_LOCATION,
CONTACT_FEDEX_TO_SCHEDULE, USE_SCHEDULED_PICKUP). The service is FedEx's code (FEDEX_INTERNATIONAL_PRIORITY,
INTERNATIONAL_ECONOMY, FEDEX_GROUND...).

Credentials: a project in the [FedEx Developer Portal](https://developer.fedex.com) with the Rate,
Ship, Track and Location APIs, its client id and secret (test and production are separate), and
your FedEx account number.

Built from FedEx's published API documentation and tested on recorded answers; not yet run against
the sandbox: that needs the credentials above.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by FedEx Corporation. It reaches their service on behalf of whoever runs it, and it does
not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with FedEx's terms ([FedEx
Developer Portal licence agreement](https://developer.fedex.com/api/en-us/legal.html)), for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or a
citation, a rate limit to respect), and for any use made of what it returns. The authors publish it
free of charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
