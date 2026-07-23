const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

module.exports = (req, res) => {
    try {
        const phpAppPath = path.join(__dirname, 'app.php');
        
        // Search for pre-compiled PHP binary from node_modules (vercel-php) or system
        let phpCmd = null;
        const candidatePaths = [
            path.join(__dirname, '../node_modules/vercel-php/bin/php'),
            path.join(__dirname, '../node_modules/vercel-php/native/php'),
            path.join(__dirname, '../node_modules/vercel-php/php'),
            '/tmp/php',
            '/var/task/node_modules/vercel-php/bin/php',
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/bin/php'
        ];

        for (const p of candidatePaths) {
            if (fs.existsSync(p)) {
                phpCmd = p;
                break;
            }
        }

        // If no binary file path found, fallback to 'php' command
        if (!phpCmd) {
            phpCmd = 'php';
        }

        const env = {
            ...process.env,
            REQUEST_METHOD: req.method || 'GET',
            REQUEST_URI: req.url || '/',
            QUERY_STRING: req.url && req.url.includes('?') ? req.url.split('?')[1] : '',
            HTTP_HOST: req.headers.host || 'sidak-tejo.vercel.app',
            HTTP_USER_AGENT: req.headers['user-agent'] || '',
            HTTP_ACCEPT: req.headers['accept'] || '',
            SCRIPT_NAME: '/index.php',
            SCRIPT_FILENAME: phpAppPath
        };

        const output = execSync(`${phpCmd} "${phpAppPath}"`, {
            env,
            maxBuffer: 15 * 1024 * 1024
        });

        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(output.toString());
    } catch (err) {
        const stderr = err.stderr ? err.stderr.toString() : '';
        const stdout = err.stdout ? err.stdout.toString() : '';
        const msg = err.message || '';
        
        // Find installed php files in node_modules for diagnostics if needed
        let foundBinaries = [];
        try {
            const nmPath = path.join(__dirname, '../node_modules');
            if (fs.existsSync(nmPath)) {
                const findFiles = (dir) => {
                    const files = fs.readdirSync(dir);
                    for (const f of files) {
                        const full = path.join(dir, f);
                        if (f === 'php' || f.endsWith('/php')) {
                            foundBinaries.push(full);
                        } else if (fs.statSync(full).isDirectory() && !full.includes('.git') && dir.split(path.sep).length < 6) {
                            try { findFiles(full); } catch(e) {}
                        }
                    }
                };
                findFiles(nmPath);
            }
        } catch (e) {}

        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(`
            <div style="font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px; border-radius:8px;">
                <h2 style="color:red;">Serverless Bridge PHP Locator Debugger:</h2>
                <p><b>Error Message:</b> ${msg}</p>
                <p><b>Found Binary Candidates in node_modules:</b> ${foundBinaries.join(', ') || 'None found'}</p>
                <p><b>STDERR:</b> ${stderr}</p>
                <p><b>STDOUT:</b> ${stdout}</p>
            </div>
        `);
    }
};
