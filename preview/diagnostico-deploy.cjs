/**
 * Diagnóstico do deploy — reproduz a cadeia de redirecionamento que o Render
 * executa e aponta onde o ciclo se fecha.
 *
 *   SIM=https://ellenco.onrender.com node preview/diagnostico-deploy.cjs
 *
 * Por que existe: "ERR_TOO_MANY_REDIRECTS" não diz QUAL regra está repetindo.
 * Aqui cada regra do .htaccess e cada header('Location:') do PHP é avaliada
 * na ordem em que o Apache e o PHP as executam, e o caminho é seguido passo a
 * passo até repetir uma URL.
 */
const fs = require('fs');
const path = require('path');

const raiz = path.resolve(__dirname, '..');
const BASE = process.env.SIM || 'https://ellenco.onrender.com';
// Refazer o diagnóstico com o .htaccess ANTERIOR (que tinha a regra ^$):
//   HTACCESS=.htaccess.antigo node preview/diagnostico-deploy.cjs
const ARQ_HT = process.env.HTACCESS || '.htaccess';

const htaccess = fs.readFileSync(path.join(raiz, ARQ_HT), 'utf8');
const dbPhp = fs.readFileSync(path.join(raiz, 'backend/db.php'), 'utf8');

/* ------------------------------------------------ regras do .htaccess ---- */
function regrasHtaccess(texto) {
  const regras = [];
  const linhas = texto.split('\n');
  let directoryIndex = null;
  let condicoesPendentes = [];
  for (const bruta of linhas) {
    const l = bruta.trim();
    const di = l.match(/^DirectoryIndex\s+(\S+)/i);
    if (di) directoryIndex = di[1];
    const rc = l.match(/^RewriteCond\s+(\S+)\s+(\S+)/i);
    if (rc) {
      // guarda a condição para a PRÓXIMA RewriteRule
      condicoesPendentes.push({ variavel: rc[1], padrao: rc[2] });
      continue;
    }
    const rr = l.match(/^RewriteRule\s+(\S+)\s+(\S+)(\s+\[[^\]]*\])?/i);
    if (rr) {
      const flags = rr[3] || '';
      regras.push({
        de: rr[1],
        para: rr[2],
        loop: /\[L\]/i.test(flags),
        redireciona: /R=30[123]/i.test(flags),
        condicoes: condicoesPendentes,
        linha: l,
      });
      condicoesPendentes = [];
    }
  }
  return { regras, directoryIndex };
}

const { regras, directoryIndex } = regrasHtaccess(htaccess);

/* --------------------------------------------------------- simulação ---- */
function condicoesOk(regra, host, headers) {
  for (const c of regra.condicoes || []) {
    if (c.variavel === '%{HTTP_HOST}') {
      const ehIP = /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(:[0-9]+)?$/.test(host);
      if (!ehIP) return false;              // domínio normal: a regra não dispara
    }
    if (c.variavel === '%{HTTP_X_FORWARDED_HOST}') {
      if (!headers['x-forwarded-host']) return false;
    }
  }
  return true;
}

function aplicarRegra(caminho, regra) {
  // as regex do .htaccess usam escape de ponto; convertemos para JS.
  // %{REQUEST_URI} e %N são variáveis do Apache: aqui viram o próprio caminho.
  let destino = regra.para.replace(/%\{REQUEST_URI\}/g, caminho).replace(/%\d/g, caminho);
  // o .htaccess já traz os ^ e $; converte a regex do Apache para JS
  // trocando a barra invertida do escape de ponto (a regex JS lê '\\.' como ponto literal)
  const fonte = regra.de.replace(/\\./g, '.');
  const re = new RegExp(fonte);
  if (!re.test(caminho)) return null;
  return destino;
}

function seguir(url, passos = 0, vistos = new Set()) {
  if (passos > 12) {
    console.log(`\n>>> LOOP: mais de 12 saltos a partir de ${url}`);
    return true;
  }
  if (vistos.has(url)) {
    console.log(`\n>>> LOOP CONFIRMADO: ${url} já foi visitada`);
    return true;
  }
  vistos.add(url);
  console.log(`${'  '.repeat(passos)}${passos === 0 ? '' : '→ '}${url}`);

  const u = new URL(url);
  let caminho = u.pathname;

  // 1. DirectoryIndex: "/" vira "/login.html"
  if (caminho === '/' && directoryIndex) {
    const novo = '/' + directoryIndex;
    console.log(`${'  '.repeat(passos + 1)}(DirectoryIndex) / -> ${novo}`);
    return seguir(new URL(novo, url).href, passos + 1, vistos);
  }

  // 2. regras na ordem
  for (const regra of regras) {
    if (!condicoesOk(regra, u.host, {})) continue;
    const destino = aplicarRegra(caminho, regra);
    if (destino === null) continue;
    console.log(`${'  '.repeat(passos + 1)}(Rewrite ${regra.de} -> ${regra.para})`);
    if (regra.redireciona) {
      const alvo = destino.startsWith('http') ? destino : new URL(destino, url).href;
      return seguir(alvo, passos + 1, vistos);
    }
    // [L] sem R: serve o arquivo; o PHP pode redirecionar por conta própria
    return avaliarPhp(alvo_absoluto(url, destino), passos + 1, vistos);
  }

  console.log(`${'  '.repeat(passos + 1)}(nenhuma regra: serve ${caminho} direto)`);
  return false;
}

function alvo_absoluto(url, caminho) {
  const u = new URL(url);
  u.pathname = caminho;
  return u.href;
}

/* ----------------------------------------------------- o que o PHP faz -- */
function avaliarPhp(url, passos, vistos) {
  const arquivo = new URL(url).pathname.replace(/^\//, '');
  // sessão é o que decide se o PHP manda para o login
  const temSessao = process.env.SESSAO === 'sim';

  if (/backend\/painel\.php$/.test(arquivo) && !temSessao) {
    console.log(`${'  '.repeat(passos)}(PHP: require_login sem sessão -> login.html)`);
    const destino = new URL('/login.html', url).href;
    return seguir(destino, passos + 1, vistos);
  }
  if (/backend\/login\.php$/.test(arquivo)) {
    console.log(`${'  '.repeat(passos)}(PHP: login renderiza o formulário — fim)`);
    return false;
  }
  console.log(`${'  '.repeat(passos)}(PHP: ${arquivo} — fim)`);
  return false;
}

/* -------------------------------------------------- relatório ----------- */
console.log(`Base: ${BASE}   (.htaccess em análise: ${ARQ_HT})`);
console.log(`DirectoryIndex: ${directoryIndex}`);
console.log(`Regras de rewrite: ${regras.length}`);
regras.forEach((r) => console.log(`  ${r.de} -> ${r.para}${r.redireciona ? '  [redirect]' : '  [serve]'}`));

console.log('\n--- Cadeia a partir de / ---');
const loopRaiz = seguir(BASE + '/');

console.log('\n--- Cadeia a partir de /painel.html (sem sessão) ---');
const loopPainel = seguir(BASE + '/painel.html');

/* ------------------------- checagens que explicam o render --------------- */
console.log('\n=== Causas clássicas de ERR_TOO_MANY_REDIRECTS ===');
const checagens = [];

checagens.push({
  nome: 'redirect de "forçar HTTPS" dependente de X-Forwarded-Proto no .htaccess',
  tem: /X_FORWARDED_PROTO/.test(htaccess) || /HTTPS\)\s*!=\s*["']on/.test(htaccess),
});

checagens.push({
  nome: 'regra de raiz ^$ somando um salto em "/"',
  tem: regras.some((r) => r.de === '^$'),
});

checagens.push({
  nome: 'PHP redireciona para caminho absoluto sem a barra (ex.: "painel.html")',
  tem: /header\(\s*['"]Location:\s*[a-z]/i.test(dbPhp) || /Location:\s*['"]\s*\.?\$/.test(dbPhp),
});

checagens.push({
  nome: 'login.html sendo reescrito para backend/login.php e o PHP devolvendo para login.html',
  tem: regras.some((r) => r.de === '^login\\.html$' && /login\.php/.test(r.para))
       && /app_path\('login\.html'\)/.test(dbPhp),
});

checagens.push({
  nome: 'PHP responde 303 para painel.html quando o Apache ainda não escreveu o cookie de sessão',
  tem: /303/.test(fs.readFileSync(path.join(raiz, 'backend/login.php'), 'utf8')),
});

checagens.forEach((c) => console.log(`  [${c.tem ? 'ENCONTRADO' : 'ok'}] ${c.nome}`));

console.log('\nLoop detectado:', loopRaiz || loopPainel ? 'SIM' : 'não');
process.exit(loopRaiz || loopPainel ? 1 : 0);
