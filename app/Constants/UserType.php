<?php

namespace App\Constants;

/**
 * User type taxonomy. Stored on users.user_type as a free-form string for
 * backwards compatibility with existing values populated from Sage `qualite`.
 *
 * DISTRIBUTEUR  — international distributor (codepays !== "FR" AND type IN cued/chud/ciub/cihbd codes)
 * ADV_INTER     — internal sales support role, processes distributor Excel exports
 * LABORATOIRE   — lab user, can order for downstream clients
 * LDA           — lab distribution admin (existing role)
 */
class UserType
{
    public const DISTRIBUTEUR = 'DISTRIBUTEUR';
    public const ADV_INTER    = 'ADV_INTER';
    public const LABORATOIRE  = 'LABORATOIRE';
    public const LDA          = 'LDA';

    /** Sage `client.type` values that qualify a non-FR user as a distributor. */
    public const DISTRIBUTOR_SAGE_TYPES = [
        'CUEDI', 'CHUDI', 'CIUBD', 'CIHBD',
    ];
}
