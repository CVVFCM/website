<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\LiveWeatherRecord;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\FieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @RouteResource("live_weather_record")
 */
#[AsController]
#[Route(path: '/admin/api')]
final readonly class LiveWeatherRecordController
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
    ) {
    }

    #[Route('/live-weather-records', name: 'app.get_live_weather_records', methods: ['GET'])]
    public function cgetAction(): Response
    {
        /**
         * @var array{
         *      recordedAt: FieldDescriptorInterface,
         * } $fieldDescriptors
         */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(LiveWeatherRecord::RESOURCE_KEY);
        $listBuilder = $this->listBuilderFactory
            ->create(LiveWeatherRecord::class)
            ->sort($fieldDescriptors['recordedAt'], 'desc');
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        /** @var list<array<string, mixed>> $rows */
        $rows = $listBuilder->execute();

        $listRepresentation = new PaginatedRepresentation(
            array_map(self::roundNumericColumns(...), $rows),
            LiveWeatherRecord::RESOURCE_KEY,
            intval($listBuilder->getCurrentPage()),
            (int) $listBuilder->getLimit(),
            $listBuilder->count()
        );

        return new JsonResponse($this->normalizer->normalize(
            $listRepresentation->toArray(),
            'json',
            ['sulu_admin' => true, 'sulu_admin_custom_url' => true, 'sulu_admin_custom_url_list' => true],
        ));
    }

    /**
     * Display-only rounding: whole numbers in the datagrid, DB precision untouched (windSpeed still
     * feeds the ML export). Null gaps stay null.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function roundNumericColumns(array $row): array
    {
        foreach (['temperature', 'windSpeed', 'windGusts', 'windSpeedGapForecast', 'windDirectionGapForecast', 'windSpeedGapModel', 'windDirectionGapModel'] as $column) {
            if (isset($row[$column]) && is_numeric($row[$column])) {
                $row[$column] = (int) round((float) $row[$column]);
            }
        }

        return $row;
    }
}
