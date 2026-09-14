import { Head } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';

type SystemStatus = {
    database: boolean;
    n8n: boolean;
};

const services: { key: keyof SystemStatus; label: string; detail: string }[] = [
    { key: 'database', label: 'Base de datos', detail: 'PostgreSQL' },
    { key: 'n8n', label: 'Automatización', detail: 'n8n' },
];

export default function Dashboard({
    systemStatus,
}: {
    systemStatus: SystemStatus;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-2">
                    {services.map(({ key, label, detail }) => {
                        const up = systemStatus[key];

                        return (
                            <div
                                key={key}
                                data-testid={`status-${key}`}
                                data-state={up ? 'up' : 'down'}
                                className="border-sidebar-border/70 dark:border-sidebar-border flex items-center justify-between rounded-xl border p-4"
                            >
                                <div>
                                    <p className="text-sm font-medium">
                                        {label}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {detail}
                                    </p>
                                </div>
                                <span
                                    className={`inline-flex items-center gap-2 text-sm ${up ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                                >
                                    <span
                                        className={`size-2 rounded-full ${up ? 'bg-emerald-500' : 'bg-red-500'}`}
                                    />
                                    {up ? 'Operativa' : 'Sin conexión'}
                                </span>
                            </div>
                        );
                    })}
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border md:min-h-min">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
