<?php

/**
 * Listagem de Eventos em JSON (usada pelo renderJson quando Accept: application/json).
 * Campos um a um: nunca serializar o objeto inteiro.
 *
 * @var array<\App\Models\Event> $events
 * @var \Lib\Paginator $paginator
 */

$json['events'] = array_map(fn(\App\Models\Event $event) => [
    'id' => $event->id,
    'title' => $event->title,
    'kind' => $event->kind,
    'modality' => $event->modality,
    'starts_on' => $event->starts_on,
    'location' => $event->location,
    'capacity' => (int) $event->capacity,
    'enrollments' => $event->enrollmentsCount(),
    'available_spots' => $event->availableSpots(),
], $events);
$json['page'] = $paginator->getPage();
$json['total'] = $paginator->totalOfRegisters();
