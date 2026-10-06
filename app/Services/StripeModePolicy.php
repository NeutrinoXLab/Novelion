<?php

namespace App\Services;

class StripeModePolicy
{
    public function livemode(): bool
    {
        $mode = config('services.stripe.mode');
        if (! in_array($mode, ['live', 'test'], true)) {
            throw new \LogicException('Stripe mode configuration requires explicit live or test.');
        }
        $secret = config('services.stripe.secret');
        // The explicit mode is authoritative; a recognizable contradictory API key is a configuration error.
        if (! is_string($secret) || ! preg_match('/^(sk|rk)_(live|test)_\S+$/D', $secret, $matches)
            || $matches[2] !== $mode) {
            throw new \LogicException('Stripe API key configuration does not match the explicit mode.');
        }

        return $mode === 'live';
    }
}
