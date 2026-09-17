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
 *     summary: ?string,
 *     missing_information: ?list<string>,
 *     contradictions: ?list<string>,
 *     draft_message: ?string,
 *     draft_status: ?string,
 *     draft_approved_by: ?string,
 *     draft_approved_at: ?string,
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
        private readonly ?ClassificationConfidence $confidence,
        private readonly ?ClassificationReason $reason,
        private readonly ?ProjectSummary $summary,
        private readonly ?LeadFindings $missingInformation,
        private readonly ?LeadFindings $contradictions,
        private ?CustomerMessageDraft $draft,
        private ?DraftStatus $draftStatus,
        private ?string $draftApprovedBy,
        private ?DateTimeImmutable $draftApprovedAt,
        private readonly ?string $model,
        private ?string $reviewReason,
        private ?string $reviewedBy,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    // Por debajo del umbral la propuesta se guarda, pero una persona decide.
    public static function fromProposal(LeadId $leadId, ClassificationProposal $proposal, float $minConfidence, LeadContact $customer, DateTimeImmutable $now): self
    {
        $doubtful = $proposal->confidence->isBelow($minConfidence);

        $classification = new self(
            $leadId,
            $doubtful ? ClassificationStatus::NeedsReview : ClassificationStatus::Classified,
            $proposal->category,
            $proposal->urgency,
            $proposal->confidence,
            $proposal->reason,
            $proposal->summary,
            $proposal->missingInformation,
            $proposal->contradictions,
            // El borrador nunca sale solo: queda pendiente hasta que una persona lo apruebe.
            $proposal->customerMessage,
            DraftStatus::Pending,
            null,
            null,
            $proposal->model,
            $doubtful ? 'La IA no está segura de la clasificación.' : null,
            null,
            $now,
            $now,
        );

        $classification->recordClassified($customer, $now);

        return $classification;
    }

    // Sin propuesta usable: el lead no se queda sin atender, pasa a una persona.
    // También se avisa: un lead sin clasificar no puede quedarse esperando sin que nadie lo sepa.
    public static function unclassified(LeadId $leadId, string $why, LeadContact $customer, DateTimeImmutable $now): self
    {
        $classification = new self($leadId, ClassificationStatus::NeedsReview, null, null, null, null, null, null, null, null, null, null, null, null, $why, null, $now, $now);

        $classification->recordClassified($customer, $now);

        return $classification;
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

    // Se guarda el texto que la persona dio por bueno, no el del modelo.
    public function approveDraft(CustomerMessageDraft $finalMessage, string $approver, LeadContact $customer, DateTimeImmutable $now): void
    {
        if ($this->draftStatus !== DraftStatus::Pending) {
            throw new CustomerMessageDraftNotPendingError($this->leadId->value);
        }

        $this->draft = $finalMessage;
        $this->draftStatus = DraftStatus::Approved;
        $this->draftApprovedBy = $approver;
        $this->draftApprovedAt = $now;
        $this->updatedAt = $now;

        $this->recordEvent(new CustomerMessageApprovedDomainEvent($this->leadId->value, $now, $finalMessage->value, $approver, $customer));
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
            $primitives['summary'] === null ? null : new ProjectSummary($primitives['summary']),
            $primitives['missing_information'] === null ? null : new LeadFindings($primitives['missing_information']),
            $primitives['contradictions'] === null ? null : new LeadFindings($primitives['contradictions']),
            $primitives['draft_message'] === null ? null : new CustomerMessageDraft($primitives['draft_message']),
            $primitives['draft_status'] === null ? null : DraftStatus::from($primitives['draft_status']),
            $primitives['draft_approved_by'],
            $primitives['draft_approved_at'] === null ? null : new DateTimeImmutable($primitives['draft_approved_at']),
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
            'summary' => $this->summary?->value,
            'missing_information' => $this->missingInformation?->items,
            'contradictions' => $this->contradictions?->items,
            'draft_message' => $this->draft?->value,
            'draft_status' => $this->draftStatus?->value,
            'draft_approved_by' => $this->draftApprovedBy,
            'draft_approved_at' => $this->draftApprovedAt?->format(DateTimeInterface::ATOM),
            'model' => $this->model,
            'review_reason' => $this->reviewReason,
            'reviewed_by' => $this->reviewedBy,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }

    private function recordClassified(LeadContact $customer, DateTimeImmutable $now): void
    {
        $primitives = $this->toPrimitives();

        // Sin el borrador: el aviso al equipo no lleva el mensaje pendiente de aprobar.
        $this->recordEvent(new LeadClassifiedDomainEvent($this->leadId->value, $now, [
            'lead_id' => $primitives['lead_id'],
            'status' => $primitives['status'],
            'category' => $primitives['category'],
            'urgency' => $primitives['urgency'],
            'confidence' => $primitives['confidence'],
            'reason' => $primitives['reason'],
            'summary' => $primitives['summary'],
            'missing_information' => $primitives['missing_information'],
            'contradictions' => $primitives['contradictions'],
            'review_reason' => $primitives['review_reason'],
        ], $customer));
    }
}
