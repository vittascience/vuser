<?php

namespace User\Repository;

use Doctrine\ORM\EntityRepository;
use User\Entity\User;
use User\Entity\Regular;
use Doctrine\ORM\Query\Expr\Join;
use User\Entity\Teacher;

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
}
