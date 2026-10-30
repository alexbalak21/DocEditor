<?php

namespace App\Services;

use App\Core\Logger;
use App\Models\User;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;

class AuthService
{
    public const WINDOW_MINUTES = 15;
    public const MAX_PER_EMAIL  = 5;    // failed attempts for one email from one IP
    public const MAX_PER_IP     = 20;   // failed attempts from one IP, any email

    /** A valid bcrypt hash of a random string: verified when the email is unknown, so timing doesn't reveal it. */
    private const DUMMY_HASH = '$2y$10$hMmRQ1ShIiFeYlmUOIgWm.R2.RwbBuF2uSH95EPhUGnrSDl1h9hei';

    public function __construct(
        private UserRepository         $users    = new UserRepository(),
        private LoginAttemptRepository $attempts = new LoginAttemptRepository(),
    ) {}

    /** @return array{ok:bool, error?:string, user?:User} */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));

        if ($this->isThrottled($ip, $email)) {
            $mins = max(1, (int) ceil($this->attempts->secondsUntilWindowFrees($ip, self::WINDOW_MINUTES) / 60));
            Logger::warning('login.throttled', [
                'ip'      => $ip,
                'email'   => $email,
                'wait_m'  => $mins,
                'ip_count'  => $this->attempts->countForIp($ip, self::WINDOW_MINUTES),
            ]);
            return ['ok' => false, 'error' => "Too many failed attempts. Please try again in about {$mins} minute(s)."];
        }

        $user = ($email !== '' && strlen($email) <= 190) ? $this->users->findByEmail($email) : null;

        // Always run one password_verify, whether or not the user exists.
        $tooLong = strlen($password) > 1024;
        $valid   = password_verify($tooLong ? '' : $password, $user->passwordHash ?? self::DUMMY_HASH)
                 && $user !== null && !$tooLong;

        if (!$valid) {
            $this->attempts->record($ip, $email);
            Logger::warning('login.failed', [
                'ip'          => $ip,
                'email'       => $email,
                'reason'      => $tooLong ? 'password_too_long' : 'bad_credentials',
                'email_known' => $user !== null,
            ]);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
            $this->users->rehashPassword($user->id, password_hash($password, PASSWORD_DEFAULT));
        }
        $this->attempts->clear($ip, $email);
        $this->users->touchLogin($user->id);

        Logger::info('login.success', [
            'ip'    => $ip,
            'email' => $email,
            'uid'   => $user->id,
        ]);

        return ['ok' => true, 'user' => $user];
    }

    public function isThrottled(string $ip, string $email): bool
    {
        return $this->attempts->countForIpAndEmail($ip, $email, self::WINDOW_MINUTES) >= self::MAX_PER_EMAIL
            || $this->attempts->countForIp($ip, self::WINDOW_MINUTES) >= self::MAX_PER_IP;
    }
}
