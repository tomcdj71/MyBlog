<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\SecurityHelper;
use App\Helper\TwigHelper;
use App\Manager\CategoryManager;
use App\Manager\CommentManager;
use App\Manager\PostManager;
use App\Manager\TagManager;
use App\Manager\UserManager;
use App\Router\Request;
use App\Router\ServerRequest;
use App\Router\Session;
use App\Service\CommentService;
use App\Service\CsrfTokenService;

class BlogController extends AbstractController
{
    private CategoryManager $categoryManager;
    private TagManager $tagManager;
    private CommentManager $commentManager;
    private CommentService $commentService;
    private PostManager $postManager;
    private $sidebar;
    private CsrfTokenService $csrfTokenService;

    public function __construct(
        TwigHelper $twig,
        Session $session,
        ServerRequest $serverRequest,
        SecurityHelper $securityHelper,
        UserManager $userManager,
        Request $request,
        CategoryManager $categoryManager,
        TagManager $tagManager,
        CommentManager $commentManager,
        CommentService $commentService,
        PostManager $postManager,
        CsrfTokenService $csrfTokenService
    ) {
        parent::__construct($twig, $session, $serverRequest, $securityHelper, $userManager, $request);
        $this->postManager = $postManager;
        $this->categoryManager = $categoryManager;
        $this->tagManager = $tagManager;
        $this->commentManager = $commentManager;
        $this->commentService = $commentService;
        $this->csrfTokenService = $csrfTokenService;
        $this->sidebar = [
            'categories' => $this->categoryManager->findAll(),
            'tags' => $this->tagManager->findAll(),
            'recentPosts' => $this->postManager->findRecentPosts(),
        ];
    }

    /**
     * Display the blog index page.
     */
    public function blogIndex()
    {
        $page = $this->serverRequest->getQuery('page') ? intval($this->serverRequest->getQuery('page')) : 1;
        $limit = 10;
        $totalPosts = $this->postManager->countAll();
        $totalPages = ceil($totalPosts / $limit);
        $posts = $this->postManager->findAll($page, $limit);

        return $this->twig->render('pages/blog/index.html.twig', array_merge([
            'title' => 'MyBlog - Blog',
            'user' => $this->securityHelper->getUser(),
            'posts' => $posts['posts'],
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ], $this->sidebar));
    }

    /**
     * Display the blog post page.
     *
     * @param mixed $slug
     */
    public function blogPost($slug)
    {
        $post = $this->postManager->findOneBy('slug', $slug);
        if (null === $post) {
            header('Location: /404');
        }
        $user = $this->securityHelper->getUser();
        if (null === $user) {
            $user = null;
        }

        if ('POST' === $this->serverRequest->getRequestMethod() && $this->serverRequest->getPost('content') && $this->serverRequest->getPost('csrfToken')) {
            $postData = [
                'content' => $this->serverRequest->getPost('content'),
                'post_id' => $post,
                'user_id' => $this->session->getUserFromSession()->getId(),
                'parent_id' => $this->serverRequest->getPost('parentId'),
                'csrfToken' => $this->serverRequest->getPost('csrfToken'),
            ];
            $errors = $this->commentService->handleCommentPostRequest($post, $postData);
            if (empty($errors)) {
                $csrfToken = $this->csrfTokenService->generateToken('comment');

                return $this->twig->render('pages/blog/post.html.twig', array_merge([
                    'message' => 'Commentaire posté avec succès !',
                    'csrfToken' => $csrfToken,
                    'post' => $post,
                    'errors' => $errors ?? '',
                    'comments' => $this->commentManager->findAllByPost($post->getId()),
                ], $this->sidebar));
            }
            $errors = array_map('strval', $errors);
        }
        $csrfToken = $this->csrfTokenService->generateToken('comment');

        return $this->twig->render('pages/blog/post.html.twig', array_merge([
            'user' => $user,
            'csrfToken' => $csrfToken,
            'post' => $post,
            'errors' => $errors ?? '',
            'comments' => $this->commentManager->findAllByPost($post->getId()),
        ], $this->sidebar));
    }

    /**
     * Display the blog category page.
     *
     * @param mixed $slug
     * @param mixed $categorySlug
     */
    public function blogCategory($slug)
    {
        return $this->twig->render('pages/blog/index.html.twig', array_merge([
            'posts' => $this->postManager->findBy('category_slug', $this->path),
            'searchType' => 'Catégorie',
            'search' => $slug,
        ], $this->sidebar));
    }

    public function blogTag($slug)
    {
        return $this->twig->render('pages/blog/index.html.twig', array_merge([
            'title' => 'MyBlog - Blog',
            'posts' => $this->postManager->findPostsWithTag($this->path),
            'searchType' => 'Tag',
            'search' => $slug,
        ], $this->sidebar));
    }

    public function blogAuthor($author)
    {
        $author = $this->userManager->findOneBy(['username' => $this->path]);

        return $this->twig->render('pages/blog/index.html.twig', array_merge([
            'title' => 'MyBlog - Blog',
            'posts' => $this->postManager->findBy('author_id', $author->getId()),
            'searchType' => 'Auteur',
            'search' => $author->getUsername(),
        ], $this->sidebar));
    }

    /**
     * Return the posts made in the last 30 days.
     *
     * @param mixed $date
     */
    public function blogDate($date)
    {
        $endDate = new \DateTime($date);
        $startDate = clone $endDate;
        $startDate->modify('-30 days');

        return $this->twig->render('pages/blog/index.html.twig', array_merge([
            'posts' => $this->postManager->findPostsBetweenDates($startDate, $endDate),
            'searchType' => 'Date',
            'search' => 'Postés entre le '.$startDate->format('d-m-Y').' et le '.$endDate->format('d-m-Y').'.',
        ], $this->sidebar));
    }
}
