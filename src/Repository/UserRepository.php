<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\User;
use User\Entity\Regular;
use Doctrine\ORM\Query\Expr\Join;
use User\Entity\Teacher;
use Classroom\Entity\Groups;
use User\Entity\UserPremium;
use Classroom\Entity\UsersLinkGroups;
use Classroom\Entity\UsersRestrictions;

class UserRepository extends EntityRepository
{
    public function getMultipleUsers($array)
    {
        $queryBuilder = $this->getEntityManager()
            ->createQueryBuilder();
        $query = $queryBuilder
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN(' . implode(', ', $array) . ")")
            ->getQuery();
        return $query->getResult();
    }

    public function getNewsLetterMembers()
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("r.email, u.firstname, u.surname")
            ->from(User::class, 'u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'u.id = r.user')
            ->where('r.newsletter = 1')
            ->getQuery();
        return $query->getResult();
    }


    public function findPaginated(
        int $page = 1,
        int $perPage = 25,
        ?string $search = null,
        $sort = 'id',
        string $dir = 'asc',
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $orderable = [
            'id'         => 'u.id',
            'firstname'  => 'u.firstname',
            'surname'    => 'u.surname',
            'email'      => 'r.email',
            'newsletter' => 'r.newsletter',
            'is_active'  => 'r.active',
            'is_admin'   => 'r.isAdmin',
            'isFromSSO'  => 'r.fromSso',
            'teacher'    => '(CASE WHEN t.user IS NULL THEN 0 ELSE 1 END)',
        ];

        $filterable = [
            'email'      => ['expr' => 'r.email',     'type' => 'string'],
            'email~'     => ['expr' => 'r.email',     'type' => 'like'],
            'firstname'  => ['expr' => 'u.firstname', 'type' => 'string'],
            'firstname~' => ['expr' => 'u.firstname', 'type' => 'like'],
            'surname'    => ['expr' => 'u.surname',   'type' => 'string'],
            'surname~'   => ['expr' => 'u.surname',   'type' => 'like'],

            'newsletter' => ['expr' => 'r.newsletter', 'type' => 'bool'],
            'is_active'  => ['expr' => 'r.active',    'type' => 'bool'],
            'is_admin'   => ['expr' => 'r.isAdmin',   'type' => 'bool'],

            'isFromSSO'      => ['expr' => 'r.fromSso', 'type' => 'string'],
            'isFromSSO~'     => ['expr' => 'r.fromSso', 'type' => 'like'],
            'isFromSSO:null' => ['expr' => 'r.fromSso', 'type' => 'null'],
            'isFromSSO:notnull' => ['expr' => 'r.fromSso', 'type' => 'notnull'],

            'teacher'    => ['expr' => 't.user', 'type' => 'exists'],
        ];

        // total
        $total = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()->getSingleScalarResult();

        // requête de base
        $qb = $this->createQueryBuilder('u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'r.user = u')
            ->leftJoin(Teacher::class,  't', Join::WITH, 't.user = u')
            ->select("
            u.id         AS id,
            u.firstname  AS firstname,
            u.surname    AS surname,
            r.email      AS email,
            r.newsletter AS newsletter,
            r.active     AS is_active,
            r.isAdmin    AS is_admin,
            r.fromSso    AS isFromSSO,
            (CASE WHEN t.user IS NULL THEN 0 ELSE 1 END) AS teacher
        ");

        if ($search !== null && $search !== '') {
            $q = mb_strtolower($search);
            $qb->andWhere('LOWER(u.firstname) LIKE :q OR LOWER(u.surname) LIKE :q OR LOWER(r.email) LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $i = 0;
        foreach ($filters as $key => $value) {
            $i++;
            if (isset($filterable[$key . ':null']) && ($value === null || $value === 'null')) {
                $qb->andWhere($filterable[$key . ':null']['expr'] . ' IS NULL');
                continue;
            }
            if (isset($filterable[$key . ':notnull']) && ($value === 'notnull')) {
                $qb->andWhere($filterable[$key . ':notnull']['expr'] . ' IS NOT NULL');
                continue;
            }

            if (!isset($filterable[$key])) {
                continue;
            }
            $meta = $filterable[$key];
            $param = 'f_' . $i;

            switch ($meta['type']) {
                case 'bool':
                    $qb->andWhere($meta['expr'] . ' = :' . $param)
                        ->setParameter($param, filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0);
                    break;
                case 'string':
                    $qb->andWhere($meta['expr'] . ' = :' . $param)
                        ->setParameter($param, $value);
                    break;
                case 'like':
                    $qb->andWhere('LOWER(' . $meta['expr'] . ') LIKE :' . $param)
                        ->setParameter($param, '%' . mb_strtolower((string)$value) . '%');
                    break;
                case 'exists':
                    $truthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? true : false;
                    $qb->andWhere($meta['expr'] . ($truthy ? ' IS NOT NULL' : ' IS NULL'));
                    break;
                case 'null':
                    $qb->andWhere($meta['expr'] . ' IS NULL');
                    break;
                case 'notnull':
                    $qb->andWhere($meta['expr'] . ' IS NOT NULL');
                    break;
            }
        }

        $filtered = (int) (clone $qb)
            ->resetDQLPart('select')
            ->select('COUNT(DISTINCT u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        $orders = [];
        if (is_array($sort)) {
            $orders = $sort;
        } elseif (is_string($sort) && strpos($sort, ':') !== false) {
            foreach (explode(',', $sort) as $part) {
                [$f, $d] = array_map('trim', explode(':', $part) + [null, null]);
                if ($f) $orders[] = ['field' => $f, 'dir' => strtolower($d ?? 'asc')];
            }
        } else {
            $orders[] = ['field' => $sort, 'dir' => $dir];
        }

        foreach ($orders as $o) {
            $f = $o['field'] ?? 'id';
            $d = (isset($o['dir']) && strtolower($o['dir']) === 'desc') ? 'DESC' : 'ASC';
            $expr = $orderable[$f] ?? 'u.id';
            $qb->addOrderBy($expr, $d);
        }

        if (!array_key_exists('id', array_column($orders, 'field', 'field'))) {
            $qb->addOrderBy('u.id', 'ASC');
        }

        $rows = $qb->setFirstResult($offset)->setMaxResults($perPage)->getQuery()->getArrayResult();

        foreach ($rows as &$row) {
            $row['newsletter'] = (int) ($row['newsletter'] ?? 0);
            $row['is_active']  = (int) ($row['is_active']  ?? 0);
            $row['is_admin']   = (int) ($row['is_admin']   ?? 0);
            $row['teacher']    = ((int)$row['teacher']) === 1;
        }

        return [
            'items'    => $rows,
            'total'    => $total,
            'filtered' => $filtered,
            'page'     => $page,
            'perPage'  => $perPage,
            'sort'     => $orders,
        ];
    }


    public function getUserFromSSO(string $ssoId): ?User
    {
        return $this->createQueryBuilder('u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'r.user = u')
            ->where('r.fromSso like :ssoId')
            ->setParameter('ssoId', '%' . $ssoId . '%')
            ->getQuery()
            ->getOneOrNullResult();
    }



    public function findPaginatedUpdated(
        int $page = 1,
        int $perPage = 25,
        ?string $search = null,
        $sort = 'id',
        string $dir = 'asc',
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        // ---- dates "now" pour les sous-requêtes ----
        $now = new \DateTimeImmutable('now');

        // 🔹 NEW: sous-requêtes prêtes à réutiliser (strings DQL)
        // Personal active restrictions
        $existsUR = "EXISTS(
        SELECT ur1.id FROM " . UsersRestrictions::class . " ur1
        WHERE ur1.user = u.id AND (ur1.dateEnd IS NULL OR ur1.dateEnd > :now)
    )";
        // Group active (via link)
        $existsG = "EXISTS(
        SELECT g1.id FROM " . UsersLinkGroups::class . " ulg1
        JOIN " . Groups::class . " g1 WITH g1.id = ulg1.group
        WHERE ulg1.user = u.id AND (g1.dateEnd IS NULL OR g1.dateEnd > :now)
    )";
        // Legacy personal premium
        $existsUP = "EXISTS(
        SELECT up1.id FROM " . UserPremium::class . " up1
        WHERE up1.user = u.id
    )";

        // Dates perso / groupe par MAX (si plusieurs enregistrements)
        $urDateBegin = "(SELECT MAX(ur2.dateBegin) FROM " . UsersRestrictions::class . " ur2
                     WHERE ur2.user = u.id AND (ur2.dateEnd IS NULL OR ur2.dateEnd > :now))";
        $urDateEnd   = "(SELECT MAX(ur3.dateEnd)   FROM " . UsersRestrictions::class . " ur3
                     WHERE ur3.user = u.id AND (ur3.dateEnd IS NULL OR ur3.dateEnd > :now))";

        $gDateBegin  = "(SELECT MAX(g2.dateBegin) FROM " . UsersLinkGroups::class . " ulg2
                    JOIN " . Groups::class . " g2 WITH g2.id = ulg2.group
                    WHERE ulg2.user = u.id AND (g2.dateEnd IS NULL OR g2.dateEnd > :now))";
        $gDateEnd    = "(SELECT MAX(g3.dateEnd)   FROM " . UsersLinkGroups::class . " ulg3
                    JOIN " . Groups::class . " g3 WITH g3.id = ulg3.group
                    WHERE ulg3.user = u.id AND (g3.dateEnd IS NULL OR g3.dateEnd > :now))";

        // triables
        $orderable = [
            'id'         => 'u.id',
            'firstname'  => 'u.firstname',
            'surname'    => 'u.surname',
            'email'      => 'r.email',
            'newsletter' => 'r.newsletter',
            'is_active'  => 'r.active',
            'is_admin'   => 'r.isAdmin',
            'isFromSSO'  => 'r.fromSso',
            'teacher'    => '(CASE WHEN t.user IS NULL THEN 0 ELSE 1 END)',

            // 🔹 NEW
            'premium'        => 'premium',
            'premiumType'    => 'premiumType',
            'premiumBegin'   => 'premiumDateBegin',
            'premiumEnd'     => 'premiumDateEnd',
        ];

        // filtrables
        $filterable = [
            'email'      => ['expr' => 'r.email',     'type' => 'string'],
            'email~'     => ['expr' => 'r.email',     'type' => 'like'],
            'firstname'  => ['expr' => 'u.firstname', 'type' => 'string'],
            'firstname~' => ['expr' => 'u.firstname', 'type' => 'like'],
            'surname'    => ['expr' => 'u.surname',   'type' => 'string'],
            'surname~'   => ['expr' => 'u.surname',   'type' => 'like'],

            'newsletter' => ['expr' => 'r.newsletter', 'type' => 'bool'],
            'is_active'  => ['expr' => 'r.active',    'type' => 'bool'],
            'is_admin'   => ['expr' => 'r.isAdmin',   'type' => 'bool'],

            'isFromSSO'      => ['expr' => 'r.fromSso', 'type' => 'string'],
            'isFromSSO~'     => ['expr' => 'r.fromSso', 'type' => 'like'],
            'isFromSSO:null' => ['expr' => 'r.fromSso', 'type' => 'null'],
            'isFromSSO:notnull' => ['expr' => 'r.fromSso', 'type' => 'notnull'],

            'teacher'    => ['expr' => 't.user', 'type' => 'exists'],

            // 🔹 NEW
            'premium'        => ['expr' => 'premium',         'type' => 'bool'],
            'premiumType'    => ['expr' => 'premiumType',     'type' => 'string'],
            'premiumType~'   => ['expr' => 'premiumType',     'type' => 'like'],
            'premiumBegin'   => ['expr' => 'premiumDateBegin', 'type' => 'string'], // tu peux mettre un opérateur côté appelant
            'premiumEnd'     => ['expr' => 'premiumDateEnd',  'type' => 'string'],
        ];

        // total
        $total = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()->getSingleScalarResult();

        // base
        $qb = $this->createQueryBuilder('u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'r.user = u')
            ->leftJoin(Teacher::class,  't', Join::WITH, 't.user = u')
            ->select("
            u.id         AS id,
            u.firstname  AS firstname,
            u.surname    AS surname,
            r.email      AS email,
            r.newsletter AS newsletter,
            r.active     AS is_active,
            r.isAdmin    AS is_admin,
            r.fromSso    AS isFromSSO,
            (CASE WHEN t.user IS NULL THEN 0 ELSE 1 END) AS teacher,

            -- 🔹 NEW: bool premium (0/1)
            (CASE
                WHEN $existsUR THEN 1
                WHEN $existsG  THEN 1
                WHEN $existsUP THEN 1
                WHEN r.isPremium = 1 THEN 1
                ELSE 0
            END) AS premium,

            -- 🔹 NEW: type priorisé
            (CASE
                WHEN $existsUR THEN 'PersonalPremium'
                WHEN $existsG  THEN 'GroupPremium'
                WHEN $existsUP THEN 'LegacyPersonalPremium'
                WHEN r.isPremium = 1 THEN 'RegularFlag'
                ELSE 'free'
            END) AS premiumType,

            -- 🔹 NEW: dates (perso prioritaire, sinon groupe)
            COALESCE($urDateBegin, $gDateBegin) AS premiumDateBegin,
            COALESCE($urDateEnd,   $gDateEnd)   AS premiumDateEnd
        ")
            ->setParameter('now', $now);

        if ($search !== null && $search !== '') {
            $q = mb_strtolower($search);
            $qb->andWhere('LOWER(u.firstname) LIKE :q OR LOWER(u.surname) LIKE :q OR LOWER(r.email) LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // filtres
        $i = 0;
        foreach ($filters as $key => $value) {
            $i++;
            if (isset($filterable[$key . ':null']) && ($value === null || $value === 'null')) {
                $qb->andWhere($filterable[$key . ':null']['expr'] . ' IS NULL');
                continue;
            }
            if (isset($filterable[$key . ':notnull']) && ($value === 'notnull')) {
                $qb->andWhere($filterable[$key . ':notnull']['expr'] . ' IS NOT NULL');
                continue;
            }
            if (!isset($filterable[$key])) {
                continue;
            }
            $meta = $filterable[$key];
            $param = 'f_' . $i;

            switch ($meta['type']) {
                case 'bool':
                    $qb->andWhere($meta['expr'] . ' = :' . $param)
                        ->setParameter($param, filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0);
                    break;
                case 'string':
                    // NOTE: si tu veux faire des comparaisons de dates, passe un \DateTimeImmutable côté appelant
                    $qb->andWhere($meta['expr'] . ' = :' . $param)
                        ->setParameter($param, $value);
                    break;
                case 'like':
                    $qb->andWhere('LOWER(' . $meta['expr'] . ') LIKE :' . $param)
                        ->setParameter($param, '%' . mb_strtolower((string)$value) . '%');
                    break;
                case 'exists':
                    $truthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? true : false;
                    $qb->andWhere($meta['expr'] . ($truthy ? ' IS NOT NULL' : ' IS NULL'));
                    break;
                case 'null':
                    $qb->andWhere($meta['expr'] . ' IS NULL');
                    break;
                case 'notnull':
                    $qb->andWhere($meta['expr'] . ' IS NOT NULL');
                    break;
            }
        }

        // comptage post-filtres (sans perturber $qb)
        $filtered = (int) (clone $qb)
            ->resetDQLPart('select')
            ->select('COUNT(DISTINCT u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        // tris
        $orders = [];
        if (is_array($sort)) {
            $orders = $sort;
        } elseif (is_string($sort) && strpos($sort, ':') !== false) {
            foreach (explode(',', $sort) as $part) {
                [$f, $d] = array_map('trim', explode(':', $part) + [null, null]);
                if ($f) $orders[] = ['field' => $f, 'dir' => strtolower($d ?? 'asc')];
            }
        } else {
            $orders[] = ['field' => $sort, 'dir' => $dir];
        }

        foreach ($orders as $o) {
            $f = $o['field'] ?? 'id';
            $d = (isset($o['dir']) && strtolower($o['dir']) === 'desc') ? 'DESC' : 'ASC';
            $expr = $orderable[$f] ?? 'u.id';
            $qb->addOrderBy($expr, $d);
        }
        if (!array_key_exists('id', array_column($orders, 'field', 'field'))) {
            $qb->addOrderBy('u.id', 'ASC');
        }

        // pas besoin de GROUP BY: aucune duplication grâce aux EXISTS/sous-requêtes
        $rows = $qb->setFirstResult($offset)->setMaxResults($perPage)->getQuery()->getArrayResult();

        foreach ($rows as &$row) {
            $row['newsletter'] = (int) ($row['newsletter'] ?? 0);
            $row['is_active']  = (int) ($row['is_active']  ?? 0);
            $row['is_admin']   = (int) ($row['is_admin']   ?? 0);
            $row['teacher']    = ((int)$row['teacher']) === 1;

            // 🔹 NEW: normalisation premium -> bool
            $row['premium']    = ((int)($row['premium'] ?? 0)) === 1;
            // Optionnel: caster les dates en string ISO si besoin d’API JSON propre
            // if ($row['premiumDateBegin'] instanceof \DateTimeInterface) { ... }
        }

        return [
            'items'    => $rows,
            'total'    => $total,
            'filtered' => $filtered,
            'page'     => $page,
            'perPage'  => $perPage,
            'sort'     => $orders,
        ];
    }
}
