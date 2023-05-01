<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\Configuration;
use App\DependencyInjection\Container;
use App\Manager\PostManager;
use App\Manager\UserManager;
use App\Service\MailerService;
use App\Service\PostService;
use App\Service\ProfileService;
use App\Validator\LoginFormValidator;
use App\Validator\RegisterFormValidator;
use Tracy\Debugger;

class UserController extends AbstractController
{
    protected UserManager $userManager;
    private MailerService $mailerService;
    private ProfileService $profileService;
    private PostService $postService;
    private LoginFormValidator $loginFV;
    private Configuration $configuration;
    private PostManager $postManager;

    public function __construct(MailerService $mailerService, Configuration $configuration, Container $container)
    {
        parent::__construct($container);
        $container->injectProperties($this);
        $this->loginFV = new LoginFormValidator($this->userManager, $this->securityHelper);
        $this->mailerService = $mailerService;
        $this->configuration = $configuration;
        $this->postManager = $this->container->get(PostManager::class);
    }

    // Display the profile page.
    public function profile()
    {
        $redirect = $this->ensureAuthenticatedUser();
        if ($redirect) {
            return $redirect;
        }
        $user = $this->securityHelper->getUser();
        $errors = [];
        if ('POST' == $this->serverRequest->getRequestMethod() && filter_input(INPUT_POST, 'csrfToken', FILTER_SANITIZE_SPECIAL_CHARS)) {
            $errors = $this->profileService->handleProfilePostRequest($user);
        }
        $csrfToken = $this->securityHelper->generateCsrfToken('editProfile');
        $userPostsData = $this->postService->getUserPostsData();
        $hasPost = ($userPostsData['total'] > 0) ? true : false;

        return $this->twig->render('pages/profile/profile.html.twig', [
            'title' => 'MyBlog - Profile',
            'errors' => $errors ?? null,
            'csrfToken' => $csrfToken,
            'hasPost' => $hasPost,
            'impersonate' => false,
            'user' => $user,
            'session' => $this->session,
        ]);
    }

    /**
     * Display the login page.
     */
    public function login()
    {
        $this->ensureAuthenticatedUser();
        Debugger::barDump($this->securityHelper->getUser());
        $errors = [];
        if ('POST' === $this->serverRequest->getRequestMethod()) {
            $postData = [
                'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
                'password' => filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS),
                'remember' => filter_input(INPUT_POST, 'remember', FILTER_SANITIZE_SPECIAL_CHARS),
                'csrfToken' => filter_input(INPUT_POST, 'csrfToken', FILTER_SANITIZE_SPECIAL_CHARS),
            ];
            $validationResult = $this->loginFV->validate($postData);
            $errors = $validationResult['errors'];
            if ($validationResult['valid']) {
                $login = $this->securityHelper->authenticate($postData, $this->loginFV->shouldRemember($postData));
                if ($login) {
                    return $this->request->redirectToRoute('blog');
                }
                $errors = ['email' => 'Email ou mot de passe incorrect.'];
            }
        }
        $csrfToken = $this->securityHelper->generateCsrfToken('login');

        return $this->twig->render('pages/security/login.html.twig', [
            'title' => 'MyBlog - Connexion',
            'csrfToken' => $csrfToken,
            'errors' => $errors ?? null,
        ]);
    }

    /**
     * Display the register page.
     */
    public function register()
    {
        $this->ensureAuthenticatedUser();
        if ($this->securityHelper->getUser()) {
            return $this->request->redirectToRoute('blog');
        }
        $errors = [];
        $csrfToken = $this->securityHelper->generateCsrfToken('register');
        if ('POST' === $this->serverRequest->getRequestMethod()) {
            $postData = [
                'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
                'username' => filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS),
                'password' => filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS),
                'passwordConfirm' => filter_input(INPUT_POST, 'passwordConfirm', FILTER_SANITIZE_SPECIAL_CHARS),
                'csrfToken' => $csrfToken,
            ];
            $registerFV = new RegisterFormValidator($this->userManager, $this->securityHelper);
            $validationResult = $registerFV->validate($postData);
            if ($validationResult['valid']) {
                $registered = $this->securityHelper->register($postData);
                if ($registered) {
                    $csrfToken = $this->securityHelper->generateCsrfToken('register');
                    $message = 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.';
                    $this->mailerService->sendEmail(
                        $this->configuration->get('mailer.from_email'),
                        $postData['email'],
                        'Bienvenue sur MyBlog',
                        $this->twig->render('emails/contact.html.twig')
                    );

                    return $this->request->redirectToRoute('login');
                }
                $errors[] = "Échec de l'enregistrement. Veuillez réessayer.";
            }
            $errors = array_merge($errors, $validationResult['errors']);
        }

        return $this->twig->render('pages/security/register.html.twig', [
            'title' => 'MyBlog - Connexion',
            'csrfToken' => $csrfToken,
            'errors' => $errors,
        ]);
    }

    /**
     * Display the user profile page.
     *
     * @param mixed $username
     */
    public function userProfile(string $username)
    {
        $username = $this->serverRequest->getPath();
        $user = $this->userManager->findOneBy(['username' => $username]);
        $userPostsData = $this->postService->getOtherUserPostsData($user->getId());
        $hasPost = ($userPostsData['total'] > 0) ? true : false;

        return $this->twig->render('pages/profile/profile.html.twig', [
            'title' => 'MyBlog - Profile',
            'userPostsData' => $userPostsData,
            'hasPost' => $hasPost,
            'impersonate' => true,
            'user' => $user,
            'session' => $this->session,
        ]);
    }

    /**
     * Logout the user.
     */
    public function logout()
    {
        $this->session->destroy();

        return $this->request->redirectToRoute('blog');
    }
}
