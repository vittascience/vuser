<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\User;
use User\Entity\Regular;
use Doctrine\ORM\Query\Expr\Join;

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
            ->select("r.email, u.firstname, u.surname")
            ->from(User::class, 'u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'u.id = r.user')
            ->where('r.newsletter = 1')
            ->getQuery();
        return $query->getResult();
    }
}
