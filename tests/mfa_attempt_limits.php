<?php

// Only tests/run_mfa_limits.py supplies this isolated directory. Never load project .env.
require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\middleware\AdminMfaThrottleMiddleware;
use app\common\library\Totp;
use app\common\model\AdminMfaChallenge;
use app\common\model\AdminSession;
use app\common\model\AdminUser;
use app\common\server\AdminAuthServer;
use support\Redis;
use support\Request;
use Webman\Config;

$root = $argv[1] ?? '';
$port = (int) ($argv[2] ?? 0);
if (!is_file($root . '/isolated-mfa-test') || !is_file($root . '/mysql/auto.cnf') || !$port) {
    throw new RuntimeException('Run via python3 tests/run_mfa_limits.py');
}
$worker = $argv[3] ?? '';
if ($worker === '') {
    $pdo = new PDO('mysql:unix_socket=' . $root . '/mysql.sock', 'root', '');
    $pdo->exec('CREATE DATABASE cc_mfa_test');
    $pdo->exec('USE cc_mfa_test');
    $adminSql = explode(';', file_get_contents(dirname(__DIR__) . '/sql/001_config_center.sql'))[0];
    $pdo->exec($adminSql);
    foreach (['002_admin_session.sql', '006_admin_mfa_attempt_limits.sql'] as $file) {
        foreach (explode(';', file_get_contents(dirname(__DIR__) . '/sql/' . $file)) as $statement) {
            if (trim($statement) !== '') $pdo->exec($statement);
        }
    }
    mkdir($root . '/config');
    $config = [
        'app' => ['enable' => true],
        'database' => ['default' => 'mysql', 'connections' => ['mysql' => [
            'driver' => 'mysql', 'unix_socket' => $root . '/mysql.sock',
            'database' => 'cc_mfa_test', 'username' => 'root', 'password' => '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        ]]],
        'redis' => ['default' => ['host' => '127.0.0.1', 'port' => $port, 'database' => 0, 'timeout' => 1]],
        'log' => ['default' => ['handlers' => [[
            'class' => Monolog\Handler\StreamHandler::class,
            'constructor' => [$root . '/audit.log', Monolog\Logger::DEBUG],
        ]]]],
    ];
    foreach ($config as $name => $value) {
        file_put_contents($root . '/config/' . $name . '.php', '<?php return ' . var_export($value, true) . ';');
    }
    unset($pdo);
}
Config::load($root . '/config');
// Composer can load support\Model before Config::load; bind an isolated ORM resolver explicitly.
$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection(config('database.connections.mysql'));
$capsule->setAsGlobal();
$capsule->bootEloquent();
$auth = new AdminAuthServer();
function ipRequest(string $ip): Request {
    return new Request("POST /cc-admin/api/admin/v1/auth/mfa/verify HTTP/1.1\r\nHost: test\r\nali-cdn-real-ip: $ip\r\nContent-Length: 0\r\n\r\n");
}
if ($worker === 'verify') {
    try {
        $auth->verifyMfa($argv[4], $argv[5], '192.0.2.1');
        echo 'success';
    } catch (InvalidArgumentException $e) {
        echo 'denied';
    }
    exit;
}
if ($worker === 'ip') {
    echo (new AdminMfaThrottleMiddleware())->process(ipRequest('192.0.2.88'), fn () => json(['ok' => true]))->getStatusCode();
    exit;
}
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    ++$checks;
}
function denied(callable $call, string $message): void {
    try { $call(); } catch (InvalidArgumentException $e) {
        check(str_contains($e->getMessage(), $message), 'Unexpected error: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
}
function account(): AdminUser {
    return AdminUser::query()->create([
        'username' => bin2hex(random_bytes(8)), 'password_hash' => password_hash('password-test', PASSWORD_DEFAULT),
        'mfa_enabled' => 1, 'mfa_secret' => Totp::secret(),
    ]);
}
function challenge(AdminUser $user): string {
    global $auth;
    return $auth->login($user->username, 'password-test')['challengeToken'];
}
function otp(AdminUser $user): string {
    $method = new ReflectionMethod(Totp::class, 'code');
    $method->setAccessible(true);
    return $method->invoke(null, $user->mfa_secret, intdiv(time(), 30));
}
function parallel(array $calls): array {
    global $root, $port;
    $processes = [];
    foreach ($calls as $args) {
        $pipes = [];
        $process = proc_open(array_merge([PHP_BINARY, __FILE__, $root, (string) $port], $args),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $results[] = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0, 'Worker failed: ' . $error);
    }
    return $results;
}

$user = account();
$token = challenge($user);
for ($i = 1; $i <= 5; $i++) {
    denied(fn () => $auth->verifyMfa($token, 'bad'), $i === 5 ? '已达上限' : '验证码错误');
    check((int) $user->refresh()->mfa_failed_attempts === $i, 'Failure must commit despite exception');
}
check(!AdminMfaChallenge::query()->where('admin_user_id', $user->id)->exists(), 'Challenge invalidated at five');
denied(fn () => $auth->verifyMfa($token, otp($user)), '已过期');
$token = challenge($user);
check((int) $user->refresh()->mfa_failed_attempts === 5, 'Replacement must retain account failures');
for ($i = 6; $i <= 10; $i++) {
    denied(fn () => $auth->verifyMfa($token, 'bad'), $i === 10 ? '10 分钟' : '验证码错误');
}
check($user->refresh()->mfa_locked_until > date('Y-m-d H:i:s'), 'Account must be locked');
denied(fn () => challenge($user), '尝试过多');
// Recovery without waiting: move the isolated account timestamps to the past.
$user->forceFill(['mfa_failure_window_at' => date('Y-m-d H:i:s', time() - 1200),
    'mfa_locked_until' => date('Y-m-d H:i:s', time() - 1)])->save();
$token = challenge($user);
denied(fn () => $auth->verifyMfa($token, 'bad'), '验证码错误');
check((int) $user->refresh()->mfa_failed_attempts === 1, 'Expired account window resets');
$auth->verifyMfa($token, otp($user));
check((int) $user->refresh()->mfa_failed_attempts === 0, 'Successful MFA resets failures');
denied(fn () => $auth->verifyMfa($token, otp($user)), '已过期');
check(AdminSession::query()->where('admin_user_id', $user->id)->count() === 1, 'One session only');

$user = account();
$token = challenge($user);
$row = AdminMfaChallenge::query()->where('admin_user_id', $user->id)->first();
$row->forceFill(['expires_at' => date('Y-m-d H:i:s', time() - 1)])->save();
denied(fn () => $auth->verifyMfa($token, otp($user)), '已过期');
$token = challenge($user);
$row = AdminMfaChallenge::query()->where('admin_user_id', $user->id)->first();
$row->forceFill(['setup_required' => 1])->save();
denied(fn () => $auth->verifyMfa($token, otp($user)), '个人中心');
check((int) $user->refresh()->mfa_failed_attempts === 0, 'Expired/setup challenges do not charge failures');

$user = account();
$token = challenge($user);
$failSession = new class extends AdminAuthServer {
    public function createSession(AdminUser $user): array { throw new RuntimeException('session failed'); }
};
try { $failSession->verifyMfa($token, otp($user)); } catch (RuntimeException $e) {
    check($e->getMessage() === 'session failed', 'Expected injected failure');
}
check(AdminMfaChallenge::query()->where('admin_user_id', $user->id)->count() === 1, 'Session failure rolls back challenge consumption');

$user = account();
$token = challenge($user);
$results = parallel(array_fill(0, 12, ['verify', $token, 'bad']));
check(count(array_filter($results, fn ($r) => $r === 'denied')) === 12, 'All wrong concurrent attempts denied');
check((int) $user->refresh()->mfa_failed_attempts === 5, 'Concurrent challenge budget exactly five');
check(AdminSession::query()->where('admin_user_id', $user->id)->count() === 0, 'Wrong attempts create no session');

$user = account();
$tokens = [];
for ($i = 0; $i < 3; $i++) {
    $token = bin2hex(random_bytes(32));
    AdminMfaChallenge::query()->create(['admin_user_id' => $user->id, 'token_hash' => hash('sha256', $token),
        'setup_required' => 0, 'expires_at' => date('Y-m-d H:i:s', time() + 600)]);
    $tokens[] = $token;
}
$calls = [];
for ($i = 0; $i < 15; $i++) $calls[] = ['verify', $tokens[$i % 3], 'bad'];
parallel($calls);
check((int) $user->refresh()->mfa_failed_attempts === 10, 'Concurrent challenges share account budget');
check(AdminMfaChallenge::query()->where('admin_user_id', $user->id)->count() === 0, 'Account lock removes all challenges');

$user = account();
$token = challenge($user);
$results = parallel(array_fill(0, 8, ['verify', $token, otp($user)]));
check(count(array_filter($results, fn ($r) => $r === 'success')) === 1, 'Only one concurrent verification succeeds');
check(AdminSession::query()->where('admin_user_id', $user->id)->count() === 1, 'Challenge creates at most one session');

$middleware = new AdminMfaThrottleMiddleware();
$next = fn () => json(['ok' => true]);
for ($i = 0; $i < 30; $i++) check($middleware->process(ipRequest('192.0.2.1'), $next)->getStatusCode() === 200, 'First thirty admitted');
$response = $middleware->process(ipRequest('192.0.2.1'), $next);
check($response->getStatusCode() === 429, 'Thirty-first rejected');
check(str_contains((string) $response, 'Retry-After:'), 'Throttle reports retry delay');
check($middleware->process(ipRequest('192.0.2.2'), $next)->getStatusCode() === 200, 'IPs isolated');
$key = 'config-center:admin-mfa:ip:' . hash('sha256', '192.0.2.88');
Redis::setex($key, 60, 29);
$results = parallel(array_fill(0, 10, ['ip']));
check(count(array_filter($results, fn ($r) => $r === '200')) === 1, 'Atomic IP admission under concurrency');
check((int) Redis::ttl($key) > 0, 'Throttle expires');
Redis::expire($key, 0);
check($middleware->process(ipRequest('192.0.2.88'), $next)->getStatusCode() === 200, 'IP window recovers');
$logs = file_get_contents($root . '/audit.log');
check(str_contains($logs, 'account_locked') && str_contains($logs, 'challenge_invalidated'), 'Thresholds audited');
check(!str_contains($logs, $token) && !str_contains($logs, $user->mfa_secret), 'Audit excludes token and secret');

// Stop only this disposable Redis; verify fail-closed behavior.
$native = new \Redis();
$native->connect('127.0.0.1', $port);
try {
    $native->rawCommand('SHUTDOWN', 'NOSAVE');
} catch (RedisException $e) {
    // Redis may close the connection before returning a shutdown response.
}
check($middleware->process(ipRequest('192.0.2.99'), $next)->getStatusCode() === 503, 'Redis failure must not bypass throttle');
echo "PASS: $checks checks (disposable MySQL + Redis, including concurrent workers)\n";
