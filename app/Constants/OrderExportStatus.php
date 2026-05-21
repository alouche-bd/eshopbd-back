<?php

namespace App\Constants;

class OrderExportStatus
{
    public const PENDING  = 'pending';   // saved, no Excel yet
    public const EXPORTED = 'exported';  // Excel generated + mailed to ADV_INTER
    public const SENT     = 'sent';      // ZSOH payload sent to Sage X3
    public const FAILED   = 'failed';    // last sendorder attempt failed
}
