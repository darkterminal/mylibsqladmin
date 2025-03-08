import { getQuery } from '@/lib/utils';
import { type LibSQLDatabases } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronRight, Database, FileText, Plus } from 'lucide-react';
import { CreateDatabaseProps, ModalCreateDatabaseV2 } from './modals/modal-create-database-v2';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from './ui/dropdown-menu';
import { Separator } from './ui/separator';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from './ui/sidebar';

const groupDatabases = (databases: LibSQLDatabases[]) => {
    const parents = databases.filter(db => db.is_schema === '1');
    const childrenMap = databases.reduce((map, db) => {
        if (db.is_schema !== '1') {
            const parentName = db.is_schema;
            map.set(parentName.toString(), [...(map.get(parentName.toString()) || []), db]);
        }
        return map;
    }, new Map<string, LibSQLDatabases[]>());

    return { parents, childrenMap };
};

export function SelectDatabase() {
    const { props } = usePage();
    const databases = props.databases as LibSQLDatabases[];
    const { parents, childrenMap } = groupDatabases(databases);
    const selectedDatabase = getQuery('database', 'Select Database');

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
        };

        router.post(route('database.create'), submittedData);
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
                        className="w-[--radix-popper-anchor-width]"
                        alignOffset={-150}
                        align='end'
                        sideOffset={-30}
                    >
                        <DropdownMenuItem asChild>
                            <ModalCreateDatabaseV2 existingDatabases={parents} onSubmit={handleDatabaseSubmit}>
                                <div className="flex items-center p-1 text-sm cursor-pointer">
                                    <Plus className="mr-1 w-4 h-4" />
                                    <span>New Database</span>
                                </div>
                            </ModalCreateDatabaseV2>
                        </DropdownMenuItem>
                        {databases.length > 0 && (
                            <>
                                <Separator className='my-1' />
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
                                            <FileText className="ml-1 w-4 h-4" />
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
