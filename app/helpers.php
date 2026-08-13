<?php

if (! function_exists('money')) {
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $decimals = strtoupper((string) $currency) === 'USD' ? 2 : 0;

        return number_format((float) $amount, $decimals);
    }
}
