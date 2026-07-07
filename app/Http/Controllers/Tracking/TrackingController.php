<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint public de tracking d'OUVERTURE des cold emails de Juliette.
 *
 * Le mailer de la flotte (SMTP OVH direct, sans ESP) injecte dans la partie HTML un pixel 1x1
 * pointant sur /o/{token}.gif. Le token est un JSON compact base64url signé HMAC-SHA256 avec
 * un secret partagé (config/acquisition.env ↔ services.tracking.secret) : on valide l'ouverture
 * SANS lookup DB, puis on journalise une activité `email_opened` (source=juliette) idempotente.
 *
 * Le pixel étant first-party (même domaine racine que l'adresse d'envoi), la délivrabilité est
 * préservée. L'endpoint renvoie TOUJOURS un GIF transparent, même en cas de token invalide, pour
 * ne rien divulguer et ne jamais casser le rendu de l'email.
 */
class TrackingController extends Controller
{
    /** GIF 1x1 transparent (43 octets), encodé en base64. */
    private const PIXEL_GIF_B64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** User-agents de proxies/scanners qui pré-chargent les images (ouvertures NON humaines). */
    private const BOT_AGENTS = [
        'GoogleImageProxy', 'GmailImageProxy', 'ggpht', 'YahooMailProxy', 'Yahoo',
        'Outlook', 'safelinks', 'Barracuda', 'Proofpoint', 'Mimecast',
        'Microsoft-CryptoAPI', 'Symantec', 'Cloudmark', 'Fortinet',
    ];

    /** En-dessous de ce délai après l'envoi, une ouverture est quasi certainement un scanner. */
    private const BOT_DELAY_SECONDS = 10;

    public function open(Request $request, string $token)
    {
        try {
            $this->record($request, $token);
        } catch (\Throwable $e) {
            // Le pixel ne doit JAMAIS échouer visiblement.
            Log::warning('[Tracking] open pixel error: ' . $e->getMessage());
        }

        $gif = base64_decode(self::PIXEL_GIF_B64);

        return response($gif, 200, [
            'Content-Type'  => 'image/gif',
            'Content-Length' => (string) strlen($gif),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * Vérifie le token, enrichit depuis l'envoi correspondant, journalise l'ouverture.
     */
    private function record(Request $request, string $token): void
    {
        $payload = $this->verifyToken($token);
        if ($payload === null) {
            return; // signature invalide / token malformé → ignoré silencieusement
        }

        $mid = $payload['m'] ?? null;
        if (! $mid) {
            return;
        }

        $contactId = $payload['c'] ?? null;
        $campaign  = $payload['k'] ?? null;
        $step      = $payload['s'] ?? null;
        $sentTs    = isset($payload['t']) ? (int) $payload['t'] : null;

        $now = now();

        // Source de vérité : l'activité d'envoi correspondante (matchée par message_id).
        $sent = Activity::where('source', 'juliette')
            ->where('type', Activity::TYPE_EMAIL_SENT)
            ->where('metadata->message_id', $mid)
            ->first();

        $subject = null;
        $sentAt  = $sentTs ? Carbon::createFromTimestamp($sentTs) : null;
        if ($sent) {
            $contactId = $contactId ?? $sent->subject_id;
            $meta      = $sent->metadata ?? [];
            $campaign  = $campaign ?? ($meta['campaign'] ?? null);
            $step      = $step ?? ($meta['step'] ?? null);
            $subject   = $meta['subject'] ?? null;
            $sentAt    = $sent->occurred_at ?? $sentAt; // occurred_at plus fiable que le ts du token
        }

        // Délai depuis l'envoi (secondes) — calcul en timestamps pour éviter l'ambiguïté de signe.
        $delay = $sentAt ? ($now->timestamp - $sentAt->timestamp) : null;

        $ua    = (string) $request->userAgent();
        $isBot = $this->looksLikeBot($ua, $delay);

        $externalId = 'open:' . $mid;

        $existing = Activity::where('source', 'juliette')
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            // Réouverture : incrémente le compteur, met à jour le dernier accès.
            $meta = $existing->metadata ?? [];
            $meta['opens']           = (int) ($meta['opens'] ?? 1) + 1;
            $meta['last_open_at']    = $now->toIso8601String();
            $meta['last_user_agent'] = $ua;
            // Une ouverture humaine réelle après un pré-chargement bot → on reclasse en humain.
            if (! $isBot && ! empty($meta['is_bot'])) {
                $meta['is_bot']              = false;
                $meta['human_open_at']       = $now->toIso8601String();
                $meta['human_delay_seconds'] = $delay;
            }
            $existing->update(['metadata' => $meta]);

            return;
        }

        // Première ouverture.
        Activity::create([
            'type'         => Activity::TYPE_EMAIL_OPENED,
            'source'       => 'juliette',
            'external_id'  => $externalId,
            'subject_type' => $contactId ? Contact::class : null,
            'subject_id'   => $contactId,
            'title'        => 'Email ouvert' . ($subject ? " — {$subject}" : ''),
            'body'         => '',
            'occurred_at'  => $now,
            'metadata'     => [
                'campaign'      => $campaign,
                'step'          => $step,
                'subject'       => $subject,
                'message_id'    => $mid,
                'opens'         => 1,
                'first_open_at' => $now->toIso8601String(),
                'last_open_at'  => $now->toIso8601String(),
                'user_agent'    => $ua,
                'delay_seconds' => $delay,
                'is_bot'        => $isBot,
            ],
        ]);
    }

    /**
     * Décode + vérifie le token « base64url(payload).hmac_sha256_hex ».
     * Renvoie le payload (tableau) si la signature est valide, sinon null.
     */
    private function verifyToken(string $token): ?array
    {
        $token = preg_replace('/\.gif$/i', '', $token); // retire l'extension éventuelle
        if (! str_contains($token, '.')) {
            return null;
        }
        [$p64, $sig] = explode('.', $token, 2);

        $secret = (string) config('services.tracking.secret');
        if ($secret === '' || $p64 === '' || $sig === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $p64, $secret);
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        // base64url → base64 + padding
        $padded = strtr($p64, '-_', '+/') . str_repeat('=', (4 - strlen($p64) % 4) % 4);
        $json   = base64_decode($padded, true);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /** Heuristique : ouverture par un robot/proxy (délai trop court OU user-agent de scanner). */
    private function looksLikeBot(string $ua, ?int $delay): bool
    {
        if ($delay !== null && $delay >= 0 && $delay < self::BOT_DELAY_SECONDS) {
            return true;
        }
        foreach (self::BOT_AGENTS as $needle) {
            if ($ua !== '' && stripos($ua, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
