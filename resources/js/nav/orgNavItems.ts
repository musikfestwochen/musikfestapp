import { FolderGit2Icon, LayoutGrid, ProjectorIcon, Users2Icon, WindIcon } from 'lucide-vue-next';

// Function that returns nav items with the organization parameter already injected
export const orgMainNavItems = (organization: string | number) => [
    {
        title: 'Dashboard',
        route: 'organization.dashboard',
        icon: LayoutGrid,
        params: { organization },
    },
    {
        title: 'Users',
        route: 'orgmgmt.users.index',
        icon: Users2Icon,
        permission: 'orgmgmt.users.index',
        params: { organization },
    },
    {
        title: 'Peoplecount',
        icon: ProjectorIcon,
        children: [
            {
                title: 'Sensors',
                route: 'peoplecount.sensors.index',
                permission: 'peoplecount.sensors.index',
                params: { organization },
            },
            {
                title: 'Events',
                route: 'peoplecount.events.index',
                permission: 'peoplecount.events.index',
                params: { organization },
            },
            {
                title: 'Areas',
                route: 'peoplecount.areas.index',
                permission: 'peoplecount.areas.index',
                params: { organization },
            },
            {
                title: 'Assignments',
                route: 'peoplecount.assignments.index',
                permission: 'peoplecount.assignments.index',
                params: { organization },
            },
        ],
    },
    {
        title: 'Stage Safety',
        icon: WindIcon,
        children: [
            {
                title: 'Sensors',
                route: 'stage-safety.sensors.index',
                permission: 'stage-safety.sensors.index',
                params: { organization },
            },
        ],
    },
];

export const orgFooterNavItems = [
    {
        title: 'Github Repo',
        url: 'https://github.com/musikfestwochen/musikfestapp',
        icon: FolderGit2Icon,
    },
    // Add more org-specific footer items here
];
