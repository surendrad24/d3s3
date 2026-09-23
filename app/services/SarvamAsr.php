<?php
/**
 * SarvamAsr – Sarvam AI speech-to-text service wrapper.
 *
 * Calls Sarvam's speech-to-text endpoint when `SARVAM_API_KEY` is set in .env.
 * When the key is absent the service returns a graceful "not configured" error
 * so callers can surface a friendly message instead of crashing. The endpoint
 * defaults to Sarvam's public STT batch endpoint but can be overridden via
 * `SARVAM_STT_URL`.
 *
 * Sarvam expects a multipart POST with:
 *   file             – the audio blob
 *   model            – e.g. "saarika:v2"          (SARVAM_STT_MODEL)
 *   language_code    – "unknown" or BCP-47 tag    (auto-detect by default)
 *
 * Response shape (per Sarvam docs, current as of 2026-01):
 *   { "transcript": "…", "language_code": "en-IN", "diarized_transcript": … }
 *
 * On failure returns:
 *   ['success' => false, 'transcript' => '', 'language' => null, 'error' => '…']
 */

final class SarvamAsr
{
    /** Sensible default; can be overridden with SARVAM_STT_URL in .env */
    private const DEFAULT_URL   = 'https://api.sarvam.ai/speech-to-text';
    private const DEFAULT_MODEL = 'saarika:v2';
    private const TIMEOUT_SECS  = 60;

    public static function isConfigured(): bool
    {
        return trim((string)getenv('SARVAM_API_KEY')) !== '';
    }

    /**
     * @param string      $absoluteFilePath  Path to the audio file on disk
     * @param string      $mimeType          e.g. audio/webm
     * @param string|null $language          BCP-47 code, or null for auto-detect
     * @return array{success:bool, transcript:string, language:?string, error:?string}
     */
    public static function transcribe(string $absoluteFilePath, string $mimeType, ?string $language = null): array
    {
        if (!self::isConfigured()) {
            return [
                'success'    => false,
                'transcript' => '',
                'language'   => null,
                'error'      => 'Sarvam ASR is not configured. Set SARVAM_API_KEY in .env to enable transcription.',
            ];
        }
        if (!is_file($absoluteFilePath)) {
            return [
                'success'    => false,
                'transcript' => '',
                'language'   => null,
                'error'      => 'Audio file not found on server.',
            ];
        }

        $url   = getenv('SARVAM_STT_URL')   ?: self::DEFAULT_URL;
        $model = getenv('SARVAM_STT_MODEL') ?: self::DEFAULT_MODEL;
        $key   = trim((string)getenv('SARVAM_API_KEY'));

        $post = [
            'file'          => new CURLFile($absoluteFilePath, $mimeType, basename($absoluteFilePath)),
            'model'         => $model,
            'language_code' => $language ?: 'unknown',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECS,
            CURLOPT_HTTPHEADER     => [
                'api-subscription-key: ' . $key,
                'Accept: application/json',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'success'    => false,
                'transcript' => '',
                'language'   => null,
                'error'      => 'Sarvam request failed: ' . ($err ?: 'network error'),
            ];
        }

        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? (string)$data['error']['message']
                : ('HTTP ' . $status);
            return [
                'success'    => false,
                'transcript' => '',
                'language'   => null,
                'error'      => 'Sarvam returned ' . $msg,
            ];
        }

        $transcript = '';
        if (is_array($data)) {
            $transcript = (string)($data['transcript'] ?? $data['text'] ?? '');
        }
        $lang = is_array($data) ? ($data['language_code'] ?? $data['language'] ?? null) : null;

        return [
            'success'    => $transcript !== '',
            'transcript' => $transcript,
            'language'   => $lang,
            'error'      => $transcript === '' ? 'Sarvam returned an empty transcript.' : null,
        ];
    }
}
