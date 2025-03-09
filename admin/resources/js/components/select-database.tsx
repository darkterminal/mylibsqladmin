import { getQuery, groupDatabases } from '@/lib/utils';
import { type LibSQLDatabases } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronRight, Database, Eye, FileText, Plus } from 'lucide-react';
import { toast } from 'sonner';
import { AppTooltip } from './app-tooltip';
import { CreateDatabaseProps, ModalCreateDatabaseV2 } from './modals/modal-create-database-v2';
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
                            <ModalCreateDatabaseV2 existingDatabases={parents} onSubmit={handleDatabaseSubmit}>
                                <div className="flex items-center p-1 text-sm">
                                    <Plus className="mr-1 w-4 h-4" />
                                    <span>New Database</span>
                                </div>
                            </ModalCreateDatabaseV2>
                        </DropdownMenuItem>
                        {databases.length > 0 && (
                            <>
                                <Separator className='my-1' />
                                {standalone.map((db) => (
                                    <DropdownMenuItem
                                        key={db.database_name}
                                        onSelect={() => router.get('/dashboard', { database: db.database_name })}
                                        className="flex justify-between items-center p-1 text-sm cursor-pointer"
                                    >
                                        <span className="flex items-center">
                                            {db.database_name}
                                        </span>
                                    </DropdownMenuItem>
                                ))}
                                {parents.map((parent) => (
                                    <div key={parent.database_name}>
                                        {/* Parent database item */}
                                        <DropdownMenuItem
                                            onSelect={() => router.get('/dashboard', { database: parent.database_name })}
                                            className="flex justify-between items-center p-1 text-sm cursor-pointer"
                                        >
                                            <span className="flex items-center">
                                                {parent.database_name}
                                            </span>
                                            <AppTooltip text='Schema Database'>
                                                <FileText className="ml-1 w-4 h-4" />
                                            </AppTooltip>
                                        </DropdownMenuItem>

                                        {/* Child databases */}
                                        {childrenMap.get(parent.database_name)?.map((child) => (
                                            <DropdownMenuItem
                                                key={child.database_name}
                                                onSelect={() => router.get('/dashboard', { database: child.database_name })}
                                                className="p-1 text-sm cursor-pointer"
                                            >
                                                <div className="flex items-center">
                                                    <span className="text-muted-foreground/50 mr-1">└──</span>
                                                    {child.database_name}
                                                </div>
                                            </DropdownMenuItem>
                                        ))}
                                    </div>
                                ))}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
