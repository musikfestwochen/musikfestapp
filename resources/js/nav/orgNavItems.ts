import { FolderGit2Icon, LayoutGrid } from 'lucide-vue-next';

// orgMainNavItems should be a function to accept organization context if needed
export const orgMainNavItems = [
    {
        title: 'Dashboard',
        route: 'organization.dashboard',
        icon: LayoutGrid,
        // params: { organization: ... } // Add dynamically in the page if needed
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
