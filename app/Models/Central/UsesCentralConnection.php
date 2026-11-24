<?php

namespace App\Models\Central;

use App\Tenancy\Tenancy;

/** A model of the central database: it never follows the clinic that is open. */
trait UsesCentralConnection
{
    public function getConnectionName()
    {
        return app(Tenancy::class)->central();
    }
}
