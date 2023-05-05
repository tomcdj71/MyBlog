<?php

declare(strict_types=1);

namespace App\Service;

use App\Helper\ImageHelper;
use App\Helper\SecurityHelper;
use App\Helper\StringHelper;
use App\Manager\CategoryManager;
use App\Manager\PostManager;
use App\Manager\TagManager;
use App\Manager\UserManager;
use App\Router\ServerRequest;
use App\Router\Session;
use App\Validator\PostFormValidator;
use Tracy\Debugger;

class PostService extends AbstractService
{
    protected PostManager $postManager;
    protected Session $session;
    protected CsrfTokenService $csrfTokenService;
    protected ServerRequest $serverRequest;
    protected SecurityHelper $securityHelper;
    protected UserManager $userManager;
    protected StringHelper $stringHelper;
    protected ImageHelper $imageHelper;
    protected CategoryManager $categoryManager;
    protected TagManager $tagManager;

    public function __construct(
        ServerRequest $serverRequest,
        SecurityHelper $securityHelper,
        PostManager $postManager,
        Session $session,
        CsrfTokenService $csrfTokenService,
        UserManager $userManager,
        StringHelper $stringHelper,
        CategoryManager $categoryManager,
        TagManager $tagManager
    ) {
        $this->postManager = $postManager;
        $this->session = $session;
        $this->csrfTokenService = $csrfTokenService;
        $this->serverRequest = $serverRequest;
        $this->securityHelper = $securityHelper;
        $this->userManager = $userManager;
        $this->stringHelper = $stringHelper;
        $this->categoryManager = $categoryManager;
        $this->tagManager = $tagManager;
        $this->imageHelper = new ImageHelper('uploads/featured/', 1200, 900);
    }

    public function getUserPostsData()
    {
        $user = $this->securityHelper->getUser();
        $offset = $this->serverRequest->getQuery('offset') ? intval($this->serverRequest->getQuery('offset')) : 1;
        $limit = $this->serverRequest->getQuery('limit') ? intval($this->serverRequest->getQuery('limit')) : 10;
        $page = intval($offset / $limit) + 1;
        $userPostsData = $this->postManager->findUserPosts($user->getId(), $page, $limit);
        $userPosts = $userPostsData['posts'];
        $userPostsArray = [];
        $comments = '';
        foreach ($userPosts as $post) {
            $numberOfComments = isset($comments['number_of_comments']) ? $comments['number_of_comments'] : 0;
            $tags = array_map(function ($tag) {
                return $tag->getName();
            }, $post->getTags());
            $userPostsArray[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'slug' => $post->getSlug(),
                'created_at' => $post->getCreatedAt(),
                'is_enabled' => $post->getIsEnabled(),
                'category' => $post->getCategory()->getName(),
                'comments' => $numberOfComments.' commentaire(s)',
                'tags' => $tags,
                'type' => 'myPosts',
            ];
        }
        $totalPosts = $userPostsData['count'];

        return [
            'rows' => $userPostsArray,
            'total' => $totalPosts,
        ];
    }

    public function getOtherUserPostsData(int $userId)
    {
        $offset = $this->serverRequest->getQuery('offset') ? intval($this->serverRequest->getQuery('offset')) : 1;
        $limit = $this->serverRequest->getQuery('limit') ? intval($this->serverRequest->getQuery('limit')) : 10;
        $page = intval($offset / $limit) + 1;
        $otherUserPostsData = $this->postManager->findUserPosts($userId, $page, $limit);
        $otherUserPosts = $otherUserPostsData['posts'];
        $otherUserPostsArray = [];
        $comments = '';
        foreach ($otherUserPosts as $post) {
            $numberOfComments = isset($comments['number_of_comments']) ? $comments['number_of_comments'] : 0;
            $tags = array_map(function ($tag) {
                return $tag->getName();
            }, $post->getTags());
            $otherUserPostsArray[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'slug' => $post->getSlug(),
                'created_at' => $post->getCreatedAt(),
                'is_enabled' => $post->getIsEnabled(),
                'category' => $post->getCategory()->getName(),
                'comments' => $numberOfComments.' commentaire(s)',
                'tags' => $tags,
                'type' => 'otherPosts',
            ];
        }
        $totalPosts = $otherUserPostsData['count'];

        return [
            'rows' => $otherUserPostsArray,
            'total' => $totalPosts,
        ];
    }

    public function handleAddPostRequest()
    {
        $errors = [];
        $csrfToCheck = $this->serverRequest->getPost('csrfToken');
        if (!$this->csrfTokenService->checkCsrfToken('addPost', $csrfToCheck)) {
            $errors[] = 'Jeton CSRF invalide.';
        }
        $postData = $this->getPostData();
        $postFormValidator = new PostFormValidator($this->userManager, $this->session, $this->csrfTokenService);
        $response = $postFormValidator->validate($postData);
        $message = $response['valid'] ? $this->createPost($postData) : null;
        $errors = $response['valid'] ? null : $response['errors'];

        return [$errors, $message];
    }

    public function getPostData()
    {
        $fields = ['title', 'chapo', 'content', 'category', 'tags'];
        $postData = array_map(function ($field) {
            return $this->serverRequest->getPost($field, '');
        }, array_combine($fields, $fields));
        $postData['tags'] = implode(',', $postData['tags']);
        $postData['featuredImage'] = $_FILES['featuredImage'] ?? null;
        $postData['csrfToken'] = $this->serverRequest->getPost('csrfToken');

        return $postData;
    }

    public function handleEditPostRequest($post)
    {
        $errors = [];
        $postData = $this->getPostData();
        $csrfToCheck = $this->serverRequest->getPost('csrfToken');
        if (!$this->csrfTokenService->checkCsrfToken('editPost', $csrfToCheck)) {
            $errors[] = 'Jeton CSRF invalide.';
        }
        foreach ($postData as $key => $value) {
            if ('csrfToken' === $key) {
                continue;
            }
            if ('' === $value) {
                $postGetter = 'get'.ucfirst($key);
                if (is_object($post) && method_exists($post, $postGetter)) {
                    $postData[$key] = $post->{$postGetter}();
                }
            }
            if (is_array($value)) {
                if ('featuredImage' === $key && empty($value['name'])) {
                    $postData[$key] = $post->getFeaturedImage();
                } else {
                    unset($postData[$key]);
                }
            }
        }
        $postData['slug'] = isset($postData['title']) ? $this->stringHelper->slugify($postData['title']) : $post->getSlug();
        $updatedAt = new \DateTime();
        $postData['updatedAt'] = $updatedAt->format('Y-m-d H:i:s');
        $postData['isEnabled'] = false;
        foreach ($postData as $key => $value) {
            if ('category' === $key) {
                if ((int) $value !== $post->getCategory()->getId()) {
                    $message[] = 'Category has changed.';
                }
            } elseif ('tags' === $key) {
                $tags = implode(',', array_map(function ($tag) {
                    return $tag->getId();
                }, $post->getTags()));
                if ($tags !== $value) {
                    $message[] = 'Tags have changed.';
                }
            } else {
                $postGetter = 'get'.ucfirst($key);

                if (is_object($post) && method_exists($post, $postGetter) && $post->{$postGetter}() !== $value) {
                    $message[] = ucfirst($key).' has changed.';
                }
            }
        }
        if (empty($errors)) {
            $postFormValidator = new PostFormValidator($this->userManager, $this->session, $this->csrfTokenService);
            $response = $postFormValidator->validate($postData);
            $message = $response['valid'] ? $this->editPost($post, $postData) : null;
            $errors = $response['valid'] ? null : $response['errors'];
        }

        return [$errors, $message];
    }

    public function createPost(array $data)
    {
        $user = $this->securityHelper->getUser();
        $createdAt = new \DateTime();
        $createdAt = $createdAt->format('Y-m-d H:i:s');
        $filename = $this->imageHelper->uploadImage($data['featuredImage'], 1200, 900);
        if (0 === strpos($filename, 'Error')) {
            throw new \RuntimeException($filename);
        }
        $filename = explode('.', $filename)[0];
        $data['avatar'] = $filename;

        $postData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'author' => $user->getId(),
            'chapo' => $data['chapo'],
            'createdAt' => $createdAt,
            'updatedAt' => $createdAt,
            'isEnabled' => false,
            'featuredImage' => $filename,
            'category' => $data['category'],
            'slug' => $this->stringHelper->slugify($data['title']),
            'tags' => $data['tags'],
            'csrfToken' => $data['csrfToken'],
        ];
        $this->postManager->create($postData);

        return 'Votre article a été ajouté avec succès!';
    }

    public function editPost($post, $data)
    {
        $fields = ['title', 'chapo', 'content', 'category', 'tags', 'featuredImage', 'slug', 'updated_at', 'is_enabled'];
        foreach ($fields as $field) {
            $setter = 'set'.$field;
            $dataKey = lcfirst($field);
            if (isset($data[$dataKey])) {
                if ('category' == $field) {
                    $category = $this->categoryManager->find((int) $data[$dataKey]);
                    $post->setCategory($category);
                } elseif ('tags' == $field) {
                    $tags = $this->tagManager->findByIds(explode(',', $data[$dataKey]));
                    $post->addTags($tags);
                } else {
                    $post->{$setter}($data[$dataKey]);
                }
            }
        }
        $postUpdated = $this->postManager->updatePost($post, $data);
        $tagsUpdated = $this->postManager->updatePostTags($post, $post->getTags());
        Debugger::barDump(['postUpdated' => $postUpdated, 'tagsUpdated' => $tagsUpdated]);
        if ($postUpdated && $tagsUpdated) {
            return 'Article '.$post->getTitle().' mis à jour avec succès!';
        }

        return null;
    }
}
