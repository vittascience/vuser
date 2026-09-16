<?php

namespace User\Gar;

/**
 * Determines whether a GAR user is old enough to be assigned generative AI (IAG) activities,
 * per the Ministère de l'Éducation nationale's "Cadre d'usage de l'IA en éducation" (juin 2025),
 * which restricts pedagogical use of generative AI to students in 4e and above.
 *
 * Eligibility is derived from the E_MS4/P_MS4 GAR attribute (stored as ClassroomUser::garMs4),
 * which is a MEF_STAT_4 code from the BCN nomenclature:
 * https://bcn.depp.education.fr/bcn/index.php/workspace/viewTable/n/N_MEF_STAT_4/d/29
 */
class GarIagEligibility
{
    /**
     * MEF_STAT_4 codes known to represent a level strictly below 4e, or whose level cannot
     * be confidently mapped to a standard grade (SES / classe atelier — dispositifs relais
     * with their own 1-to-6 "année" numbering, not aligned with collège grades).
     * Anything in this list, or not present in the nomenclature at all, is excluded.
     */
    private const EXCLUDED_CODES = [
        // Maternelle
        '1110', '1111', '1112', '1113',
        // Élémentaire (CP, CE1, CE2, CM1, CM2)
        '1121', '1122', '1123', '1124', '1125',
        // 1er degré : initiation / adaptation / enseignement spécial
        '1211', '1311', '1411',
        // Collège général : 6e, 5e
        '2111', '2112',
        // SEGPA : 6e, 5e
        '2431', '2432',
        // SES (ancien nom de la SEGPA, dispositif relais, niveau non garanti) : toutes années
        '2411', '2412', '2413', '2414', '2415', '2416',
        // Classe atelier (dispositif relais, niveau non garanti) : toutes années
        '2421', '2422', '2423', '2424', '2425', '2426',
    ];

    /**
     * @param string|null $mefStat4Code the raw E_MS4/P_MS4 value (ClassroomUser::garMs4)
     * @return bool true only if the code is known and confirmed to be 4e or above
     */
    public static function isEligible(?string $mefStat4Code): bool
    {
        if (empty($mefStat4Code)) {
            return false;
        }

        return !in_array($mefStat4Code, self::EXCLUDED_CODES, true);
    }
}
