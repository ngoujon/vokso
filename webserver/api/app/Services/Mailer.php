<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Envoi d'emails texte ; un échec est journalisé et signalé par false, jamais propagé. */
class Mailer
{
    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        try {
            Mail::raw($body, function (Message $message) use ($to, $subject, $replyTo) {
                $message->to($to)->subject($subject);
                if ($replyTo !== null) {
                    $message->replyTo($replyTo);
                }
            });

            return true;
        } catch (Throwable $e) {
            Log::error('Envoi d\'email échoué', ['service' => 'mailer', 'exception' => get_class($e), 'reason' => $e->getMessage()]);
            report($e);

            return false;
        }
    }
}
