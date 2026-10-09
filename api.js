// Cliente da API PHP. Todas as chamadas vão para /api/*.php (mesma origem, cookie de sessão).
// Ponte temporária: shEspelharSessao() copia o usuário vindo do servidor para o localStorage,
// pra as páginas que ainda usam app.js (versão protótipo) continuarem funcionando
// enquanto migramos tela por tela para a API.
const SH_API = 'api/';

async function shApi(arquivo, { acao, id, corpo, metodo } = {}) {
  const qs = new URLSearchParams();
  if (acao) qs.set('acao', acao);
  if (id) qs.set('id', id);
  const url = SH_API + arquivo + (qs.toString() ? '?' + qs : '');
  let resp;
  try {
    resp = await fetch(url, {
      method: metodo || (corpo ? 'POST' : 'GET'),
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: corpo ? JSON.stringify(corpo) : undefined
    });
  } catch (e) {
    return { ok: false, status: 0, erro: 'Não foi possível falar com o servidor. O Apache/XAMPP está ligado?' };
  }
  let dados = {};
  try { dados = await resp.json(); } catch (e) {}
  return { ok: resp.ok, status: resp.status, erro: dados.erro, ...dados };
}

function shEspelharSessao(u) {
  shLogoutLocal();
  shSaveUser({ nome: u.nome, email: u.email, papel: u.papel, status: u.status, avatar: u.avatar || undefined, criadoEm: u.criadoEm });
}
