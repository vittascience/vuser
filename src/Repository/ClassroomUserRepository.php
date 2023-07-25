<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\ClassroomUser;

class ClassroomUserRepository extends EntityRepository
{
    public function getGarUsers()
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $results = $queryBuilder->select('cu')
            ->from(ClassroomUser::class)
            ->where('cu.garId IS NOT NULL AND cu.schoolId IS NOT NULL');
        
        return $results;
    }
}
