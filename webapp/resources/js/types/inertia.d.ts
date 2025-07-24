import { Auth, Configs, FlashMessageProps, GroupDatabaseProps, Invitation, LibSQLDatabases } from ".";

declare module '@inertiajs/core' {
    interface PageProps {
        name: string;
        auth: Auth;
        flash: FlashMessageProps;
        databases: LibSQLDatabases[];
        groups: GroupDatabaseProps[];
        configs?: Configs;
        invitation?: Invitation;
        csrfToken: string;
    }
}
