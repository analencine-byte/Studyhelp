// ==========================================================
// StudyHelp — lógica compartilhada do front-end
//
// Os dados de verdade ficam no servidor (PHP + MySQL, pasta /api).
// Aqui no navegador guardamos só um "espelho" do usuário logado
// (localStorage) pra as páginas decidirem rápido o que mostrar;
// quem manda é sempre o servidor: ao abrir cada página, a sessão é
// conferida (shPronto) e o espelho é corrigido se mudou algo.
// ==========================================================

const SH_API = 'api/';
const SH_LOGADO_KEY = 'sh_logado';
const SH_USUARIO_KEY = 'sh_usuario';

// ---------- Chamadas à API ----------
// shApi('materias.php', { acao:'criar', corpo:{...} })  |  { form: FormData }  |  { id, params }
async function shApi(arquivo, opcoes) {
  const o = opcoes || {};
  const qs = new URLSearchParams();
  if (o.acao) qs.set('acao', o.acao);
  if (o.id) qs.set('id', o.id);
  Object.keys(o.params || {}).forEach(k => qs.set(k, o.params[k]));
  const url = SH_API + arquivo + (qs.toString() ? '?' + qs : '');
  let body;
  const headers = {};
  if (o.form) body = o.form;
  else if (o.corpo !== undefined) { body = JSON.stringify(o.corpo); headers['Content-Type'] = 'application/json'; }
  let resp;
  try {
    resp = await fetch(url, { method: o.metodo || (body !== undefined ? 'POST' : 'GET'), headers, credentials: 'same-origin', body });
  } catch (e) {
    return { ok: false, status: 0, erro: 'Não foi possível falar com o servidor. O Apache/XAMPP está ligado?' };
  }
  let dados = {};
  try { dados = await resp.json(); } catch (e) {}
  if (!resp.ok && !dados.erro) dados.erro = resp.status === 404 ? 'Servidor não encontrou a rota (abra o site por http://localhost/..., não pelo arquivo).' : 'Erro ' + resp.status + ' no servidor.';
  return Object.assign({ ok: resp.ok, status: resp.status }, dados);
}

// ---------- Datas ----------
// O servidor manda datas em UTC ("2026-10-09 10:42:56"); aqui viram horário local.
function shParseData(v) {
  if (!v) return null;
  const s = String(v);
  const d = new Date(/[TZ]/.test(s) ? s : s.replace(' ', 'T') + 'Z');
  return isNaN(d) ? null : d;
}
function shDataBR(v) {
  const d = shParseData(v);
  return d ? d.toLocaleDateString('pt-BR') : '';
}
function shFormatarMesAno(v) {
  const d = shParseData(v);
  if (!d) return '';
  const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  return meses[d.getMonth()] + '/' + d.getFullYear();
}
function shTempoAtras(v) {
  const d = shParseData(v);
  if (!d) return '';
  const seg = Math.max(0, (Date.now() - d.getTime()) / 1000);
  if (seg < 60) return 'agora mesmo';
  if (seg < 3600) return 'há ' + Math.floor(seg / 60) + ' min';
  if (seg < 86400) return 'há ' + Math.floor(seg / 3600) + ' h';
  if (seg < 172800) return 'ontem';
  if (seg < 30 * 86400) return 'há ' + Math.floor(seg / 86400) + ' dias';
  return d.toLocaleDateString('pt-BR');
}
function shHora(v) {
  const d = shParseData(v);
  return d ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
}

// Escapa texto vindo do servidor/usuário antes de colocar em innerHTML (evita injeção de HTML).
function shEsc(t) {
  return String(t === null || t === undefined ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ---------- Sessão (espelho local do usuário logado) ----------
function shGetUser() {
  try {
    const u = JSON.parse(localStorage.getItem(SH_USUARIO_KEY));
    if (u && u.email) return u;
  } catch (e) {}
  // Ninguém logado — valores só pra a tela não quebrar (páginas privadas redirecionam)
  return { id: 0, nome: 'Visitante', email: '', avatar: null, papel: 'Aluno', status: 'Aprovado', criadoEm: null, config: {} };
}
function shEstaLogado() {
  try { return localStorage.getItem(SH_LOGADO_KEY) === '1' && !!JSON.parse(localStorage.getItem(SH_USUARIO_KEY)); } catch (e) { return false; }
}
function shGuardarUsuario(u) {
  try {
    localStorage.setItem(SH_USUARIO_KEY, JSON.stringify(u));
    localStorage.setItem(SH_LOGADO_KEY, '1');
    return true;
  } catch (e) { return false; }
}
// Só limpa o espelho local.
function shLogoutLocal() {
  try { localStorage.removeItem(SH_USUARIO_KEY); localStorage.removeItem(SH_LOGADO_KEY); } catch (e) {}
}
// "Sair" de verdade: limpa o local E encerra a sessão no servidor (sendBeacon sobrevive à troca de página).
function shLogout() {
  shLogoutLocal();
  try { navigator.sendBeacon(SH_API + 'auth.php?acao=logout', new Blob(['{}'], { type: 'application/json' })); } catch (e) {}
}

// Pergunta ao servidor quem está logado e corrige o espelho. Resolve com o usuário (ou null = visitante).
const shPronto = (async function () {
  const antes = shEstaLogado() ? JSON.stringify(shGetUser()) : null;
  const r = await shApi('auth.php', { acao: 'eu' });
  if (r.status === 0) return shEstaLogado() ? shGetUser() : null; // servidor fora do ar: confia no espelho
  const u = r.ok ? r.usuario : null;
  if (u) shGuardarUsuario(u); else shLogoutLocal();
  if (JSON.stringify(u) !== antes) {
    document.addEventListener('DOMContentLoaded', shConfigurarSessaoNaPagina);
    if (document.readyState !== 'loading') shConfigurarSessaoNaPagina();
  }
  return u;
})();

// Usar no topo de páginas que só fazem sentido pra quem já entrou.
// Esconde a página e manda pro login na hora (pelo espelho) e confirma com o servidor logo depois.
function shExigirLogin() {
  if (!shEstaLogado()) {
    document.documentElement.style.display = 'none';
    window.location.replace('login.html');
    throw new Error('SH_REDIRECT_LOGIN');
  }
  shPronto.then(u => { if (!u) window.location.replace('login.html'); });
}

// Restringe a página a certos papéis (confere com o servidor). Quem não pode vai pro destino indicado.
function shExigirPapel(papeis, destinoSeNegado) {
  const confere = u => {
    if (!u || papeis.indexOf(u.papel) === -1) { window.location.replace(destinoSeNegado || (u ? shDestinoPainel(u) : 'login.html')); return false; }
    return true;
  };
  if (!confere(shEstaLogado() ? shGetUser() : null)) return false;
  shPronto.then(u => { if (!u || papeis.indexOf(u.papel) === -1) window.location.replace(destinoSeNegado || (u ? shDestinoPainel(u) : 'login.html')); });
  return true;
}

function shDestinoPainel(user) {
  if (user.papel === 'Administrador') return 'admin.html';
  if (user.papel === 'Tutor') return (user.status === 'Aprovado' || user.status === 'Ativo') ? 'professor.html' : 'pendente.html';
  return 'aluno.html';
}

function shPrimeiroNome(nomeCompleto) {
  return (nomeCompleto || '').trim().split(' ')[0] || 'Aluno';
}

function shAplicarUsuario(user) {
  document.querySelectorAll('.sh-user-firstname').forEach(el => el.textContent = shPrimeiroNome(user.nome));
  document.querySelectorAll('.sh-user-fullname').forEach(el => el.textContent = user.nome);
  document.querySelectorAll('.sh-user-email').forEach(el => el.textContent = user.email);
  document.querySelectorAll('.sh-user-role').forEach(el => el.textContent = user.papel || 'Aluno');
  document.querySelectorAll('.sh-user-membro-desde').forEach(el => el.textContent = user.criadoEm ? 'Membro desde ' + shFormatarMesAno(user.criadoEm) : '');
  if (user.avatar) {
    document.querySelectorAll('.sh-user-avatar').forEach(el => el.src = user.avatar);
  }
  const nomeInput = document.getElementById('sh-input-nome');
  const emailInput = document.getElementById('sh-input-email');
  if (nomeInput) nomeInput.value = user.nome;
  if (emailInput) emailInput.value = user.email;
}

document.addEventListener('DOMContentLoaded', () => {
  shConfigurarSessaoNaPagina();
});

// ==========================================================
// Modo visitante / logado
// Páginas públicas (index, materiais, tutores...) funcionam pra QUALQUER
// pessoa: quem não tem conta navega livre no modo visitante, mas ações
// que dependem de conta (abrir um material, falar com um tutor, acessar
// o painel) ficam bloqueadas até fazer login ou se cadastrar.
//
// Convenção usada nas páginas HTML:
//   #sh-nav-visitante / #sh-nav-logado    -> blocos da navbar
//   .sh-somente-visitante / .sh-somente-logado
//   .sh-link-inicio  -> "Início": painel se logado, home pública se visitante
//   .sh-link-painel  -> aponta pro painel certo (aluno/professor/admin)
// ==========================================================
function shConfigurarSessaoNaPagina() {
  const logado = shEstaLogado();

  const navVisitante = document.getElementById('sh-nav-visitante');
  const navLogado = document.getElementById('sh-nav-logado');
  if (navVisitante) navVisitante.classList.toggle('d-none', logado);
  if (navLogado) navLogado.classList.toggle('d-none', !logado);

  document.querySelectorAll('.sh-somente-visitante').forEach(el => el.classList.toggle('d-none', logado));
  document.querySelectorAll('.sh-somente-logado').forEach(el => el.classList.toggle('d-none', !logado));

  const painel = logado ? shDestinoPainel(shGetUser()) : null;
  document.querySelectorAll('.sh-link-inicio').forEach(el => el.setAttribute('href', logado ? painel : 'index.html'));
  document.querySelectorAll('.sh-link-painel').forEach(el => el.setAttribute('href', painel || '#'));

  if (logado) {
    shAplicarUsuario(shGetUser());
    shRenderizarNotificacoes();
  }
  return logado;
}

// Chamar no clique de qualquer ação que só faz sentido pra quem tem conta.
// Logado: retorna true. Visitante: mostra o aviso de cadastro/login e retorna false.
function shExigirLoginParaAcao() {
  if (shEstaLogado()) return true;
  const modalEl = document.getElementById('modalBloqueado');
  if (modalEl && window.bootstrap) {
    new bootstrap.Modal(modalEl).show();
  } else {
    window.location.href = 'login.html';
  }
  return false;
}

// ==========================================================
// Notificações (vêm do servidor: mensagens novas, materiais novos...)
// ==========================================================
let shNotifLigado = false;
async function shRenderizarNotificacoes() {
  const lista = document.getElementById('lista-notificacoes');
  const badge = document.getElementById('badge-notificacoes');
  const vazio = document.getElementById('notificacoes-vazio');
  const botao = document.getElementById('btn-notificacoes');
  if (!lista || !botao || !shEstaLogado()) return;

  const r = await shApi('notificacoes.php');
  if (!r.ok) return;
  const notifs = r.notificacoes || [];

  lista.innerHTML = '';
  if (notifs.length === 0) {
    if (vazio) vazio.classList.remove('d-none');
  } else {
    if (vazio) vazio.classList.add('d-none');
    notifs.forEach(n => {
      const div = document.createElement('div');
      div.className = 'px-3 py-2 border-bottom';
      div.style.fontSize = '.83rem';
      div.innerHTML = '<div>' + shEsc(n.texto) + '</div><div class="sh-muted" style="font-size:.75rem">' + shEsc(shTempoAtras(n.criadoEm)) + '</div>';
      lista.appendChild(div);
    });
  }

  if (badge) {
    if (r.naoLidas > 0) { badge.textContent = r.naoLidas; badge.classList.remove('d-none'); }
    else badge.classList.add('d-none');
  }

  if (!shNotifLigado) {
    shNotifLigado = true;
    botao.addEventListener('click', function () {
      if (badge) badge.classList.add('d-none');
      shApi('notificacoes.php', { corpo: {} });
    });
  }
}

// ==========================================================
// Painel do administrador: carrega tudo de uma vez do servidor
// e deixa em shDados (as telas do admin leem daqui e redesenham).
// ==========================================================
const shDados = { usuarios: [], materias: [], atividades: [] };

async function shCarregarAdmin() {
  const [u, m, a] = await Promise.all([
    shApi('usuarios.php'), shApi('materias.php', { acao: 'admin' }), shApi('atividades.php')
  ]);
  if (u.status === 401 || u.status === 403) { window.location.replace('admin-login.html'); return false; }
  shDados.usuarios = (u.usuarios || []).map(x => Object.assign({}, x, { id: String(x.id), avatar: x.avatar || null }));
  shDados.materias = (m.materias || []).map(x => Object.assign({}, x, { id: String(x.id), tutorId: x.tutorId === null ? null : String(x.tutorId) }));
  shDados.usuarios.forEach(x => { x.materias = shDados.materias.filter(mm => mm.tutorId === x.id).map(mm => mm.id); });
  shDados.atividades = (a.atividades || []).map(x => ({ texto: x.texto, tempo: shTempoAtras(x.criadoEm) }));
  return true;
}
function shGetUsuarios() { return shDados.usuarios; }
function shGetMaterias() { return shDados.materias; }
function shGetAtividades() { return shDados.atividades; }

// Mostra um erro de API de forma amigável (retorna true se houve erro)
function shErroApi(r) {
  if (r && !r.ok) { alert(r.erro || 'Algo deu errado. Tente de novo.'); return true; }
  return false;
}

// ==========================================================
// Atividades do aluno (vêm de api/tarefas.php) — clicar abre a atividade
// ==========================================================
async function shGetAtividadesAluno() {
  const r = await shApi('tarefas.php');
  return (r.tarefas || []).map(t => ({
    id: 'a' + t.id, titulo: t.titulo, materia: t.materia || '', data: shDataBR(t.criadoEm), status: t.status,
    tipo: t.tipo, tutor: t.tutorId, tutorNome: t.tutorNome, nota: t.nota
  }));
}

function shClasseStatus(st) { return st === 'Em andamento' ? 'sh-pill warn' : 'sh-pill'; }

// Desenha a lista de atividades dentro de `seletor` (limite opcional). Clicar abre a atividade.
async function shRenderizarAtividades(seletor, limite) {
  const alvo = document.querySelector(seletor);
  if (!alvo) return;
  let lista = await shGetAtividadesAluno();
  if (limite) lista = lista.slice(0, limite);
  alvo.innerHTML = '';
  if (lista.length === 0) {
    alvo.innerHTML = '<p class="sh-muted text-center py-3 mb-0" style="font-size:.85rem">Nenhuma atividade ainda. Clique em "Solicitar ajuda" para começar.</p>';
    return;
  }
  lista.forEach((a, i) => {
    const linha = document.createElement('div');
    linha.className = 'd-flex justify-content-between align-items-center py-2' + (i < lista.length - 1 ? ' border-bottom' : '');
    linha.style.cursor = 'pointer';
    linha.setAttribute('role', 'button');
    const esq = document.createElement('div');
    const t = document.createElement('div'); t.className = 'fw-semibold'; t.style.fontSize = '.9rem'; t.textContent = a.titulo;
    const m = document.createElement('div'); m.className = 'sh-muted'; m.style.fontSize = '.8rem'; m.textContent = (a.materia ? a.materia + ' · ' : '') + a.data;
    esq.append(t, m);
    const pill = document.createElement('span'); pill.className = shClasseStatus(a.status); pill.textContent = a.status;
    linha.append(esq, pill);
    linha.addEventListener('click', () => shAbrirAtividade(a));
    alvo.appendChild(linha);
  });
}

function shAbrirAtividade(a) {
  let modal = document.getElementById('sh-modal-atividade');
  if (!modal) {
    modal = document.createElement('div');
    modal.className = 'modal fade'; modal.id = 'sh-modal-atividade'; modal.tabIndex = -1;
    modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px"><div class="modal-header border-0"><h5 class="modal-title" id="sh-ma-titulo"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body pt-0"><div class="mb-2"><span id="sh-ma-status"></span></div><div class="sh-muted mb-2" id="sh-ma-info" style="font-size:.88rem"></div><div id="sh-ma-detalhe" style="font-size:.9rem"></div></div><div class="modal-footer border-0"><button type="button" class="btn btn-sh-outline btn-sm" data-bs-dismiss="modal">Fechar</button><a href="#" id="sh-ma-acao" class="btn btn-sh-primary btn-sm"></a></div></div></div>';
    document.body.appendChild(modal);
  }
  document.getElementById('sh-ma-titulo').textContent = a.titulo;
  const st = document.getElementById('sh-ma-status'); st.className = shClasseStatus(a.status); st.textContent = a.status;
  document.getElementById('sh-ma-info').textContent = (a.materia ? a.materia + ' · ' : '') + a.data + (a.tutorNome ? ' · Tutor: ' + a.tutorNome : '');
  document.getElementById('sh-ma-detalhe').textContent =
    a.status === 'Em andamento' ? 'Esta atividade ainda está em andamento. Continue de onde parou.' :
    a.nota ? 'Atividade concluída. Nota: ' + a.nota :
    a.tipo === 'duvida' ? 'Dúvida respondida. A resposta do tutor está no chat.' : 'Atividade concluída.';
  const acao = document.getElementById('sh-ma-acao');
  let href = 'materiais.html' + (a.materia ? '?materia=' + encodeURIComponent(a.materia) : ''), txt = 'Ver material';
  if (a.tipo === 'caderno') { href = 'cadernos.html'; txt = a.status === 'Em andamento' ? 'Continuar atividade' : 'Abrir caderno'; }
  else if (a.tipo === 'duvida' && a.tutor) { href = 'chat.html?tutor=' + a.tutor; txt = a.status === 'Em andamento' ? 'Continuar conversa' : 'Ver resposta'; }
  else if (a.status === 'Em andamento') txt = 'Continuar atividade';
  if (a.tipo === 'avaliacao') { acao.classList.add('d-none'); } else { acao.classList.remove('d-none'); acao.href = href; acao.textContent = txt; }
  bootstrap.Modal.getOrCreateInstance(modal).show();
}

// ==========================================================
// Matérias favoritas e inscrições do aluno (api/materias.php)
// ==========================================================
const shMinhas = { inscritas: [], favoritas: [] };

async function shCarregarMinhasMaterias() {
  const r = await shApi('materias.php', { acao: 'minhas' });
  if (r.ok) { shMinhas.inscritas = r.inscritas || []; shMinhas.favoritas = r.favoritas || []; }
  return shMinhas;
}
function shGetMateriasFavoritas() { return shMinhas.favoritas.slice(); }
async function shSalvarMateriasFavoritas(lista) {
  const anterior = shMinhas.favoritas;
  shMinhas.favoritas = lista.slice();
  const r = await shApi('materias.php', { acao: 'favoritas', corpo: { favoritas: lista } });
  if (!r.ok) { shMinhas.favoritas = anterior; alert(r.erro || 'Não foi possível salvar as favoritas.'); return false; }
  return true;
}
// Nomes de matérias (sem repetir), vindos do servidor
async function shNomesMaterias() {
  const r = await shApi('materias.php');
  const nomes = (r.materias || []).map(m => m.nome);
  return nomes.filter((n, i) => nomes.indexOf(n) === i).sort((a, b) => a.localeCompare(b, 'pt-BR'));
}
