<?php

namespace app\common\server;

use app\common\library\Totp;
use app\common\model\AdminMfaChallenge;
use app\common\model\AdminSession;
use app\common\model\AdminUser;
use InvalidArgumentException;

class AdminAuthServer
{
    public function login(string $username, string $password): array
    {
        $this->bootstrap($username, $password);
        $user = AdminUser::query()->where('username', $username)->first();
        if (!$user || !password_verify($password, $user->password_hash)) {
            throw new InvalidArgumentException('用户名或密码错误');
        }
        return $this->createMfaChallenge($user);
    }

    public function verifyMfa(string $challengeToken, string $code): array
    {
        $challenge = AdminMfaChallenge::query()
            ->where('token_hash', hash('sha256', $challengeToken))
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
        if (!$challenge) {
            throw new InvalidArgumentException('二次验证已过期，请重新登录');
        }

        $user = AdminUser::query()->find($challenge->admin_user_id);
        if (!$user) {
            throw new InvalidArgumentException('管理员账号不存在');
        }

        $setupRequired = (bool) $challenge->setup_required;
        $secret = $setupRequired ? (string) $challenge->mfa_secret : (string) $user->mfa_secret;
        if (!Totp::verify($secret, $code)) {
            throw new InvalidArgumentException('动态验证码错误');
        }

        if ($setupRequired) {
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_enabled' => 1,
                'mfa_enabled_at' => date('Y-m-d H:i:s'),
            ])->save();
        }

        AdminMfaChallenge::query()->where('admin_user_id', $user->id)->delete();
        return $this->createSession($user);
    }

    public function createSession(AdminUser $user): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = date('Y-m-d H:i:s', time() + 28800);
        AdminSession::query()->create([
            'admin_user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
        return ['token' => $token, 'username' => $user->username, 'expiresAt' => $expiresAt];
    }

    public function userByToken(string $token): AdminUser
    {
        $session = AdminSession::query()->where('token_hash', hash('sha256', $token))->where('expires_at', '>', date('Y-m-d H:i:s'))->first();
        if (!$session) throw new InvalidArgumentException('登录已过期，请重新登录');
        $user = AdminUser::query()->find($session->admin_user_id);
        if (!$user) throw new InvalidArgumentException('登录已失效，请重新登录');
        return $user;
    }

    public function logout(string $token): void
    {
        AdminSession::query()->where('token_hash', hash('sha256', $token))->delete();
    }

    private function bootstrap(string $username, string $password): void
    {
        if (AdminUser::query()->exists()) return;
        $bootstrapUser = (string) getenv('CONFIG_CENTER_BOOTSTRAP_USERNAME');
        $bootstrapPassword = (string) getenv('CONFIG_CENTER_BOOTSTRAP_PASSWORD');
        if ($bootstrapUser === '' || $bootstrapPassword === '' || !hash_equals($bootstrapUser, $username) || !hash_equals($bootstrapPassword, $password)) return;
        AdminUser::query()->create(['username' => $bootstrapUser, 'password_hash' => password_hash($bootstrapPassword, PASSWORD_DEFAULT)]);
    }

    private function createMfaChallenge(AdminUser $user): array
    {
        AdminMfaChallenge::query()->where('admin_user_id', $user->id)->delete();
        $challengeToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $setupRequired = !(bool) $user->mfa_enabled || !$user->mfa_secret;
        $secret = $setupRequired ? Totp::secret() : null;

        AdminMfaChallenge::query()->create([
            'admin_user_id' => $user->id,
            'token_hash' => hash('sha256', $challengeToken),
            'mfa_secret' => $secret,
            'setup_required' => $setupRequired ? 1 : 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        $data = [
            'mfaRequired' => true,
            'mfaSetupRequired' => $setupRequired,
            'challengeToken' => $challengeToken,
            'username' => $user->username,
        ];

        if ($setupRequired) {
            $data['secret'] = $secret;
            $data['otpauthUri'] = Totp::otpauthUri($secret, $user->username);
        }

        return $data;
    }
}
