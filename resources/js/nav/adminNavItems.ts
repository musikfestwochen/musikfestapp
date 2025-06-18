import { Building2Icon, FolderGit2Icon, LayoutGrid, UnplugIcon, Users2Icon } from 'lucide-vue-next';

export const adminMainNavItems = [
    {
        title: 'Dashboard',
        route: 'admin.dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Organization Selection',
        route: 'organization-selection.index',
        icon: UnplugIcon,
    },
];

export const adminFooterNavItems = [
    {
        title: 'Users',
        route: 'users.index',
        icon: Users2Icon,
        permission: 'users.index',
    },
    {
        title: 'Organizations',
        route: 'organizations.index',
        icon: Building2Icon,
        permission: 'organizations.index',
    },
    {
        title: 'Github Repo',
        url: 'https://github.com/musikfestwochen/musikfestapp',
        icon: FolderGit2Icon,
    },
];
