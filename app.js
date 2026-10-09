// ==========================================================
// StudyHelp — simulação local (SEM backend real)
// Tudo isso fica guardado no localStorage do navegador só pra dar
// vida ao protótipo. Quando o backend em PHP/MySQL existir, cada
// uma dessas partes vira uma tabela/consulta de verdade no servidor.
//
// IMPORTANTE: existe uma ÚNICA base de contas (sh_db_usuarios) — é
// nela que TODA conta cadastrada fica guardada (nome, email, foto,
// papel, status). A "sessão" (sh_sessao) só guarda QUAL email está
// ativo agora neste navegador. Fazer login com um email busca os
// dados daquela conta específica na base — é isso que impede um
// perfil aparecer "misturado" com o de outra pessoa.
// ==========================================================

const SH_SESSAO_KEY = 'sh_sessao';
const SH_LOGADO_KEY = 'sh_logado';
const SH_DB_USUARIOS_KEY = 'sh_db_usuarios';
const SH_DB_ATIVIDADES_KEY = 'sh_db_atividades';
const SH_DB_MATERIAS_KEY = 'sh_db_materias';

function shGetSessaoRaw() {
  try { return JSON.parse(localStorage.getItem(SH_SESSAO_KEY)) || {}; } catch (e) { return {}; }
}

function shGetUser() {
  const sessao = shGetSessaoRaw();
  const usuarios = shGetUsuarios();
  const conta = usuarios.find(u => u.email === sessao.emailAtual);

  if (conta) {
    return { nome: conta.nome, email: conta.email, avatar: conta.avatar || null, papel: conta.papel, status: conta.status, criadoEm: conta.criadoEm || null };
  }
  // Ninguém logado ainda neste navegador — valores padrão só pra tela não quebrar
  return { nome: 'Ana Fernanda', email: 'ana.fernanda@email.com', avatar: null, papel: 'Aluno', status: 'Aprovado' };
}

// Diz se existe alguém de verdade logado agora (fez cadastro ou login).
// Além da "flag" de logado, confere se a conta da sessão AINDA existe na
// base — evita mostrar uma conta "fantasma" quando a sessão ficou órfã
// (ex: conta foi apagada, ou storage ficou com lixo de um teste anterior).
function shEstaLogado() {
  try {
    if (localStorage.getItem(SH_LOGADO_KEY) !== '1') return false;
    const sessao = shGetSessaoRaw();
    const usuarios = shGetUsuarios();
    const contaExiste = usuarios.some(u => u.email === sessao.emailAtual);
    if (!contaExiste) {
      shLogoutLocal();
      return false;
    }
    return true;
  } catch (e) { return false; }
}

// Usar no topo de páginas que só fazem sentido pra quem já entrou
// (aluno, professor, matérias, tutores, chat, cadernos, perfil...).
// Se ninguém logou ainda, manda pra tela de login/cadastro E interrompe
// a execução do restante do script — sem isso, o navegador ainda não saiu
// da página no momento em que o resto do JS roda, e dá pra ver por um
// instante (ou até "grudado", em preview embutido) dados de uma conta
// que não é a da pessoa.
function shExigirLogin() {
  if (!shEstaLogado()) {
    // Esconde a página na hora: mesmo que algum outro <script> da página
    // ainda rode nos milissegundos antes da troca de tela acontecer, a
    // pessoa não chega a ver nenhum conteúdo (nem conta de demonstração).
    document.documentElement.style.display = 'none';
    window.location.replace('login.html');
    throw new Error('SH_REDIRECT_LOGIN');
  }
}

// Cria ou atualiza a conta na base (por email) e já ativa a sessão nela.
// Usado no CADASTRO e nas CONFIGURAÇÕES do perfil (editar nome/email/foto).
function shSaveUser(user) {
  try {
    const usuarios = shGetUsuarios();
    const sessaoAtual = shGetSessaoRaw();
    // Se a pessoa está editando o perfil (mudando nome/email/foto), acha a
    // conta pelo email ANTIGO da sessão pra não criar uma duplicada.
    let idx = usuarios.findIndex(u => u.email === sessaoAtual.emailAtual);
    if (idx === -1) idx = usuarios.findIndex(u => u.email === user.email);

    if (idx === -1) {
      usuarios.push({ id: shGerarId(), nome: user.nome, email: user.email, avatar: user.avatar || null, papel: user.papel, status: user.status, criadoEm: user.criadoEm || new Date().toISOString() });
    } else {
      usuarios[idx].nome = user.nome;
      usuarios[idx].email = user.email;
      usuarios[idx].avatar = user.avatar !== undefined ? user.avatar : usuarios[idx].avatar;
      usuarios[idx].papel = user.papel;
      usuarios[idx].status = user.status;
      if (user.criadoEm) usuarios[idx].criadoEm = user.criadoEm;
    }
    shSalvarUsuarios(usuarios);
    localStorage.setItem(SH_SESSAO_KEY, JSON.stringify({ emailAtual: user.email }));
    localStorage.setItem(SH_LOGADO_KEY, '1');
    return true;
  } catch (e) {
    console.warn('Não foi possível salvar a conta simulada:', e);
    return false;
  }
}

// Login de verdade: busca a conta pelo EMAIL digitado. Se não achar,
// retorna false (não deixa entrar como se fosse outra pessoa).
function shFazerLogin(email, papelEscolhido) {
  try {
    const usuarios = shGetUsuarios();
    const conta = usuarios.find(u => u.email === email);
    if (!conta) return false;

    if (papelEscolhido && papelEscolhido !== conta.papel) {
      conta.papel = papelEscolhido;
      if (papelEscolhido === 'Tutor') {
        conta.status = conta.status === 'Aprovado' ? 'Aprovado' : 'Pendente';
      } else {
        conta.status = 'Aprovado';
      }
      shSalvarUsuarios(usuarios);
    }

    localStorage.setItem(SH_SESSAO_KEY, JSON.stringify({ emailAtual: email }));
    localStorage.setItem(SH_LOGADO_KEY, '1');
    return true;
  } catch (e) {
    console.warn('Não foi possível entrar:', e);
    return false;
  }
}

// Eleva o papel da conta JÁ ATIVA nesta sessão (ex: entrar como admin),
// sem trocar de conta. Se ninguém estiver logado ainda, cria uma conta
// padrão pra poder continuar a demonstração.
function shIniciarSessao(papel, status) {
  try {
    const sessaoAtual = shGetSessaoRaw();
    const usuarios = shGetUsuarios();
    let idx = usuarios.findIndex(u => u.email === sessaoAtual.emailAtual);

    if (idx === -1) {
      const nova = { id: shGerarId(), nome: 'Ana Fernanda', email: 'ana.fernanda@email.com', avatar: null, papel, status };
      usuarios.push(nova);
      shSalvarUsuarios(usuarios);
      localStorage.setItem(SH_SESSAO_KEY, JSON.stringify({ emailAtual: nova.email }));
    } else {
      usuarios[idx].papel = papel;
      usuarios[idx].status = status;
      shSalvarUsuarios(usuarios);
      localStorage.setItem(SH_SESSAO_KEY, JSON.stringify({ emailAtual: usuarios[idx].email }));
    }
    localStorage.setItem(SH_LOGADO_KEY, '1');
    return true;
  } catch (e) {
    console.warn('Não foi possível iniciar a sessão simulada:', e);
    return false;
  }
}

function shStorageDisponivel() {
  try {
    localStorage.setItem('__sh_teste__', '1');
    localStorage.removeItem('__sh_teste__');
    return true;
  } catch (e) {
    return false;
  }
}

// "Sair" encerra a sessão de verdade agora: tira a flag de logado e
// esquece qual email estava ativo. A CONTA continua na base — só
// fazer login de novo com o mesmo email pra recuperar tudo.
// Só limpa o espelho local (usado internamente, sem encerrar a sessão no servidor).
function shLogoutLocal() {
  try {
    localStorage.removeItem(SH_SESSAO_KEY);
    localStorage.removeItem(SH_LOGADO_KEY);
  } catch (e) {}
}

// "Sair" de verdade: limpa o local E encerra a sessão no servidor (sendBeacon sobrevive à troca de página).
function shLogout() {
  shLogoutLocal();
  try { navigator.sendBeacon('api/auth.php?acao=logout', new Blob(['{}'], { type: 'application/json' })); } catch (e) {}
}

// Painel certo pra cada tipo de conta (usado no login e na home).
function shDestinoPainel(user) {
  if (user.papel === 'Administrador') return 'admin.html';
  if (user.papel === 'Tutor') return user.status === 'Aprovado' ? 'professor.html' : 'pendente.html';
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
  if (shEstaLogado()) {
    shRenderizarNotificacoes();
  }
});

// ==========================================================
// Modo visitante / logado
// Páginas públicas (index, materiais, tutores...) funcionam pra QUALQUER
// pessoa: quem não tem conta navega livre no modo visitante — vê listas,
// pesquisa, filtra — mas ações que dependem de conta (abrir um material
// por completo, falar com um tutor, acessar o painel) ficam bloqueadas
// até fazer login ou se cadastrar.
//
// Convenção usada nas páginas HTML:
//   #sh-nav-visitante        -> bloco da navbar com Entrar/Cadastre-se
//   #sh-nav-logado           -> bloco da navbar com avatar/menu da conta
//   .sh-somente-visitante    -> só aparece pra quem NÃO tem sessão ativa
//   .sh-somente-logado       -> só aparece pra quem TEM sessão ativa
//   .sh-link-inicio          -> "Início": manda pro painel se logado, ou
//                                pra home pública se visitante
//   .sh-link-painel          -> aponta pro painel certo (aluno/professor/admin)
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

  if (logado) shAplicarUsuario(shGetUser());
  return logado;
}

// Chamar no clique de qualquer ação que só faz sentido pra quem tem
// conta (abrir um material completo, mandar mensagem pra um tutor...).
// Se a pessoa já estiver logada, deixa passar (retorna true) e quem
// chamou continua normalmente. Se for visitante, mostra o aviso de
// cadastro/login (modal #modalBloqueado, quando existir na página) e
// devolve false pra quem chamou cancelar a ação.
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
// Notificações
// ==========================================================
function shGetNotificacoes() {
  const user = shGetUser();
  if (user.papel === 'Tutor') {
    return [
      { texto: 'Mariana Souza se inscreveu em Matemática', tempo: 'há 2h' },
      { texto: 'Novo comentário no fórum sobre "Funções do 1º grau"', tempo: 'há 5h' },
      { texto: 'Pedro Henrique enviou uma dúvida', tempo: 'ontem' }
    ];
  }
  return [
    { texto: 'Lucas Oliveira respondeu sua dúvida de Matemática', tempo: 'há 10 min' },
    { texto: 'Novo material publicado em Ciências: Leis de Newton', tempo: 'há 3h' },
    { texto: 'Sua redação foi corrigida por Juliana Santos', tempo: 'ontem' }
  ];
}

function shNotificacoesLidas() {
  try { return localStorage.getItem('sh_notif_lidas') === '1'; } catch (e) { return false; }
}
function shMarcarNotificacoesLidas() {
  try { localStorage.setItem('sh_notif_lidas', '1'); } catch (e) {}
}

function shRenderizarNotificacoes() {
  const lista = document.getElementById('lista-notificacoes');
  const badge = document.getElementById('badge-notificacoes');
  const vazio = document.getElementById('notificacoes-vazio');
  const botao = document.getElementById('btn-notificacoes');
  if (!lista || !botao) return;

  const notifs = shGetNotificacoes();
  const lidas = shNotificacoesLidas();

  lista.innerHTML = '';
  if (notifs.length === 0) {
    vazio.classList.remove('d-none');
  } else {
    vazio.classList.add('d-none');
    notifs.forEach(n => {
      const div = document.createElement('div');
      div.className = 'px-3 py-2 border-bottom';
      div.style.fontSize = '.83rem';
      div.innerHTML = `<div>${n.texto}</div><div class="sh-muted" style="font-size:.75rem">${n.tempo}</div>`;
      lista.appendChild(div);
    });
  }

  if (!lidas && notifs.length > 0) {
    badge.textContent = notifs.length;
    badge.classList.remove('d-none');
  } else {
    badge.classList.add('d-none');
  }

  botao.addEventListener('click', function () {
    shMarcarNotificacoesLidas();
    badge.classList.add('d-none');
  }, { once: true });
}

// ==========================================================
// "Banco de dados" simulado dos usuários — é o que o painel do
// Administrador usa. Numa versão real, isso é uma tabela "usuarios"
// no MySQL, com cada linha sendo uma pessoa cadastrada.
// ==========================================================
function shGerarId() {
  return 'u' + Date.now() + Math.floor(Math.random() * 1000);
}

function shSeedDB() {
  try {
    if (!localStorage.getItem(SH_DB_USUARIOS_KEY)) {
      const usuariosIniciais = [
        { id: 'u1', nome: 'Ana Fernanda', email: 'ana.fernanda@email.com', papel: 'Aluno', status: 'Aprovado', criadoEm: '2025-03-12T12:00:00Z' },
        { id: 'u2', nome: 'Lucas Oliveira', email: 'lucas.oliveira@email.com', papel: 'Tutor', status: 'Aprovado', criadoEm: '2025-02-20T12:00:00Z' },
        { id: 'u3', nome: 'Pedro Henrique', email: 'pedro.henrique@email.com', papel: 'Aluno', status: 'Aprovado', criadoEm: '2026-01-15T12:00:00Z' },
        { id: 'u4', nome: 'Mariana Souza', email: 'mariana.souza@email.com', papel: 'Tutor', status: 'Pendente', criadoEm: '2026-09-20T12:00:00Z' },
        { id: 'u5', nome: 'Juliana Santos', email: 'juliana.santos@email.com', papel: 'Tutor', status: 'Aprovado', criadoEm: '2025-05-02T12:00:00Z' },
        { id: 'u6', nome: 'Rodrigo Alves', email: 'rodrigo.alves@email.com', papel: 'Tutor', status: 'Ativo', criadoEm: '2025-04-18T12:00:00Z' },
        { id: 'u7', nome: 'Fernanda Lopes', email: 'fernanda.lopes@email.com', papel: 'Tutor', status: 'Aprovado', criadoEm: '2025-06-30T12:00:00Z' },
        { id: 'u8', nome: 'Carla Menezes', email: 'carla.menezes@email.com', papel: 'Tutor', status: 'Aprovado', criadoEm: '2025-07-08T12:00:00Z' }
      ];
      localStorage.setItem(SH_DB_USUARIOS_KEY, JSON.stringify(usuariosIniciais));
    }
    if (!localStorage.getItem(SH_DB_ATIVIDADES_KEY)) {
      const atividadesIniciais = [
        { texto: 'Mariana Souza solicitou cadastro como tutora', tempo: 'há 2 dias' },
        { texto: 'Pedro Henrique se cadastrou como aluno', tempo: 'há 3 dias' },
        { texto: 'Rodrigo Alves ficou inativo por falta de acesso', tempo: 'há 5 dias' }
      ];
      localStorage.setItem(SH_DB_ATIVIDADES_KEY, JSON.stringify(atividadesIniciais));
    }
    if (!localStorage.getItem(SH_DB_MATERIAS_KEY)) {
      const materiasIniciais = [
        { nome: 'Matemática', alunos: 45 },
        { nome: 'Física', alunos: 32 },
        { nome: 'Química', alunos: 28 },
        { nome: 'História', alunos: 23 },
        { nome: 'Biologia', alunos: 18 }
      ];
      localStorage.setItem(SH_DB_MATERIAS_KEY, JSON.stringify(materiasIniciais));
    }
  } catch (e) {}
}

function shGetUsuarios() {
  shSeedDB();
  try { return JSON.parse(localStorage.getItem(SH_DB_USUARIOS_KEY)) || []; } catch (e) { return []; }
}
function shSalvarUsuarios(lista) {
  try { localStorage.setItem(SH_DB_USUARIOS_KEY, JSON.stringify(lista)); return true; } catch (e) { return false; }
}

function shGetAtividades() {
  shSeedDB();
  try { return JSON.parse(localStorage.getItem(SH_DB_ATIVIDADES_KEY)) || []; } catch (e) { return []; }
}
function shAdicionarAtividade(texto) {
  const lista = shGetAtividades();
  lista.unshift({ texto: texto, tempo: 'agora mesmo' });
  try { localStorage.setItem(SH_DB_ATIVIDADES_KEY, JSON.stringify(lista.slice(0, 30))); } catch (e) {}
}

function shGetMaterias() {
  shSeedDB();
  try { return JSON.parse(localStorage.getItem(SH_DB_MATERIAS_KEY)) || []; } catch (e) { return []; }
}
function shSalvarMaterias(lista) {
  try { localStorage.setItem(SH_DB_MATERIAS_KEY, JSON.stringify(lista)); return true; } catch (e) { return false; }
}

// Registra (ou atualiza) o usuário atual na "base" que o admin enxerga.
// Chamado no cadastro, pra a pessoa aparecer na tela de Usuários do admin.
function shRegistrarUsuarioNaBase(nome, email, papel, status) {
  const lista = shGetUsuarios();
  const existente = lista.find(u => u.email === email);
  if (existente) {
    existente.nome = nome;
    existente.papel = papel;
    existente.status = status;
  } else {
    lista.push({ id: shGerarId(), nome, email, papel, status });
  }
  shSalvarUsuarios(lista);
}


// ===== Data de cadastro =====
function shFormatarMesAno(iso) {
  let d = new Date(String(iso).indexOf('T') > -1 ? iso : String(iso).replace(' ', 'T') + 'Z');
  if (isNaN(d)) return '';
  const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  return meses[d.getMonth()] + '/' + d.getFullYear();
}

// ===== Atividades do aluno (simulação até a API de atividades) =====
function shGetAtividadesAluno() {
  return [
    { id: 'a1', titulo: 'Dúvida sobre Funções do 1º grau', materia: 'Matemática', data: '22/05/2026', status: 'Respondida', tipo: 'duvida', tutor: 'lucas', tutorNome: 'Lucas Oliveira', resposta: 'Resposta do tutor disponível no chat.' },
    { id: 'a2', titulo: 'Redação: Dissertação argumentativa', materia: 'Português', data: '20/05/2026', status: 'Em andamento', tipo: 'caderno' },
    { id: 'a3', titulo: 'Exercícios de Frações', materia: 'Matemática', data: '18/05/2026', status: 'Concluída', tipo: 'material', nota: '9,0' },
    { id: 'a4', titulo: 'Dúvida sobre Revolução Francesa', materia: 'História', data: '15/05/2026', status: 'Em andamento', tipo: 'duvida', tutor: 'juliana', tutorNome: 'Juliana Santos' },
    { id: 'a5', titulo: 'Lista de Leis de Newton', materia: 'Física', data: '12/05/2026', status: 'Concluída', tipo: 'material', nota: '8,5' },
    { id: 'a6', titulo: 'Avaliou o tutor Rafael Costa', materia: '', data: '18/05/2026', status: 'Concluída', tipo: 'avaliacao' }
  ];
}

function shClasseStatus(st) { return st === 'Em andamento' ? 'sh-pill warn' : 'sh-pill'; }

// Desenha a lista de atividades dentro de `seletor` (limite opcional). Clicar abre a atividade.
function shRenderizarAtividades(seletor, limite) {
  const alvo = document.querySelector(seletor);
  if (!alvo) return;
  let lista = shGetAtividadesAluno();
  if (limite) lista = lista.slice(0, limite);
  alvo.innerHTML = '';
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
    a.resposta || 'Atividade concluída.';
  const acao = document.getElementById('sh-ma-acao');
  let href = 'materiais.html' + (a.materia ? '?materia=' + encodeURIComponent(a.materia) : ''), txt = 'Ver material';
  if (a.tipo === 'caderno') { href = 'cadernos.html'; txt = a.status === 'Em andamento' ? 'Continuar atividade' : 'Abrir caderno'; }
  else if (a.tipo === 'duvida' && a.tutor) { href = 'chat.html?tutor=' + a.tutor; txt = a.status === 'Em andamento' ? 'Continuar conversa' : 'Ver resposta'; }
  else if (a.status === 'Em andamento') txt = 'Continuar atividade';
  if (a.tipo === 'avaliacao') { acao.classList.add('d-none'); } else { acao.classList.remove('d-none'); acao.href = href; acao.textContent = txt; }
  bootstrap.Modal.getOrCreateInstance(modal).show();
}

// ===== Matérias favoritas =====
const SH_TODAS_MATERIAS = ['Matemática','Português','Ciências','História','Inglês','Física','Química','Biologia','Informática'];
function shGetMateriasFavoritas() {
  try {
    const v = JSON.parse(localStorage.getItem('sh_materias_favoritas'));
    if (Array.isArray(v)) return v;
  } catch (e) {}
  return ['Matemática','Português','História'];
}
function shSalvarMateriasFavoritas(lista) {
  try { localStorage.setItem('sh_materias_favoritas', JSON.stringify(lista)); } catch (e) {}
}
