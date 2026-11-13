<?php

namespace app\common\server {
    // Observe real verification calls and advance only the cache clock; production has no test hooks.
    function password_verify(string $password, string $hash): bool
    {
        $GLOBALS['verify_calls']++;
        return \password_verify($password, $hash);
    }

    function hrtime(bool $asNumber = false): array|int|float
    {
        return $asNumber ? \hrtime(true) + $GLOBALS['clock_offset_ns'] : \hrtime();
    }
}

namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';

    use app\common\model\ClientAccount;
    use app\common\server\ClientAccountAdminServer;
    use app\common\server\ClientConfigServer;
    use Illuminate\Database\Capsule\Manager;
    use Illuminate\Database\Schema\Blueprint;

    // No bootstrap/.env: all database operations use this process's in-memory SQLite only.
    class_exists(\support\Model::class);
    $capsule = new Manager();
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $capsule->getConnection()->getSchemaBuilder()->create('cc_client_account', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('username')->unique();
        $table->string('password_hash');
        $table->boolean('enabled')->default(true);
        $table->dateTime('last_used_at')->nullable();
        $table->timestamps();
    });
    $GLOBALS['verify_calls'] = 0;
    $GLOBALS['clock_offset_ns'] = 0;
    $checks = 0;
    function check(bool $ok, string $message): void {
        global $checks;
        if (!$ok) throw new \RuntimeException($message);
        ++$checks;
    }
    function denied(string $username, string $password): void {
        try {
            (new ClientConfigServer())->authenticate($username, $password);
        } catch (\InvalidArgumentException $e) {
            check(true, 'Rejected');
            return;
        }
        throw new \RuntimeException('Invalid credentials accepted');
    }
    function account(string $username, string $hash): ClientAccount {
        return ClientAccount::query()->create([
            'name' => $username, 'username' => $username, 'password_hash' => $hash, 'enabled' => true,
        ]);
    }
    $password = 'client-test-password';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    $user = account('client-a', $hash);
    $other = account('client-b', password_hash('another-password', PASSWORD_BCRYPT, ['cost' => 4]));
    $admin = new ClientAccountAdminServer();
    denied('', $password);
    denied('client-a', '');
    denied('missing', $password);
    check($GLOBALS['verify_calls'] === 0, 'Missing accounts do not verify passwords');
    $first = (new ClientConfigServer())->authenticate(' client-a ', $password);
    for ($i = 0; $i < 10; $i++) (new ClientConfigServer())->authenticate('client-a', $password);
    check($GLOBALS['verify_calls'] === 1, 'Repeated requests across new service instances reuse successful verification');
    check($user->refresh()->last_used_at !== null, 'Authentication records last use');
    $user->forceFill(['name' => 'updated-name', 'last_used_at' => '2000-01-01 00:00:00'])->save();
    $fresh = (new ClientConfigServer())->authenticate('client-a', $password);
    check($fresh !== $first && $fresh->name === 'updated-name', 'Cache returns freshly read model');
    check($user->refresh()->last_used_at !== '2000-01-01 00:00:00', 'Cache hit still saves last use');
    $calls = $GLOBALS['verify_calls'];
    denied('client-a', 'wrong-password');
    denied('client-a', 'wrong-password');
    check($GLOBALS['verify_calls'] === $calls + 2, 'Wrong passwords are never cached');
    (new ClientConfigServer())->authenticate('client-a', $password);
    check($GLOBALS['verify_calls'] === $calls + 2, 'Wrong attempts do not evict valid credentials');
    denied('client-b', $password);
    $admin->disable($user->id);
    $calls = $GLOBALS['verify_calls'];
    denied('client-a', $password);
    check($GLOBALS['verify_calls'] === $calls, 'Disabled account rejected before cache');
    $admin->update($user->id, 'updated-name', 'client-a', '', true);
    (new ClientConfigServer())->authenticate('client-a', $password);
    $admin->update($user->id, 'updated-name', 'client-a', 'new-password', true);
    denied('client-a', $password);
    (new ClientConfigServer())->authenticate('client-a', 'new-password');
    $calls = $GLOBALS['verify_calls'];
    (new ClientConfigServer())->authenticate('client-a', 'new-password');
    check($GLOBALS['verify_calls'] === $calls, 'New credentials cached after rotation');
    // A new salt/hash for the same password must also force verification.
    $user->refresh()->forceFill(['password_hash' => password_hash('new-password', PASSWORD_BCRYPT, ['cost' => 4])])->save();
    (new ClientConfigServer())->authenticate('client-a', 'new-password');
    check($GLOBALS['verify_calls'] === $calls + 1, 'Same-password rehash invalidates cache');
    $admin->update($user->id, 'updated-name', 'renamed-client', '', true);
    denied('client-a', 'new-password');
    (new ClientConfigServer())->authenticate('renamed-client', 'new-password');
    $user->delete();
    denied('renamed-client', 'new-password');

    $expiry = account('expiry', $hash);
    (new ClientConfigServer())->authenticate('expiry', $password);
    $calls = $GLOBALS['verify_calls'];
    $GLOBALS['clock_offset_ns'] += 59 * 1_000_000_000;
    (new ClientConfigServer())->authenticate('expiry', $password);
    check($GLOBALS['verify_calls'] === $calls, 'Hit within TTL');
    $GLOBALS['clock_offset_ns'] += 2 * 1_000_000_000;
    (new ClientConfigServer())->authenticate('expiry', $password);
    check($GLOBALS['verify_calls'] === $calls + 1, 'Hit does not extend fixed expiry');

    for ($i = 0; $i < 1025; $i++) {
        account('capacity-' . $i, $hash);
        (new ClientConfigServer())->authenticate('capacity-' . $i, $password);
    }
    $calls = $GLOBALS['verify_calls'];
    (new ClientConfigServer())->authenticate('capacity-1024', $password);
    check($GLOBALS['verify_calls'] === $calls, 'Newest successful entry retained');
    (new ClientConfigServer())->authenticate('capacity-0', $password);
    check($GLOBALS['verify_calls'] === $calls + 1, 'Oldest entry evicted at capacity');

    // Real verification is used on both sides. Same ORM query/save as original authenticate().
    account('benchmark', password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
    $baseline = function () use ($password) {
        $account = ClientAccount::query()->where('username', 'benchmark')->where('enabled', true)->first();
        if (!$account || !password_verify($password, $account->password_hash)) throw new \RuntimeException('Benchmark failed');
        $account->forceFill(['last_used_at' => date('Y-m-d H:i:s')])->save();
    };
    $optimized = fn () => (new ClientConfigServer())->authenticate('benchmark', $password);
    $optimized(); // First request remains a real password_verify.
    $measure = function (callable $call): array {
        $cpuStart = getrusage();
        $start = hrtime(true);
        for ($i = 0; $i < 40; $i++) $call();
        $wall = (hrtime(true) - $start) / 1_000_000;
        $cpuEnd = getrusage();
        $cpu = 0;
        foreach (['utime', 'stime'] as $kind) {
            $cpu += ($cpuEnd['ru_' . $kind . '.tv_sec'] - $cpuStart['ru_' . $kind . '.tv_sec']) * 1000
                + ($cpuEnd['ru_' . $kind . '.tv_usec'] - $cpuStart['ru_' . $kind . '.tv_usec']) / 1000;
        }
        return ['wall_ms' => round($wall, 2), 'cpu_ms' => round($cpu, 2)];
    };
    $baselineResult = $measure($baseline);
    $calls = $GLOBALS['verify_calls'];
    $optimizedResult = $measure($optimized);
    check($GLOBALS['verify_calls'] === $calls, 'Warm benchmark avoids password_verify');
    echo "PASS: $checks checks\n";
    echo json_encode(['iterations' => 40, 'hash' => 'bcrypt cost=10', 'baseline' => $baselineResult,
        'warm_cache' => $optimizedResult], JSON_PRETTY_PRINT) . "\n";
}
