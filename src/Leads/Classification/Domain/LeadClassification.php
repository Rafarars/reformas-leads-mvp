<?php

namespace Reformas\Leads\Classification\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Shared\Domain\AggregateRoot;

/**
 * @phpstan-type LeadClassificationPrimitives array{
 *     lead_id: string,
 *     status: string,
 *     category: ?string,
 *     urgency: ?string,
 *     confidence: ?float,
 *     reason: ?string,
 *     model: ?string,
 *     review_reason: ?string,
 *     reviewed_by: ?string,
 *     created_at: string,
 *     updated_at: string
 * }
 */
final class LeadClassification extends AggregateRoot
{
    private function __construct(
        private readonly LeadId $leadId,
        private ClassificationStatus $status,
        private ?WorkCategory $category,
        private ?Urgency $urgency,
        private ?ClassificationConfidence $confidence,
        private ?ClassificationReason $reason,
        private readonly ?string $model,
        private ?string $reviewReason,
        private ?string $reviewedBy,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    // Por debajo del umbral la propuesta se guarda, pero una persona decide.
    public static function fromProposal(LeadId $leadId, ClassificationProposal $proposal, float $minConfidence, DateTimeImmutable $now): self
    {
        $doubtful = $proposal->confidence->isBelow($minConfidence);

        return new self(
            $leadId,
            $doubtful ? ClassificationStatus::NeedsReview : ClassificationStatus::Classified,
            $proposal->category,
            $proposal->urgency,
            $proposal->confidence,
            $proposal->reason,
            $proposal->model,
            $doubtful ? 'La IA no está segura de la clasificación.' : null,
            null,
            $now,
            $now,
        );
    }

    // Sin propuesta usable: el lead no se queda sin atender, pasa a una persona.
    public static function unclassified(LeadId $leadId, string $why, DateTimeImmutable $now): self
    {
        return new self($leadId, ClassificationStatus::NeedsReview, null, null, null, null, null, $why, null, $now, $now);
    }

    // La decisión humana manda: sustituye a la de la IA, que queda en el motivo y el modelo.
    public function review(WorkCategory $category, Urgency $urgency, string $reviewer, DateTimeImmutable $now): void
    {
        $this->category = $category;
        $this->urgency = $urgency;
        $this->status = ClassificationStatus::Reviewed;
        $this->reviewReason = null;
        $this->reviewedBy = $reviewer;
        $this->updatedAt = $now;
    }

    public function leadId(): LeadId
    {
        return $this->leadId;
    }

    /**
     * @param  LeadClassificationPrimitives  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            new LeadId($primitives['lead_id']),
            ClassificationStatus::from($primitives['status']),
            $primitives['category'] === null ? null : WorkCategory::from($primitives['category']),
            $primitives['urgency'] === null ? null : Urgency::from($primitives['urgency']),
            $primitives['confidence'] === null ? null : new ClassificationConfidence($primitives['confidence']),
            $primitives['reason'] === null ? null : new ClassificationReason($primitives['reason']),
            $primitives['model'],
            $primitives['review_reason'],
            $primitives['reviewed_by'],
            new DateTimeImmutable($primitives['created_at']),
            new DateTimeImmutable($primitives['updated_at']),
        );
    }

    /**
     * @return LeadClassificationPrimitives
     */
    public function toPrimitives(): array
    {
        return [
            'lead_id' => $this->leadId->value,
            'status' => $this->status->value,
            'category' => $this->category?->value,
            'urgency' => $this->urgency?->value,
            'confidence' => $this->confidence?->value,
            'reason' => $this->reason?->value,
            'model' => $this->model,
            'review_reason' => $this->reviewReason,
            'reviewed_by' => $this->reviewedBy,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
