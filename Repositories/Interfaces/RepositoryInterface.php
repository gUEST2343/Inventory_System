<?php

namespace App\Repositories\Interfaces;

interface RepositoryInterface
{
    /**
     * Find a single record by ID
     */
    public function find(int $id);
    
    /**
     * Find all records
     */
    public function findAll(): array;
    
    /**
     * Find records by criteria
     */
    public function findBy(array $criteria, array $orderBy = null, int $limit = null, int $offset = null): array;
    
    /**
     * Find one record by criteria
     */
    public function findOneBy(array $criteria);
    
    /**
     * Save/update an entity
     */
    public function save($entity);
    
    /**
     * Delete an entity
     */
    public function delete($entity): bool;
    
    /**
     * Count records by criteria
     */
    public function count(array $criteria = []): int;
    
    /**
     * Check if entity exists by criteria
     */
    public function exists(array $criteria): bool;
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): void;
    
    /**
     * Commit transaction
     */
    public function commit(): void;
    
    /**
     * Rollback transaction
     */
    public function rollback(): void;
}