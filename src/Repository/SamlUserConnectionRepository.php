<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\SamlUserConnection;
use User\Entity\User;

class SamlUserConnectionRepository extends EntityRepository
{
    public function trackLogin(User $user, ?\DateTimeInterface $when = null): void
    {
        $when = $when ?? new \DateTimeImmutable('now');
        [$academicYear] = SamlUserConnection::computeAcademicYear($when);

        $em = $this->getEntityManager();
        $repo = $em->getRepository(SamlUserConnection::class);

        $connection = $repo->findOneBy([
            'user' => $user,
            'academicYear' => $academicYear,
        ]);

        if ($connection === null) {
            $connection = new SamlUserConnection($user, $when);
            $em->persist($connection);
        } else {
            $connection->touch($when);
        }

        $em->flush();
    }
}
