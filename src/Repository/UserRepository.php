<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\User;

class UserRepository extends EntityRepository
{
    public function getMultipleUsers($array)
    {
        $queryBuilder = $this->getEntityManager()
            ->createQueryBuilder();
        $query = $queryBuilder
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN(' . implode(', ', $array) . ")")
            ->getQuery();
        return $query->getResult();
    }

    public function getNewsLetterMembers() {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("r.id, r.email, u.firstname, u.lastname")
            ->from(Regular::class, 'r')
            ->innerJoin(User::class, 'u', Join::WITH, 'r.user = u.id')
            ->where('u.newsletter = 1')
            ->getQuery();
        return $query->getResult();
    }
}
