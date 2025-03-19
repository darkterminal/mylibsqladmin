import { triggerEvent } from '@/hooks/use-custom-event';
import { getQuery, groupDatabases } from '@/lib/utils';
import { type LibSQLDatabases } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronRight, Database, DatabaseIcon, Eye, FileText, Plus, Trash } from 'lucide-react';
import { useCallback } from 'react';
import { toast } from 'sonner';
import { AppContextMenu, ContextMenuItemProps } from './app-context-menu';
import { AppTooltip } from './app-tooltip';
import { CreateDatabaseProps, ModalCreateDatabase } from './modals/modal-create-database';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from './ui/dropdown-menu';
import { Separator } from './ui/separator';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from './ui/sidebar';

export function SelectDatabase() {
    const { props } = usePage();
    const { isMobile } = useSidebar();
    const databases = props.databases as LibSQLDatabases[];
    const { standalone, parents, childrenMap } = groupDatabases(databases);
    const selectedDatabase = getQuery('database', 'Select Database');

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
        };

        router.post(route('database.create'), submittedData, {
            onSuccess: () => {
                toast.success('Database created successfully', {
                    position: 'top-center',
                    action: {
                        label: <><Eye className="mr-1 w-4 h-4" /> View Database</>,
                        onClick: () => {
                            router.get('/dashboard?database=' + submittedData.database);
                        },
                    }
                });
            }
        });
    }

    const getContextMenuItems = useCallback((database: LibSQLDatabases): ContextMenuItemProps[] => {
        return [
            ...(Number(database.is_schema) ? [{
                title: 'Create Child Schema Database',
                icon: DatabaseIcon,
                onClick: () => {
                    triggerEvent('open-modal-changed', { isModalOpen: true, parentDatabase: database.database_name })
                }
            }] : []),
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
                                        router.visit('/dashboard', {
                                            only: ['databases', 'mostUsedDatabases', 'databaseMetrics'],
                                            replace: true,
                                            preserveScroll: true
                                        });
                                    }
                                });
                            }
                        }
                    });
                }
            }
        ];
    }, []);

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton>
                            <Database className="mr-1" />
                            <span>{selectedDatabase}</span>
                            <ChevronRight className="ml-auto" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                        align="start"
                        side={isMobile ? "bottom" : "right"}
                        sideOffset={4}
                    >
                        <DropdownMenuItem asChild>
                            <ModalCreateDatabase existingDatabases={parents} onSubmit={handleDatabaseSubmit}>
                                <div className="flex items-center p-1 text-sm">
                                    <Plus className="mr-1 w-4 h-4" />
                                    <span>New Database</span>
                                </div>
                            </ModalCreateDatabase>
                        </DropdownMenuItem>
                        {databases.length > 0 ? (
                            <>
                                <Separator className='my-1' />
                                {standalone.map((db) => (
                                    <AppContextMenu key={db.database_name} items={getContextMenuItems(db)}>
                                        <DropdownMenuItem
                                            key={db.database_name}
                                            onSelect={() => router.get('/dashboard', { database: db.database_name })}
                                            className={`flex justify-between items-center p-1 text-sm ${selectedDatabase === db.database_name ? 'bg-muted cursor-not-allowed' : 'cursor-pointer'}`}
                                            disabled={selectedDatabase === db.database_name}
                                        >
                                            <span className="flex items-center">
                                                {db.database_name}
                                            </span>
                                        </DropdownMenuItem>
                                    </AppContextMenu>
                                ))}
                                {parents.map((parent) => (
                                    <div key={parent.database_name}>
                                        {/* Parent database item */}
                                        <AppContextMenu items={getContextMenuItems(parent)}>
                                            <DropdownMenuItem
                                                onSelect={() => router.get('/dashboard', { database: parent.database_name })}
                                                className={`flex justify-between items-center p-1 text-sm ${selectedDatabase === parent.database_name ? 'bg-muted cursor-not-allowed' : 'cursor-pointer'}`}
                                                disabled={selectedDatabase === parent.database_name}
                                            >
                                                <span className="flex items-center">
                                                    {parent.database_name}
                                                </span>
                                                <AppTooltip text='Schema Database'>
                                                    <FileText className="ml-1 w-4 h-4" />
                                                </AppTooltip>
                                            </DropdownMenuItem>
                                        </AppContextMenu>

                                        {/* Child databases */}
                                        {childrenMap.get(parent.database_name)?.map((child) => (
                                            <AppContextMenu key={child.database_name} items={getContextMenuItems(child)}>
                                                <DropdownMenuItem
                                                    key={child.database_name}
                                                    onSelect={() => router.get('/dashboard', { database: child.database_name })}
                                                    className="p-1 text-sm cursor-pointer"
                                                    disabled={selectedDatabase === child.database_name}
                                                >
                                                    <div className="flex items-center">
                                                        <span className="text-muted-foreground/50 mr-1">└──</span>
                                                        {child.database_name}
                                                    </div>
                                                </DropdownMenuItem>
                                            </AppContextMenu>
                                        ))}
                                    </div>
                                ))}
                            </>
                        ) : (
                            <div className="p-2 text-sm text-muted-foreground">
                                No databases found. Create one to get started.
                            </div>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
