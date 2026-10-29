<?php

namespace Omnibus\Fedex\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Fedex\Api;
use Omnibus\Fedex\Mapping;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;

/** The Track API: the parcel's scan events, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call('POST', '/track/v1/trackingnumbers', ['includeDetailedScans' => true, 'trackingInfo' => [['trackingNumberInfo' => ['trackingNumber' => $request->trackingNumber]]]], self::locale($request->locale));
        $result = $data['output']['completeTrackResults'][0]['trackResults'][0] ?? [];
        if (!empty($result['error'])) {
            $request->setResult(new TrackingModel('fedex', $request->trackingNumber, TrackingStatus::UNKNOWN));

            return;
        }
        $events = [];
        foreach ($result['scanEvents'] ?? [] as $scan) {
            $events[] = new TrackingEvent(new \DateTimeImmutable((string) ($scan['date'] ?? 'now')), Mapping::status($scan['eventType'] ?? null), (string) ($scan['eventDescription'] ?? ''), trim(implode(' ', array_filter([$scan['scanLocation']['city'] ?? null, $scan['scanLocation']['countryCode'] ?? null]))) ?: null, $scan['eventType'] ?? null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $status = Mapping::status($result['latestStatusDetail']['code'] ?? ($events ? $events[array_key_last($events)]->code : null));
        $request->setResult(new TrackingModel('fedex', $request->trackingNumber, $status, $events));
    }

    /** A bare language's usual FedEx locale; anything unknown is en_US. */
    private const LOCALES = ['en' => 'en_US', 'fr' => 'fr_FR', 'de' => 'de_DE', 'es' => 'es_ES', 'it' => 'it_IT', 'nl' => 'nl_NL', 'pt' => 'pt_BR', 'ja' => 'ja_JP', 'zh' => 'zh_CN', 'ko' => 'ko_KR', 'pl' => 'pl_PL', 'sv' => 'sv_SE', 'da' => 'da_DK', 'cs' => 'cs_CZ', 'tr' => 'tr_TR', 'ru' => 'ru_RU', 'ar' => 'ar_AE', 'th' => 'th_TH', 'vi' => 'vi_VN', 'id' => 'id_ID', 'ms' => 'ms_MY', 'he' => 'he_IL', 'hu' => 'hu_HU', 'fi' => 'fi_FI', 'no' => 'no_NO', 'el' => 'el_GR'];

    /** The request's locale as FedEx's X-locale: language_COUNTRY as given (en-GB becomes en_GB), or a bare language's usual country. */
    private static function locale(string $locale): string
    {
        $parts = preg_split('/[-_]/', $locale);
        if (isset($parts[1]) && '' !== $parts[1]) {
            return strtolower($parts[0]).'_'.strtoupper($parts[1]);
        }

        return self::LOCALES[strtolower($parts[0])] ?? 'en_US';
    }
}
