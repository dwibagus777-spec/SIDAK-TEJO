const path = require('path');

module.exports = async (req, res) => {
    try {
        const { loadNodeRuntime } = require('@php-wasm/node');
        const php = await loadNodeRuntime();

        const appPath = path.join(__dirname, 'app.php');
        
        const result = await php.run({
            code: `<?php
                $_SERVER['REQUEST_METHOD'] = '${req.method || 'GET'}';
                $_SERVER['REQUEST_URI'] = '${req.url || '/'}';
                $_SERVER['HTTP_HOST'] = '${req.headers.host || 'sidak-tejo.vercel.app'}';
                $_SERVER['SCRIPT_NAME'] = '/index.php';
                $_SERVER['SCRIPT_FILENAME'] = '${appPath}';
                require '${appPath}';
            `
        });

        const outputText = result.text || result.stdout || (typeof result === 'string' ? result : JSON.stringify(result));
        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(outputText);
    } catch (err) {
        res.setHeader('Content-Type', 'text/html; charset=UTF-8');
        return res.status(200).send(`
            <div style="font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px; border-radius:8px;">
                <h2 style="color:red;">Serverless Bridge WASM Runtime Exception:</h2>
                <pre style="white-space:pre-wrap;">${err.stack || err.message}</pre>
            </div>
        `);
    }
};
