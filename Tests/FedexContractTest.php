<?php

namespace Omnibus\Fedex\Tests;

use Omnibus\Fedex\FedexGatewayFactory;
use Omnibus\GatewayFactoryInterface;
use Omnibus\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on FedEx's recorded answers (the sandbox). */
final class FedexContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new FedexGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['client_id' => 'id', 'client_secret' => 'secret', 'account_number' => '510087', 'sandbox' => true];
    }

    protected function sessionRoutes(): array
    {
        return ['POST /oauth/token'];
    }

    protected function contractTrackingNumber(): string
    {
        return '794644790000';
    }

    protected function recordedAnswers(): array
    {
        return [
            'POST /oauth/token' => ['access_token' => 'tok', 'expires_in' => 3599],
            '/rate/v1/rates/quotes' => ['output' => ['rateReplyDetails' => [
                ['serviceType' => 'INTERNATIONAL_ECONOMY', 'serviceName' => 'FedEx International Economy', 'ratedShipmentDetails' => [['totalNetCharge' => 31.2, 'currency' => 'EUR']], 'operationalDetail' => ['transitTime' => 'THREE_DAYS']],
                ['serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY', 'serviceName' => 'FedEx International Priority', 'ratedShipmentDetails' => [['totalNetCharge' => 48.5, 'currency' => 'EUR']], 'operationalDetail' => ['transitTime' => 'ONE_DAY']],
            ]]],
            '/ship/v1/shipments' => ['output' => ['transactionShipments' => [['masterTrackingNumber' => '794644790000', 'pieceResponses' => [['trackingNumber' => '794644790000', 'packageDocuments' => [['contentType' => 'LABEL', 'encodedLabel' => base64_encode('%PDF-1.4')]]]]]]]],
            '/track/v1/trackingnumbers' => ['output' => ['completeTrackResults' => [['trackResults' => [['latestStatusDetail' => ['code' => 'IT', 'description' => 'In transit'], 'scanEvents' => [
                ['date' => '2026-10-02T08:00:00+02:00', 'eventType' => 'IT', 'eventDescription' => 'In transit', 'scanLocation' => ['city' => 'ROISSY', 'countryCode' => 'FR']],
                ['date' => '2026-10-01T18:00:00+02:00', 'eventType' => 'PU', 'eventDescription' => 'Picked up', 'scanLocation' => ['city' => 'STRASBOURG', 'countryCode' => 'FR']],
            ]]]]]]],
            '/location/v1/locations' => ['output' => ['locationDetailList' => [
                ['locationId' => 'STRA', 'locationType' => 'FEDEX_ONSITE', 'contactAndAddress' => ['contact' => ['companyName' => 'PRESSE DU CENTRE'], 'address' => ['streetLines' => ['5 RUE DU DOME'], 'city' => 'STRASBOURG', 'postalCode' => '67000', 'countryCode' => 'FR']], 'distance' => ['value' => 0.8, 'units' => 'KM'], 'normalHours' => [['dayOfWeek' => 'MON', 'operationalHours' => [['begins' => '09:00', 'ends' => '19:00']]]]],
                ['locationId' => 'STRB', 'locationType' => 'FEDEX_ONSITE', 'contactAndAddress' => ['contact' => ['companyName' => 'TABAC DE LA GARE'], 'address' => ['streetLines' => ['2 PLACE DE LA GARE'], 'city' => 'STRASBOURG', 'postalCode' => '67000', 'countryCode' => 'FR']], 'distance' => ['value' => 1.4, 'units' => 'KM']],
                ['locationId' => 'STRC', 'locationType' => 'FEDEX_ONSITE', 'contactAndAddress' => ['contact' => ['companyName' => 'LOCKER NEUDORF'], 'address' => ['streetLines' => ['1 ROUTE DU RHIN'], 'city' => 'STRASBOURG', 'postalCode' => '67100', 'countryCode' => 'FR']], 'distance' => ['value' => 2.1, 'units' => 'KM']],
            ]]],
        ];
    }
}
