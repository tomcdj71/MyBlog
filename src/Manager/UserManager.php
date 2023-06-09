<?php

declare(strict_types=1);

namespace App\Manager;

use App\Config\DatabaseConnexion;
use App\Model\UserModel;
use App\ModelParameters\UserModelParameters;

class UserManager
{
    private $database;
    private UserModelParameters $userModelParams;

    public function __construct(DatabaseConnexion $databaseConnexion)
    {
        $this->database = $databaseConnexion->connect();
        $this->userModelParams = new UserModelParameters();
    }

    public function find(int $id): ?UserModel
    {
        try {
            $sql = 'SELECT * FROM user WHERE id = :id';
            $statement = $this->database->prepare($sql);
            $statement->execute(['id' => $id]);
            $data = $statement->fetch(\PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->createUserModelFromArray($data);
        } catch (\PDOException $error) {
            throw new \PDOException($error->getMessage(), (int) $error->getCode());
        }
    }

    public function findByPage(int $page, int $limit): array
    {
        try {
            $sql = 'SELECT * FROM user ORDER BY id DESC LIMIT :limit OFFSET :offset';
            $statement = $this->database->prepare($sql);
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
            $statement->bindValue('offset', ($page - 1) * $limit, \PDO::PARAM_INT);
            $statement->execute();
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (!$data) {
                return [];
            }
            $users = [];
            foreach ($data as $user) {
                $users[] = $this->createUserModelFromArray($user);
            }

            return $users;
        } catch (\PDOException $error) {
            throw new \PDOException($error->getMessage(), (int) $error->getCode());
        }
    }

    public function findOneBy(array $criteria): ?UserModel
    {
        try {
            $sql = 'SELECT * FROM user WHERE ';
            $where = [];
            $parameters = [];
            foreach ($criteria as $key => $value) {
                $where[] = $key.' = :'.$key;
                $parameters[$key] = $value;
            }
            $sql .= implode(' AND ', $where);
            $statement = $this->database->prepare($sql);
            $statement->execute($parameters);
            $data = $statement->fetch(\PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->createUserModelFromArray($data);
        } catch (\PDOException $error) {
            throw new \PDOException($error->getMessage(), (int) $error->getCode());
        }
    }

    public function findAll(): array
    {
        try {
            $sql = 'SELECT * FROM user';
            $statement = $this->database->prepare($sql);
            $statement->execute();
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (!$data) {
                return [];
            }
            $users = [];
            foreach ($data as $user) {
                $users[] = $this->createUserModelFromArray($user);
            }

            return $users;
        } catch (\PDOException $error) {
            throw new \PDOException($error->getMessage(), (int) $error->getCode());
        }
    }

    public function createUser(array $userData): ?UserModel
    {
        try {
            $sql = 'INSERT INTO user (username, email, password, created_at, role, avatar, bio, twitter, facebook, linkedin, github)
            VALUES (:username, :email, :password, :created_at, :role, :avatar, :bio, :twitter, :facebook, :linkedin, :github)';
            $statement = $this->database->prepare($sql);
            $params = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => $userData['password'],
                'created_at' => date('Y-m-d H:i:s'),
                'role' => $userData['role'] ?? 'ROLE_USER',
                'avatar' => $userData['avatar'] ?? 'https:// i.pravatar.cc/150?img=6',
                'bio' => $userData['bio'] ?? '',
                'twitter' => $userData['twitter'] ?? '',
                'facebook' => $userData['facebook'] ?? '',
                'linkedin' => $userData['linkedin'] ?? '',
                'github' => $userData['github'] ?? '',
            ];
            $statement->execute($params);
            $lastInsertId = $this->database->lastInsertId();
            $user = $this->find((int) $lastInsertId);
            if (null !== $user) {
                return $user;
            }
        } catch (\PDOException $error) {
            return null;
        }
    }

    public function updateProfile(UserModel $user, array $data): bool
    {
        try {
            $sql = 'UPDATE user SET email = :email, firstName = :firstName, lastName = :lastName, bio = :bio, twitter = :twitter, facebook = :facebook, github = :github, avatar = :avatar, linkedin = :linkedin';
            $sql .= ' WHERE id = :id';
            $statement = $this->database->prepare($sql);
            $statement->bindValue('id', $user->getId(), \PDO::PARAM_INT);
            $statement->bindValue(':email', $data['email'] ?? $user->getEmail(), \PDO::PARAM_STR);
            $statement->bindValue(':firstName', $data['firstName'] ?? $user->getFirstName(), \PDO::PARAM_STR);
            $statement->bindValue(':lastName', $data['lastName'] ?? $user->getLastName(), \PDO::PARAM_STR);
            $statement->bindValue(':bio', $data['bio'] ?? $user->getBio(), \PDO::PARAM_STR);
            $statement->bindValue(':twitter', $data['twitter'] ?? $user->getTwitter(), \PDO::PARAM_STR);
            $statement->bindValue(':facebook', $data['facebook'] ?? $user->getFacebook(), \PDO::PARAM_STR);
            $statement->bindValue(':github', $data['github'] ?? $user->getGithub(), \PDO::PARAM_STR);
            $statement->bindValue(':avatar', $data['avatar'] ?? $user->getAvatar(), \PDO::PARAM_STR);
            $statement->bindValue(':linkedin', $data['linkedin'] ?? $user->getLinkedin(), \PDO::PARAM_STR);
            $statement->execute();

            return true;
        } catch (\PDOException $error) {
            error_log($error->getMessage(), (int) $error->getCode());
        }
    }

    public function createUserModelFromArray(array $data): UserModel
    {
        $userModelParams = $this->userModelParams->createFromData($data);

        return new UserModel($userModelParams);
    }

    public function updateRole(UserModel $user): bool
    {
        try {
            $sql = 'UPDATE user SET role = :role WHERE id = :id';
            $statement = $this->database->prepare($sql);
            $statement->bindValue(':role', $user->getRole(), \PDO::PARAM_STR);
            $statement->bindValue(':id', $user->getId(), \PDO::PARAM_INT);
            $statement->execute();

            return true;
        } catch (\PDOException $error) {
            throw new \PDOException($error->getMessage(), (int) $error->getCode());
        }
    }
}
