<?php

namespace User\Repository;

use User\Entity\User;
use User\Entity\Regular;
use User\Entity\Teacher;
use User\Entity\UserPremium;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\EntityRepository;
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


    public function getUsersFromSSO(string $ssoId)
    {
        return $this->createQueryBuilder('u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'r.user = u')
            ->where('r.fromSso like :ssoId')
            ->setParameter('ssoId', '%' . $ssoId . '%')
            ->getQuery()
            ->getResult();
    }



    public function findPaginated(int $page = 1, int $perPage = 25, ?string $search = null, $sort = 'id', string $dir = 'asc', array $filters = []): array {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $now = new \DateTimeImmutable('now');

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

            // existants
            'premium'        => 'premium',
            'premiumType'    => 'premiumType',
            'premiumBegin'   => 'premiumDateBegin',
            'premiumEnd'     => 'premiumDateEnd',

            // nouveaux tris utiles
            'premiumLegacy'   => 'premiumLegacy',
            'premiumPersonal' => 'premiumPersonal',
            'premiumGroup'    => 'premiumGroup',
            'legacyEnd'       => 'legacyDateEnd',
            'personalEnd'     => 'personalDateEnd',
            'groupEnd'        => 'groupDateEnd',
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

            'premium'         => ['expr' => 'premium',         'type' => 'bool'],
            'premiumLegacy'   => ['expr' => 'premiumLegacy',   'type' => 'bool'],
            'premiumPersonal' => ['expr' => 'premiumPersonal', 'type' => 'bool'],
            'premiumGroup'    => ['expr' => 'premiumGroup',    'type' => 'bool'],
        ];

        $total = (int) $this->createQueryBuilder('u')->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $qb = $this->createQueryBuilder('u')
            ->innerJoin(Regular::class, 'r', Join::WITH, 'r.user = u')
            ->leftJoin(Teacher::class,  't', Join::WITH, 't.user = u')
            ->leftJoin(UserPremium::class,'up',Join::WITH,'up.user = u AND (up.dateEnd IS NULL OR up.dateEnd > :now)')
            ->leftJoin(UsersRestrictions::class, 'ur', Join::WITH, 'ur.user = u AND ur.dateEnd IS NOT NULL AND ur.dateEnd > :now')
            ->leftJoin(UsersLinkGroups::class, 'ulg', Join::WITH, 'ulg.user = u')
            ->leftJoin('ulg.group', 'g', Join::WITH, '(g.dateEnd IS NULL OR g.dateEnd > :now)')
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

            -- dates par type (max si multiples)
            MAX(up.dateBegin)     AS legacyDateBegin,
            MAX(up.dateEnd)       AS legacyDateEnd,
            MAX(ur.dateBegin)     AS personalDateBegin,
            MAX(ur.dateEnd)       AS personalDateEnd,
            MAX(g.dateBegin)      AS groupDateBegin,
            MAX(g.dateEnd)        AS groupDateEnd,

            -- flags par type
            (CASE WHEN COUNT(up.user)        > 0 THEN 1 ELSE 0 END) AS premiumLegacy,
            (CASE WHEN COUNT(DISTINCT ur.id) > 0 THEN 1 ELSE 0 END) AS premiumPersonal,
            (CASE WHEN COUNT(DISTINCT g.id)  > 0 THEN 1 ELSE 0 END) AS premiumGroup,

            -- bool global
            (CASE
                WHEN (COUNT(up.user) > 0 OR COUNT(DISTINCT ur.id) > 0 OR COUNT(DISTINCT g.id) > 0)
                THEN 1 ELSE 0
            END) AS premium,

            COALESCE(
                MAX(ur.dateBegin),
                MAX(g.dateBegin),
                MAX(up.dateBegin)
            ) AS premiumDateBegin,
            COALESCE(
                MAX(ur.dateEnd),
                MAX(g.dateEnd),
                MAX(up.dateEnd)
            ) AS premiumDateEnd,

            -- type unique (priorité: Personal > Group > Legacy > free)
            (CASE
                WHEN (COUNT(DISTINCT ur.id) > 0) THEN 'PersonalPremium'
                WHEN (COUNT(DISTINCT g.id)  > 0) THEN 'GroupPremium'
                WHEN (COUNT(up.user)        > 0) THEN 'LegacyPersonalPremium'
                ELSE 'free'
            END) AS premiumType
        ")
            ->setParameter('now', $now)
            ->groupBy('u.id, r.email, r.newsletter, r.active, r.isAdmin, r.fromSso, t.user');

        // recherche globale
        if ($search !== null && $search !== '') {
            $q = mb_strtolower($search);
            $qb->andWhere('LOWER(u.firstname) LIKE :q OR LOWER(u.surname) LIKE :q OR LOWER(r.email) LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // filtres dynamiques
        $i = 0;
        foreach ($filters as $key => $value) {
            $i++;
            if (isset($filterable[$key . ':null']) && ($value === null || $value === 'null')) {
                $qb->andWhere($filterable[$key . ':null']['expr'] . ' IS NULL');
                continue;
            }
            if (isset($filterable[$key . ':notnull']) && $value === 'notnull') {
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

        $filtered = (int) (clone $qb)->resetDQLPart('select')->resetDQLPart('orderBy')->resetDQLPart('groupBy')->select('COUNT(DISTINCT u.id)')->getQuery()->getSingleScalarResult();

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

        // exécution
        $rows = $qb->setFirstResult($offset)->setMaxResults($perPage)->getQuery()->getArrayResult();

        // normalisation
        foreach ($rows as &$row) {
            // normalisation existante
            $row['newsletter'] = (int) ($row['newsletter'] ?? 0);
            $row['is_active']  = (int) ($row['is_active']  ?? 0);
            $row['is_admin']   = (int) ($row['is_admin']   ?? 0);
            $row['teacher']    = ((int)$row['teacher']) === 1;

            // flags -> bool
            $premium           = ((int)($row['premium'] ?? 0)) === 1;
            $premiumLegacy     = ((int)($row['premiumLegacy'] ?? 0)) === 1;
            $premiumPersonal   = ((int)($row['premiumPersonal'] ?? 0)) === 1;
            $premiumGroup      = ((int)($row['premiumGroup'] ?? 0)) === 1;

            // liste des types actifs (ordre arbitraire)
            $types = [];
            if ($premiumPersonal) $types[] = 'PersonalPremium';
            if ($premiumGroup)    $types[] = 'GroupPremium';
            if ($premiumLegacy)   $types[] = 'LegacyPersonalPremium';

            // type prioritaire (Personal > Group > Legacy > free)
            $primaryType = 'free';
            if ($premiumPersonal) $primaryType = 'PersonalPremium';
            elseif ($premiumGroup)    $primaryType = 'GroupPremium';
            elseif ($premiumLegacy)   $primaryType = 'LegacyPersonalPremium';

            // construction premiumData
            $row['premiumData'] = [
                'active'      => $premium,
                'types'       => $types,        // ex: ['GroupPremium','LegacyPersonalPremium']
                'primaryType' => $primaryType,  // ex: 'GroupPremium'

                // dates "globales" (déjà priorisées dans le SELECT: perso > group > legacy)
                'dateBegin'   => $row['premiumDateBegin'] ?? null,
                'dateEnd'     => $row['premiumDateEnd']   ?? null,

                // détails par type
                'legacy' => [
                    'active'    => $premiumLegacy,
                    'dateBegin' => $row['legacyDateBegin'] ?? null,
                    'dateEnd'   => $row['legacyDateEnd']   ?? null,
                ],
                'personal' => [
                    'active'    => $premiumPersonal,
                    'dateBegin' => $row['personalDateBegin'] ?? null,
                    'dateEnd'   => $row['personalDateEnd']   ?? null,
                ],
                'group' => [
                    'active'    => $premiumGroup,
                    'dateBegin' => $row['groupDateBegin'] ?? null,
                    'dateEnd'   => $row['groupDateEnd']   ?? null,
                ],
            ];

            // on nettoie les champs à plat pour ne garder que premiumData
            unset(
                $row['premium'],
                $row['premiumLegacy'],
                $row['premiumPersonal'],
                $row['premiumGroup'],
                $row['premiumType'],
                $row['premiumDateBegin'],
                $row['premiumDateEnd'],
                $row['legacyDateBegin'],
                $row['legacyDateEnd'],
                $row['personalDateBegin'],
                $row['personalDateEnd'],
                $row['groupDateBegin'],
                $row['groupDateEnd']
            );
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
