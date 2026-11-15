import { renderHook } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { getRole, usePermission } from '@/lib/auth';

// SharedData payload shape (mirror of the Laravel HandleInertiaRequests share).
const makeProps = ({
    globalRole = 'Member',
    teamRoleLevel = null as string | null,
    abilities = [] as string[],
}) => ({
    auth: {
        user: {
            id: 1,
            name: 'Test User',
            username: 'testuser',
            email: 'test@example.com',
            role: globalRole,
            teams: teamRoleLevel
                ? [{ id: 7, pivot: { permission_level: teamRoleLevel } }]
                : [],
            permission_names: abilities,
        },
        permissions: {
            abilities,
            role: [globalRole],
        },
    },
});

let currentProps: ReturnType<typeof makeProps>;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: currentProps }),
}));

let currentTeamId: number | null = null;

vi.mock('@/lib/current-team', () => ({
    getCurrentTeamId: () => currentTeamId,
}));

describe('getRole', () => {
    it('maps pivot permission levels to display roles', () => {
        expect(getRole('super-admin')).toBe('Super Admin');
        expect(getRole('team-manager')).toBe('Team Manager');
        expect(getRole('database-maintainer')).toBe('Database Maintainer');
        expect(getRole('member')).toBe('Member');
    });

    it('passes through unknown roles unchanged', () => {
        expect(getRole('custom-role')).toBe('custom-role');
    });
});

describe('usePermission', () => {
    beforeEach(() => {
        currentTeamId = null;
        currentProps = makeProps({});
    });

    it('can() checks the abilities list', () => {
        currentProps = makeProps({ abilities: ['view-databases'] });

        const { result } = renderHook(() => usePermission());

        expect(result.current.can('view-databases')).toBe(true);
        expect(result.current.can('delete-databases')).toBe(false);
    });

    it('can() is false when permissions are missing', () => {
        currentProps = makeProps({});
        // @ts-expect-error — deliberately malformed payload to prove the guard works
        currentProps.auth = undefined;

        const { result } = renderHook(() => usePermission());

        expect(result.current.can('view-databases')).toBe(false);
    });

    it('hasRole() is true for the global role even without teams', () => {
        currentProps = makeProps({ globalRole: 'Super Admin' });

        const { result } = renderHook(() => usePermission());

        expect(result.current.hasRole('Super Admin')).toBe(true);
        expect(result.current.hasRole('Member')).toBe(false);
    });

    it('hasRole() is true for the team role of the current team', () => {
        currentTeamId = 7;
        currentProps = makeProps({ teamRoleLevel: 'team-manager' });

        const { result } = renderHook(() => usePermission());

        expect(result.current.hasRole('Team Manager')).toBe(true);
        expect(result.current.hasRole('Database Maintainer')).toBe(false);
    });

    it('hasRole() ignores the team role of non-current teams', () => {
        currentTeamId = 99; // current team is not the one carrying the role
        currentProps = makeProps({ teamRoleLevel: 'team-manager' });

        const { result } = renderHook(() => usePermission());

        expect(result.current.hasRole('Team Manager')).toBe(false);
    });

    it('hasAnyRole() matches the global or team role', () => {
        currentTeamId = 7;
        currentProps = makeProps({ globalRole: 'Member', teamRoleLevel: 'database-maintainer' });

        const { result } = renderHook(() => usePermission());

        expect(result.current.hasAnyRole('Database Maintainer', 'Team Manager')).toBe(true);
        expect(result.current.hasAnyRole('Super Admin')).toBe(false);
    });
});
