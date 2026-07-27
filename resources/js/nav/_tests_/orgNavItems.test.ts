import { describe, expect, it } from 'vitest';
import { orgMainNavItems } from '../orgNavItems';

describe('organization navigation', () => {
    it('groups organization features under four top-level items', () => {
        const items = orgMainNavItems('musikfest');

        expect(items.map((item) => item.title)).toEqual(['Dashboard', 'Users', 'Peoplecount', 'Stage Safety']);
        expect(items[2].children?.map((item) => item.title)).toEqual(['Dashboard', 'Sensors', 'Events', 'Areas', 'Assignments']);
        expect(items[2].children?.[0]).toMatchObject({ route: 'peoplecount.dashboard', permission: 'peoplecount.dashboard.view' });
        expect(items[3].children?.map((item) => item.title)).toEqual(['Dashboard', 'Sensors']);
        expect(items[3].children?.[0]).toMatchObject({ route: 'stage-safety.dashboard', permission: 'stage-safety.monitoring.view' });
    });
});
