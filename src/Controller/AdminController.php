<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\SecurityHelper;
use App\Helper\TwigHelper;
use App\Manager\CategoryManager;
use App\Manager\PostManager;
use App\Manager\TagManager;
use App\Manager\UserManager;
use App\Router\Request;
use App\Router\ServerRequest;
use App\Router\Session;
use App\Service\CsrfTokenService;
use App\Service\PostService;
use Tracy\Debugger;

class AdminController extends AbstractController
{
    private TagManager $tagManager;
    private CategoryManager $categoryManager;
    private PostService $postService;
    private CsrfTokenService $csrfTokenService;
    private PostManager $postManager;

    public function __construct(
        TwigHelper $twig,
        Session $session,
        ServerRequest $serverRequest,
        SecurityHelper $securityHelper,
        UserManager $userManager,
        Request $request,
        CategoryManager $categoryManager,
        TagManager $tagManager,
        PostService $postService,
        CsrfTokenService $csrfTokenService,
        PostManager $postManager
    ) {
        parent::__construct($twig, $session, $serverRequest, $securityHelper, $userManager, $request);
        $this->tagManager = $tagManager;
        $this->categoryManager = $categoryManager;
        $this->postService = $postService;
        $this->csrfTokenService = $csrfTokenService;
        $this->postManager = $postManager;
    }

    public function index()
    {
        return $this->twig->render('pages/admin/pages/index.html.twig', [
            'user' => $this->securityHelper->getUser(),
        ]);
    }

    public function categories()
    {
        return $this->twig->render('pages/admin/pages/category_admin.html.twig', [
            'user' => $this->securityHelper->getUser(),
        ]);
    }

    public function comments()
    {
        return $this->twig->render('pages/admin/pages/comment_admin.html.twig', [
            'user' => $this->securityHelper->getUser(),
        ]);
    }

    public function posts()
    {
        $data = [
            'message' => $this->session->get('message', ''),
            'postSlug' => $this->session->get('postSlug', ''),
            'postData' => $this->session->get('postData', []),
        ];
        Debugger::barDump($this->session);
        $this->session->removeKeys(['message', 'postSlug', 'postData']);

        return $this->twig->render('pages/admin/pages/post_admin.html.twig', [
            'user' => $this->securityHelper->getUser(),
            'data' => $data,
        ]);
    }

    public function tags()
    {
        return $this->twig->render('pages/admin/pages/tag_admin.html.twig', [
            'user' => $this->securityHelper->getUser(),
        ]);
    }

    public function users()
    {
        return $this->twig->render('pages/admin/pages/user_admin.html.twig', [
            'user' => $this->securityHelper->getUser(),
        ]);
    }

    public function addPost()
    {
        if ('POST' == $this->serverRequest->getRequestMethod() && filter_input(INPUT_POST, 'csrfToken', FILTER_SANITIZE_SPECIAL_CHARS)) {
            list($errors, $message, $postData, $postSlug) = $this->postService->handleAddPostRequest();
            if (!empty($postSlug)) {
                $this->session->set('message', $message);
                $this->session->set('postSlug', $postSlug);
                $this->session->set('postData', $postData);

                $url = $this->request->generateUrl('admin_posts');
                $this->request->redirect($url);
            }
        }
        $csrfToken = $this->csrfTokenService->generateToken('addPost');

        return $this->twig->render('pages/admin/pages/add_post.html.twig', [
            'user' => $this->securityHelper->getUser(),
            'categories' => $this->categoryManager->findAll(),
            'tags' => $this->tagManager->findAll(),
            'csrfToken' => $csrfToken,
            'errors' => $errors ?? [],
            'message' => $message ?? '',
        ]);
    }

    public function editPost(int $postId)
    {
        $post = $this->postManager->find($postId);
        if (!$post) {
        }

        if ('POST' == $this->serverRequest->getRequestMethod() && filter_input(INPUT_POST, 'csrfToken', FILTER_SANITIZE_SPECIAL_CHARS)) {
            list($errors, $message, $postData, $postSlug) = $this->postService->handleEditPostRequest($post);
            if (!empty($postSlug)) {
                $this->session->set('message', $message);
                $this->session->set('postSlug', $postSlug);
                $this->session->set('postData', $postData);

                $url = $this->request->generateUrl('admin_posts');
                $this->request->redirect($url);
            }
        }

        $csrfToken = $this->csrfTokenService->generateToken('editPost');

        return $this->twig->render('pages/admin/pages/edit_post.html.twig', [
            'user' => $this->securityHelper->getUser(),
            'categories' => $this->categoryManager->findAll(),
            'tags' => $this->tagManager->findAll(),
            'csrfToken' => $csrfToken,
            'post' => $post,
            'errors' => $errors ?? [],
            'message' => $message ?? '',
            'postData' => $postData ?? [],
            'postSlug' => $postSlug ?? '',
        ]);
    }
}
