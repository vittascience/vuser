<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\SamlUserConnection;
use User\Entity\User;

class SamlUserConnectionRepository extends EntityRepository
{
   public function trackLogin(User $user, \DateTime $when = null): void
    {
        $when = $when ?: new \DateTime('now');
        [$label] = SamlUserConnection::computeAcademicYear($when);

        $qb = $this->getEntityManager()->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.academicYear = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $label)
            ->setMaxResults(1);

        $conn = $qb->getQuery()->getOneOrNullResult();

        $em = $this->getEntityManager();

        if ($conn === null) {
            $conn = new SamlUserConnection($user, $when);
            $em->persist($conn);
        } else {
            $conn->touch($when);
        }

        $em->flush();
    }
}
