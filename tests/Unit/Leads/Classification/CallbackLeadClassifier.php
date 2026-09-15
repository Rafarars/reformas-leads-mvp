<?php

namespace Tests\Unit\Leads\Classification;

use Closure;
use Reformas\Leads\Classification\Domain\ClassificationProposal;
use Reformas\Leads\Classification\Domain\LeadClassifier;
use Reformas\Leads\Classification\Domain\LeadToClassify;

final class CallbackLeadClassifier implements LeadClassifier
{
    /** @var list<LeadToClassify> */
    public array $received = [];

    /**
     * @param  Closure(LeadToClassify): ClassificationProposal  $respond
     */
    public function __construct(private readonly Closure $respond) {}

    public static function proposing(ClassificationProposal $proposal): self
    {
        return new self(static fn (): ClassificationProposal => $proposal);
    }

    public static function throwing(\Throwable $exception): self
    {
        return new self(static fn (): ClassificationProposal => throw $exception);
    }

    public function classify(LeadToClassify $lead): ClassificationProposal
    {
        $this->received[] = $lead;

        return ($this->respond)($lead);
    }
}
