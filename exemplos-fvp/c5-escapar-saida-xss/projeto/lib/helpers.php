<?php

if (!function_exists('e')) {
    /**
     * Escapa um valor para impressão segura em HTML (texto e atributos).
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
