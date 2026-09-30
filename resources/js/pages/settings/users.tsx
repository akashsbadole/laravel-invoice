import { Form, Head, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import UserController from '@/actions/App/Http/Controllers/Settings/UserController';
import { destroy as destroyUser } from '@/routes/users';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Auth } from '@/types/auth';
import type { ManagedUser, RoleOption } from '@/types/user';

export default function UsersPage({ users, roles }: { users: ManagedUser[]; roles: RoleOption[] }) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6">
                <Heading variant="small" title="Users" description="Who can sign in, and what they can do" />

                <div className="flex justify-end">
                    <AddUserDialog roles={roles} />
                </div>

                <div className="space-y-2">
                    {users.map((user) => (
                        <Card key={user.id}>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{user.name}</span>
                                        <Badge variant="outline" className="capitalize">{user.role.replace('_', ' ')}</Badge>
                                        {!user.is_active && <Badge variant="secondary">inactive</Badge>}
                                        {user.id === auth.user.id && <Badge variant="secondary">you</Badge>}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {user.email}
                                        {user.last_login_at && ` · last seen ${new Date(user.last_login_at).toLocaleString()}`}
                                    </p>
                                </div>
                                <EditUserDialog user={user} roles={roles} isSelf={user.id === auth.user.id} />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

function AddUserDialog({ roles }: { roles: RoleOption[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Add user
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New user</DialogTitle>
                </DialogHeader>
                <Form
                    {...UserController.store.form()}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" required autoComplete="name" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" name="email" type="email" required autoComplete="email" />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role">Role</Label>
                                <Select name="role" defaultValue="invoice_creator">
                                    <SelectTrigger id="role" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">Temporary password</Label>
                                <Input id="password" name="password" type="password" required autoComplete="new-password" />
                                <InputError message={errors.password} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button disabled={processing}>Add</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserDialog({ user, roles, isSelf }: { user: ManagedUser; roles: RoleOption[]; isSelf: boolean }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">Edit</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {user.name}</DialogTitle>
                </DialogHeader>
                <Form
                    {...UserController.update.form(user.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${user.id}`}>Name</Label>
                                <Input id={`name-${user.id}`} name="name" defaultValue={user.name} required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`email-${user.id}`}>Email</Label>
                                <Input id={`email-${user.id}`} name="email" type="email" defaultValue={user.email} required />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`role-${user.id}`}>Role</Label>
                                <Select name="role" defaultValue={user.role} disabled={isSelf}>
                                    <SelectTrigger id={`role-${user.id}`} className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {isSelf && <p className="text-xs text-muted-foreground">You can't change your own role.</p>}
                                <InputError message={errors.role} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`password-${user.id}`}>New password (optional)</Label>
                                <Input id={`password-${user.id}`} name="password" type="password" autoComplete="new-password" />
                                <InputError message={errors.password} />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id={`active-${user.id}`}
                                    name="is_active"
                                    defaultChecked={user.is_active}
                                    disabled={isSelf}
                                    className="size-4"
                                />
                                <Label htmlFor={`active-${user.id}`}>Active (can sign in)</Label>
                            </div>
                            <DialogFooter className="gap-2">
                                {!isSelf && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        className="mr-auto"
                                        onClick={() => {
                                            if (confirm(`Delete ${user.name}? This can't be undone.`)) {
                                                router.delete(destroyUser(user.id).url);
                                            }
                                        }}
                                    >
                                        Delete
                                    </Button>
                                )}
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">Close</Button>
                                </DialogClose>
                                <Button disabled={processing}>Save</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
