import { exec, execFile } from 'node:child_process';
import { readFile, rm, stat, unlink } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { promisify } from 'node:util';

const execAsync = promisify(exec);
const execFileAsync = promisify(execFile);

/**
 * @param {string} file
 */
async function my_unlink(file) {
    try {
        await unlink(file);
    } catch (e) {
        if (e.code !== 'ENOENT') {
            throw e;
        }
    }
}

/**
 * @param {string} dir
 */
async function my_rm(dir) {
    try {
        await rm(dir, { recursive: true });
    } catch (e) {
        if (e.code !== 'ENOENT') {
            throw e;
        }
    }
}

/**
 * @param {string} file
 * @returns {Promise<boolean>}
 */
async function exists(file) {
    try {
        const st = await stat(file);
        return st.isFile();
    } catch (e) {
        return false;
    }
}

const [,, ...args] = process.argv;
for (const arg of args) {
    console.log(`Processing ${arg}...`);
    const file = resolve(arg);
    if (await exists(file)) {
        const dir = dirname(file);
        const vendor = join(dir, 'vendor');

        const source = JSON.parse(await readFile(file, 'utf8'));

        await my_unlink(file);
        await my_rm(vendor);

        await execAsync(`composer install -n`);

        const updated = JSON.parse(await readFile(file, 'utf8'));

        const packages = new Map();
        updated.packages.forEach((pkg) => packages.set(pkg.name, pkg.version));
        updated['packages-dev'].forEach((pkg) => packages.set(pkg.name, pkg.version));

        const update = [];

        [...source.packages, ...source['packages-dev']].forEach((pkg) => {
            if (packages.has(pkg.name)) {
                if (packages.get(pkg.name) === pkg.version) {
                    packages.delete(pkg.name);
                } else {
                    console.log(`Downgrade ${pkg.name} from ${packages.get(pkg.name)} to ${pkg.version}`);
                    packages.delete(pkg.name);
                    update.push(`${pkg.name}:${pkg.version}`);
                }
            } else {
                console.log(`New package: ${pkg.name}:${pkg.version}`);
            }
        });

        packages.forEach((version, name) => {
            console.log(`Remove: ${name}:${version}`);
        });

        console.log(`Running \`composer update -W -n ${update.join(' ')}\`...`);
        await execFileAsync('composer', ['update', '-W', '-n', ...update])
    } else {
        console.warn(`::warning file=${file}::File not found: ${file}`);
    }
};
