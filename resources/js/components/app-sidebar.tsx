import { Link, usePage } from '@inertiajs/react';
import {
    Copy,
    LayoutGrid,
    LifeBuoy,
    ListChecks,
    ScrollText,
    Target,
    Users,
    Zap,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard, help } from '@/routes';
import { index as auditIndex } from '@/routes/audit';
import { index as automationsIndex } from '@/routes/automations';
import { index as duplicatesIndex } from '@/routes/duplicates';
import { index as leadsIndex } from '@/routes/leads';
import { index as tasksIndex } from '@/routes/tasks';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const dashboardUrl = dashboard();

    const workspaceNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Leads',
            href: leadsIndex(),
            icon: Target,
        },
        {
            title: 'Tasks',
            href: tasksIndex(),
            icon: ListChecks,
        },
        ...(auth.can.merge_duplicates
            ? [
                  {
                      title: 'Duplicates',
                      href: duplicatesIndex(),
                      icon: Copy,
                  },
              ]
            : []),
    ];

    const adminNavItems: NavItem[] = [
        ...(auth.can.manage_users
            ? [
                  {
                      title: 'Automations',
                      href: automationsIndex(),
                      icon: Zap,
                  },
                  {
                      title: 'Users',
                      href: usersIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(auth.can.view_audit_log
            ? [
                  {
                      title: 'Audit log',
                      href: auditIndex(),
                      icon: ScrollText,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0 py-2">
                <NavMain items={workspaceNavItems} label="Workspace" />
                {adminNavItems.length > 0 && (
                    <NavMain items={adminNavItems} label="Admin" />
                )}
            </SidebarContent>

            <SidebarFooter className="gap-1">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip="Help"
                            className="[&>svg]:text-sidebar-foreground/70"
                        >
                            <Link href={help()} prefetch>
                                <LifeBuoy />
                                <span>Help</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarSeparator className="my-1" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
