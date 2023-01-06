<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\LtiUser;
use User\Entity\User;

class LtiUserRepository extends EntityRepository{
    public function getTeachersIdByConsumer($consumer){
       
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('lu')
                        ->from(LtiUser::class,'lu')
                        ->andWhere('lu.isTeacher=1')
                        ->andWhere('lu.ltiConsumer= :consumer')
                        ->setParameter('consumer',$consumer)
                        ->getQuery()
                        ->getResult();
        $teachersId = [];
        foreach($results as $result){
            array_push($teachersId, $result->getUser()->getId());
        }
        return $results;
    }

    public function getStudentsIdByConsumer($consumer){
       
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('lu')
                        ->from(LtiUser::class,'lu')
                        ->andWhere('lu.isTeacher=0')
                        ->andWhere('lu.ltiConsumer= :consumer')
                        ->setParameter('consumer',$consumer)
                        ->getQuery()
                        ->getResult();
        $studentsId = [];
        foreach($results as $result){
            array_push($studentsId, $result->getUser()->getId());
        }
        return $results;
    }
}