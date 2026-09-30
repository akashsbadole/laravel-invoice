import { Link } from '@inertiajs/react';
import {
    LayoutDashboard,
    Users,
    FileText,
    CreditCard,
    BarChart3,
    Bell,
    Settings,
} from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarHeader, SidebarContent, SidebarFooter } from '@/components/ui/sidebar';

const navItems = [
    { title: 'Dashboard', href: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" /> },
    { title: 'Customers', href: '/customers', icon: <Users className="h-4 w-4" /> },
    { title: 'Invoices', href: '/invoices', icon: <FileText className="h-4 w-4" /> },
    { title: 'Payments', href: '/payments', icon: <CreditCard className="h-4 w-4" /> },
    { title: 'Reports', href: '/reports', icon: <BarChart3 className="h-4 w-4" /> },
    { title: 'Reminders', href: '/reminders', icon: <Bell className="h-4 w-4" /> },
];

export function AppSidebar() {
    return (
        <Sidebar>
            <SidebarHeader>
                <AppLogo />
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
                <NavFooter />
            </SidebarFooter>
        </Sidebar>
    );
}
