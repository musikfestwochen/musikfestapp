import { exec } from 'child_process';
import { promisify } from 'util';
import { waitForVite } from './utils/waitForVite';

const execAsync = promisify(exec);

async function globalSetup() {
    await waitForVite();
    console.log('Running database migrations and seeding...');

    try {
        const { stdout, stderr } = await execAsync('php artisan migrate:fresh --seed');

        if (stdout) {
            console.log('Migration output:', stdout);
        }

        if (stderr) {
            console.error('Migration error:', stderr);
        }

        console.log('Database setup complete.');
    } catch (error) {
        console.error('Failed to setup database:', error);
        throw error;
    }
}

export default globalSetup;
