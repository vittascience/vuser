<?php

namespace User\Repository;

use User\Entity\User;
use User\Entity\LtiUser;
use Doctrine\ORM\EntityRepository;
use User\Entity\LtiUserConnection;

class LtiUserRepository extends EntityRepository
{

    public function getTeachersIdByConsumer($consumer)
    {

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('lu')
            ->from(LtiUser::class, 'lu')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->setParameter('consumer', $consumer)
            ->getQuery()
            ->getResult();
        $teachersId = [];
        foreach ($results as $result) {
            array_push($teachersId, $result->getUser()->getId());
        }
        return $results;
    }

    public function getActiveTeachersIdByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('IDENTITY(luc.user)')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->groupBy('luc.user')
            ->getQuery()
            ->getResult();
        return $results;
    }

    public function getNewTeachersIdByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $insertDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('lu')
            ->from(LtiUser::class, 'lu')
            ->leftJoin(User::class, 'u', 'WITH', 'lu.user=u.id')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('u.insertDate LIKE :insertDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('insertDate', "$insertDate%")
            ->getQuery()
            ->getResult();
        $newTeachersId = [];

        foreach ($results as $result) {

            array_push($newTeachersId, $result->getUser()->getId());
        }
        return $newTeachersId;
    }

    public function getTeachersConnectionsByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('luc')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->getQuery()
            ->getResult();
        $activeTeachersId = [];
        foreach ($results as $result) {

            array_push($activeTeachersId, $result->getUser()->getId());
        }
        return $activeTeachersId;
    }

    public function getAverageTimeSpentByTeachersAndPerConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $result = $queryBuilder->select('SUM(luc.connectionDuration) AS timeSpent, COUNT(luc) AS connectionsNumber')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->getQuery()
            ->getResult();

        $averageTimeSpentByActiveTeachers = $result[0]['connectionsNumber'] === 0 ? 0 : $result[0]['timeSpent'] / $result[0]['connectionsNumber'];

        return $averageTimeSpentByActiveTeachers;
    }

    public function getStudentsIdByConsumer($consumer)
    {

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('lu')
            ->from(LtiUser::class, 'lu')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->setParameter('consumer', $consumer)
            ->getQuery()
            ->getResult();
        $studentsId = [];
        foreach ($results as $result) {
            array_push($studentsId, $result->getUser()->getId());
        }
        return $results;
    }

    public function getActiveStudentsIdByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('IDENTITY(luc.user)')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->groupBy('luc.user')
            ->getQuery()
            ->getResult();
        return $results;
    }

    public function getNewStudentsIdByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $insertDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('lu')
            ->from(LtiUser::class, 'lu')
            ->leftJoin(User::class, 'u', 'WITH', 'lu.user=u.id')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('u.insertDate LIKE :insertDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('insertDate', "$insertDate%")
            ->getQuery()
            ->getResult();
        $newTeachersId = [];

        foreach ($results as $result) {

            array_push($newTeachersId, $result->getUser()->getId());
        }
        return $newTeachersId;
    }

    public function getStudentsConnectionsByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('luc')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->getQuery()
            ->getResult();
        $activeTeachersId = [];
        foreach ($results as $result) {

            array_push($activeTeachersId, $result->getUser()->getId());
        }
        return $activeTeachersId;
    }

    public function getAverageTimeSpentByStudentsAndPerConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $result = $queryBuilder->select('SUM(luc.connectionDuration) AS timeSpent, COUNT(luc) AS connectionsNumber')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->getQuery()
            ->getResult();

        $averageTimeSpentByActiveStudents = $result[0]['connectionsNumber'] === 0 ? 0 : $result[0]['timeSpent'] / $result[0]['connectionsNumber'];
        return $averageTimeSpentByActiveStudents;
    }

    public function getLastMonthConnectionsByConsumer($consumer, $data)
    {

        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('luc')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->getQuery()
            ->getResult();

        $connectionsId = [];
        foreach ($results as $result) {
            array_push($connectionsId, $result->getId());
        }

        return $connectionsId;
    }
    public function deleteConnectionsEntries($connectionsIds)
    {
        if ($connectionsIds) {
            $queryBuilder = $this->getEntityManager()->createQueryBuilder();
            $queryBuilder->delete(LtiUserConnection::class, 'luc')
                ->andWhere('luc.id IN (:idsArray)')
                ->setParameter('idsArray', $connectionsIds)
                ->getQuery()
                ->execute();

            return;
        }
    }

    public function getInterfacesByConsumer($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('luc.interface')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'lu.user=luc.user')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->groupBy('luc.interface')
            ->getQuery()
            ->getResult();

        return $results;
    }


    public function getTeachersIdByConsumerAndInterface($consumer, $data)
    {

        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('IDENTITY(luc.user)')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'lu.user=luc.user')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->andWhere('luc.interface= :interface')
            ->setParameters(array(
                'consumer' => $consumer,
                'connectionDate' => "$connectionDate%",
                'interface' => $data->interface
            ))
            ->groupBy('luc.user')
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function getTeachersConnectionsByConsumerAndInterface($consumer, $data)
    {

        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('luc')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->andWhere('luc.interface LIKE :interface')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->setParameter('interface', $data->interface)
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function getAverageTimeSpentByTeachersPerConsumerAndInterface($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $result = $queryBuilder->select('SUM(luc.connectionDuration) AS timeSpent, COUNT(luc) AS connectionsNumber')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=1')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->andWhere('luc.interface LIKE :interface')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->setParameter('interface', $data->interface)
            ->getQuery()
            ->getResult();
        
        $averageTimeSpentByTeachers = $result[0]['connectionsNumber'] === 0 ? 0 : $result[0]['timeSpent'] / $result[0]['connectionsNumber'];

        return $averageTimeSpentByTeachers;
    }

    public function getStudentsIdByConsumerAndInterface($consumer, $data)
    {

        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('IDENTITY(luc.user)')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'lu.user=luc.user')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->andWhere('luc.interface= :interface')
            ->setParameters(array(
                'consumer' => $consumer,
                'connectionDate' => "$connectionDate%",
                'interface' => $data->interface
            ))
            ->groupBy('luc.user')
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function getStudentsConnectionsByConsumerAndInterface($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $results = $queryBuilder->select('luc')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->andWhere('luc.interface LIKE :interface')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->setParameter('interface', $data->interface)
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function getAverageTimeSpentByStudentsPerConsumerAndInterface($consumer, $data)
    {
        $formattedMonth = sprintf('%02d', $data->month);
        $connectionDate = "{$data->year}-$formattedMonth";

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $result = $queryBuilder->select('SUM(luc.connectionDuration) AS timeSpent, COUNT(luc) AS connectionsNumber')
            ->from(LtiUserConnection::class, 'luc')
            ->leftJoin(LtiUser::class, 'lu', 'WITH', 'luc.user=lu.user')
            ->andWhere('lu.isTeacher=0')
            ->andWhere('lu.ltiConsumer= :consumer')
            ->andWhere('luc.connectionDate LIKE :connectionDate')
            ->andWhere('luc.interface LIKE :interface')
            ->setParameter('consumer', $consumer)
            ->setParameter('connectionDate', "$connectionDate%")
            ->setParameter('interface', $data->interface)
            ->getQuery()
            ->getResult();

            $averageTimeSpentByStudents = $result[0]['connectionsNumber'] === 0 ? 0 : $result[0]['timeSpent'] / $result[0]['connectionsNumber'];

        return $averageTimeSpentByStudents;
    }
}
