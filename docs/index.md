# omnibus/fedex

FedEx for [omniscient/omnibus](https://github.com/glitchr-studio/omnibus): rate quotes, shipments
and labels, tracking and drop-off locations, through FedEx's REST APIs on an OAuth 2
client-credentials token. The gateway calls:

| Operation | FedEx API | Path |
|---|---|---|
| token | OAuth | `POST /oauth/token` |
| rate | Rate API | `POST /rate/v1/rates/quotes` |
| ship | Ship API | `POST /ship/v1/shipments` |
| track | Track API | `POST /track/v1/trackingnumbers` |
| pickup points | Location API | `POST /location/v1/locations` |

on `https://apis.fedex.com` (live) or `https://apis-sandbox.fedex.com` (`sandbox: true`).

## Installation

```sh
composer require omnibus/fedex
```

Requires PHP 8.2, `omniscient/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- A project in the [FedEx Developer Portal](https://developer.fedex.com) with the Rate, Ship,
  Track and Location APIs: its **client id** and **client secret**. Test and production keys are
  separate: the test keys go with `sandbox: true`.
- The shipper's **FedEx account number**: it is sent with every rate and shipment request and pays
  for the shipments (`paymentType: SENDER`).

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id` | string | - | yes | the project's client id |
| `client_secret` | string | - | yes | the project's client secret |
| `account_number` | string | - | yes | the shipper's account |
| `sandbox` | bool | `false` | no | `true`: `https://apis-sandbox.fedex.com` |
| `rates` | list | - | no | prices from configuration instead of the Rate API ([the rates option](https://github.com/glitchr-studio/omnibus/blob/2.x/docs/configuration.md#the-rates-option)): with it, the Rate API is not called |

Shipment options (`Shipment::$options`):

| Option | Read by | Default | |
|---|---|---|---|
| `pickup_type` | rate, ship | `DROPOFF_AT_FEDEX_LOCATION` | how FedEx gets the parcel: `DROPOFF_AT_FEDEX_LOCATION`, `CONTACT_FEDEX_TO_SCHEDULE`, `USE_SCHEDULED_PICKUP` |
| `label_format` | ship | `PDF` | FedEx's `imageType`: `PDF`, `PNG` or `ZPLII` (sent in capitals) |

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | yes (the Rate API), or configured rates with `rates` |
| `Shipping` | yes |
| `Tracking` | yes |
| `Pickup` | yes (FedEx drop-off locations) |
| `GetSlip` | no |
| `Cancel` | no |
| `Paperless` | no |

## Behaviour

**Token.** Asked for on the first call, not when the gateway is built: a form `POST` with
`grant_type=client_credentials`, the client id and the secret. It is kept in the API client until
60 seconds before it expires (`expires_in`, 3600 seconds when FedEx does not say). Every call sends
`Authorization: Bearer`, `X-locale` (`en_US` but for tracking) and a random
`x-customer-transaction-id`, with a 20-second timeout.

**Addresses and parcels.** A party is its contact (`personName` and `companyName` cut to 70
characters, the phone's digits only, the email) and its address (the non-empty street lines, city,
postcode, country). No state or province code is sent: the address model has none. A parcel is its
weight in kilograms rounded to two decimals (at least 0.1 kg) and its dimensions in centimetres
when all three are given.

**Rate.** One quote request for every service, `rateRequestType: ACCOUNT` (the account's rates),
with transit times; `Shipment::$service` is not sent. For each `rateReplyDetails`: the service is
FedEx's `serviceType`, the label its `serviceName`, the amount the first rated detail's
`totalNetCharge` (else `totalNetFedExCharge`) in its currency (`EUR` when none is given), `days`
read from `transitTime` (`ONE_DAY` to `SEVEN_DAYS`; anything else `null`). Rates come cheapest
first; `toPickupPoint` is always `false`.

**Ship.** One shipment request with `labelResponseOptions: LABEL`: the ship date
`Shipment::$shippingDate` (else today), the service `Shipment::$service` or
`FEDEX_INTERNATIONAL_PRIORITY`, packaging `YOUR_PACKAGING`, a 4 x 6 inch paper label, the
reference (40 characters) as `CUSTOMER_REFERENCE`. The tracking number is
`masterTrackingNumber`, else the first piece's; the label is the first piece's first document,
base64-decoded into `content`, or its `url`. The format is `application/zpl` for `ZPLII`,
`image/png` for `PNG`, `application/pdf` otherwise. `trackingUrl` is
`https://www.fedex.com/fedextrack/?trknbr=<number>`. A response without a number is a
`CarrierException` (`FedEx booked no shipment.`). `pickupPoint` is not sent: delivery to a FedEx
location is not booked by this package.

**Track.** With detailed scans, and `X-locale` built from the request's locale: `fr` becomes
`fr_FR`, `en-GB` becomes `en_GB`; a bare language is doubled in capitals, so `en` becomes
`en_EN`. When FedEx answers the number with an error, the tracking is `UNKNOWN` with no events,
not an exception. Scan events are sorted oldest first, each with FedEx's `eventType` as `code` and
`city countryCode` as `location`. The status is mapped from `latestStatusDetail.code`, else from
the latest event's type:

> [!WARNING]
> Known defect: a bare locale `en` becomes `en_EN`, where FedEx expects `en_US` (or another existing locale).

| FedEx code | `TrackingStatus` |
|---|---|
| `DL` | `DELIVERED` |
| `OD` | `OUT_FOR_DELIVERY` |
| `HL` | `AVAILABLE_FOR_PICKUP` |
| `IT`, `PU`, `AR`, `DP`, `IX`, `AF`, `OC` | `IN_TRANSIT` |
| `DE`, `SE`, `CA` | `EXCEPTION` |
| `RS` | `RETURNED` |
| `IN`, `PX` | `PENDING` |
| anything else | `UNKNOWN` |

**Pickup points.** FedEx locations offering `DROPOFF_SERVICE` within 25 km of the address (its
street lines, city, postcode and country), at most `min(50, limit)`; the parcel is not sent. The
id is the `locationId`, the name the contact's company name (else the location type), the distance
FedEx's kilometres converted to metres, opening hours read from `normalHours` (`MON` to `SUN` as
ISO weekdays 1 to 7) with each slot's `begins` and `ends` as FedEx writes them. The points are kept
in FedEx's order and cut to `limit`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Fedex\FedexGatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Symfony\Component\HttpClient\HttpClient;

$fedex = (new FedexGatewayFactory(HttpClient::create()))->create([
    'client_id' => getenv('FEDEX_CLIENT_ID'),
    'client_secret' => getenv('FEDEX_CLIENT_SECRET'),
    'account_number' => getenv('FEDEX_ACCOUNT'),
    'sandbox' => true,
]);

$shipment = new Shipment(
    new Address('Glitch Art', ['5 rue du Dôme'], '67000', 'Strasbourg', 'FR', phone: '+33388000000'),
    new Address('Ada Lovelace', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'ada@example.org'),
    [new Parcel(1200, 30, 20, 10)],
    service: 'INTERNATIONAL_ECONOMY',
    reference: 'CMD-1042',
    options: ['label_format' => 'PDF'],
);

foreach ($fedex->rate($shipment) as $rate) {
    printf("%-28s %6.2f %s, %s days\n", $rate->service, $rate->amount / 100, $rate->currency, $rate->days ?? '?');
}

$label = $fedex->ship($shipment);
file_put_contents($label->trackingNumber.'.pdf', $label->content);
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $fedex->track($label->trackingNumber, 'fr');
echo $tracking->status->value, "\n";

foreach ($fedex->pickupPoints($shipment->sender, 3) as $point) {
    printf("%s %s %d m\n", $point->id, $point->name, $point->distance);
}
```

In Symfony:

```yaml
omnibus:
    gateways:
        fedex:
            factory: fedex
            options:
                client_id: '%env(FEDEX_CLIENT_ID)%'
                client_secret: '%env(FEDEX_CLIENT_SECRET)%'
                account_number: '%env(FEDEX_ACCOUNT)%'
                sandbox: '%env(bool:FEDEX_SANDBOX)%'
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `fedex`:

- an HTTP status of 400 or more: the message is FedEx's first error (`errors[0].message`, else
  `HTTP <status>`), its code in `carrierCode`;
- a body that is not JSON, a network error: `FedEx request failed: ...` or `FedEx answered HTTP
  <status> with a body that is not JSON.`;
- no token: `FedEx gave no token: ...`, FedEx's error message, or `FedEx gave no token: check the
  client id and secret.`;
- `FedEx booked no shipment.` when the Ship API returns no tracking number.

A tracking number FedEx does not know is not an error: the tracking is `UNKNOWN`. A missing
`client_id`, `client_secret` or `account_number` is an `InvalidConfigException` when the gateway
is built; `GetSlip`, `Cancel` and `Paperless` raise `RequestNotSupportedException` before any
call.

## Verified, and not

Tested offline: `Tests/FedexGatewayTest.php` (the sandbox host, rates cheapest first with their
transit days and the account in the request, a booked shipment with its PDF label, scans oldest
first with their location, a location's distance in metres and its Monday hours) and
`Tests/FedexContractTest.php`, the family's conformance suite on recorded answers. The example
above was run in the core's harness on those recorded answers.

Not verified: the README says the package is built from FedEx's published API documentation and
was not yet run against the sandbox; that needs the credentials above. Unchecked against a real
answer: the format of the Location API's `begins` and `ends` (passed on as written; the recorded
answers use `HH:MM`).

## Responsibility

An independent, unofficial client, not affiliated with FedEx Corporation. Whoever runs it complies
with FedEx's terms ([FedEx Developer Portal licence
agreement](https://developer.fedex.com/api/en-us/legal.html)): see the README's Responsibility
section.
