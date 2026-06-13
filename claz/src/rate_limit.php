<?php
require_once __DIR__ . '/config.php';

function rate_limit_check(string $key, int $limit, int $windowSeconds = 60): bool {
    $pdo = db();
    $now = time();
    $windowStart = $now - ($now % $windowSeconds); // floor to window
    // Attempt to find existing window row
    $sel = $pdo->prepare('SELECT id, count FROM rate_limits WHERE rl_key=? AND window_start=? LIMIT 1');
    $sel->execute([$key, $windowStart]);
    $row = $sel->fetch();
    if ($row) {
        if ($row['count'] >= $limit) {
            return false; // rate limit exceeded
        }
        $upd = $pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE id=?');
        $upd->execute([$row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO rate_limits (rl_key, window_start, count) VALUES (?,?,1)');
        $ins->execute([$key, $windowStart]);
    }
    // Opportunistic cleanup of very old windows (older than 1 day)
    if (mt_rand(0, 100) === 0) {
        $oldCut = $now - 86400;
        $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$oldCut]);
    }
    return true;
}

function rate_limit_assert(string $key, int $limit, int $windowSeconds = 60): void {
    if (!rate_limit_check($key, $limit, $windowSeconds)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'rate_limited', 'retry_after' => $windowSeconds]);
        exit;
    }
}

// Hybrid user+IP limiting: both must pass. Distinct limits allow tighter IP throttle.
function rate_limit_user_ip_assert(int $userId, string $ip, int $userLimit, int $ipLimit, int $windowSeconds = 60): void {
    $ipKey = 'ip:'.$ip;
    $userKey = 'user:'.$userId;
    if (!rate_limit_check($ipKey, $ipLimit, $windowSeconds) || !rate_limit_check($userKey, $userLimit, $windowSeconds)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'rate_limited','retry_after'=>$windowSeconds]);
        exit;
    }
}
