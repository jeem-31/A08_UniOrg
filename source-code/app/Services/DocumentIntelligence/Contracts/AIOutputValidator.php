<?php

namespace App\Services\DocumentIntelligence\Contracts;

use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\ValidatedAIOutput;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;

interface AIOutputValidator
{
    /** @throws InvalidAIOutputException */
    public function validate(CandidateAIOutput $candidate): ValidatedAIOutput;
}
