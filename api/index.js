const { execSync } = require('child_process');
const path = require('path');

module.exports = (req, res) => {
    try {
        const phpAppPath = path.join(__dirname, 'app.php');
        const phpCmd = process.env.PHP_BINARY || 'php';

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
        const errOutput = err.stdout ? err.stdout.toString() : (err.stderr ? err.stderr.toString() : err.message);
        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(`
            <div style="font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px; border-radius:8px;">
                <h3 style="color:red;">Serverless Bridge Output:</h3>
                <pre style="white-space:pre-wrap;">${errOutput}</pre>
            </div>
        `);
    }
};
