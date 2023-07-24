<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;

class ClassroomUserConnectionLogRepository extends EntityRepository
{
    public function getUsersToDelete()
    {
        $augustFifteenth = mktime(23, 59, 59, 8, 15,   date("Y"));
        $augustFifteenthFormated = date('Y-m-d H:i:s', $augustFifteenth);
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $results =  $queryBuilder->select(' u.id AS userId, cu.garId,MAX(cucl.connectionDate) AS lastConnection, cu.isTeacher')
            ->from(ClassroomUser::class, 'cu')
            ->leftJoin(ClassroomUserConnectionLog::class, 'cucl', 'WITH', 'cu.garId=cucl.garId')
            ->join(User::class,'u','WITH','u.id=cu.id')
            ->andWhere('cu.garId IS NOT NULL')
            ->groupBy('cucl.garId')
            ->having("lastConnection IS NULL")
            ->orHaving("lastConnection < '$augustFifteenthFormated'")
            ->orderBy('lastConnection','DESC')
            ->getQuery()
            ->getResult(); 
        
        return $results;
    }
   
}
