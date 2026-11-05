<?php

/**
 * Listagem de Eventos em JSON (usada pelo renderJson quando Accept: application/json).
 * Campos um a um: nunca serializar o objeto inteiro.
 *
 * Com parâmetro inválido (status 422) só `$errors` chega aqui: o corpo leva só os erros.
 *
 * @var array<string, string> $errors
 * @var array<\App\Models\Event> $events
 * @var \Lib\Paginator $paginator
 * @var array{q: string, kind: ?string, sort: string, dir: string} $filters
 */

if ($errors !== []) {
    $json['errors'] = $errors;
    return;
}

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
$json['per_page'] = $paginator->perPage();
$json['total'] = $paginator->totalOfRegisters();
$json['total_pages'] = $paginator->totalOfPages();
$json['filters'] = $filters;
