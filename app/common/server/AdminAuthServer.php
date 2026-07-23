<?php

namespace app\common\server;

use app\common\library\Totp;
use app\common\model\AdminMfaChallenge;
use app\common\model\AdminSession;
use app\common\model\AdminUser;
use app\common\library\DateTimeFormatter;
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
        if ((bool) $user->mfa_enabled && $user->mfa_secret) {
            return $this->createMfaChallenge($user);
        }
        return $this->createSession($user);
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
        if ($setupRequired) {
            throw new InvalidArgumentException('MFA 开启流程请在个人中心完成');
        }
        $secret = (string) $user->mfa_secret;
        if (!Totp::verify($secret, $code)) {
            throw new InvalidArgumentException('动态验证码错误');
        }

        AdminMfaChallenge::query()->where('admin_user_id', $user->id)->delete();
        return $this->createSession($user);
    }

    public function profile(AdminUser $user): array
    {
        return [
            'username' => $user->username,
            'mfaEnabled' => (bool) $user->mfa_enabled,
            'mfaEnabledAt' => DateTimeFormatter::beijing($user->mfa_enabled_at),
        ];
    }

    public function changePassword(AdminUser $user, string $oldPassword, string $newPassword): void
    {
        if (!password_verify($oldPassword, $user->password_hash)) {
            throw new InvalidArgumentException('旧密码错误');
        }
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('新密码至少需要 8 位');
        }
        $user->forceFill(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)])->save();
    }

    public function startMfaSetup(AdminUser $user): array
    {
        if ((bool) $user->mfa_enabled && $user->mfa_secret) {
            throw new InvalidArgumentException('MFA 已开启');
        }

        AdminMfaChallenge::query()->where('admin_user_id', $user->id)->delete();
        $challengeToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $secret = Totp::secret();
        AdminMfaChallenge::query()->create([
            'admin_user_id' => $user->id,
            'token_hash' => hash('sha256', $challengeToken),
            'mfa_secret' => $secret,
            'setup_required' => 1,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        return [
            'challengeToken' => $challengeToken,
            'secret' => $secret,
            'otpauthUri' => Totp::otpauthUri($secret, $user->username),
        ];
    }

    public function enableMfa(AdminUser $user, string $challengeToken, string $code): array
    {
        $challenge = AdminMfaChallenge::query()
            ->where('admin_user_id', $user->id)
            ->where('token_hash', hash('sha256', $challengeToken))
            ->where('setup_required', 1)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
        if (!$challenge) {
            throw new InvalidArgumentException('MFA 绑定已过期，请重新生成密钥');
        }
        $secret = (string) $challenge->mfa_secret;
        if (!Totp::verify($secret, $code)) {
            throw new InvalidArgumentException('动态验证码错误');
        }

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => 1,
            'mfa_enabled_at' => date('Y-m-d H:i:s'),
        ])->save();
        AdminMfaChallenge::query()->where('admin_user_id', $user->id)->delete();

        return $this->profile($user->refresh());
    }

    public function disableMfa(AdminUser $user, string $code): array
    {
        if (!(bool) $user->mfa_enabled || !$user->mfa_secret) {
            return $this->profile($user);
        }
        if (!Totp::verify((string) $user->mfa_secret, $code)) {
            throw new InvalidArgumentException('动态验证码错误');
        }
        $user->forceFill([
            'mfa_enabled' => 0,
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
        ])->save();
        AdminMfaChallenge::query()->where('admin_user_id', $user->id)->delete();

        return $this->profile($user->refresh());
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

        AdminMfaChallenge::query()->create([
            'admin_user_id' => $user->id,
            'token_hash' => hash('sha256', $challengeToken),
            'mfa_secret' => null,
            'setup_required' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        $data = [
            'mfaRequired' => true,
            'mfaSetupRequired' => false,
            'challengeToken' => $challengeToken,
            'username' => $user->username,
        ];

        return $data;
    }
}
