import { execSync } from 'child_process';
try {
    const output = execSync('npx vite build', { encoding: 'utf8' });
    console.log(output);
} catch(e) {
    console.error(e.stdout || e.message);
    process.exit(1);
}
