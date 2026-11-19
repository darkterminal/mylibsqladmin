import { Head, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'sonner';
import { useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'General settings',
        href: '/settings/general',
    },
];

type GeneralSettingsProps = {
    effectiveTimezone: string;
    timezoneSource: 'environment' | 'system-settings' | 'default';
    envTimezone: string | null;
    availableTimezones: string[];
};

export default function GeneralSettings({
    effectiveTimezone,
    timezoneSource,
    envTimezone,
    availableTimezones,
}: GeneralSettingsProps) {
    const { data, setData, patch, processing, recentlySuccessful } = useForm({
        timezone: effectiveTimezone,
    });

    useEffect(() => {
        if (recentlySuccessful) {
            toast.success('General settings updated', { duration: 2500 });
        }
    }, [recentlySuccessful]);

    const options = availableTimezones.map((tz) => ({ value: tz, label: tz }));

    const sourceLabel =
        timezoneSource === 'system-settings'
            ? 'System settings (this page)'
            : timezoneSource === 'environment'
              ? `APP_TIMEZONE environment (${envTimezone})`
              : 'Default (UTC)';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="General settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="General settings"
                        description="Instance-wide options for this installation. Visible and editable only by a Super Admin. All data is stored in UTC regardless of these settings; they only affect how times are displayed to users."
                    />

                    <form
                        className="space-y-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            patch(route('general.settings.update'), {
                                preserveScroll: true,
                                onError: () => toast.error('Could not save settings. Pick a valid timezone from the list.'),
                            });
                        }}
                    >
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <label htmlFor="timezone" className="text-sm font-medium leading-none">
                                    Timezone (display)
                                </label>
                                <Badge variant="outline" className="text-xs font-normal">
                                    {sourceLabel}
                                </Badge>
                            </div>
                            <Combobox
                                options={options}
                                value={data.timezone}
                                onValueChange={(value) => setData('timezone', value)}
                                placeholder="Select a timezone"
                                emptyMessage="No timezone matches your search"
                                className="w-full max-w-md"
                            />
                            <p className="text-xs text-muted-foreground max-w-md">
                                Used to render dates and times in the interface (e.g. activity logs, created timestamps).
                                Saving changes only affects display — storage stays in UTC. Example: <code>Asia/Jakarta</code> renders WIB.
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button type="submit" disabled={processing || data.timezone === effectiveTimezone}>
                                {processing ? 'Saving…' : 'Save settings'}
                            </Button>
                            {data.timezone !== effectiveTimezone && (
                                <p className="text-xs text-muted-foreground">Unsaved changes</p>
                            )}
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
