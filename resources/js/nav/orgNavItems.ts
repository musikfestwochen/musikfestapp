import { CalendarIcon, FolderGit2Icon, LayoutGrid, LinkIcon, MapPinIcon, ProjectorIcon, Users2Icon } from 'lucide-vue-next';

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
        title: 'Peoplecount Sensors',
        route: 'peoplecount.sensors.index',
        icon: ProjectorIcon,
        permission: 'peoplecount.sensors.index',
        params: { organization },
    },
    {
        title: 'Peoplecount Events',
        route: 'peoplecount.events.index',
        icon: CalendarIcon,
        permission: 'peoplecount.events.index',
        params: { organization },
    },
    {
        title: 'Peoplecount Areas',
        route: 'peoplecount.areas.index',
        icon: MapPinIcon,
        permission: 'peoplecount.areas.index',
        params: { organization },
    },
    {
        title: 'Peoplecount Assignments',
        route: 'peoplecount.assignments.index',
        icon: LinkIcon,
        permission: 'peoplecount.assignments.index',
        params: { organization },
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
