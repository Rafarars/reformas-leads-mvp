import { Head, router, usePage, usePoll } from '@inertiajs/react';
import ResilienceLeadsPostController from '@/actions/App/Http/Controllers/Resilience/ResilienceLeadsPostController';
import ResilienceOutagePostController from '@/actions/App/Http/Controllers/Resilience/ResilienceOutagePostController';
import ResilienceReplayPostController from '@/actions/App/Http/Controllers/Resilience/ResilienceReplayPostController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { resilience } from '@/routes';

type Counts = { pending: number; delivered: number; dead: number };

type MessageSummary = {
    id: string;
    event: string;
    lead_name: string | null;
    repeated: boolean;
    status: 'pending' | 'delivered' | 'dead';
    attempts: number;
    next_attempt_at: string | null;
    last_error: string | null;
    created_at: string;
    delivered_at: string | null;
};

type Delivery = {
    counts: Counts;
    circuit: {
        open: boolean;
        open_until: string | null;
        consecutive_failures: number;
    };
    messages: MessageSummary[];
};

type Crm = {
    outage: boolean;
    rejected: number;
    duplicates: number;
    deliveries: number;
};

type PageProps = { errors: Record<string, string> };

const DEMO_LEADS = 10;
const POLL_EVERY_MS = 2000;

const statusLabels: Record<MessageSummary['status'], string> = {
    pending: 'Pendiente',
    delivered: 'Entregado',
    dead: 'Apartado',
};

function time(iso: string | null): string {
    return iso === null
        ? '—'
        : new Date(iso).toLocaleTimeString('es-ES', {
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
          });
}

function Card({
    title,
    detail,
    children,
    ...attributes
}: {
    title: string;
    detail: string;
    children: React.ReactNode;
    [attribute: `data-${string}`]: string;
}) {
    return (
        <div
            {...attributes}
            className="border-sidebar-border/70 dark:border-sidebar-border space-y-3 rounded-xl border p-4"
        >
            <div>
                <p className="text-sm font-medium">{title}</p>
                <p className="text-muted-foreground text-xs">{detail}</p>
            </div>
            {children}
        </div>
    );
}

function Counter({
    label,
    value,
    name,
}: {
    label: string;
    value: number;
    name: string;
}) {
    return (
        <div>
            <p
                data-test={`resilience-count-${name}`}
                data-value={value}
                className="text-2xl font-semibold tabular-nums"
            >
                {value}
            </p>
            <p className="text-muted-foreground text-xs">{label}</p>
        </div>
    );
}

export default function Resilience({
    delivery,
    crm,
}: {
    delivery: Delivery;
    crm: Crm | null;
}) {
    const { errors } = usePage<PageProps>().props;

    // Refresco periódico: durante la demo se ve la cola crecer y drenarse sin recargar.
    usePoll(POLL_EVERY_MS);

    const crmState = crm === null ? 'unreachable' : crm.outage ? 'down' : 'up';
    const post = (url: string, data: Record<string, number | boolean> = {}) =>
        router.post(url, data, { preserveScroll: true });

    return (
        <>
            <Head title="Resiliencia" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Resiliencia de la entrega"
                    description="Cada lead deja un aviso en el outbox. Si la API externa cae, el aviso espera y se reintenta con backoff; nunca se pierde."
                />

                <InputError message={errors.crm ?? errors.replay} />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card
                        title="API externa (CRM simulado)"
                        detail="Destino de los avisos"
                        data-test="resilience-crm"
                        data-state={crmState}
                    >
                        <Badge
                            variant={
                                crmState === 'up' ? 'secondary' : 'destructive'
                            }
                        >
                            {crmState === 'up'
                                ? 'Disponible'
                                : crmState === 'down'
                                  ? 'Caída simulada'
                                  : 'Sin conexión'}
                        </Badge>

                        {crm !== null && (
                            <div className="grid grid-cols-3 gap-2">
                                <Counter
                                    label="Recibidos"
                                    value={crm.deliveries}
                                    name="crm-received"
                                />
                                <Counter
                                    label="Rechazados"
                                    value={crm.rejected}
                                    name="crm-rejected"
                                />
                                <Counter
                                    label="Duplicados"
                                    value={crm.duplicates}
                                    name="crm-duplicates"
                                />
                            </div>
                        )}

                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="destructive"
                                disabled={crm === null || crm.outage}
                                data-test="resilience-outage-on"
                                onClick={() =>
                                    post(ResilienceOutagePostController.url(), {
                                        enabled: true,
                                    })
                                }
                            >
                                Simular caída
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={crm === null || !crm.outage}
                                data-test="resilience-outage-off"
                                onClick={() =>
                                    post(ResilienceOutagePostController.url(), {
                                        enabled: false,
                                    })
                                }
                            >
                                Restablecer
                            </Button>
                        </div>
                    </Card>

                    <Card
                        title="Circuit breaker"
                        detail="Deja de insistir contra un destino caído"
                        data-test="resilience-circuit"
                        data-state={delivery.circuit.open ? 'open' : 'closed'}
                    >
                        <Badge
                            variant={
                                delivery.circuit.open
                                    ? 'destructive'
                                    : 'secondary'
                            }
                        >
                            {delivery.circuit.open
                                ? `Abierto hasta las ${time(delivery.circuit.open_until)}`
                                : 'Cerrado'}
                        </Badge>
                        <Counter
                            label="Fallos seguidos"
                            value={delivery.circuit.consecutive_failures}
                            name="circuit-failures"
                        />
                    </Card>

                    <Card
                        title="Outbox"
                        detail="Avisos guardados junto a cada lead"
                        data-test="resilience-outbox"
                    >
                        <div className="grid grid-cols-3 gap-2">
                            <Counter
                                label="Pendientes"
                                value={delivery.counts.pending}
                                name="pending"
                            />
                            <Counter
                                label="Entregados"
                                value={delivery.counts.delivered}
                                name="delivered"
                            />
                            <Counter
                                label="Apartados"
                                value={delivery.counts.dead}
                                name="dead"
                            />
                        </div>
                        <Button
                            size="sm"
                            data-test="resilience-inject"
                            onClick={() =>
                                post(ResilienceLeadsPostController.url(), {
                                    count: DEMO_LEADS,
                                })
                            }
                        >
                            Inyectar {DEMO_LEADS} leads de prueba
                        </Button>
                    </Card>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="text-muted-foreground text-left text-xs">
                            <tr className="border-b">
                                <th className="p-3 font-medium">Lead</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 font-medium">Intentos</th>
                                <th className="p-3 font-medium">
                                    Próximo intento
                                </th>
                                <th className="p-3 font-medium">
                                    Último error
                                </th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {delivery.messages.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        Todavía no hay avisos.
                                    </td>
                                </tr>
                            )}
                            {delivery.messages.map((message) => (
                                <tr
                                    key={message.id}
                                    data-test="resilience-message"
                                    data-state={message.status}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3">
                                        <p>{message.lead_name ?? '—'}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {message.repeated
                                                ? 'Contacto repetido'
                                                : 'Lead nuevo'}{' '}
                                            · {time(message.created_at)}
                                        </p>
                                    </td>
                                    <td className="p-3">
                                        <Badge
                                            variant={
                                                message.status === 'dead'
                                                    ? 'destructive'
                                                    : message.status ===
                                                        'pending'
                                                      ? 'outline'
                                                      : 'secondary'
                                            }
                                        >
                                            {statusLabels[message.status]}
                                        </Badge>
                                    </td>
                                    <td className="p-3 tabular-nums">
                                        {message.attempts}
                                    </td>
                                    <td className="p-3 tabular-nums">
                                        {message.status === 'pending'
                                            ? time(message.next_attempt_at)
                                            : '—'}
                                    </td>
                                    <td className="text-muted-foreground max-w-xs truncate p-3 text-xs">
                                        {message.last_error ?? '—'}
                                    </td>
                                    <td className="p-3 text-right">
                                        {message.status === 'dead' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                data-test={`resilience-replay-${message.id}`}
                                                onClick={() =>
                                                    post(
                                                        ResilienceReplayPostController.url(
                                                            {
                                                                message:
                                                                    message.id,
                                                            },
                                                        ),
                                                    )
                                                }
                                            >
                                                Reintentar
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Resilience.layout = {
    breadcrumbs: [
        {
            title: 'Resiliencia',
            href: resilience(),
        },
    ],
};
