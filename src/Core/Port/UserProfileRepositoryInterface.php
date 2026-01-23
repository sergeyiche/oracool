<?php

namespace App\Core\Port;

use App\Core\Domain\User\UserProfile;

/**
 * Интерфейс репозитория профилей пользователей
 */
interface UserProfileRepositoryInterface
{
    public function findById(string $id): ?UserProfile;
    
    public function findByUserId(string $userId): ?UserProfile;
    
    public function save(UserProfile $profile): void;
    
    public function delete(string $id): void;
    
    public function findAll(): array;
}
