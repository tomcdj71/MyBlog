<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\Configuration;
use App\Helper\SecurityHelper;
use App\Helper\TwigHelper;
use App\Manager\UserManager;
use App\Router\Request;
use App\Router\ServerRequest;
use App\Router\Session;
use App\Service\CsrfTokenService;
use App\Service\MailerService;
use App\Service\PostService;
use App\Service\ProfileService;
use App\Service\SecurityService;
use App\Validator\RegistrationFormValidator;

class UserController extends AbstractController
{
    protected UserManager $userManager;
    private MailerService $mailerService;
    private ProfileService $profileService;
    private PostService $postService;
    private SecurityService $securityService;
    private Configuration $configuration;
    private RegistrationFormValidator $registrationFV;
    private CsrfTokenService $csrfTokenService;
    private $navbar;

    public function __construct(
        TwigHelper $twig,
        Session $session,
        ServerRequest $serverRequest,
        SecurityHelper $securityHelper,
        UserManager $userManager,
        Request $request,
        CsrfTokenService $csrfTokenService,
        SecurityService $securityService,
    ) {
        parent::__construct($twig, $session, $serverRequest, $securityHelper, $userManager, $request, $csrfTokenService, $securityService);
        $this->navbar = [
            'profile' => $this->securityHelper->getUser(),
        ];
    }

    // Display the profile page.
    public function profile()
    {
        $this->securityHelper->denyAccessUnlessAuthenticated();
        $user = $this->securityHelper->getUser();
        $errors = [];
        if ('POST' == $this->serverRequest->getRequestMethod() && filter_input(INPUT_POST, 'csrfToken', FILTER_SANITIZE_SPECIAL_CHARS)) {
            list($errors, $message, $formData) = $this->profileService->handleProfilePostRequest($user);
            if ($errors) {
                $this->session->set('formData', $formData);
            }
        }
        $userPostsData = $this->postService->getUserPostsData();
        $hasPost = ($userPostsData['total'] > 0) ? true : false;
        $csrfToken = $this->csrfTokenService->generateToken('editProfile');

        return $this->twig->render('pages/profile/profile.html.twig', array_merge([
            'csrfToken' => $csrfToken,
            'hasPost' => $hasPost,
            'impersonate' => false,
            'user' => $user,
            'errors' => $errors ?? [],
            'message' => $message ?? '',
            'formData' => $formData ?? [],
            'session' => $this->session,
        ], $this->navbar));
    }

    /**
     * Display the login page.
     */
    public function login()
    {
        $this->securityHelper->denyAccessIfAuthenticated();
        if ('POST' == $this->serverRequest->getRequestMethod() && filter_input(INPUT_POST, 'csrfToken', FILTER_SANITIZE_SPECIAL_CHARS)) {
            list($errors, $formData) = $this->securityService->handleLoginPostRequest();
            if ($errors) {
                $this->session->set('formData', $formData);
                $this->session->set('errors', $errors);
            }
            if ($this->securityHelper->getUser()) {
                $url = $this->request->generateUrl('blog');
                $this->request->redirect($url);
            }
        }
        $csrfToken = $this->csrfTokenService->generateToken('login');

        return $this->twig->render('pages/security/login.html.twig', [
            'csrfToken' => $csrfToken,
            'errors' => $this->session->flash('errors'),
            'message' => $this->session->flash('message'),
            'formData' => $this->session->flash('formData'),
        ]);
    }

    /**
     * Display the register page.
     */
    public function register()
    {
        $this->securityHelper->denyAccessIfAuthenticated();
        $errors = [];
        $csrfToken = $this->csrfTokenService->generateToken('register');
        if ('POST' === $this->serverRequest->getRequestMethod()) {
            $formData = [
                'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
                'username' => filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS),
                'password' => filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS),
                'passwordConfirm' => filter_input(INPUT_POST, 'passwordConfirm', FILTER_SANITIZE_SPECIAL_CHARS),
                'csrfToken' => $csrfToken,
            ];
            $validationResult = $this->registrationFV->validate($formData);
            if ($validationResult['valid']) {
                $registered = $this->securityHelper->registerUser($formData);
                if ($registered) {
                    $csrfToken = $this->csrfTokenService->generateToken('register');
                    $message = 'Votre compte a été créé avec succès. Vous êtes désormais connecté.';
                    $mailerError = $this->mailerService->sendEmail(
                        $this->configuration->get('mailer.from_email'),
                        $formData['email'],
                        'Bienvenue sur MyBlog',
                        $this->twig->render('emails/registration.html.twig')
                    );
                    if ($mailerError) {
                        $this->session->set('mailerError', $mailerError);
                    }
                    $this->session->set('success', $message);
                    $url = $this->request->generateUrl('blog');
                    $this->request->redirect($url);
                }
                $errors[] = 'Échec de l\'enregistrement. Veuillez réessayer.';
            }
            $errors = array_merge($errors, $validationResult['errors']);
        }

        return $this->twig->render('pages/security/register.html.twig', [
            'csrfToken' => $csrfToken,
            'errors' => $errors ?? $this->session->flash('errors') ?? null,
            'formData' => $formData ?? $this->session->flash('formData') ?? null,
            'message' => $message ?? null,
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

        return $this->twig->render('pages/profile/profile.html.twig', array_merge([
            'userPostsData' => $userPostsData,
            'hasPost' => $hasPost,
            'impersonate' => true,
            'user' => $user,
            'session' => $this->session,
        ], $this->navbar));
    }

    /**
     * Logout the user.
     */
    public function logout()
    {
        $this->session->destroy();
        $url = $this->request->generateUrl('blog');
        $this->request->redirect($url);
    }
}
