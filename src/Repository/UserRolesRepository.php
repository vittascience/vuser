<?php

namespace User\Repository;

use User\Entity\UserRoles;
use Doctrine\ORM\EntityRepository;

class UserRolesRepository extends EntityRepository
{
    /**
     * Find a role by its name
     * @param string $name
     * @return UserRoles|null
     */
    public function findByName(string $name): ?UserRoles
    {
        return $this->findOneBy(['name' => $name, 'active' => true]);
    }

    /**
     * Find a role by its ID
     * @param int $id
     * @return UserRoles|null
     */
    public function findById(int $id): ?UserRoles
    {
        return $this->findOneBy(['id' => $id, 'active' => true]);
    }
}
