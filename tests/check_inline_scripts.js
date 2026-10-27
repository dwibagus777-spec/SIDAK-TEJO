const fs = require('fs');
const vm = require('vm');

const content = fs.readFileSync('app/Views/gis/index.php', 'utf8');

// Match <script> blocks
const scriptRegex = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
let match;
let count = 0;
let errors = 0;

while ((match = scriptRegex.exec(content)) !== null) {
    count++;
    let js = match[1];
    
    // Calculate line number where this script starts in index.php
    const prefix = content.substring(0, match.index);
    const startLine = prefix.split('\n').length;

    // Check if script has src attribute
    const tag = match[0].substring(0, match[0].indexOf('>') + 1);
    if (/src\s*=/i.test(tag)) {
        console.log(`Script ${count} (line ${startLine}): External script (src) - skipped`);
        continue;
    }

    // Replace PHP tags with valid dummy JS:
    // When inside quotes e.g. "<?= ... ?>" or '<?= ... ?>', replace with dummy word
    // When outside quotes, replace with "dummy_val"
    let sanitizedJs = js.replace(/(["'])(?:(?=(\\?))\2.)*?\1/g, function(str) {
        return str.replace(/<\?[\s\S]*?\?>/g, 'PHP_VAL');
    });
    sanitizedJs = sanitizedJs.replace(/<\?=\s*([\s\S]*?)\s*\?>/g, '"PHP_EXPR"');
    sanitizedJs = sanitizedJs.replace(/<\?php[\s\S]*?\?>/g, '/* php */');

    try {
        new vm.Script(sanitizedJs, { filename: 'gis_inline_script_' + count + '.js', lineOffset: startLine - 1 });
        console.log(`Script ${count} (line ${startLine}): OK (PASSED PARSE CHECK)`);
    } catch (err) {
        errors++;
        console.error(`Script ${count} (starts around line ${startLine}) SYNTAX ERROR:`);
        console.error(err.message);
        console.error(err.stack);
    }
}

console.log(`Total scripts checked: ${count}, errors: ${errors}`);
if (errors > 0) process.exit(1);
