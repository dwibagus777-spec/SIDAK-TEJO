const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

module.exports = (req, res) => {
    try {
        const phpAppPath = path.join(__dirname, 'app.php');
        
        let phpCmd = null;
        
        // Deep search for any php or php-cgi binary file in node_modules or system
        const searchDirs = [
            path.join(__dirname, '../node_modules'),
            '/tmp',
            '/opt',
            '/usr/bin',
            '/usr/local/bin'
        ];

        for (const dir of searchDirs) {
            if (fs.existsSync(dir)) {
                try {
                    const findPhp = (currentDir, depth = 0) => {
                        if (depth > 6 || phpCmd) return;
                        const items = fs.readdirSync(currentDir);
                        for (const item of items) {
                            const fullPath = path.join(currentDir, item);
                            try {
                                const stat = fs.statSync(fullPath);
                                if (stat.isFile() && (item === 'php' || item === 'php-cgi')) {
                                    phpCmd = fullPath;
                                    return;
                                } else if (stat.isDirectory() && !item.startsWith('.')) {
                                    findPhp(fullPath, depth + 1);
                                }
                            } catch (e) {}
                        }
                    };
                    findPhp(dir);
                } catch (e) {}
            }
            if (phpCmd) break;
        }

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

        const output = execSync(`"${phpCmd}" "${phpAppPath}"`, {
            env,
            maxBuffer: 15 * 1024 * 1024
        });

        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(output.toString());
    } catch (err) {
        const stderr = err.stderr ? err.stderr.toString() : '';
        const stdout = err.stdout ? err.stdout.toString() : '';
        const msg = err.message || '';
        
        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(`
            <div style="font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px; border-radius:8px;">
                <h2 style="color:red;">Serverless Bridge Deep PHP Finder Debugger:</h2>
                <p><b>Error Message:</b> ${msg}</p>
                <p><b>STDERR:</b> ${stderr}</p>
                <p><b>STDOUT:</b> ${stdout}</p>
            </div>
        `);
    }
};
