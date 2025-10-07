<?php

namespace App\Repository;

use App\Entity\UserConnectionHistory;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use User\Entity\Regular;

class UserConnectionHistoryRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserConnectionHistory::class);
    }

    public function findRecentConnections(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('u.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent connection entries for a given user.
     *
     * @param int $userId The ID of the user
     * @param int $limit Number of results to return
     * @return UserConnectionHistory[]
     */
    public function findConnectionsByUser(int $userId, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get connection entries for users authenticated via a specific SSO.
     *
     * @param string $ssoName Name of the SSO provider
     * @param int $limit Number of results to return
     * @return UserConnectionHistory[]
     */
    public function findConnectionsBySso(string $ssoName, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->join(Regular::class, 'r', 'WITH', 'r.user = c.userId')
            ->where('r.fromSso = :sso')
            ->setParameter('sso', $ssoName)
            ->orderBy('c.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count the number of connections for a given SSO within a time range.
     *
     * @param string $ssoName Name of the SSO provider
     * @param \DateTime $from Start date
     * @param \DateTime $to End date
     * @return int Number of connections
     */
    public function countConnectionsBySso(string $ssoName, \DateTime $from, \DateTime $to): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join(Regular::class, 'r', 'WITH', 'r.user = c.userId')
            ->where('r.fromSso = :sso')
            ->andWhere('c.timestamp BETWEEN :from AND :to')
            ->setParameter('sso', $ssoName)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get the most recent distinct devices used by a user.
     *
     * @param int $userId The ID of the user
     * @param int $limit Number of distinct devices to return
     * @return string[] List of device names
     */
    public function getRecentDevices(int $userId, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('DISTINCT c.device')
            ->where('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'device');
    }

    /**
     * Get connection entries for a user filtered by country.
     *
     * @param int $userId The ID of the user
     * @param string $country ISO 2-letter country code
     * @return UserConnectionHistory[]
     */
    public function findConnectionsByCountry(int $userId, string $country): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->andWhere('c.country = :country')
            ->setParameter('userId', $userId)
            ->setParameter('country', $country)
            ->orderBy('c.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

}
