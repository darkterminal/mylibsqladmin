import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { getQuery } from '@/lib/utils';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { router } from '@inertiajs/react';
import { Database, Unlink } from 'lucide-react';
import { AppTooltip } from './app-tooltip';
import AppearanceToggleDropdown from './appearance-dropdown';
import { Button } from './ui/button';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {

    const database = getQuery('database', 'No database selected');

    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex justify-between items-center gap-2 w-full">
                <div className='flex items-center'>
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <div className='flex items-center'>
                    <div className='flex items-center'>
                        <Database className='h-4 w-4 mr-1' />
                        <span>{database}</span>
                    </div>
                    {database !== 'No database selected' && (
                        <AppTooltip text='Disconnect'>
                            <Button variant="destructive" size="sm" className='ml-2' aria-label='Disconnect' onClick={() => router.get(route('dashboard'))}>
                                <Unlink className='h-4 w-4' />
                                <span className='sr-only'>Disconnect</span>
                            </Button>
                        </AppTooltip>
                    )}
                    <div className="ml-2 pl-2 border-l">
                        <AppearanceToggleDropdown />
                    </div>
                </div>
            </div>
        </header>
    );
}
