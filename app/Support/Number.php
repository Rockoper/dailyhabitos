<?php

namespace App\Support;

class Number
{
    /**
     * Formatea un número quitando ceros decimales sobrantes (100.00 -> "100",
     * 33.30 -> "33.3") sin mutilar la parte entera.
     *
     * No usar `rtrim((string) $valor, '0')` para esto: cuando el valor es un
     * entero sin punto decimal (ej. (string) 800000.0 === "800000"), rtrim
     * recorta los ceros finales del propio número — 800000 se convierte en
     * "8", 100 en "1", etc. Forzar `number_format` primero garantiza que
     * siempre haya un punto decimal que detenga el recorte en el lugar
     * correcto.
     */
    public static function trim(float|int|string $value, int $decimals = 2): string
    {
        return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
    }
}
