import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import ClassificationDraftApprovePostController from '@/actions/App/Http/Controllers/Classification/ClassificationDraftApprovePostController';
import ClassificationReviewPostController from '@/actions/App/Http/Controllers/Classification/ClassificationReviewPostController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { classification as classificationRoute } from '@/routes';

type Status = 'classified' | 'needs_review' | 'reviewed';

type Classification = {
    lead_id: string;
    status: Status;
    category: string | null;
    urgency: string | null;
    confidence: number | null;
    reason: string | null;
    summary: string | null;
    missing_information: string[] | null;
    contradictions: string[] | null;
    draft_message: string | null;
    draft_status: 'pending' | 'approved' | null;
    draft_approved_by: string | null;
    model: string | null;
    review_reason: string | null;
    reviewed_by: string | null;
    updated_at: string;
    lead: {
        name: string;
        renovation_type: string;
        zone: string | null;
        budget: number | null;
        description: string | null;
    } | null;
};

type PageProps = { errors: Record<string, string> };

const POLL_EVERY_MS = 3000;

const categoryLabels: Record<string, string> = {
    kitchen: 'Cocina',
    bathroom: 'Baño',
    full_home: 'Vivienda completa',
    facade: 'Fachada',
    installations: 'Instalaciones',
    other: 'Otro',
    partial: 'Reforma parcial',
};

const urgencyLabels: Record<string, string> = {
    low: 'Baja',
    medium: 'Media',
    high: 'Alta',
};

const statusLabels: Record<Status, string> = {
    classified: 'Clasificado por IA',
    needs_review: 'Pendiente de revisión',
    reviewed: 'Revisado',
};

function Review({
    item,
    categories,
    urgencies,
}: {
    item: Classification;
    categories: string[];
    urgencies: string[];
}) {
    // Parte de lo que propuso la IA: confirmar es un clic, corregir son dos.
    const [category, setCategory] = useState(item.category ?? '');
    const [urgency, setUrgency] = useState(item.urgency ?? '');
    const test = `classification-review-${item.lead_id}`;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select value={category} onValueChange={setCategory}>
                <SelectTrigger className="w-40" data-test={`${test}-category`}>
                    <SelectValue placeholder="Categoría" />
                </SelectTrigger>
                <SelectContent>
                    {categories.map((value) => (
                        <SelectItem
                            key={value}
                            value={value}
                            data-test={`${test}-category-${value}`}
                        >
                            {categoryLabels[value] ?? value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={urgency} onValueChange={setUrgency}>
                <SelectTrigger className="w-28" data-test={`${test}-urgency`}>
                    <SelectValue placeholder="Urgencia" />
                </SelectTrigger>
                <SelectContent>
                    {urgencies.map((value) => (
                        <SelectItem
                            key={value}
                            value={value}
                            data-test={`${test}-urgency-${value}`}
                        >
                            {urgencyLabels[value] ?? value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button
                size="sm"
                disabled={category === '' || urgency === ''}
                data-test={`${test}-submit`}
                onClick={() =>
                    router.post(
                        ClassificationReviewPostController.url({
                            lead: item.lead_id,
                        }),
                        { category, urgency },
                        { preserveScroll: true },
                    )
                }
            >
                {item.status === 'reviewed' ? 'Corregir' : 'Confirmar'}
            </Button>
        </div>
    );
}

// El modelo no conoce el nombre: escribe el marcador y aquí se completa antes de revisarlo.
const NAME_PLACEHOLDER = '{nombre}';

function Findings({
    title,
    items,
    name,
}: {
    title: string;
    items: string[] | null;
    name: string;
}) {
    if (items === null || items.length === 0) {
        return null;
    }

    return (
        <div data-test={`classification-${name}`}>
            <p className="text-muted-foreground text-xs font-medium">{title}</p>
            <ul className="list-disc pl-5 text-xs">
                {items.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </div>
    );
}

function Draft({ item }: { item: Classification }) {
    const [message, setMessage] = useState(
        (item.draft_message ?? '').replaceAll(
            NAME_PLACEHOLDER,
            item.lead?.name ?? '',
        ),
    );

    if (item.draft_message === null) {
        return null;
    }

    const test = `classification-draft-${item.lead_id}`;
    const approved = item.draft_status === 'approved';

    return (
        <div
            className="space-y-2"
            data-test={test}
            data-state={item.draft_status ?? ''}
        >
            <p className="text-muted-foreground text-xs font-medium">
                {approved
                    ? `Mensaje al cliente aprobado por ${item.draft_approved_by}`
                    : 'Borrador del mensaje al cliente (no se envía sin aprobación)'}
            </p>
            <textarea
                rows={6}
                value={approved ? (item.draft_message ?? '') : message}
                readOnly={approved}
                onChange={(event) => setMessage(event.target.value)}
                data-test={`${test}-message`}
                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
            />
            {!approved && (
                <Button
                    size="sm"
                    variant="outline"
                    disabled={message.trim() === ''}
                    data-test={`${test}-approve`}
                    onClick={() =>
                        router.post(
                            ClassificationDraftApprovePostController.url({
                                lead: item.lead_id,
                            }),
                            { message },
                            { preserveScroll: true },
                        )
                    }
                >
                    Aprobar mensaje
                </Button>
            )}
        </div>
    );
}

export default function ClassificationIndex({
    classifications,
    categories,
    urgencies,
}: {
    classifications: Classification[];
    categories: string[];
    urgencies: string[];
}) {
    const { errors } = usePage<PageProps>().props;
    const pending = classifications.filter(
        (item) => item.status === 'needs_review',
    ).length;

    // La IA trabaja en segundo plano: el lead aparece clasificado sin recargar.
    usePoll(POLL_EVERY_MS);

    return (
        <>
            <Head title="Clasificación" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Clasificación de leads"
                    description="La IA propone categoría, urgencia y un motivo. Si duda, no responde o su respuesta no es válida, decide una persona."
                />

                <p
                    className="text-muted-foreground text-sm"
                    data-test="classification-pending"
                    data-value={pending}
                >
                    {pending === 0
                        ? 'Nada pendiente de revisión.'
                        : `${pending} pendiente${pending === 1 ? '' : 's'} de revisión.`}
                </p>

                <InputError
                    message={
                        errors.category ??
                        errors.urgency ??
                        errors.review ??
                        errors.message
                    }
                />

                <div className="space-y-3">
                    {classifications.length === 0 && (
                        <p className="text-muted-foreground rounded-xl border p-6 text-center text-sm">
                            Todavía no hay leads clasificados.
                        </p>
                    )}

                    {classifications.map((item) => (
                        <div
                            key={item.lead_id}
                            data-test="classification-item"
                            data-lead={item.lead_id}
                            data-state={item.status}
                            className="border-sidebar-border/70 dark:border-sidebar-border grid gap-4 rounded-xl border p-4 lg:grid-cols-2"
                        >
                            <div className="space-y-1 text-sm">
                                <p className="font-medium">
                                    {item.lead?.name ?? '—'}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Marcó:{' '}
                                    {categoryLabels[
                                        item.lead?.renovation_type ?? ''
                                    ] ?? item.lead?.renovation_type}
                                    {item.lead?.zone && ` · ${item.lead.zone}`}
                                    {item.lead?.budget !== null &&
                                        item.lead?.budget !== undefined &&
                                        ` · ${item.lead.budget.toLocaleString('es-ES')} €`}
                                </p>
                                <p className="italic">
                                    {item.lead?.description ??
                                        'Sin descripción'}
                                </p>
                            </div>

                            <div className="space-y-2 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            item.status === 'needs_review'
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {statusLabels[item.status]}
                                    </Badge>
                                    {item.category && (
                                        <Badge variant="outline">
                                            {categoryLabels[item.category]}
                                        </Badge>
                                    )}
                                    {item.urgency && (
                                        <Badge variant="outline">
                                            Urgencia{' '}
                                            {urgencyLabels[
                                                item.urgency
                                            ].toLowerCase()}
                                        </Badge>
                                    )}
                                    {item.confidence !== null && (
                                        <span className="text-muted-foreground text-xs tabular-nums">
                                            Confianza{' '}
                                            {Math.round(item.confidence * 100)}%
                                        </span>
                                    )}
                                </div>
                                {item.summary && (
                                    <p className="font-medium">
                                        {item.summary}
                                    </p>
                                )}
                                {item.reason && <p>{item.reason}</p>}
                                <Findings
                                    title="Falta para presupuestar"
                                    items={item.missing_information}
                                    name="missing"
                                />
                                <Findings
                                    title="Datos que no cuadran"
                                    items={item.contradictions}
                                    name="contradictions"
                                />
                                {item.review_reason && (
                                    <p className="text-muted-foreground text-xs">
                                        {item.review_reason}
                                    </p>
                                )}
                                {item.reviewed_by && (
                                    <p className="text-muted-foreground text-xs">
                                        Revisado por {item.reviewed_by}
                                    </p>
                                )}
                                <Review
                                    item={item}
                                    categories={categories}
                                    urgencies={urgencies}
                                />
                                <Draft item={item} />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

ClassificationIndex.layout = {
    breadcrumbs: [
        {
            title: 'Clasificación',
            href: classificationRoute(),
        },
    ],
};
