import StatisticsDashboard from '@/components/analytics/statistics-dashboard';
import { AppContextMenu, ContextMenuItemProps } from '@/components/app-context-menu';
import { AppTooltip } from '@/components/app-tooltip';
import { LibsqlStudio } from '@/components/libsql-studio';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { getQuery, groupDatabases } from '@/lib/utils';
import { type BreadcrumbItem, type LibSQLDatabases } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { DatabaseIcon, FileText, Trash } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {

    const { props } = usePage();
    const userDatabases = props.databases as LibSQLDatabases[];
    const { standalone, parents, childrenMap } = groupDatabases(userDatabases);

    const [clientUrl, setClientUrl] = useState<string | null>("http://localhost:8080");
    const [authToken, setAuthToken] = useState<string | undefined>("");

    const parent = getQuery('database');
    const [databaseName, setDatabaseName] = useState<string | null>(parent);

    const getContextMenuItems = useCallback((database: LibSQLDatabases): ContextMenuItemProps[] => {
        let menuItems: ContextMenuItemProps[] = [];

        if (database.is_schema !== '0') {
            menuItems = [
                {
                    title: 'Create Child Shcema Database',
                    icon: DatabaseIcon,
                    onClick: () => {
                        alert(`Create Child Shcema Database for ${database.database_name}`);
                    }
                }
            ];
        }

        return menuItems.concat([
            {
                title: 'Delete',
                icon: Trash,
                onClick: () => {
                    toast.error(`Delete ${database.database_name}`, {
                        position: 'top-center',
                        action: {
                            label: 'Delete',
                            onClick: () => {
                                router.delete(`/databases/delete/${database.database_name}`, {
                                    onFinish: () => {
                                        router.get('/dashboard');
                                    }
                                });
                            }
                        }
                    });
                }
            }
        ]);
    }, []);

    const [dataSidebarState, setDataSidebarState] = useState<string>('');
    const targetRef = useRef<HTMLElement | null>(null);
    const observerRef = useRef<MutationObserver | null>(null);

    useEffect(() => {
        targetRef.current = document.querySelector('[data-state]');

        if (!targetRef.current) return;

        setDataSidebarState(targetRef.current.getAttribute('data-state') || '');

        observerRef.current = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-state') {
                    const newState = (mutation.target as HTMLElement).getAttribute('data-state') || '';
                    setDataSidebarState(newState);
                }
            });
        });

        observerRef.current.observe(targetRef.current, {
            attributes: true,
            attributeFilter: ['data-state']
        });

        return () => {
            observerRef.current?.disconnect();
        };
    }, []);

    useEffect(() => {
        if (parent) {
            setDatabaseName(parent);
            setClientUrl(`http://${parent}.localhost:8080`);
        }
    }, [parent]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {clientUrl && databaseName ? (
                <div className="flex h-full flex-1">
                    {dataSidebarState === 'collapsed' && (
                        <div className="w-60 h-full bg-neutral-50 dark:bg-neutral-950 rounded-bl-lg border-r border-neutral-200 p-1 dark:border-neutral-800">
                            <ul className="list-none p-0">
                                <li className="flex items-center gap-2 px-2 p-1 mb-1">
                                    <span className="text-sm font-semibold">Databases</span>
                                </li>
                                {userDatabases.length > 0 && (
                                    <>
                                        <Separator className='my-2' />
                                        {standalone.map((database: LibSQLDatabases) => (
                                            <li
                                                key={database.database_name}
                                                className='flex justify-between items-center gap-2 p-2 mb-1 cursor-pointer rounded-sm dark:hover:bg-neutral-700 dark:hover:text-neutral-100 hover:bg-neutral-300'
                                                onClick={() => router.get('/dashboard', { database: database.database_name })}
                                            >
                                                <span className={`text-sm ${database.database_name == databaseName ? 'font-semibold' : ''}`}>{database.database_name}</span>
                                            </li>
                                        ))}
                                        {parents.map((parent) => (
                                            <>
                                                <AppContextMenu key={parent.database_name} items={getContextMenuItems(parent)}>
                                                    <li
                                                        key={parent.database_name}
                                                        className={
                                                            `flex justify-between items-center gap-2 p-2 mb-1 cursor-pointer rounded-sm ${parent.database_name === databaseName ? 'dark:bg-neutral-700 dark:hover:bg-neutral-800 dark:text-neutral-100 bg-neutral-200 hover:bg-neutral-300' : 'dark:hover:bg-neutral-700 dark:hover:text-neutral-100 hover:bg-neutral-300'}`
                                                        }
                                                        onClick={() => router.get('/dashboard', { database: parent.database_name })}
                                                    >
                                                        <span className={`text-sm ${parent.database_name == databaseName ? 'font-semibold' : ''}`}>{parent.database_name}</span>
                                                        {(parent.is_schema === '1' || parent.is_schema !== '0') && (
                                                            <AppTooltip text="Schema Database">
                                                                <FileText className="ml-1 w-4 h-4" />
                                                            </AppTooltip>
                                                        )}
                                                    </li>
                                                </AppContextMenu>
                                                {childrenMap.get(parent.database_name)?.map((child) => (
                                                    <AppContextMenu key={child.database_name} items={getContextMenuItems(child)}>
                                                        <li
                                                            key={child.database_name}
                                                            className={
                                                                `ml-2 flex justify-between items-center gap-2 p-2 mb-1 cursor-pointer rounded-sm ${child.database_name === databaseName ? 'dark:bg-neutral-700 dark:hover:bg-neutral-800 dark:text-neutral-100 bg-neutral-200 hover:bg-neutral-300' : 'dark:hover:bg-neutral-700 dark:hover:text-neutral-100 hover:bg-neutral-300'}`
                                                            }
                                                            onClick={() => router.get('/dashboard', { database: child.database_name })}
                                                        >
                                                            <span className={`text-sm ${child.database_name == databaseName ? 'font-semibold' : ''}`}>{child.database_name}</span>
                                                            {(child.is_schema === '1' || child.is_schema !== '0') && (
                                                                <AppTooltip text={`Child of ${parent.database_name}`}>
                                                                    <FileText className="ml-1 w-4 h-4" />
                                                                </AppTooltip>
                                                            )}
                                                        </li>
                                                    </AppContextMenu>
                                                ))}
                                            </>
                                        ))}
                                    </>
                                )}
                            </ul>
                        </div>
                    )}
                    <LibsqlStudio databaseName={databaseName} clientUrl={clientUrl} authToken={authToken} />
                </div>
            ) : (
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl px-4 py-2">
                    <StatisticsDashboard />
                </div>
            )}
        </AppLayout>
    );
}
