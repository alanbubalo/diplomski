<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\IntermediaryResponse;

/** Vanjska granica kojoj se predaje dokument ili fiskalizacijska poruka. */
interface SubmissionEndpoint
{
    public function send(string $senderKey, string $compositeIdentifier): IntermediaryResponse;
}
