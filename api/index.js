const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

module.exports = (req, res) => {
    try {
        const phpAppPath = path.join(__dirname, 'app.php');
        
        let phpCmd = 'php';
        const possiblePaths = ['/usr/bin/php', '/usr/local/bin/php', '/opt/bin/php', 'php'];
        for (const p of possiblePaths) {
            if (fs.existsSync(p)) {
                phpCmd = p;
                break;
            }
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
        
        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(`
            <div style="font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px; border-radius:8px;">
                <h2 style="color:red;">Serverless Bridge Output Debugger:</h2>
                <p><b>Error Message:</b> ${msg}</p>
                <p><b>STDOUT:</b> ${stdout}</p>
                <p><b>STDERR:</b> ${stderr}</p>
            </div>
        `);
    }
};
