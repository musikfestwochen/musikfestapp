import { FolderGit2Icon, LayoutGrid } from 'lucide-vue-next';

// Function that returns nav items with the organization parameter already injected
export const orgMainNavItems = (organization: string | number) => [
    {
        title: 'Dashboard',
        route: 'organization.dashboard',
        icon: LayoutGrid,
        params: { organization },
    },
    // Add more org-specific nav items here
];

export const orgFooterNavItems = [
    {
        title: 'Github Repo',
        url: 'https://github.com/musikfestwochen/musikfestapp',
        icon: FolderGit2Icon,
    },
    // Add more org-specific footer items here
];
