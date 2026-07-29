const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const filePath = path.join(__dirname, '../app/Views/temuan/create.php');
let content = fs.readFileSync(filePath, 'utf8');

// Replace PHP echo tags with clean JS strings/identifiers
content = content.replace(/<\?=\s*[\s\S]*?\?>/g, 'dummyVar');
content = content.replace(/<\?php\s*[\s\S]*?\?>/g, '/* php code */');

const regex = /<script[\s\S]*?>([\s\S]*?)<\/script>/gi;
let match;
let count = 0;

while ((match = regex.exec(content)) !== null) {
    count++;
    let jsCode = match[1];

    const tempFile = path.join(__dirname, `temp_script_${count}.js`);
    fs.writeFileSync(tempFile, jsCode, 'utf8');

    console.log(`Checking JavaScript block #${count}...`);
    try {
        const output = execSync(`node --check "${tempFile}"`, { encoding: 'utf8', stdio: 'pipe' });
        console.log(`✅ JavaScript block #${count} is 100% VALID JAVASCRIPT WITH 0 SYNTAX ERRORS!`);
    } catch (err) {
        console.error(`❌ SYNTAX ERROR IN JAVASCRIPT BLOCK #${count}:`);
        console.error(err.stderr || err.message);
    }
}
