<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class DiagnosticFilter implements FilterInterface
{
    private static ?string $currentCorrelationId = null;
    private static ?float $startTime = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        // 🛡️ 1. FAIL-SAFE WRAPPER: Diagnostic execution MUST NEVER interrupt business flow
        try {
            self::$startTime = microtime(true);
            $appConfig = config(\Config\App::class);
            // Log entry heartbeat FIRST to verify filter execution and report exact flag state
            $diagState = isset($appConfig->diagnosticMode) ? var_export($appConfig->diagnosticMode, true) : 'NOT_SET';
            log_message('error', '[SIDAK-DIAG-ENTRY] Filter Invoked | diagnosticMode=' . $diagState . ' | Method: ' . strtoupper($request->getMethod()) . ' | URI: ' . (string)$request->getUri()->getPath());

            // 🛡️ 2. DIRECT DIAGNOSTIC ACTIVATION (No App.php dependency required)
            $diagnosticActive = true;
            if (!$diagnosticActive) {
                return;
            }

            // 🛡️ 3. HTTP METHOD SCOPE CHECK (POST Requests Only)
            if ($request->getMethod() !== 'POST') {
                return;
            }

            // 🛡️ 4. SAFE CORRELATION ID GENERATION
            try {
                self::$currentCorrelationId = bin2hex(random_bytes(4));
            } catch (\Throwable) {
                self::$currentCorrelationId = uniqid('diag_', false);
            }

            $microtime = sprintf('%.4f', microtime(true));
            $timestamp = date('Y-m-d H:i:s') . '.' . substr((string)explode('.', $microtime)[1], 0, 3);

            // 🛡️ 5. DYNAMIC COOKIE NAME RESOLUTION FROM CONFIG
            $sessionConfig  = config(\Config\Session::class);
            $securityConfig = config(\Config\Security::class);

            $sessionCookieName = $sessionConfig->cookieName ?? session_name();
            $csrfCookieName    = $securityConfig->cookieName ?? 'csrf_cookie_name';
            $csrfTokenName     = $securityConfig->tokenName  ?? 'csrf_test_name';

            // 🛡️ 6. SAFE SESSION ACCESS & DEEP SESSION INSPECTION
            $sessionId = 'NO_SESSION';
            $sessionStatus = session_status();
            $hasUser = false;
            try {
                $session = service('session');
                if ($session) {
                    $sessionId = $session->getId() ?: 'NO_SESSION';
                    $hasUser   = $session->has('user');
                }
            } catch (\Throwable) {}

            // 🛡️ 7. DUAL IPv4 / IPv6 MASKING FOR PRIVACY COMPLIANCE
            $rawIp = $request->getIPAddress();
            $maskedIp = $this->maskIpAddress($rawIp);

            // 🛡️ 8. SAFE COOKIE & CSRF INSPECTION WITH TOKEN HEAD COMPARISON
            $csrfToken       = $request->getPost($csrfTokenName);
            $csrfCookie      = $request->getCookie($csrfCookieName);
            $sessCookie      = $request->getCookie($sessionCookieName);
            $rawCookieHeader = $_SERVER['HTTP_COOKIE'] ?? null;

            $postHead   = ($csrfToken !== null && strlen((string)$csrfToken) > 0) ? substr((string)$csrfToken, 0, 8) : 'NULL';
            $cookieHead = ($csrfCookie !== null && strlen((string)$csrfCookie) > 0) ? substr((string)$csrfCookie, 0, 8) : 'NULL';
            $postLen    = $csrfToken !== null ? strlen((string)$csrfToken) : 0;
            $cookieLen  = $csrfCookie !== null ? strlen((string)$csrfCookie) : 0;
            $matchStatus = ($csrfToken !== null && $csrfCookie !== null) ? (hash_equals((string)$csrfCookie, (string)$csrfToken) ? 'YES (EXACT MATCH)' : 'NO (TOKEN MISMATCH)') : 'NO (TOKEN MISSING)';

            $sessCookieHead = ($sessCookie !== null && strlen((string)$sessCookie) > 0) ? substr((string)$sessCookie, 0, 12) . '...' : 'MISSING';

            // 🛡️ 9. PRIMARY FORENSIC LOG ENTRY (BEFORE - Self-Contained)
            $logMsg = sprintf(
                "[SIDAK-DIAG-BEFORE][%s][%s]\n" .
                "- Client IP / Method : %s | %s\n" .
                "- Target Endpoint    : %s\n" .
                "- Session State      : ID=%s | Status=%d (PHP_ACTIVE=2) | HasUser=%s | SESSION_COOKIE=%s\n" .
                "- Protocol State     : isSecure()=%s | \$_SERVER['HTTPS']=%s | X-Forwarded-Proto=%s\n" .
                "- Configured Cookies : %s=%s | %s=%s\n" .
                "- Raw Cookie Header  : %s\n" .
                "- POST Payload CSRF  : %s=%s\n" .
                "- CSRF Token Audit   : POST_LEN=%d | COOKIE_LEN=%d | POST_HEAD=%s | COOKIE_HEAD=%s | MATCH=%s\n",
                $timestamp,
                self::$currentCorrelationId,
                $maskedIp,
                $request->getMethod(),
                (string)$request->getUri()->getPath(),
                $sessionId,
                $sessionStatus,
                var_export($hasUser, true),
                $sessCookieHead,
                var_export($request->isSecure(), true),
                var_export($_SERVER['HTTPS'] ?? 'NULL', true),
                var_export($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NULL', true),
                $sessionCookieName,
                $sessCookie !== null ? 'PRESENT (Len: ' . strlen((string)$sessCookie) . ')' : 'MISSING',
                $csrfCookieName,
                $csrfCookie !== null ? 'PRESENT (Len: ' . strlen((string)$csrfCookie) . ')' : 'MISSING',
                $rawCookieHeader !== null ? 'PRESENT (Len: ' . strlen($rawCookieHeader) . ')' : 'MISSING',
                $csrfTokenName,
                $csrfToken !== null  ? 'PRESENT (Len: ' . strlen((string)$csrfToken) . ')'  : 'MISSING',
                $postLen,
                $cookieLen,
                $postHead,
                $cookieHead,
                $matchStatus
            );

            log_message('error', $logMsg);

        } catch (\Throwable $e) {
            log_message('critical', '[SIDAK-DIAG-ERROR] Diagnostic filter before exception: ' . $e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // 🛡️ 10. SECONDARY LOG & CORRELATION HEADER IN AFTER()
        try {
            $diagnosticActive = true;
            if (!$diagnosticActive) {
                return;
            }

            if (empty(self::$currentCorrelationId)) {
                return;
            }

            // Attach DevTools Header ONLY when Diagnostic Mode is Active
            $response->setHeader('X-Diagnostic-ID', self::$currentCorrelationId);

            $durationMs  = self::$startTime !== null ? sprintf('%.2f', (microtime(true) - self::$startTime) * 1000) : '0.00';
            $peakMemMb   = sprintf('%.2f MB', memory_get_peak_usage(true) / 1048576);
            $statusCode  = $response->getStatusCode();

            $logMsg = sprintf(
                "[SIDAK-DIAG-AFTER][%s]\n" .
                "- HTTP Response Status : %d\n" .
                "- Request Duration    : %s ms\n" .
                "- Peak Memory Usage   : %s\n",
                self::$currentCorrelationId,
                $statusCode,
                $durationMs,
                $peakMemMb
            );

            log_message('error', $logMsg);

        } catch (\Throwable $e) {
            log_message('critical', '[SIDAK-DIAG-ERROR] Diagnostic filter after exception: ' . $e->getMessage());
        }
    }

    /**
     * Anonymize IPv4 and IPv6 addresses for privacy compliance
     */
    private function maskIpAddress(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (string) preg_replace('/(\d+)\.(\d+)\.(\d+)\.(\d+)/', '$1.$2.xxx.xxx', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 2) {
                return $parts[0] . ':' . $parts[1] . ':xxxx:xxxx:xxxx:xxxx:xxxx:xxxx';
            }
        }

        return 'xxx.xxx.xxx.xxx';
    }
}
