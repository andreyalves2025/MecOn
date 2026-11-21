/**
 * MecOn - auth.js
 * Responsável por:
 *  - Verificar o estado de login atual (consultando a sessão PHP)
 *  - Montar dinamicamente os botões do header (Login / Cadastrar oficina / Sair)
 *  - Expor funções utilitárias usadas pelos outros scripts (chamarApi, exigirLoginUI)
 *
 * Este script deve ser incluído em TODAS as páginas, antes do script específico
 * de cada página (ex: antes de index.js, oficinas.js, etc).
 *
 * Existem dois tipos de sessão independentes, guardados em chaves separadas
 * do localStorage:
 *   - mecon_usuario: cliente ou admin (tabela usuarios)
 *   - mecon_oficina: oficina logada com conta própria (tabela oficinas)
 * Apenas um dos dois costuma estar ativo por vez na UI, mas tecnicamente
 * podem coexistir no navegador sem conflito, pois o backend trata as
 * sessões de forma independente.
 */
const MECON_API_BASE = '/mecon/backend';
/**
 * Wrapper de fetch que já trata JSON e erros padronizados da API.
 * Retorna o corpo já parseado; lança erro com a mensagem do backend em caso de falha.
 */
async function chamarApi(caminho, opcoes = {}) {
    const resposta = await fetch(MECON_API_BASE + caminho, {
        credentials: 'include', // necessário para enviar o cookie de sessão PHP
        headers: { 'Content-Type': 'application/json' },
        ...opcoes,
    });
    const texto = await resposta.text();
    let corpo = null;
    try {
        corpo = JSON.parse(texto);
    } catch (e) {
        // Resposta que não é JSON: quase sempre erro/aviso do PHP, 404 (pasta errada) ou página do Apache.
        console.error('Resposta não-JSON de ' + MECON_API_BASE + caminho + ' (HTTP ' + resposta.status + '):', texto.slice(0, 500));
        throw new Error('Resposta inválida do servidor (HTTP ' + resposta.status + '). Veja o console (F12) e abra /mecon/backend/diagnostico.php.');
    }
    if (!resposta.ok) {
        const detalhe = corpo.detalhe ? ' [' + corpo.detalhe + ']' : '';
        throw new Error((corpo.erro || 'Ocorreu um erro inesperado.') + detalhe);
    }
    return corpo;
}
/**
 * Descobre se há um usuário logado e quem é, perguntando a um endpoint
 * que apenas inspeciona a sessão atual (qualquer endpoint protegido serve;
 * usamos check_favorito como sonda seria inadequado, então criamos uma
 * verificação local simples via list_favoritos, que falha com 401 se deslogado).
 *
 * Para manter isso simples e não depender de um endpoint dedicado de "whoami",
 * o estado de login é mantido no localStorage do navegador, sincronizado nos
 * pontos de login/cadastro/logout. Isso evita uma chamada extra de rede em
 * toda navegação de página.
 */
function obterUsuarioLocal() {
    const dados = localStorage.getItem('mecon_usuario');
    return dados ? JSON.parse(dados) : null;
}
function salvarUsuarioLocal(usuario) {
    localStorage.setItem('mecon_usuario', JSON.stringify(usuario));
}
function limparUsuarioLocal() {
    localStorage.removeItem('mecon_usuario');
    sessionStorage.removeItem('mecon_lembretes_cache');
}
/**
 * Equivalentes às funções acima, para a sessão de oficina (login próprio,
 * tabela oficinas — distinta da tabela usuarios).
 */
function obterOficinaLocal() {
    const dados = localStorage.getItem('mecon_oficina');
    return dados ? JSON.parse(dados) : null;
}
function salvarOficinaLocal(oficina) {
    localStorage.setItem('mecon_oficina', JSON.stringify(oficina));
}
function limparOficinaLocal() {
    localStorage.removeItem('mecon_oficina');
}
/**
 * Extrai as iniciais de um nome completo: primeira letra do primeiro nome
 * + primeira letra do último sobrenome. Ex: "João Alves" -> "JA".
 * Nomes com uma única palavra retornam só essa inicial.
 */
function obterIniciais(nomeCompleto) {
    const partes = nomeCompleto.trim().split(/\s+/);
    if (partes.length === 1) {
        return partes[0].charAt(0).toUpperCase();
    }
    const primeira = partes[0].charAt(0);
    const ultima = partes[partes.length - 1].charAt(0);
    return (primeira + ultima).toUpperCase();
}
/**
 * Gera uma cor de fundo consistente a partir do nome (mesmo nome sempre
 * resulta na mesma cor), usando um hash simples sobre uma paleta fixa de
 * tons harmônicos com o azul do MecOn.
 */
function obterCorAvatar(nomeCompleto) {
    const paleta = [
        '#5B6FE8', '#D85A30', '#1D9E75', '#D4537E',
        '#BA7517', '#534AB7', '#185FA5', '#993556',
    ];
    let hash = 0;
    for (let i = 0; i < nomeCompleto.length; i++) {
        hash = (hash * 31 + nomeCompleto.charCodeAt(i)) % paleta.length;
    }
    return paleta[Math.abs(hash)];
}
/**
 * Monta os botões do header de acordo com o estado de login.
 * Caminho-base usado para os links varia conforme a página estar
 * na raiz (index.html) ou em /pages/.
 */
function montarHeader() {
    const container = document.getElementById('header-acoes');
    if (!container) return;
    const estaEmPages = window.location.pathname.includes('/pages/');
    const base = estaEmPages ? '' : 'pages/';
    const usuario = obterUsuarioLocal();
    const oficina = obterOficinaLocal();
    // Estado 1: ninguém logado (nem cliente/admin, nem oficina)
    if (!usuario && !oficina) {
        container.innerHTML = `
      <a href="${base}login.html" class="header__botao">Login</a>
    `;
        return;
    }
    // Estado 2: oficina logada
    if (oficina) {
        container.innerHTML = `
      <button type="button" class="header__botao" id="botao-sair">Sair</button>
    `;
        document.getElementById('botao-sair').addEventListener('click', async () => {
            try {
                await chamarApi('/oficina_auth/logout_oficina.php', { method: 'POST' });
            } catch (erro) {
                console.error('Erro ao encerrar sessão da oficina:', erro);
            } finally {
                limparOficinaLocal();
                window.location.href = estaEmPages ? '../index.html' : 'index.html';
            }
        });
        return;
    }
    // Estado 3: cliente ou admin logado
    const destinoPerfil = usuario.tipo === 'admin' ? `${base}admin.html` : `${base}perfil.html`;
    const avatarHtml = usuario.tipo === 'admin'
        ? `
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
      </svg>
    `
        : `<span style="color: ${obterCorAvatar(usuario.nome)}; font-weight: 700; font-size: 0.95rem;">${obterIniciais(usuario.nome)}</span>`;
    container.innerHTML = `
    <button type="button" class="header__botao" id="botao-sair">Sair</button>
    <a href="${destinoPerfil}" class="header__avatar" aria-label="Perfil de ${usuario.nome}">
      ${avatarHtml}
    </a>
  `;
    if (usuario.tipo === 'cliente') {
        obterLembretesPendentes().then((lista) => atualizarBadgeLembretes(lista.length));
    }
    document.getElementById('botao-sair').addEventListener('click', async () => {
        try {
            await chamarApi('/auth/logout.php', { method: 'POST' });
        } catch (erro) {
            console.error('Erro ao encerrar sessão:', erro);
        } finally {
            limparUsuarioLocal();
            const baseRedirect = estaEmPages ? '../index.html' : 'index.html';
            window.location.href = baseRedirect;
        }
    });
}
/**
 * Redireciona para a página de login se não houver usuário logado.
 * Útil em páginas inteiras que exigem login (ex: perfil.html, admin.html).
 * Retorna o usuário logado quando há sessão.
 */
function exigirLoginUI() {
    const usuario = obterUsuarioLocal();
    if (!usuario) {
        const estaEmPages = window.location.pathname.includes('/pages/');
        window.location.href = estaEmPages ? 'login.html' : 'pages/login.html';
        return null;
    }
    return usuario;
}
/**
 * Redireciona para a home se o usuário logado não for administrador.
 * Usado nas páginas admin*.html.
 */
function exigirAdminUI() {
    const usuario = exigirLoginUI();
    if (usuario && usuario.tipo !== 'admin') {
        window.location.href = window.location.pathname.includes('/pages/') ? '../index.html' : 'index.html';
        return null;
    }
    return usuario;
}
/**
 * Redireciona para a página de login (unificada) se não houver oficina logada.
 * Usado em perfil-oficina.html.
 * Retorna os dados da oficina logada quando há sessão.
 */
/**
 * Renderiza os controles de paginação (Anterior / Página X de Y / Próxima)
 * dentro de containerEl, a partir do bloco "paginacao" retornado pelas
 * rotas paginadas (ver backend/config/paginacao.php). Chama aoMudarPagina(n)
 * quando o usuário troca de página.
 */
function renderizarControlesPaginacao(containerEl, paginacao, aoMudarPagina) {
  if (!paginacao || paginacao.total_paginas <= 1) {
    containerEl.innerHTML = '';
    return;
  }
  const { pagina_atual: paginaAtual, total_paginas: totalPaginas, total_registros: totalRegistros } = paginacao;
  containerEl.innerHTML = `
    <div style="display:flex; align-items:center; justify-content:center; gap:16px; margin: 24px 0;">
      <button type="button" class="botao botao--secundario" id="botao-pagina-anterior" ${paginaAtual <= 1 ? 'disabled' : ''}>
        &larr; Anterior
      </button>
      <span style="color: var(--texto-secundario); font-size:0.9rem;">
        Página ${paginaAtual} de ${totalPaginas} (${totalRegistros} no total)
      </span>
      <button type="button" class="botao botao--secundario" id="botao-proxima-pagina" ${paginaAtual >= totalPaginas ? 'disabled' : ''}>
        Próxima &rarr;
      </button>
    </div>
  `;
  const botaoAnterior = document.getElementById('botao-pagina-anterior');
  const botaoProxima = document.getElementById('botao-proxima-pagina');
  if (botaoAnterior) botaoAnterior.addEventListener('click', () => aoMudarPagina(paginaAtual - 1));
  if (botaoProxima) botaoProxima.addEventListener('click', () => aoMudarPagina(paginaAtual + 1));
}

/**
 * Editor de peças reutilizável (histórico do usuário e conclusão de
 * agendamento pela oficina). Uso:
 *   html = montarEditorPecasHTML(pecasIniciais);   // injetar no formulário
 *   ligarEditorPecas(containerDoFormulario);        // depois de injetar
 *   pecas = lerPecasDoEditor(containerDoFormulario); // ao enviar
 * Cada peça: { nome, quantidade, valor_unitario }. Linhas sem nome são ignoradas.
 */
function _linhaPecaHTML(p = {}) {
  const esc = (t) => String(t ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  return `
    <div class="linha-peca" style="display:flex; gap:6px; margin-bottom:6px; align-items:center;">
      <input type="text" class="peca-nome" placeholder="Peça (ex: Filtro de óleo)" maxlength="150" value="${esc(p.nome)}" style="flex:3; min-width:0;">
      <input type="number" class="peca-qtd" placeholder="Qtd" min="0.01" step="0.01" value="${p.quantidade ?? 1}" style="flex:1; min-width:0;">
      <input type="number" class="peca-valor" placeholder="R$ unit." min="0" step="0.01" value="${p.valor_unitario ?? ''}" style="flex:1.2; min-width:0;">
      <button type="button" class="botao-remover-peca" aria-label="Remover peça" style="background:none; border:none; color:#C0392B; cursor:pointer; font-size:1.1rem;">✕</button>
    </div>`;
}
function montarEditorPecasHTML(pecas = []) {
  return `
    <div class="campo-formulario editor-pecas">
      <label>Peças utilizadas (opcional)</label>
      <div class="lista-pecas">${pecas.map(_linhaPecaHTML).join('')}</div>
      <button type="button" class="botao botao--secundario botao-adicionar-peca" style="padding:6px 12px; font-size:0.85rem;">+ Adicionar peça</button>
    </div>`;
}
function ligarEditorPecas(raiz) {
  const editor = raiz.querySelector('.editor-pecas');
  if (!editor) return;
  const lista = editor.querySelector('.lista-pecas');
  editor.querySelector('.botao-adicionar-peca').addEventListener('click', () => {
    lista.insertAdjacentHTML('beforeend', _linhaPecaHTML());
  });
  lista.addEventListener('click', (evento) => {
    if (evento.target.classList.contains('botao-remover-peca')) {
      evento.target.closest('.linha-peca').remove();
    }
  });
}
function lerPecasDoEditor(raiz) {
  return Array.from(raiz.querySelectorAll('.editor-pecas .linha-peca'))
    .map((linha) => ({
      nome: linha.querySelector('.peca-nome').value.trim(),
      quantidade: linha.querySelector('.peca-qtd').value !== '' ? Number(linha.querySelector('.peca-qtd').value) : 1,
      valor_unitario: linha.querySelector('.peca-valor').value !== '' ? Number(linha.querySelector('.peca-valor').value) : null,
    }))
    .filter((p) => p.nome !== '');
}

/**
 * Bloco de reputação da oficina (indicadores além das estrelas), usado na
 * página pública da oficina e no painel de desempenho da própria oficina.
 * Recebe o objeto "reputacao" calculado por backend/config/reputacao.php.
 * Indicadores com amostra pequena chegam como null e aparecem como
 * "poucos dados" em vez de um percentual enganoso.
 */
function formatarTempoResposta(horas) {
  if (horas === null) return null;
  if (horas < 1) return 'menos de 1 h';
  if (horas < 24) return `${horas.toLocaleString('pt-BR')} h`;
  return `${(horas / 24).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} dias`;
}
function montarBlocoReputacaoHTML(rep, comTitulo = true) {
  if (!rep) return '';
  const pct = (v) => (v === null ? null : `${v.toLocaleString('pt-BR')}%`);
  const itens = [
    { rotulo: 'Serviços concluídos', valor: String(rep.servicos_concluidos), sempre: true },
    { rotulo: 'Taxa de conclusão', valor: pct(rep.taxa_conclusao), dica: 'Dos serviços aceitos que já tiveram desfecho, quantos foram realizados' },
    { rotulo: 'Taxa de aceite', valor: pct(rep.taxa_aceite), dica: 'Das solicitações respondidas, quantas a oficina aceitou' },
    { rotulo: 'Tempo médio de resposta', valor: formatarTempoResposta(rep.tempo_medio_resposta_horas), dica: 'Tempo entre a solicitação e a resposta da oficina' },
    { rotulo: 'Clientes que voltaram', valor: pct(rep.clientes_recorrentes_pct), dica: 'Clientes com 2 ou mais serviços concluídos nesta oficina' },
  ];
  const cartoes = itens.map((i) => `
    <div title="${i.dica || ''}" class="${i.valor === null ? 'indicador--vazio' : ''}" style="flex:1 1 130px; background: var(--lilas-claro); border-radius: var(--raio-botao); padding:10px 12px; text-align:center;">
      <div style="font-size:1.25rem; font-weight:700; color: var(--azul-mecon);">
        ${i.valor !== null ? i.valor : '<span style="font-size:0.85rem; font-weight:400; color: var(--texto-secundario);">poucos dados</span>'}
      </div>
      <div style="font-size:0.78rem; color: var(--texto-secundario); margin-top:2px;">${i.rotulo}</div>
    </div>
  `).join('');
  return `
    <div class="bloco-reputacao" style="margin: 14px 0;">
      ${comTitulo ? '<p style="font-weight:600; margin:0 0 8px;">Reputação na plataforma</p>' : ''}
      <div style="display:flex; flex-wrap:wrap; gap:10px;">${cartoes}</div>
    </div>
  `;
}

/**
 * Manutenções pendentes (detecção proativa): reaproveita os lembretes
 * calculados por backend/historico/lembretes_manutencao.php e os torna
 * visíveis fora da aba de veículos — selo no avatar do header e seção na
 * home. O resultado fica em cache na sessão por 10 minutos para não
 * adicionar uma requisição a cada página; perfil.js renova o cache sempre
 * que carrega os lembretes (ex.: depois de atualizar km ou registrar um
 * serviço). Só vale para clientes (oficina e admin não têm veículos).
 */
const LEMBRETES_CACHE_CHAVE = 'mecon_lembretes_cache';
const LEMBRETES_CACHE_TTL_MS = 10 * 60 * 1000;
function _escHtml(texto) {
  return String(texto ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function descreverLembrete(l) {
  const servico = _escHtml(l.nome_servico);
  const veiculo = _escHtml(l.placa);
  const partes = [];
  if (l.progresso_tempo_pct !== null && l.progresso_tempo_pct >= 80) {
    partes.push(`${l.meses_desde} meses desde a última (recomendado: a cada ${l.intervalo_recomendado_meses})`);
  }
  if (l.progresso_km_pct !== null && l.progresso_km_pct >= 80) {
    partes.push(`${l.km_rodados.toLocaleString('pt-BR')} km rodados desde a última (recomendado: a cada ${l.intervalo_recomendado_km.toLocaleString('pt-BR')} km)`);
  }
  const situacao = l.status === 'atrasado' ? 'manutenção atrasada' : 'manutenção chegando';
  return `<strong>${servico}</strong> (${veiculo}) — ${situacao}: ${partes.join(' · ')}.`;
}
function atualizarBadgeLembretes(quantidade) {
  const avatar = document.querySelector('.header__avatar');
  if (!avatar) return;
  let badge = avatar.querySelector('.badge-lembretes');
  if (quantidade <= 0) {
    if (badge) badge.remove();
    return;
  }
  if (!badge) {
    badge = document.createElement('span');
    badge.className = 'badge-lembretes';
    badge.style.cssText = 'position:absolute; top:-4px; right:-4px; background:#C0392B; color:#fff; border-radius:999px; min-width:18px; height:18px; font-size:0.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 4px;';
    // o avatar corta o que passa da borda (overflow:hidden no CSS); o selo precisa vazar
    avatar.style.position = 'relative';
    avatar.style.overflow = 'visible';
    avatar.appendChild(badge);
  }
  badge.textContent = String(quantidade);
  badge.title = `${quantidade} manutenção(ões) pendente(s)`;
}
function atualizarCacheLembretes(lembretes) {
  const usuario = obterUsuarioLocal();
  if (!usuario) return;
  try {
    sessionStorage.setItem(LEMBRETES_CACHE_CHAVE, JSON.stringify({ idUsuario: usuario.id_usuario, t: Date.now(), lembretes }));
  } catch (e) { /* armazenamento indisponível: segue sem cache */ }
  atualizarBadgeLembretes(lembretes.length);
}
async function obterLembretesPendentes(forcar = false) {
  const usuario = obterUsuarioLocal();
  if (!usuario || usuario.tipo !== 'cliente') return [];
  if (!forcar) {
    try {
      const cache = JSON.parse(sessionStorage.getItem(LEMBRETES_CACHE_CHAVE));
      if (cache && cache.idUsuario === usuario.id_usuario && Date.now() - cache.t < LEMBRETES_CACHE_TTL_MS) {
        return cache.lembretes;
      }
    } catch (e) { /* cache inválido: busca de novo */ }
  }
  try {
    const resposta = await chamarApi('/historico/lembretes_manutencao.php');
    atualizarCacheLembretes(resposta.lembretes);
    return resposta.lembretes;
  } catch (erro) {
    return [];
  }
}

/**
 * Upload de arquivos (multipart/form-data). Diferente de chamarApi, NÃO
 * define Content-Type: o navegador precisa gerar o cabeçalho com o
 * "boundary" do FormData. Mesmo tratamento de erro de chamarApi.
 */
async function chamarApiUpload(caminho, formData) {
  const resposta = await fetch(MECON_API_BASE + caminho, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  });
  const corpo = await resposta.json().catch(() => ({}));
  if (!resposta.ok) {
    throw new Error(corpo.erro || 'Ocorreu um erro inesperado no envio.');
  }
  return corpo;
}

/**
 * Anexos (fotos/vídeos) de um pedido de orçamento. Os arquivos só saem do
 * servidor por baixar_anexo.php, que confere permissão pela sessão (por
 * isso <img>/<video> funcionam: o cookie vai junto na mesma origem).
 */
function montarAnexosHTML(anexos) {
  if (!anexos || anexos.length === 0) return '';
  const url = (a) => `${MECON_API_BASE}/orcamentos/baixar_anexo.php?id_anexo=${a.id_anexo}`;
  const itens = anexos.map((a) => a.tipo_mime.startsWith('video/')
    ? `<video controls preload="metadata" style="width:220px; max-width:100%; border-radius: var(--raio-botao); background:#000;" src="${url(a)}"></video>`
    : `<a href="${url(a)}" target="_blank" rel="noopener"><img src="${url(a)}" alt="${_escHtml(a.nome_original)}" style="height:110px; border-radius: var(--raio-botao); border:1px solid var(--borda);"></a>`
  ).join('');
  return `<div style="display:flex; flex-wrap:wrap; gap:8px; margin:8px 0;">${itens}</div>`;
}
function formatarReaisGeral(valor) {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
function formatarDataISO(dataIso) {
  if (!dataIso) return '';
  const [ano, mes, dia] = dataIso.slice(0, 10).split('-');
  return `${dia}/${mes}/${ano}`;
}
function exigirOficinaUI() {
    const oficina = obterOficinaLocal();
    if (!oficina) {
        const estaEmPages = window.location.pathname.includes('/pages/');
        window.location.href = estaEmPages ? 'login.html' : 'pages/login.html';
        return null;
    }
    return oficina;
}
document.addEventListener('DOMContentLoaded', montarHeader);
