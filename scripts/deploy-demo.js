// Публикует демо-версию на GitHub Pages (ветка gh-pages): node scripts/deploy-demo.js
// Демо закрыто от индексации, форма заявки в нём не отправляет (Pages не выполняет PHP).
const { execSync } = require('child_process');
const path = require('path');
const ROOT = path.join(__dirname, '..');
const REPO = 'teh-energo-perm';
const run = (cmd, cwd = ROOT) => execSync(cmd, { cwd, stdio: 'inherit', env: { ...process.env, MSYS_NO_PATHCONV: '1' } });

run('node scripts/build.js', ROOT);
execSync('node scripts/build.js', { cwd: ROOT, stdio: 'inherit', env: { ...process.env, DEMO_BASE: '/' + REPO } });
const remote = execSync('git remote get-url origin', { cwd: ROOT }).toString().trim();
const demo = path.join(ROOT, '.demo');
run('git init -q -b gh-pages', demo);
run('git add -A', demo);
run('git -c user.name="mihailburmasov" -c user.email="mihail.burmasov@gmail.com" commit -q -m "Демо-сборка сайта"', demo);
run(`git push -f "${remote}" gh-pages`, demo);
console.log(`Демо: https://mihailburmasov.github.io/${REPO}/`);
