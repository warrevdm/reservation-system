<?php

declare(strict_types=1);

final class BotProtection
{
    private const SESSION_KEY = 'booking_form_tokens';

    public static function issueFormToken(): string
    {
        $token = bin2hex(random_bytes(24));
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $now = time();
        foreach ($tokens as $existingToken => $issuedAt) {
            if (!is_int($issuedAt) || $issuedAt < $now - 7200) {
                unset($tokens[$existingToken]);
            }
        }

        $tokens[$token] = $now;
        if (count($tokens) > 10) {
            asort($tokens);
            $tokens = array_slice($tokens, -10, null, true);
        }

        $_SESSION[self::SESSION_KEY] = $tokens;
        return $token;
    }

    public static function verifyFormToken(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens) || !array_key_exists($token, $tokens)) {
            return false;
        }

        $issuedAt = (int) $tokens[$token];
        unset($tokens[$token]);
        $_SESSION[self::SESSION_KEY] = $tokens;

        $age = time() - $issuedAt;
        $minimumAge = max(1, (int) config('security.form_min_seconds', 3));
        $maximumAge = max($minimumAge + 1, (int) config('security.form_max_seconds', 7200));

        return $age >= $minimumAge && $age <= $maximumAge;
    }

    public static function recaptchaMode(): string
    {
        $mode = strtolower(trim((string) config('security.recaptcha.mode', 'v2')));
        return in_array($mode, ['v2', 'v3'], true) ? $mode : 'v2';
    }

    public static function recaptchaEnabled(): bool
    {
        return (bool) config('security.recaptcha.enabled', false)
            && trim((string) config('security.recaptcha.site_key', '')) !== ''
            && trim((string) config('security.recaptcha.secret_key', '')) !== '';
    }

    public static function verifyRecaptcha(?string $token): array
    {
        if (!self::recaptchaEnabled()) {
            return ['ok' => true, 'score' => null, 'reason' => 'disabled'];
        }

        if (!is_string($token) || trim($token) === '') {
            return ['ok' => false, 'score' => null, 'reason' => 'missing-token'];
        }

        $secret = (string) config('security.recaptcha.secret_key', '');
        $payload = http_build_query([
            'secret' => $secret,
            'response' => trim($token),
            'remoteip' => client_ip(),
        ]);

        $raw = self::post('https://www.google.com/recaptcha/api/siteverify', $payload);
        if ($raw === null) {
            return ['ok' => false, 'score' => null, 'reason' => 'verification-unavailable'];
        }

        $result = json_decode($raw, true);
        if (!is_array($result) || empty($result['success'])) {
            return [
                'ok' => false,
                'score' => $result['score'] ?? null,
                'reason' => 'verification-failed',
                'errors' => $result['error-codes'] ?? [],
            ];
        }

        $expectedHostname = trim((string) config('security.recaptcha.expected_hostname', ''));
        if ($expectedHostname !== '' && strcasecmp((string) ($result['hostname'] ?? ''), $expectedHostname) !== 0) {
            return ['ok' => false, 'score' => $result['score'] ?? null, 'reason' => 'wrong-hostname'];
        }

        if (self::recaptchaMode() === 'v3') {
            $expectedAction = (string) config('security.recaptcha.action', 'reservation');
            if (($result['action'] ?? '') !== $expectedAction) {
                return ['ok' => false, 'score' => $result['score'] ?? null, 'reason' => 'wrong-action'];
            }

            $score = (float) ($result['score'] ?? 0.0);
            $minimumScore = max(0.0, min(1.0, (float) config('security.recaptcha.min_score', 0.5)));
            if ($score < $minimumScore) {
                return ['ok' => false, 'score' => $score, 'reason' => 'low-score'];
            }

            return ['ok' => true, 'score' => $score, 'reason' => 'ok'];
        }

        return ['ok' => true, 'score' => null, 'reason' => 'ok'];
    }

    private static function post(string $url, string $payload): ?string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return null;
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            return is_string($response) && $status >= 200 && $status < 300 ? $response : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 7,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        return is_string($response) ? $response : null;
    }
}
