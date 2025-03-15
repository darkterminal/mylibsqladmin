import TokenMangement from "@/components/tables/token-mangement";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head } from "@inertiajs/react";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Tokens',
        href: '/dashboard/tokens',
    }
];

export default function DatabaseToken() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tokens" />
            <div className="flex h-full flex-1 p-8">
                <TokenMangement />
            </div>
        </AppLayout>
    );
}
