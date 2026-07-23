const path = require('path');
const fs = require('fs');

module.exports = async (req, res) => {
    try {
        const phpWasm = require('@php-wasm/node');
        const exportsList = Object.keys(phpWasm).join(', ');
        
        let phpInstance = null;

        if (phpWasm.PHP && typeof phpWasm.PHP.load === 'function') {
            phpInstance = await phpWasm.PHP.load('8.2');
        } else if (phpWasm.loadPHPRuntime && typeof phpWasm.loadPHPRuntime === 'function') {
            phpInstance = await phpWasm.loadPHPRuntime('8.2');
        } else if (phpWasm.PhpNode) {
            phpInstance = new phpWasm.PhpNode();
        } else if (phpWasm.PhpWeb) {
            phpInstance = new phpWasm.PhpWeb();
        } else if (typeof phpWasm === 'function') {
            phpInstance = await phpWasm();
        }

        if (!phpInstance) {
            throw new Error(`WASM exports available: [${exportsList}], but no load/instantiator method matched.`);
        }

        const appPath = path.join(__dirname, 'app.php');
        const scriptContent = fs.readFileSync(appPath, 'utf-8');

        if (typeof phpInstance.mkdirTree === 'function') {
            phpInstance.mkdirTree('/var/task/api');
            phpInstance.writeFile('/var/task/api/app.php', scriptContent);
        }

        const result = await phpInstance.run({
            scriptPath: '/var/task/api/app.php'
        });

        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(result.text || result.stdout || JSON.stringify(result));
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
                    <h2 style="color:red;">Serverless Bridge WASM Inspector Debugger:</h2>
                    <p><b>WASM Inspection Error:</b> ${wasmErr.message || wasmErr}</p>
                    <p><b>CLI Fallback Error:</b> ${cliErr.message || cliErr}</p>
                </div>
            `);
        }
    }
};
