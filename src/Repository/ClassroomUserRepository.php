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
            ->from(ClassroomUser::class,'cu')
            ->where('cu.garId IS NOT NULL AND cu.schoolId IS NOT NULL')->getQuery()
            ->getResult();
        
        return $results;
    }
    public function getGarTeachers()
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $results = $queryBuilder->select('cu')
            ->from(ClassroomUser::class,'cu')
            ->where('cu.garId IS NOT NULL AND cu.schoolId IS NOT NULL AND cu.isTeacher=1')->getQuery()
            ->getResult();
        
        return $results;
    }
}
