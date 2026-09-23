<?php
/**
 * WhatsAppSender – outbound WhatsApp gateway wrapper.
 *
 * Supports two providers via .env:
 *   WHATSAPP_PROVIDER=meta_cloud    → Meta WhatsApp Cloud API
 *     Requires WHATSAPP_TOKEN and WHATSAPP_PHONE_ID
 *   WHATSAPP_PROVIDER=twilio         → Twilio WhatsApp
 *     Requires WHATSAPP_TOKEN (auth token), WHATSAPP_ACCOUNT_SID, WHATSAPP_FROM
 *
 * When no provider or token is configured, `send()` returns a graceful
 * "not configured" error and callers still log the message row (status=FAILED)
 * so operators can see what would have been sent.
 */

final class WhatsAppSender
{
    private const TIMEOUT_SECS = 30;

    public static function isConfigured(): bool
    {
        return trim((string)getenv('WHATSAPP_TOKEN')) !== ''
            && trim((string)getenv('WHATSAPP_PROVIDER')) !== '';
    }

    /**
     * @return array{success:bool, provider_message_id:?string, error:?string}
     */
    public static function send(string $toPhoneE164, string $body, ?string $templateName = null): array
    {
        if (!self::isConfigured()) {
            return [
                'success'             => false,
                'provider_message_id' => null,
                'error'               => 'WhatsApp gateway not configured. Set WHATSAPP_PROVIDER + WHATSAPP_TOKEN in .env.',
            ];
        }

        $to = self::normalisePhone($toPhoneE164);
        if ($to === null) {
            return [
                'success'             => false,
                'provider_message_id' => null,
                'error'               => 'Invalid destination phone number.',
            ];
        }

        $provider = strtolower(trim((string)getenv('WHATSAPP_PROVIDER')));
        return match ($provider) {
            'meta_cloud' => self::sendViaMetaCloud($to, $body, $templateName),
            'twilio'     => self::sendViaTwilio($to, $body),
            default      => ['success' => false, 'provider_message_id' => null, 'error' => 'Unknown WHATSAPP_PROVIDER: ' . $provider],
        };
    }

    private static function sendViaMetaCloud(string $to, string $body, ?string $templateName): array
    {
        $phoneId = trim((string)getenv('WHATSAPP_PHONE_ID'));
        $token   = trim((string)getenv('WHATSAPP_TOKEN'));
        if ($phoneId === '') {
            return ['success' => false, 'provider_message_id' => null, 'error' => 'WHATSAPP_PHONE_ID is not set.'];
        }
        $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($phoneId) . '/messages';

        if ($templateName) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => ltrim($to, '+'),
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => 'en'],
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => ltrim($to, '+'),
                'type'              => 'text',
                'text'              => ['body' => $body],
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECS,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $respBody = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            return ['success' => false, 'provider_message_id' => null, 'error' => 'Meta request failed: ' . ($err ?: 'network error')];
        }
        $data = json_decode($respBody, true);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? (string)$data['error']['message']
                : ('HTTP ' . $status);
            return ['success' => false, 'provider_message_id' => null, 'error' => 'Meta returned: ' . $msg];
        }
        $mid = is_array($data) ? ($data['messages'][0]['id'] ?? null) : null;
        return ['success' => true, 'provider_message_id' => $mid, 'error' => null];
    }

    private static function sendViaTwilio(string $to, string $body): array
    {
        $sid   = trim((string)getenv('WHATSAPP_ACCOUNT_SID'));
        $token = trim((string)getenv('WHATSAPP_TOKEN'));
        $from  = trim((string)getenv('WHATSAPP_FROM'));
        if ($sid === '' || $from === '') {
            return ['success' => false, 'provider_message_id' => null, 'error' => 'Twilio WHATSAPP_ACCOUNT_SID / WHATSAPP_FROM missing.'];
        }
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $post = [
            'From' => 'whatsapp:' . $from,
            'To'   => 'whatsapp:' . $to,
            'Body' => $body,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECS,
            CURLOPT_USERPWD        => $sid . ':' . $token,
        ]);
        $respBody = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            return ['success' => false, 'provider_message_id' => null, 'error' => 'Twilio request failed: ' . ($err ?: 'network error')];
        }
        $data = json_decode($respBody, true);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($data) && isset($data['message']) ? (string)$data['message'] : ('HTTP ' . $status);
            return ['success' => false, 'provider_message_id' => null, 'error' => 'Twilio returned: ' . $msg];
        }
        $mid = is_array($data) ? ($data['sid'] ?? null) : null;
        return ['success' => true, 'provider_message_id' => $mid, 'error' => null];
    }

    private static function normalisePhone(string $raw): ?string
    {
        $raw = preg_replace('/[\s\-()]/', '', $raw);
        if ($raw === null || $raw === '') return null;
        if ($raw[0] !== '+') $raw = '+' . $raw;
        return preg_match('/^\+[1-9]\d{6,14}$/', $raw) ? $raw : null;
    }
}
