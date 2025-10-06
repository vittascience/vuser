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
        [$academicYear] = SamlUserConnection::computeAcademicYear($when);

        $connection = $this->findOneBy([
            'user' => $user,
            'academicYear' => $academicYear,
        ]);

        $em = $this->getEntityManager();

        if ($connection === null) {
            $connection = new SamlUserConnection($user, $when);
            $em->persist($connection);
        } else {
            $connection->touch($when);
        }

        $em->flush();
    }
}
