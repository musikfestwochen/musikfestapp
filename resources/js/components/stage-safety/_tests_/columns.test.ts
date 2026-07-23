import type { Organization } from '@/types';
import { describe, expect, it } from 'vitest';
import { sensorColumns } from '../sensors/columns';

describe('Stage Safety sensor columns', () => {
    it('exposes API token status without a plaintext token column', () => {
        const organization = { id: 1, slug: 'test', name: 'Test', created_at: '', updated_at: '' } satisfies Organization;
        const keys = sensorColumns(organization).map((column) => ('accessorKey' in column ? column.accessorKey : column.id));

        expect(keys).toContain('has_active_token');
        expect(keys).toContain('identifier');
        expect(keys).not.toContain('api_token');
        expect(keys).not.toContain('token');
    });
});
