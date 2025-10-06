<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="User\Repository\SamlUserConnectionRepository")
 * @ORM\Table(
 *     name="user_saml_user_connections",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="uniq_user_school_year", columns={"user_id","academic_year"})
 *     }
 * )
 */
class SamlUserConnection
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $user;

    /**
     * Année scolaire au format "YYYY-YYYY", ex: "2025-2026"
     * @ORM\Column(name="academic_year", type="string", length=9)
     */
    private $academicYear;

    /**
     * Début de période (inclus) — utile pour les filtres
     * @ORM\Column(name="period_start", type="date")
     */
    private $periodStart;

    /**
     * Fin de période (inclus) — utile pour les filtres
     * @ORM\Column(name="period_end", type="date")
     */
    private $periodEnd;

    /**
     * Nombre total de connexions dans la période
     * @ORM\Column(name="connections_count", type="integer", options={"default": 0})
     */
    private $connectionsCount = 0;

    /**
     * Première connexion de la période
     * @ORM\Column(name="first_connection_at", type="datetime", nullable=false)
     */
    private $firstConnectionAt;

    /**
     * Dernière connexion de la période
     * @ORM\Column(name="last_connection_at", type="datetime", nullable=false)
     */
    private $lastConnectionAt;

    /**
     * Date de la connexion courante (si tu veux garder un champ brut comme dans ta version)
     * @ORM\Column(name="connection_date", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $connectionDate;

    public function __construct(User $user, \DateTime $when = null)
    {
        $when = $when ?: new \DateTime('now');
        $this->user = $user;
        [$label, $start, $end] = self::computeAcademicYear($when);

        $this->academicYear = $label;
        $this->periodStart = $start;
        $this->periodEnd = $end;
        $this->connectionsCount = 1;
        $this->firstConnectionAt = clone $when;
        $this->lastConnectionAt = clone $when;
        $this->connectionDate = clone $when;
    }

    /**
     * Détermine l’année scolaire contenant $d.
     * Retourne [label, periodStart(DateTime), periodEnd(DateTime)]
     */
    public static function computeAcademicYear(\DateTime $d)
    {
        $y = (int) $d->format('Y');
        // Septembre (9) démarre une nouvelle année scolaire
        $start = ((int)$d->format('n') >= 9)
            ? new \DateTime("$y-09-01 00:00:00")
            : new \DateTime(($y - 1) . "-09-01 00:00:00");

        // Fin: 31 août 23:59:59 de l’année suivante
        $end = (clone $start)->modify('+1 year')->modify('-1 day')->setTime(23, 59, 59);
        $label = $start->format('Y') . '-' . $end->format('Y');

        return [$label, $start, $end];
    }

    public function touch(\DateTime $when = null)
    {
        $when = $when ?: new \DateTime('now');
        $this->connectionsCount++;
        $this->lastConnectionAt = $when;
        $this->connectionDate   = $when;
    }

    // ==== Getters / Setters minimalistes ====
    public function getId() { return $this->id; }

    public function getUser() { return $this->user; }
    public function setUser($user) { $this->user = $user; return $this; }

    public function getAcademicYear() { return $this->academicYear; }

    public function getPeriodStart() { return $this->periodStart; }
    public function getPeriodEnd() { return $this->periodEnd; }

    public function getConnectionsCount() { return $this->connectionsCount; }

    public function getFirstConnectionAt() { return $this->firstConnectionAt; }
    public function getLastConnectionAt() { return $this->lastConnectionAt; }

    public function getConnectionDate() { return $this->connectionDate; }
    public function setConnectionDate($connectionDate) { $this->connectionDate = $connectionDate; return $this; }
}
