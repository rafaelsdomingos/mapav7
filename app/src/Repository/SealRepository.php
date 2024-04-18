<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\Persistence\ObjectRepository;
use MapasCulturais\Entities\Seal;

class SealRepository extends AbstractRepository
{
    private ObjectRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = $this->getEntityManager()->getRepository(Seal::class);
    }

    public function findAll(): array
    {
        return $this->repository
            ->createQueryBuilder('seal')
            ->getQuery()
            ->getArrayResult();
    }

    public function find(int $id): Seal
    {
        return $this->repository->find($id);
    }
}