import net from 'net';

const VITE_PORT = 5173;
const TIMEOUT = 10000; // ms

export async function waitForVite(): Promise<void> {
    if (process.env.CI) {
        // Skip check in CI
        return;
    }
    const start = Date.now();
    while (Date.now() - start < TIMEOUT) {
        const isOpen = (await isPortOpen(VITE_PORT, '127.0.0.1')) || (await isPortOpen(VITE_PORT, 'localhost'));
        if (isOpen) return;
        await new Promise((r) => setTimeout(r, 300));
    }
    throw new Error(`Vite dev server not detected on port ${VITE_PORT}. Please run 'npm run dev'.`);
}

function isPortOpen(port: number, host: string): Promise<boolean> {
    return new Promise((resolve) => {
        const socket = new net.Socket();
        socket.setTimeout(1000);
        socket.once('connect', () => {
            socket.destroy();
            resolve(true);
        });
        socket.once('timeout', () => {
            socket.destroy();
            resolve(false);
        });
        socket.once('error', () => {
            resolve(false);
        });
        socket.connect(port, host);
    });
}
