<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

class PasswordHasherService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function hashPasswordForUser(User $user, ?string $plainPassword): void
    {
        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->logger->info('Password hashed for user', [
                'user_id' => $user->getId() ?? 'new_user', //new users dont have an id yet
                'user_email' => $user->getEmail(),
            ]);
        }
    }
}

