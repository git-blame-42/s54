<?php

namespace App\Models;

/**
 * Tipos de Evento (coluna ENUM `kind` de `events`). O valor do case é o gravado no banco;
 * label() é o texto exibido nas telas.
 */
enum EventKind: string
{
    case Lecture = 'lecture';
    case Workshop = 'workshop';
    case ShortCourse = 'short_course';
    case AcademicWeek = 'academic_week';

    public function label(): string
    {
        return match ($this) {
            self::Lecture => 'Palestra',
            self::Workshop => 'Oficina',
            self::ShortCourse => 'Minicurso',
            self::AcademicWeek => 'Semana acadêmica',
        };
    }

    /**
     * Opções do select: valor do banco => rótulo, na ordem dos cases.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $kind) {
            $options[$kind->value] = $kind->label();
        }

        return $options;
    }
}
