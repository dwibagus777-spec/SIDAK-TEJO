const path = require('path');
const fs = require('fs');

module.exports = async (req, res) => {
    try {
        const { PHP } = require('@php-wasm/node');
        const php = await PHP.load('8.2');

        const appPath = path.join(__dirname, 'app.php');
        const scriptContent = fs.readFileSync(appPath, 'utf-8');

        php.mkdirTree('/var/task/api');
        php.writeFile('/var/task/api/app.php', scriptContent);

        const result = await php.run({
            scriptPath: '/var/task/api/app.php'
        });

        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(result.text || result.stdout || 'OK');
    } catch (wasmErr) {
        try {
            const { execSync } = require('child_process');
            const phpAppPath = path.join(__dirname, 'app.php');
            const output = execSync(`php "${phpAppPath}"`, {
                env: { ...process.env, REQUEST_METHOD: req.method || 'GET', REQUEST_URI: req.url || '/' },
                maxBuffer: 15 * 1024 * 1024
            });
            res.setHeader('Content-Type', 'text/html; charset=UTF-8');
            return res.status(200).send(output.toString());
        } catch (cliErr) {
            res.setHeader('Content-Type', 'text/html; charset=UTF-8');
            return res.status(200).send(`
                <div style="font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px; border-radius:8px;">
                    <h2 style="color:red;">Serverless Bridge WASM/CLI Debugger:</h2>
                    <p><b>WASM Error:</b> ${wasmErr.message || wasmErr}</p>
                    <p><b>CLI Error:</b> ${cliErr.message || cliErr}</p>
                </div>
            `);
        }
    }
};
