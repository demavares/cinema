<?php
// ============================================
// HELPERS - SEGURIDAD (CSRF, TOKENS, SESIÓN, RATE LIMITING)
// ============================================

// ============================================
// CSRF
// ============================================
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }

    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_created'] = time();

    return true;
}

function isCSRFTokenExpired()
{
    if (!isset($_SESSION['csrf_token_created'])) {
        return true;
    }

    $maxAge = 3600;

    return (time() - $_SESSION['csrf_token_created']) > $maxAge;
}

// ============================================
// TOKENS DE COMPRA
// ============================================
function generatePurchaseToken()
{
    return bin2hex(random_bytes(32));
}

function generatePurchaseTokenWithTimeout($showtimeId, $timeout = 900)
{
    $token = generatePurchaseToken();

    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + $timeout;

    return $token;
}

function verifyPurchaseToken($token, $showtimeId)
{
    if (empty($token) || empty($showtimeId)) {
        return false;
    }

    $expectedToken = $_SESSION['purchase_token_' . $showtimeId] ?? null;

    if (!$expectedToken || !hash_equals($expectedToken, $token)) {
        return false;
    }

    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

    if (time() > $expiresAt) {
        unset($_SESSION['purchase_token_' . $showtimeId]);
        unset($_SESSION['purchase_expires_at_' . $showtimeId]);
        return false;
    }

    return true;
}

function isPurchaseTokenExpired($showtimeId)
{
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

    if ($expiresAt === 0) {
        return true;
    }

    return time() > $expiresAt;
}

function getPurchaseTokenTimeLeft($showtimeId)
{
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

    if ($expiresAt === 0) {
        return 0;
    }

    return max(0, $expiresAt - time());
}

function markPurchaseTokenAsUsed($showtimeId)
{
    $_SESSION['purchase_token_used_' . $showtimeId] = true;
}

function clearPurchaseSession($showtimeId)
{
    $keys = [
        'purchase_token_' . $showtimeId,
        'purchase_expires_at_' . $showtimeId,
        'purchase_token_used_' . $showtimeId,
        'ticket_quantities_' . $showtimeId,
        'total_seats_' . $showtimeId,
        'subtotal_' . $showtimeId,
        'tax_amount_' . $showtimeId,
        'total_amount_' . $showtimeId,
        'tax_rate_' . $showtimeId,
        'food_timeout_' . $showtimeId,
        'food_seats_' . $showtimeId,
        'food_valid_' . $showtimeId,
        'food_order_' . $showtimeId,
        'base_subtotal_' . $showtimeId
    ];

    foreach ($keys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}

function generateTransactionId()
{
    return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
}

// ============================================
// SESIÓN EXPIRADA
// ============================================
function checkSessionExpired($showtimeId = null)
{
    $limite_inactividad = 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $limite_inactividad)) {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }

        session_destroy();

        header("Location: index.php?expired=1");
        exit();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    if ($showtimeId !== null && $showtimeId > 0) {
        $sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
        $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

        if (empty($sessionToken) || time() > $expiresAt) {
            clearPurchaseSession($showtimeId);
            header('Location: index.php?expired=1');
            exit;
        }
    }

    $_SESSION['last_activity'] = time();
}

// ============================================
// RATE LIMITING PERSISTENTE
// ============================================
function getLoginIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function getLoginIpKey(string $ip): string
{
    return 'ip:' . $ip;
}

function getLoginAccountKey(string $email): string
{
    $normalized = strtolower(trim($email));
    return 'account:' . hash('sha256', $normalized);
}

function getRateLimitRow(PDO $pdo, string $key): ?array
{
    $stmt = $pdo->prepare('
        SELECT *
        FROM login_rate_limits
        WHERE rate_limit_key = ?
        LIMIT 1
    ');

    $stmt->execute([$key]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function checkRateLimitKey(
    PDO $pdo,
    string $key,
    int $maxAttempts,
    int $windowMinutes,
    int $blockMinutes
): array {
    $now = time();
    $row = getRateLimitRow($pdo, $key);

    if (!$row) {
        return [
            'limited' => false,
            'retry_after' => 0
        ];
    }

    if ($row['blocked_until'] !== null) {
        if ((int)$row['blocked_until'] > $now) {
            return [
                'limited' => true,
                'retry_after' => (int)$row['blocked_until'] - $now
            ];
        }

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 0,
                first_attempt_at = NULL,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $key]);

        return [
            'limited' => false,
            'retry_after' => 0
        ];
    }

    $windowStart = $now - ($windowMinutes * 60);

    if ((int)$row['last_attempt_at'] < $windowStart) {
        return [
            'limited' => false,
            'retry_after' => 0
        ];
    }

    if ((int)$row['attempts'] >= $maxAttempts) {
        $blockedUntil = $now + ($blockMinutes * 60);

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET blocked_until = ?
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$blockedUntil, $key]);

        return [
            'limited' => true,
            'retry_after' => $blockMinutes * 60
        ];
    }

    return [
        'limited' => false,
        'retry_after' => 0
    ];
}

function recordRateLimitFailureForKey(
    PDO $pdo,
    string $key,
    int $maxAttempts,
    int $windowMinutes,
    int $blockMinutes
): void {
    $now = time();
    $row = getRateLimitRow($pdo, $key);

    if (!$row) {
        $stmt = $pdo->prepare('
            INSERT INTO login_rate_limits
                (rate_limit_key, attempts, first_attempt_at, last_attempt_at, blocked_until)
            VALUES
                (?, 1, ?, ?, NULL)
        ');

        $stmt->execute([$key, $now, $now]);

        return;
    }

    if ($row['blocked_until'] !== null) {
        if ((int)$row['blocked_until'] > $now) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 1,
                first_attempt_at = ?,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $now, $key]);

        return;
    }

    $windowStart = $now - ($windowMinutes * 60);

    if ((int)$row['last_attempt_at'] < $windowStart) {
        $attempts = 1;
        $firstAttemptAt = $now;
    } else {
        $attempts = (int)$row['attempts'] + 1;
        $firstAttemptAt = (int)$row['first_attempt_at'] ?: $now;
    }

    $blockedUntil = null;

    if ($attempts >= $maxAttempts) {
        $blockedUntil = $now + ($blockMinutes * 60);
    }

    $stmt = $pdo->prepare('
        UPDATE login_rate_limits
        SET
            attempts = ?,
            first_attempt_at = ?,
            last_attempt_at = ?,
            blocked_until = ?
        WHERE rate_limit_key = ?
    ');

    $stmt->execute([
        $attempts,
        $firstAttemptAt,
        $now,
        $blockedUntil,
        $key
    ]);
}

function consumeRateLimitKey(
    PDO $pdo,
    string $key,
    int $maxAttempts,
    int $windowMinutes,
    int $blockMinutes = 0
): bool {
    $now = time();
    $row = getRateLimitRow($pdo, $key);

    if (!$row) {
        $stmt = $pdo->prepare('
            INSERT INTO login_rate_limits
                (rate_limit_key, attempts, first_attempt_at, last_attempt_at, blocked_until)
            VALUES
                (?, 1, ?, ?, NULL)
        ');

        $stmt->execute([$key, $now, $now]);

        return true;
    }

    if ($row['blocked_until'] !== null) {
        if ((int)$row['blocked_until'] > $now) {
            return false;
        }

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 1,
                first_attempt_at = ?,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $now, $key]);

        return true;
    }

    $windowStart = $now - ($windowMinutes * 60);

    if ((int)$row['last_attempt_at'] < $windowStart) {
        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 1,
                first_attempt_at = ?,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $now, $key]);

        return true;
    }

    if ((int)$row['attempts'] >= $maxAttempts) {
        if ($blockMinutes > 0) {
            $blockedUntil = $now + ($blockMinutes * 60);

            $stmt = $pdo->prepare('
                UPDATE login_rate_limits
                SET blocked_until = ?
                WHERE rate_limit_key = ?
            ');

            $stmt->execute([$blockedUntil, $key]);
        }

        return false;
    }

    $attempts = (int)$row['attempts'] + 1;
    $blockedUntil = null;

    if ($blockMinutes > 0 && $attempts >= $maxAttempts) {
        $blockedUntil = $now + ($blockMinutes * 60);
    }

    $stmt = $pdo->prepare('
        UPDATE login_rate_limits
        SET
            attempts = ?,
            last_attempt_at = ?,
            blocked_until = ?
        WHERE rate_limit_key = ?
    ');

    $stmt->execute([$attempts, $now, $blockedUntil, $key]);

    return true;
}

function resetRateLimitKey(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('
        DELETE FROM login_rate_limits
        WHERE rate_limit_key = ?
    ');

    $stmt->execute([$key]);
}

function checkLoginRateLimit(PDO $pdo, string $ip, string $email): array
{
    $ipCheck = checkRateLimitKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );

    if ($ipCheck['limited']) {
        return $ipCheck;
    }

    return checkRateLimitKey(
        $pdo,
        getLoginAccountKey($email),
        LOGIN_ACCOUNT_MAX_ATTEMPTS,
        LOGIN_ACCOUNT_WINDOW_MINUTES,
        LOGIN_ACCOUNT_BLOCK_MINUTES
    );
}

function getLoginRateLimitStatus(PDO $pdo, string $ip, string $email): array
{
    $ipCheck = checkRateLimitKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );

    if ($ipCheck['limited']) {
        return [
            'limited' => true,
            'retry_after' => $ipCheck['retry_after'],
            'attempts' => null,
            'attempts_left' => 0,
            'warning' => false,
            'reason' => 'ip'
        ];
    }

    $accountKey = getLoginAccountKey($email);

    $accountCheck = checkRateLimitKey(
        $pdo,
        $accountKey,
        LOGIN_ACCOUNT_MAX_ATTEMPTS,
        LOGIN_ACCOUNT_WINDOW_MINUTES,
        LOGIN_ACCOUNT_BLOCK_MINUTES
    );

    if ($accountCheck['limited']) {
        return [
            'limited' => true,
            'retry_after' => $accountCheck['retry_after'],
            'attempts' => LOGIN_ACCOUNT_MAX_ATTEMPTS,
            'attempts_left' => 0,
            'warning' => false,
            'reason' => 'account'
        ];
    }

    $row = getRateLimitRow($pdo, $accountKey);
    $attempts = 0;

    if ($row) {
        $windowStart = time() - (LOGIN_ACCOUNT_WINDOW_MINUTES * 60);

        if ((int)$row['last_attempt_at'] >= $windowStart) {
            $attempts = (int)$row['attempts'];
        }
    }

    if ($attempts >= LOGIN_ACCOUNT_MAX_ATTEMPTS) {
        $blockedUntil = time() + (LOGIN_ACCOUNT_BLOCK_MINUTES * 60);

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET blocked_until = ?
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$blockedUntil, $accountKey]);

        return [
            'limited' => true,
            'retry_after' => LOGIN_ACCOUNT_BLOCK_MINUTES * 60,
            'attempts' => $attempts,
            'attempts_left' => 0,
            'warning' => false,
            'reason' => 'account'
        ];
    }

    $attemptsLeft = max(0, LOGIN_ACCOUNT_MAX_ATTEMPTS - $attempts);

    $warningThreshold = defined('LOGIN_WARNING_FAILED_ATTEMPTS')
        ? LOGIN_WARNING_FAILED_ATTEMPTS
        : max(1, LOGIN_ACCOUNT_MAX_ATTEMPTS - 1);

    $warning = ($attempts >= $warningThreshold && $attempts < LOGIN_ACCOUNT_MAX_ATTEMPTS);

    return [
        'limited' => false,
        'retry_after' => 0,
        'attempts' => $attempts,
        'attempts_left' => $attemptsLeft,
        'warning' => $warning,
        'reason' => null
    ];
}

function recordFailedLogin(PDO $pdo, string $ip, string $email): void
{
    recordRateLimitFailureForKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );

    recordRateLimitFailureForKey(
        $pdo,
        getLoginAccountKey($email),
        LOGIN_ACCOUNT_MAX_ATTEMPTS,
        LOGIN_ACCOUNT_WINDOW_MINUTES,
        LOGIN_ACCOUNT_BLOCK_MINUTES
    );
}

function resetLoginRateLimit(PDO $pdo, string $ip, string $email): void
{
    resetRateLimitKey(
        $pdo,
        getLoginAccountKey($email)
    );
}

function cleanupLoginRateLimits(PDO $pdo, int $olderThanSeconds = 86400): int
{
    $stmt = $pdo->prepare('
        DELETE FROM login_rate_limits
        WHERE last_attempt_at < ?
    ');

    $stmt->execute([time() - $olderThanSeconds]);

    return $stmt->rowCount();
}

function canGenerateToken($showtimeId)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $key = 'token_generations_' . $_SESSION['user_id'] . '_' . $showtimeId;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }

    if ($now - $_SESSION[$key]['window_start'] > 300) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }

    if ($_SESSION[$key]['count'] >= 10) {
        return false;
    }

    $_SESSION[$key]['count']++;

    return true;
}

function checkRateLimit($action, $maxAttempts = 10, $windowMinutes = 5)
{
    global $pdo;

    $key = 'action:' . $action . ':ip:' . getLoginIp();

    return consumeRateLimitKey(
        $pdo,
        $key,
        $maxAttempts,
        $windowMinutes,
        0
    );
}