import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PaginatedResults, UserDatabaseTokenProps } from '@/types';
import DatabaseToken from '@/pages/dashboard-token';

// Team Manager abilities from RoleUserSeeder: database tokens are view-only,
// but group tokens are creatable. Database Maintainer adds create-database-tokens.
const TEAM_MANAGER_ABILITIES = [
    'view-databases',
    'view-database-tokens',
    'manage-groups',
    'create-group-tokens',
    'manage-group-tokens',
];
const DATABASE_MAINTAINER_ABILITIES = [
    'view-databases',
    'view-database-tokens',
    'manage-database-tokens',
    'create-database-tokens',
];

let currentAbilities: string[] = [];

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    name: 'Test User',
                    role: 'Member',
                    teams: [],
                },
                permissions: { abilities: currentAbilities, role: [] },
            },
        },
    }),
    Head: ({ title }: { title: string }) => <title>{title}</title>,
    router: { visit: vi.fn() },
}));

vi.mock('@/lib/current-team', () => ({ getCurrentTeamId: () => null }));
vi.mock('@/layouts/app-layout', () => ({
    default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
vi.mock('@/components/app-tooltip', () => ({
    AppTooltip: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock('@/components/modals/modal-create-token', () => ({
    ModalCreateToken: ({ children }: { children: ReactNode }) => (
        <div data-testid="create-token-modal">{children}</div>
    ),
}));
vi.mock('@/components/tables/token-management', () => ({
    default: () => <div data-testid="token-table" />,
}));
vi.mock('@/components/pagination-controls', () => ({
    PaginationControls: () => <div data-testid="pagination" />,
}));

const makeTokens = (total: number) =>
    ({
        data: total ? [{} as UserDatabaseTokenProps] : [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total,
        links: [],
    }) as PaginatedResults<UserDatabaseTokenProps>;

function renderTokensPage(total: number) {
    return render(
        <DatabaseToken
            allUsers={[]}
            mostUsedDatabases={[]}
            userDatabaseTokens={makeTokens(total)}
        />
    );
}

beforeEach(() => {
    currentAbilities = TEAM_MANAGER_ABILITIES;
    vi.useFakeTimers();
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

describe('DatabaseToken create control', () => {
    it('hides both create entry points for a Team Manager with view-only access', () => {
        renderTokensPage(0);

        expect(screen.queryByText('Generate New Token')).not.toBeInTheDocument();
        expect(screen.queryByText('Generate your first token')).not.toBeInTheDocument();
        expect(screen.queryAllByTestId('create-token-modal')).toHaveLength(0);
        expect(screen.getByText(/cannot generate new ones/)).toBeInTheDocument();
    });

    it('keeps the view-only hint when the page already has tokens', () => {
        renderTokensPage(1);

        expect(screen.queryByText('Generate New Token')).not.toBeInTheDocument();
        expect(screen.getByRole('note')).toHaveTextContent('View-only access');
        expect(screen.getByTestId('token-table')).toBeInTheDocument();
    });

    it('shows the create controls to a role with create-database-tokens', () => {
        currentAbilities = DATABASE_MAINTAINER_ABILITIES;
        renderTokensPage(0);

        expect(screen.getByText('Generate New Token')).toBeInTheDocument();
        expect(screen.getByText('Generate your first token')).toBeInTheDocument();
        expect(screen.queryByText(/cannot generate new ones/)).not.toBeInTheDocument();
    });
});
