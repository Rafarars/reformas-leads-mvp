import { Form, Head, usePage } from '@inertiajs/react';
import ReceptionLeadPostController from '@/actions/App/Http/Controllers/Reception/ReceptionLeadPostController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { reception } from '@/routes';

type ReceptionResult = {
    id: string;
    status: string;
    contact_count: number;
    repeated: boolean;
};

type PageProps = {
    flash: { receptionResult: ReceptionResult | null };
};

const renovationTypeLabels: Record<string, string> = {
    kitchen: 'Cocina',
    bathroom: 'Baño',
    full_home: 'Vivienda completa',
    partial: 'Reforma parcial',
    other: 'Otro',
};

function ResultNotice({ result }: { result: ReceptionResult }) {
    const tone = result.repeated
        ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100'
        : 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-100';

    return (
        <div
            data-test="reception-result"
            data-state={result.repeated ? 'repeated' : 'new'}
            className={`rounded-lg border p-4 text-sm ${tone}`}
        >
            <p className="font-medium">
                {result.repeated
                    ? 'Esta persona ya estaba registrada'
                    : 'Lead recibido'}
            </p>
            <p className="mt-1">
                {result.repeated
                    ? `Se registró como contacto número ${result.contact_count}. Los datos que faltaban se completaron; los que ya existían no se modificaron.`
                    : 'Quedó guardado y listo para procesarse.'}
            </p>
        </div>
    );
}

export default function ReceptionCreate({
    renovationTypes,
}: {
    renovationTypes: string[];
}) {
    const { flash } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Recepción" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Recepción de leads"
                    description="Registra a una persona interesada en una reforma. Si ya existe por su teléfono o su email, se anota como un nuevo contacto."
                />

                {flash.receptionResult && (
                    <ResultNotice result={flash.receptionResult} />
                )}

                <Form
                    {...ReceptionLeadPostController.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid items-start gap-6 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoComplete="name"
                                        data-test="reception-name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Teléfono</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        required
                                        autoComplete="tel"
                                        placeholder="600 111 222"
                                        data-test="reception-phone"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        data-test="reception-email"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="renovation_type">
                                        Tipo de reforma
                                    </Label>
                                    <Select name="renovation_type" required>
                                        <SelectTrigger
                                            id="renovation_type"
                                            data-test="reception-renovation-type"
                                        >
                                            <SelectValue placeholder="Selecciona un tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {renovationTypes.map((type) => (
                                                <SelectItem
                                                    key={type}
                                                    value={type}
                                                    data-test={`reception-renovation-type-${type}`}
                                                >
                                                    {renovationTypeLabels[
                                                        type
                                                    ] ?? type}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.renovation_type}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="zone">Zona</Label>
                                    <Input
                                        id="zone"
                                        name="zone"
                                        placeholder="Madrid, Chamberí"
                                        data-test="reception-zone"
                                    />
                                    <InputError message={errors.zone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="budget">
                                        Presupuesto aproximado (€)
                                    </Label>
                                    <Input
                                        id="budget"
                                        name="budget"
                                        type="number"
                                        min={1}
                                        step={1}
                                        data-test="reception-budget"
                                    />
                                    <InputError message={errors.budget} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="description">
                                        Descripción del proyecto
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={4}
                                        data-test="reception-description"
                                        className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm"
                                    />
                                    <InputError message={errors.description} />
                                </div>
                            </div>

                            <InputError message={errors.form} />

                            <Button
                                disabled={processing}
                                data-test="reception-submit"
                            >
                                Registrar lead
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ReceptionCreate.layout = {
    breadcrumbs: [
        {
            title: 'Recepción',
            href: reception(),
        },
    ],
};
