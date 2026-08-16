<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisits
{
    /**
     * Înregistrează o vizită nouă pentru fiecare sesiune.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        /*
         * Continuăm request-ul.
         */
        $response = $next($request);

        /*
         * Numărăm doar accesările normale de pagini.
         *
         * Nu numărăm:
         * - POST
         * - Livewire
         * - AJAX
         * - API
         */
        if (
            $request->isMethod('GET')
            && ! $request->is('livewire/*')
            && ! $request->is('api/*')
            && ! $request->ajax()
            && ! $request->session()->has('visit_tracked')
        ) {

            Visit::create([
                'session_id' => $request->session()->getId(),
                'ip_address' => $request->ip(),
                'url' => $request->fullUrl(),
                'referrer' => $request->header('referer'),
                'user_agent' => $request->userAgent(),
            ]);

            /*
             * Marcăm sesiunea ca fiind deja numărată.
             */
            $request->session()->put(
                'visit_tracked',
                true
            );
        }

        return $response;
    }
}