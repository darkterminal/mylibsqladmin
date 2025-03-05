import { type LibSQLDatabases } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronRight, Database, Plus } from 'lucide-react';
import { ModalCreateDatabase } from './modals/modal-create-database';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from './ui/dropdown-menu';
import { Separator } from './ui/separator';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from './ui/sidebar';

export function SelectDatabase() {
    const { props } = usePage();
    const databases = props.databases as LibSQLDatabases[];

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton>
                            <Database className="mr-1" />
                            <span>Select Database</span>
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
                            <ModalCreateDatabase>
                                <div className="flex justify-between items-center p-1 text-sm">
                                    <Plus className="mr-1 w-4 h-4" />
                                    <span>New Database</span>
                                </div>
                            </ModalCreateDatabase>
                        </DropdownMenuItem>

                        {databases.length > 0 && (
                            <>
                                <Separator className='my-1' />
                                {databases.map((database) => (
                                    <DropdownMenuItem
                                        key={database.name}
                                    >
                                        <span>{database.name}</span>
                                    </DropdownMenuItem>
                                ))}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
