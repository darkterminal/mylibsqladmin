import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BookOpen,
    DatabaseIcon,
    Fingerprint,
    Folder,
    Handshake,
    LayoutGrid,
    Users,
    UsersRound
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
        isAllowed: true, // Always visible
    },
    {
        title: 'Databases',
        url: '/dashboard/databases',
        icon: DatabaseIcon,
        isAllowed: ({ can }) =>
            can('view-databases') || can('manage-databases'),
    },
    {
        title: 'Tokens',
        url: '/dashboard/tokens',
        icon: Fingerprint,
        isAllowed: ({ can }) =>
            can('manage-database-tokens') || can('manage-group-tokens'),
    },
    {
        title: 'Groups',
        url: '/dashboard/groups',
        icon: UsersRound,
        isAllowed: ({ can }) =>
            can('manage-groups') || can('view-groups'),
    },
    {
        title: 'Teams',
        url: '/dashboard/teams',
        icon: Handshake,
        isAllowed: ({ can, hasRole }) =>
            // Allow view-teams for basic access, manage-teams for full control
            can('view-teams') || (hasRole('Super Admin') && can('manage-teams')),
    },
    // {
    //     title: 'Team Members',
    //     url: '/dashboard/team-members',
    //     icon: UserCog,
    //     isAllowed: ({ can }) =>
    //         can('manage-team-members') || can('view-team-members'),
    // },
    {
        title: 'User Management',
        url: '/dashboard/users',
        icon: Users,
        isAllowed: ({ hasRole }) => hasRole('Super Admin'),
    },
    // {
    //     title: 'Roles & Permissions',
    //     url: '/dashboard/roles',
    //     icon: ShieldCheck,
    //     isAllowed: ({ can }) => can('manage-roles'),
    // }
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
