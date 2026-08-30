<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    /**
     * Abonare la newsletter.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ], [
            'email.required' => 'Te rugăm să introduci adresa de email.',
            'email.email' => 'Te rugăm să introduci o adresă de email validă.',
            'email.max' => 'Adresa de email este prea lungă.',
        ]);

        $email = strtolower(trim($validated['email']));

        $newsletter = Newsletter::where('email', $email)->first();

        if ($newsletter) {

            if ($newsletter->unsubscribed_at !== null) {

                $newsletter->update([
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                    'unsubscribe_token' => $newsletter->unsubscribe_token
                        ?: Str::random(64),
                ]);

                return redirect()
                    ->back()
                    ->with(
                        'newsletter_success',
                        'Te-ai abonat din nou cu succes la newsletter-ul Novelion!'
                    );
            }

            if (empty($newsletter->unsubscribe_token)) {

                $newsletter->update([
                    'unsubscribe_token' => Str::random(64),
                ]);
            }

            return redirect()
                ->back()
                ->with(
                    'newsletter_success',
                    'Această adresă de email este deja abonată la newsletter-ul Novelion.'
                );
        }

        Newsletter::create([
            'email' => $email,
            'subscribed_at' => now(),
            'unsubscribe_token' => Str::random(64),
        ]);

        return redirect()
            ->back()
            ->with(
                'newsletter_success',
                'Te-ai abonat cu succes la newsletter-ul Novelion!'
            );
    }

    /**
     * Dezabonare de la newsletter.
     */
    public function unsubscribe(string $token): View
    {
        $newsletter = Newsletter::where('unsubscribe_token', $token)->first();

        if (!$newsletter) {
            return view('newsletter.unsubscribe', [
                'success' => false,
                'message' => 'Linkul de dezabonare este invalid sau nu mai este disponibil.',
            ]);
        }

        if ($newsletter->unsubscribed_at === null) {
            $newsletter->update([
                'unsubscribed_at' => now(),
            ]);
        }

        return view('newsletter.unsubscribe', [
            'success' => true,
            'message' => 'Te-ai dezabonat cu succes de la newsletter-ul Novelion.',
        ]);
    }
}
