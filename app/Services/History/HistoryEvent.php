<?php

namespace App\Services\History;

use Carbon\CarbonImmutable;

/**
 * Representación uniforme de un suceso en la línea de tiempo, sin exponer
 * los modelos Eloquent originales a la vista. Todos los eventos se derivan
 * en el momento de tablas ya existentes (ver `HistoryService`); ninguno se
 * persiste por separado.
 */
final readonly class HistoryEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $routeParams
     */
    public function __construct(
        public string $type,
        public CarbonImmutable $date,
        public ?CarbonImmutable $occurredAt,
        public string $title,
        public ?string $description,
        public string $icon,
        public string $color,
        public string $sourceType,
        public ?int $sourceId,
        public array $metadata = [],
        public ?string $route = null,
        public array $routeParams = [],
        public int $priority = 50,
    ) {}
}
