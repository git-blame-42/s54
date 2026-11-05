<?php

/**
 * Resposta JSON do POST /events/{id}/enrollments (Accept: application/json).
 *
 * @var bool $saved
 * @var \App\Models\Enrollment $enrollment
 * @var \App\Models\Event $event
 */

$json['ok'] = $saved;
$json['message'] = $saved
    ? 'Inscrição realizada com sucesso!'
    : ($enrollment->errors('base') ?? 'Não foi possível realizar a inscrição!');

if ($saved) {
    $json['enrollment'] = [
        'id' => $enrollment->id,
        'event_id' => $enrollment->event_id,
        'user_id' => $enrollment->user_id,
    ];
} else {
    $json['errors'] = $enrollment->errorMessages();
}

$json['enrollments_count'] = $event->enrollmentsCount();
$json['capacity'] = (int) $event->capacity;
$json['available_spots'] = $event->availableSpots();
