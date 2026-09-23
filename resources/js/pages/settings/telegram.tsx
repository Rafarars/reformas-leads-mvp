import { Head, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { edit as editTelegram } from '@/routes/telegram';

interface FreshLink {
    token: string;
    url: string;
    expires_at: string;
}

interface TelegramProps {
    telegram: {
        connected: boolean;
        pending: boolean;
        chat_id: string | null;
        linked_at: string | null;
        fresh_link: FreshLink | null;
        bot_username: string | null;
    };
}

export default function Telegram({ telegram }: TelegramProps) {
    const [activeLink, setActiveLink] = useState<FreshLink | null>(
        telegram.fresh_link,
    );
    const [copied, setCopied] = useState(false);

    // Cuando el servidor flashea un nuevo enlace, actualizamos el estado de React.
    // Si la cuenta se conecta, limpiamos el enlace efímero.
    useEffect(() => {
        if (telegram.fresh_link) {
            setActiveLink(telegram.fresh_link);
        }
        if (telegram.connected) {
            setActiveLink(null);
        }
    }, [telegram.fresh_link, telegram.connected]);

    const shouldPoll =
        !telegram.connected && (telegram.pending || activeLink !== null);

    const { start, stop } = usePoll(
        3000,
        {
            only: ['telegram'],
            showProgress: false,
        },
        {
            autoStart: shouldPoll,
        },
    );

    useEffect(() => {
        if (!shouldPoll) {
            stop();
        } else {
            start();
        }
    }, [shouldPoll, start, stop]);

    const handleConnect = () => {
        router.post('/settings/telegram/link', {}, { preserveScroll: true });
    };

    const handleDisconnect = () => {
        router.delete('/settings/telegram', {
            preserveScroll: true,
            onSuccess: () => setActiveLink(null),
        });
    };

    const handleCopy = async () => {
        if (!activeLink?.url) {
            return;
        }
        try {
            await navigator.clipboard.writeText(activeLink.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Silencioso si el navegador bloquea el acceso al portapapeles.
        }
    };

    const formatDateTime = (isoDate: string) => {
        return new Intl.DateTimeFormat('es-ES', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(isoDate));
    };

    return (
        <>
            <Head title="Ajustes de Telegram" />

            <h1 className="sr-only">Ajustes de Telegram</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Telegram"
                    description="Vincula tu cuenta personal de Telegram para recibir alertas en tiempo real sobre nuevos leads y clasificaciones."
                />

                {!telegram.bot_username && (
                    <div className="rounded-md border border-amber-500/50 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/20 dark:text-amber-200">
                        El bot de Telegram no está configurado (falta{' '}
                        <code className="font-mono text-xs">
                            TELEGRAM_BOT_USERNAME
                        </code>
                        ).
                    </div>
                )}

                {telegram.connected ? (
                    <div className="space-y-4 rounded-lg border p-4">
                        <div className="flex items-center space-x-2">
                            <span className="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500" />
                            <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                Cuenta vinculada
                            </span>
                        </div>

                        <p
                            data-test="connected-since"
                            className="text-muted-foreground text-sm"
                        >
                            Conectado desde{' '}
                            {telegram.linked_at
                                ? formatDateTime(telegram.linked_at)
                                : 'hace un momento'}
                        </p>

                        {telegram.chat_id && (
                            <p className="text-muted-foreground font-mono text-xs">
                                Chat ID: {telegram.chat_id}
                            </p>
                        )}

                        <div className="pt-2">
                            <Button
                                variant="destructive"
                                size="sm"
                                data-test="disconnect-button"
                                onClick={handleDisconnect}
                            >
                                Desconectar
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4 rounded-lg border p-4">
                        {activeLink ? (
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        Enlace de vinculación generado
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        Abre este enlace en Telegram o pulsa
                                        iniciar en la aplicación para vincularte
                                        al bot.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <a
                                        href={activeLink.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        data-test="telegram-link"
                                        className="bg-muted rounded px-2 py-1 font-mono text-xs break-all text-blue-600 underline dark:text-blue-400"
                                    >
                                        {activeLink.url}
                                    </a>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        data-test="copy-link-button"
                                        onClick={handleCopy}
                                    >
                                        {copied ? 'Copiado' : 'Copiar enlace'}
                                    </Button>
                                </div>

                                <p className="text-muted-foreground text-xs">
                                    Caduca:{' '}
                                    <span
                                        data-test="link-expiry"
                                        className="font-medium"
                                    >
                                        {formatDateTime(activeLink.expires_at)}
                                    </span>
                                </p>

                                <div className="flex items-center gap-3 pt-2">
                                    <span className="flex h-2 w-2 animate-ping rounded-full bg-blue-500" />
                                    <span className="text-muted-foreground text-xs">
                                        Esperando confirmación desde Telegram...
                                    </span>
                                </div>

                                <div className="pt-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="disconnect-button"
                                        onClick={handleDisconnect}
                                    >
                                        Cancelar solicitud
                                    </Button>
                                </div>
                            </div>
                        ) : telegram.pending ? (
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <span className="h-2 w-2 animate-ping rounded-full bg-blue-500" />
                                    <p className="text-muted-foreground text-sm">
                                        Esperando confirmación desde Telegram...
                                    </p>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    El enlace anterior fue enviado. Si no lo
                                    abriste o cerraste la ventana, puedes
                                    generar uno nuevo.
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        size="sm"
                                        data-test="connect-button"
                                        disabled={!telegram.bot_username}
                                        onClick={handleConnect}
                                    >
                                        Generar nuevo enlace
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="disconnect-button"
                                        onClick={handleDisconnect}
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-sm">
                                    Tu cuenta todavía no está vinculada a
                                    Telegram. Al conectar recibirás un enlace de
                                    un solo uso con validez de 15 minutos.
                                </p>
                                <Button
                                    size="sm"
                                    data-test="connect-button"
                                    disabled={!telegram.bot_username}
                                    onClick={handleConnect}
                                >
                                    Conectar mi Telegram
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

Telegram.layout = {
    breadcrumbs: [
        {
            title: 'Telegram',
            href: editTelegram(),
        },
    ],
};
