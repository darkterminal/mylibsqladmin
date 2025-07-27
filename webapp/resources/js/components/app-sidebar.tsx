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
    Github,
    Handshake,
    HeartHandshake,
    LayoutGrid,
    Users,
    UsersRound
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: route('dashboard'),
        icon: LayoutGrid,
        isAllowed: true, // Always visible
    },
    {
        title: 'Databases',
        url: route('dashboard.databases'),
        icon: DatabaseIcon,
        isAllowed: ({ can, hasRole }) =>
            can('manage-databases') || can('view-databases') || hasRole('Database Maintainer'),
    },
    {
        title: 'Tokens',
        url: route('dashboard.tokens'),
        icon: Fingerprint,
        isAllowed: ({ can, hasRole }) =>
            can('manage-database-tokens') || can('manage-group-tokens') || hasRole('Database Maintainer'),
    },
    {
        title: 'Groups',
        url: route('dashboard.groups'),
        icon: UsersRound,
        isAllowed: ({ can }) =>
            can('manage-groups') || can('view-groups'),
    },
    {
        title: 'Teams',
        url: route('dashboard.teams'),
        icon: Handshake,
        isAllowed: ({ can, hasRole }) =>
            can('view-teams') || (hasRole('Super Admin') || hasRole('Team Manager')),
    },
    {
        title: 'User Management',
        url: '/dashboard/users',
        icon: Users,
        isAllowed: ({ hasRole }) => hasRole('Super Admin'),
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Sponsor / Donate',
        url: 'https://github.com/sponsors/darkterminal',
        icon: HeartHandshake,
    },
    {
        title: 'Repository',
        url: 'https://github.com/darkterminal/mylibsqladmin',
        icon: Github,
    },
    {
        title: 'Documentation',
        url: 'https://github.com/darkterminal/mylibsqladmin',
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
