import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type LoginForm = {
    username: string;
    password: string;
    currentTeamId: string;
    remember: boolean;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    disabledSignUpPage: boolean;
}

export default function Login({ status, canResetPassword, disabledSignUpPage }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        username: '',
        password: '',
        currentTeamId: 'null',
        remember: false,
    });

    useEffect(() => {
        const storedTeamId = localStorage.getItem('currentTeamId') || 'null';
        setData('currentTeamId', storedTeamId);

        const handleStorageChange = (e: StorageEvent) => {
            if (e.key === 'currentTeamId') {
                setData('currentTeamId', e.newValue || 'null');
            }
        };

        window.addEventListener('storage', handleStorageChange);
        return () => window.removeEventListener('storage', handleStorageChange);
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="MylibSQLAdmin" description="Your managed libSQL Database Platform">
            <Head title="Log in" />

            <form className="flex flex-col gap-6" onSubmit={submit} autoComplete='off'>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="off"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            placeholder="username"
                        />
                        <InputError message={errors.username} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">Password</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" checked={data.remember} onClick={() => setData('remember', !data.remember)} tabIndex={3} />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>
                    <Input type="hidden" name="currentTeamId" value={data.currentTeamId} />

                    <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Log in
                    </Button>
                </div>

                {disabledSignUpPage && (
                    <div className="text-muted-foreground text-center text-sm">
                        Don't have an account?{' '}
                        <TextLink href={route('register')} tabIndex={5}>
                            Sign up
                        </TextLink>
                    </div>
                )}
            </form>

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}

            <div className='text-xs text-center relative bottom-4 w-full'>
                <p>Develop by <TextLink href="https://github.com/sponsors/darkterminal" target="_blank" rel="noreferrer" className='font-bold'>darkterminal</TextLink> the creator of <TextLink href="https://github.com/tursodatabase/turso-client-php" target="_blank" rel="noreferrer" className='font-bold'>libSQL PHP Extension</TextLink></p>
            </div>
        </AuthLayout>
    );
}
