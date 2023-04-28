<?php

declare(strict_types=1);

namespace App\Helper;

use App\Config\DatabaseConnexion;
use App\Manager\UserManager;
use App\Model\UserModel;
use App\Router\ServerRequest;
use App\Router\Session;
use App\Validator\LoginFormValidator;
use App\Validator\RegisterFormValidator;

class SecurityHelper
{
    private UserManager $userManager;
    private RegisterFormValidator $registerValidator;
    private LoginFormValidator $loginValidator;
    private Session $session;
    private ServerRequest $serverRequest;

    public function __construct(Session $session)
    {
        $connexion = new DatabaseConnexion();
        $this->session = $session;
        $this->userManager = new UserManager($connexion);
        $this->registerValidator = new RegisterFormValidator($this);
        $this->loginValidator = new LoginFormValidator($this->userManager, $this);
        $this->serverRequest = new ServerRequest();
    }

    public function register(array $postData): bool
    {
        $response = $this->registerValidator->validate($postData);
        if (!empty($response['errors']) || false === $response['valid']) {
            return false;
        }
        $userData = [
            'username' => $postData['username'],
            'email' => $postData['email'],
            'password' => password_hash($postData['password'], PASSWORD_DEFAULT),
            'role' => 'ROLE_USER',
            'avatar' => 'https://i.pravatar.cc/150?img=6',
        ];
        $user = $this->userManager->createUser($userData);
        if (!$user instanceof UserModel) {
            return false;
        }
        $user = $this->authenticate([
            'email' => $user->getEmail(),
            'password' => $postData['password'],
            'remember' => 'true',
        ]);
        if (!$user) {
            return false;
        }
        header('Location: /blog');

        return true;
    }

    public function authenticate(array $data, bool $remember = false): ?UserModel
    {
        $user = $this->userManager->findOneBy(['email' => $data['email']]);
        if (!$user || !password_verify($data['password'], $user->getPassword())) {
            return null;
        }
        $this->session->regenerateId();
        $this->session->set('user', $user);
        if ($remember) {
            $this->rememberMe($user);
        }

        return $user;
    }

    public function login(array $data, bool $remember = false): ?UserModel
    {
        $user = $this->userManager->findOneBy(['email' => $data['email']]);
        if (!$user || !password_verify($data['password'], $user->getPassword())) {
            return null;
        }
        $this->session->regenerateId();
        $this->session->set('user', $user);
        if ($remember) {
            $this->rememberMe($user);
        }

        return $user;
    }

    public function getUser(): ?UserModel
    {
        return $this->session->get('user');
    }

    public function rememberMe(UserModel $user): void
    {
        $token = bin2hex(random_bytes(16));
        $expiresAt = time() + 3600 * 24 * 30; // 30 days

        $this->userManager->setRememberMeToken($user->getId(), $token, $expiresAt);

        setcookie('remember_me_token', $token, $expiresAt, '/', '', false, true);
    }

    public function generateRememberMeToken(): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + 86400 * 7;

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    public function checkRememberMeToken(): ?UserModel
    {
        if (!$this->session->get('remember_me_token') || empty($this->session->get('remember_me_token'))) {
            throw new \InvalidArgumentException("Le jeton 'Remember Me' n'est pas défini ou vide.");
        }
        $token = $this->session->getCookie('remember_me_token');
        $user = $this->userManager->findOneBy(['remember_me_token' => $token]);
        if (!$user) {
            throw new \Exception('Aucun utilisateur trouvé.');
        }
        $expiresAt = $user->getRememberMeExpires();
        $expiresAt = strtotime($expiresAt);
        if ($expiresAt < time()) {
            throw new \Exception('Le jeton a expiré.');
        }
        $this->session->set('user', $user);
        header('Location: /blog');

        return $user;
    }

    public function generateCsrfToken(string $key): string
    {
        $token = bin2hex(random_bytes(32));
        $csrfTokens = $this->session->get('csrfTokens') ?? [];
        $csrfTokens[$key] = $token;
        $this->session->set('csrfTokens', $csrfTokens);

        return $token;
    }

    public function checkCsrfToken(string $key, string $token): bool
    {
        $csrfTokens = $this->session->get('csrfTokens');
        $expected = $csrfTokens[$key] ?? null;
        if (null === $expected) {
            throw new \InvalidArgumentException('No CSRF token found for the given key.');
        }

        return hash_equals($expected, $token);
    }
}
