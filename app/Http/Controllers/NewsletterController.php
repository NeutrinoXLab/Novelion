<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterConfirmationMail;
use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
                return redirect()
                    ->back()
                    ->with(
                        'newsletter_success',
                        'Această adresă a fost dezabonată. Pentru reactivare, contactează-ne la novelionprime@gmail.com.'
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

        $newsletter = Newsletter::create([
            'email' => $email,
            'subscribed_at' => null,
            'confirmation_token' => Str::random(64),
            'confirmation_sent_at' => now(),
            'consent_ip' => $request->ip(),
            'consent_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'unsubscribe_token' => Str::random(64),
        ]);
        Mail::to($email)->send(new NewsletterConfirmationMail($newsletter));

        return redirect()
            ->back()
            ->with(
                'newsletter_success',
                'Verifică emailul și confirmă abonarea accesând linkul primit.'
            );
    }

    public function confirm(string $token): View
    {
        $newsletter = Newsletter::where('confirmation_token', $token)->whereNull('unsubscribed_at')->first();
        if (! $newsletter) {
            return view('newsletter.unsubscribe', ['success' => false, 'message' => 'Linkul de confirmare este invalid sau abonarea a fost dezactivată.']);
        }
        if (! $newsletter->confirmed_at) {
            $newsletter->update(['confirmed_at' => now(), 'subscribed_at' => now(), 'confirmation_token' => null]);
        }

        return view('newsletter.unsubscribe', ['success' => true, 'message' => 'Abonarea la newsletter a fost confirmată.']);
    }

    /**
     * Dezabonare de la newsletter.
     */
    public function unsubscribe(string $token): View
    {
        $newsletter = Newsletter::where('unsubscribe_token', $token)->first();

        if (! $newsletter) {
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
