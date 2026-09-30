import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { edit as editTelegram } from '@/routes/telegram';

export function TelegramBanner() {
    const { telegramLinked } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    // Solo visible mientras el usuario no tenga su Telegram vinculado (telegramLinked === false)
    // y no nos encontremos ya en la página de Ajustes → Telegram.
    if (telegramLinked !== false || isCurrentUrl(editTelegram())) {
        return null;
    }

    return (
        <div
            data-test="telegram-banner"
            className="border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="font-medium">
                    Conecta tu Telegram para recibir al instante los avisos de
                    leads nuevos.
                </p>
                <Button
                    size="sm"
                    variant="outline"
                    asChild
                    className="h-7 border-amber-300 bg-white text-xs text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100 dark:hover:bg-amber-900/60"
                >
                    <Link
                        href={editTelegram()}
                        data-test="telegram-banner-button"
                    >
                        Conectar Telegram
                    </Link>
                </Button>
            </div>
        </div>
    );
}
