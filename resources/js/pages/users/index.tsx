import { Form, Head, router, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index as usersIndex } from '@/routes/users';
import type { Role } from '@/types';

type RoleOption = { value: Role; label: string };

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    status: 'pending' | 'active';
    invited_at: string | null;
    invitation_accepted_at: string | null;
    created_at: string | null;
};

type Props = {
    users: ManagedUser[];
    roles: RoleOption[];
};

export default function UsersIndex({ users, roles }: Props) {
    const { auth } = usePage().props;
    const [inviteOpen, setInviteOpen] = useState(false);

    const changeRole = (user: ManagedUser, role: string) => {
        if (role === user.role) {
            return;
        }

        router.patch(
            UserController.update(user.id).url,
            { role },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(`Updated ${user.name}'s role.`),
            },
        );
    };

    const resendInvite = (user: ManagedUser) => {
        router.post(UserController.resend(user.id).url, undefined, {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(`Invitation resent to ${user.email}.`),
        });
    };

    const removeUser = (user: ManagedUser) => {
        router.delete(UserController.destroy(user.id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Removed ${user.name}.`),
        });
    };

    return (
        <div className="px-4 py-6">
            <Head title="Users" />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-4">
                <div className="space-y-0.5">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Users
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Invite teammates and manage their roles
                    </p>
                </div>

                <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                    <DialogTrigger asChild>
                        <Button data-test="invite-user-button">
                            Invite user
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Invite a user</DialogTitle>
                            <DialogDescription>
                                They'll receive an email to set a password and
                                activate their account.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            {...UserController.store.form()}
                            resetOnSuccess
                            onSuccess={() => {
                                setInviteOpen(false);
                                toast.success('Invitation sent.');
                            }}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            autoFocus
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role</Label>
                                        <Select
                                            name="role"
                                            defaultValue="member"
                                        >
                                            <SelectTrigger id="role">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((role) => (
                                                    <SelectItem
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.role} />
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Send invitation
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>

            {users.length === 0 ? (
                <div className="rounded-lg border border-border bg-card">
                    <EmptyState
                        icon={Users}
                        title="No teammates yet"
                        description="Invite the people who work this queue. They'll get an email to set a password and pick up leads."
                        action={
                            <Button onClick={() => setInviteOpen(true)}>
                                Invite user
                            </Button>
                        }
                    />
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-border bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground">
                                <th className="px-4 py-2.5">Name</th>
                                <th className="px-3 py-2.5">Email</th>
                                <th className="px-3 py-2.5">Role</th>
                                <th className="px-3 py-2.5">Status</th>
                                <th className="px-3 py-2.5 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => {
                                const isSelf = user.id === auth.user.id;

                                return (
                                    <tr
                                        key={user.id}
                                        className="group h-12 border-b border-border/60 transition-colors duration-150 ease-[var(--ease-out)] last:border-0 hover:bg-accent/50"
                                    >
                                        <td className="px-4 font-medium">
                                            {user.name}
                                            {isSelf && (
                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                    (you)
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 text-muted-foreground">
                                            {user.email}
                                        </td>
                                        <td className="px-3">
                                            <Select
                                                defaultValue={user.role}
                                                disabled={isSelf}
                                                onValueChange={(value) =>
                                                    changeRole(user, value)
                                                }
                                            >
                                                <SelectTrigger className="h-8 w-36">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem
                                                            key={role.value}
                                                            value={role.value}
                                                        >
                                                            {role.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td className="px-3">
                                            <Badge
                                                variant={
                                                    user.status === 'pending'
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                            >
                                                {user.status === 'pending'
                                                    ? 'Pending'
                                                    : 'Active'}
                                            </Badge>
                                        </td>
                                        <td className="px-3">
                                            <div className="flex justify-end gap-1.5 opacity-80 transition-opacity duration-150 ease-[var(--ease-out)] group-hover:opacity-100">
                                                {user.status === 'pending' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7"
                                                        onClick={() =>
                                                            resendInvite(user)
                                                        }
                                                    >
                                                        Resend
                                                    </Button>
                                                )}
                                                {!isSelf && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            removeUser(user)
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

UsersIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Users',
            href: usersIndex(),
        },
    ],
});
