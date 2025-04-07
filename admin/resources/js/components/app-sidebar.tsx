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
        isAllowed: ({ can, hasRole }) =>
            // Allow Super Admin or users with team database access
            hasRole('Super Admin') || can('access-team-databases'),
    },
    {
        title: 'Tokens',
        url: '/dashboard/tokens',
        icon: Fingerprint,
        isAllowed: ({ can, hasRole }) =>
            // Allow personal token management (Members) OR group token admins (Database Maintainers)
            hasRole('Super Admin') || can('manage-database-tokens') || can('manage-group-database-tokens'),
    },
    {
        title: 'Groups',
        url: '/dashboard/groups',
        icon: UsersRound,
        isAllowed: ({ can, hasRole }) =>
            // Allow team/group managers or Super Admin
            hasRole('Super Admin') || can('manage-team-groups'),
    },
    {
        title: 'Teams',
        url: '/dashboard/teams',
        icon: Handshake,
        isAllowed: ({ can, hasRole }) =>
            // Restrict to Super Admin or Team Managers
            hasRole('Super Admin') || can('manage-teams'),
    }
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
