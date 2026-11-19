/**
 * MecOn - perfil-oficina.js
 * Perfil da oficina logada: edição dos próprios dados e visualização
 * exclusiva dos comentários recebidos pela própria oficina.
 * Exige login de oficina (sessão própria, tabela oficinas).
 */
const oficinaSessao = exigirOficinaUI();
const painel = document.getElementById('painel-abas');
const abas = document.querySelectorAll('.perfil-aba');
let cacheServicos = null;
function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}
function estrelas(nota) {
  return '★★★★★☆☆☆☆☆'.slice(5 - nota, 10 - nota);
}
if (oficinaSessao) {
  document.getElementById('texto-saudacao').textContent = oficinaSessao.nome;
}
/* =========================================================
   Navegação entre abas
   ========================================================= */
const renderizadores = {
  dados: renderizarDados,
  desempenho: renderizarDesempenho,
  comentarios: renderizarComentarios,
  promocoes: renderizarPromocoes,
  agendamentos: renderizarAgendamentosOficina,
  orcamentos: renderizarOrcamentosOficina,
  mensagens: () => iniciarChat(painel, { como: 'oficina' }),
  clientes: renderizarClientes,
  financeiro: renderizarFinanceiro,
};
function selecionarAba(nomeAba) {
  abas.forEach((botao) => {
    const ativa = botao.dataset.aba === nomeAba;
    botao.classList.toggle('perfil-aba--ativa', ativa);
    botao.setAttribute('aria-selected', ativa ? 'true' : 'false');
  });
  renderizadores[nomeAba]();
}
abas.forEach((botao) => {
  botao.addEventListener('click', () => selecionarAba(botao.dataset.aba));
});
/* =========================================================
   Aba: Dados da oficina (editar)
   ========================================================= */
async function carregarServicos() {
  if (!cacheServicos) {
    const r = await chamarApi('/servicos/list_servicos.php');
    cacheServicos = r.servicos;
  }
  return cacheServicos;
}
async function renderizarDados() {
  painel.innerHTML = '<p>Carregando dados…</p>';
  try {
    await carregarServicos();
    const resposta = await chamarApi('/oficina_auth/get_perfil_oficina.php');
    desenharFormularioDados(resposta.oficina);
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar seus dados: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharFormularioDados(oficina) {
  const nota = oficina.media_avaliacao !== null ? oficina.media_avaliacao.toFixed(1) : 'Sem avaliações';
  document.getElementById('texto-saudacao').innerHTML = `
    ${escapeHtml(oficina.nome)}
    <span style="display:inline-flex; align-items:center; gap:4px; font-size:1.2rem; color: var(--estrela); margin-left:10px; vertical-align:middle;">
      <svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22" aria-hidden="true"><path d="M9.05 2.93a1 1 0 011.9 0l1.36 2.77 3.06.45a1 1 0 01.55 1.7l-2.21 2.15.52 3.05a1 1 0 01-1.45 1.05L10 12.7l-2.78 1.4a1 1 0 01-1.45-1.05l.52-3.05-2.21-2.15a1 1 0 01.55-1.7l3.06-.45z"/></svg>
      ${nota}
    </span>
  `;
  const precosAtuais = Object.fromEntries(oficina.servicos.map((s) => [s.id_servico, s.preco]));
  const linhasServicos = cacheServicos.map((s, i) => `
    <div style="display:grid; grid-template-columns:24px 1fr 110px; align-items:center; gap:10px; padding:10px 0; ${i < cacheServicos.length - 1 ? 'border-bottom:1px solid var(--borda);' : ''}">
      <input type="checkbox" class="checkbox-servico" id="servico-${s.id_servico}" value="${s.id_servico}" ${s.id_servico in precosAtuais ? 'checked' : ''}>
      <label for="servico-${s.id_servico}" style="margin:0; font-weight:400; cursor:pointer;">${escapeHtml(s.nome)}</label>
      <input type="number" class="input-preco-servico" data-id="${s.id_servico}" min="0" step="0.01" placeholder="Preço (R$)"
        style="padding:6px 10px;" value="${s.id_servico in precosAtuais ? precosAtuais[s.id_servico] : ''}" ${s.id_servico in precosAtuais ? '' : 'disabled'}>
    </div>
  `).join('');
  painel.innerHTML = `
    <div class="cartao-formulario" style="margin: 0 auto; max-width: 520px;">
      <p id="erro-dados-oficina" class="mensagem-erro"></p>
      <p id="sucesso-dados-oficina" class="mensagem-erro" style="background-color:#EAFBF1; color: var(--verde-sucesso);"></p>
      <form id="form-dados-oficina">
        <div class="campo-formulario">
          <label for="input-nome">Nome</label>
          <input type="text" id="input-nome" required value="${escapeHtml(oficina.nome)}">
        </div>
        <div class="campo-formulario">
          <label for="input-email">Email</label>
          <input type="email" id="input-email" required value="${escapeHtml(oficina.email)}">
        </div>
        <div class="campo-formulario">
          <label for="input-senha">Nova senha (deixe em branco para não alterar)</label>
          <input type="password" id="input-senha" minlength="6">
        </div>
        <div class="campo-formulario">
          <label for="input-cidade">Cidade</label>
          <input type="text" id="input-cidade" required value="${escapeHtml(oficina.cidade)}">
        </div>
        <div class="campo-formulario">
          <label for="input-bairro">Bairro</label>
          <input type="text" id="input-bairro" required value="${escapeHtml(oficina.bairro)}">
        </div>
        <div class="campo-formulario">
          <label for="input-endereco">Endereço</label>
          <input type="text" id="input-endereco" required value="${escapeHtml(oficina.endereco)}">
        </div>
        <div class="campo-formulario">
          <label for="input-telefone">Telefone</label>
          <input type="text" id="input-telefone" required value="${escapeHtml(oficina.telefone)}">
        </div>
        <div class="campo-formulario">
          <label for="input-descricao">Descrição</label>
          <input type="text" id="input-descricao" value="${escapeHtml(oficina.descricao || '')}">
        </div>
        <div class="campo-formulario">
          <label>Localização no mapa (para clientes te encontrarem por proximidade)</label>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="number" id="input-latitude" step="0.0000001" placeholder="Latitude" style="flex:1; min-width:140px;" value="${oficina.latitude ?? ''}">
            <input type="number" id="input-longitude" step="0.0000001" placeholder="Longitude" style="flex:1; min-width:140px;" value="${oficina.longitude ?? ''}">
            <button type="button" id="botao-localizar-oficina" class="botao botao--secundario" style="white-space:nowrap;">📍 Usar localização atual</button>
          </div>
          <p style="color: var(--texto-secundario); font-size:0.8rem; margin-top:4px;">
            Deixe em branco se preferir não aparecer em buscas por proximidade.
          </p>
        </div>
        <div class="campo-formulario">
          <label>Serviços oferecidos e preços</label>
          <div style="border: 1px solid var(--borda); border-radius: var(--raio-botao); padding: 4px 14px;">${linhasServicos}</div>
        </div>
        <button type="submit" class="botao botao--primario">Salvar alterações</button>
      </form>
    </div>
  `;
  document.getElementById('botao-localizar-oficina').addEventListener('click', () => {
    if (!navigator.geolocation) {
      alert('Seu navegador não suporta geolocalização.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (posicao) => {
        document.getElementById('input-latitude').value = posicao.coords.latitude.toFixed(7);
        document.getElementById('input-longitude').value = posicao.coords.longitude.toFixed(7);
      },
      () => alert('Não foi possível obter sua localização. Verifique a permissão do navegador.')
    );
  });
  painel.querySelectorAll('.checkbox-servico').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      const inputPreco = painel.querySelector(`.input-preco-servico[data-id="${checkbox.value}"]`);
      inputPreco.disabled = !checkbox.checked;
      if (!checkbox.checked) inputPreco.value = '';
    });
  });
  document.getElementById('form-dados-oficina').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const servicosMarcados = Array.from(painel.querySelectorAll('.checkbox-servico:checked'))
      .map((c) => {
        const inputPreco = painel.querySelector(`.input-preco-servico[data-id="${c.value}"]`);
        return { id_servico: Number(c.value), preco: Number(inputPreco.value) };
      });
    const erroEl = document.getElementById('erro-dados-oficina');
    const sucessoEl = document.getElementById('sucesso-dados-oficina');
    erroEl.classList.remove('mensagem-erro--visivel');
    sucessoEl.classList.remove('mensagem-erro--visivel');
    if (servicosMarcados.length === 0) {
      erroEl.textContent = 'Selecione ao menos um serviço e informe seu preço.';
      erroEl.classList.add('mensagem-erro--visivel');
      return;
    }
    const latitudeVal = document.getElementById('input-latitude').value;
    const longitudeVal = document.getElementById('input-longitude').value;
    const corpo = {
      nome: document.getElementById('input-nome').value.trim(),
      email: document.getElementById('input-email').value.trim(),
      cidade: document.getElementById('input-cidade').value.trim(),
      bairro: document.getElementById('input-bairro').value.trim(),
      endereco: document.getElementById('input-endereco').value.trim(),
      telefone: document.getElementById('input-telefone').value.trim(),
      descricao: document.getElementById('input-descricao').value.trim(),
      latitude: latitudeVal !== '' ? Number(latitudeVal) : null,
      longitude: longitudeVal !== '' ? Number(longitudeVal) : null,
      servicos: servicosMarcados,
    };
    const senha = document.getElementById('input-senha').value;
    if (senha !== '') corpo.senha = senha;
    try {
      await chamarApi('/oficina_auth/update_perfil_oficina.php', {
        method: 'POST',
        body: JSON.stringify(corpo),
      });
      const oficinaAtualizada = { ...oficinaSessao, nome: corpo.nome, email: corpo.email };
      salvarOficinaLocal(oficinaAtualizada);
      sucessoEl.textContent = 'Dados atualizados com sucesso!';
      sucessoEl.classList.add('mensagem-erro--visivel');
      document.getElementById('texto-saudacao').innerHTML = `
        ${escapeHtml(corpo.nome)}
        <span style="display:inline-flex; align-items:center; gap:4px; font-size:1.2rem; color: var(--estrela); margin-left:10px; vertical-align:middle;">
          <svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22" aria-hidden="true"><path d="M9.05 2.93a1 1 0 011.9 0l1.36 2.77 3.06.45a1 1 0 01.55 1.7l-2.21 2.15.52 3.05a1 1 0 01-1.45 1.05L10 12.7l-2.78 1.4a1 1 0 01-1.45-1.05l.52-3.05-2.21-2.15a1 1 0 01.55-1.7l3.06-.45z"/></svg>
          ${nota}
        </span>
      `;
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
/* =========================================================
   Aba: Desempenho (percentil de avaliação, favoritos,
   distribuição de notas e evolução da média)
   ========================================================= */
async function renderizarDesempenho() {
  painel.innerHTML = '<p>Carregando métricas de desempenho…</p>';
  try {
    const resposta = await chamarApi('/oficina_auth/get_desempenho_oficina.php');
    desenharDesempenho(resposta.desempenho);
    painel.insertAdjacentHTML('afterbegin', montarBlocoReputacaoHTML(resposta.desempenho.reputacao));
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar o desempenho: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharDesempenho(d) {
  if (d.total_avaliacoes === 0) {
    painel.innerHTML = `
      <div class="estado-vazio">
        <h3>Ainda não há avaliações para calcular seu desempenho</h3>
        <p>Quando sua oficina receber a primeira avaliação, as métricas aparecerão aqui.</p>
      </div>
    `;
    return;
  }
  const cardPercentil = d.percentil !== null
    ? `
      <div class="cartao-formulario" style="margin:0; text-align:center;">
        <p style="font-size:0.9rem; color: var(--texto-secundario); margin-bottom:4px;">Ranking de avaliação</p>
        <p style="font-size:1.6rem; font-weight:700; color: var(--azul-mecon); margin:0;">Top ${100 - d.percentil}%</p>
        <p style="font-size:0.85rem; color: var(--texto-secundario); margin-top:6px;">
          Você está entre as ${100 - d.percentil}% oficinas mais bem avaliadas do MecOn!
        </p>
      </div>
    `
    : '';
  const maiorContagem = Math.max(...d.distribuicao_notas.map((x) => x.total), 1);
  const barrasDistribuicao = d.distribuicao_notas
    .slice()
    .reverse()
    .map((item) => {
      const largura = Math.round((item.total / maiorContagem) * 100);
      return `
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
          <span style="width:42px; font-size:0.85rem; color: var(--texto-secundario);">${item.nota} ★</span>
          <div style="flex:1; background: var(--lilas-claro); border-radius:6px; height:14px; overflow:hidden;">
            <div style="width:${largura}%; background: var(--azul-mecon); height:100%; border-radius:6px;"></div>
          </div>
          <span style="width:28px; text-align:right; font-size:0.85rem; color: var(--texto-secundario);">${item.total}</span>
        </div>
      `;
    }).join('');
  const evolucaoHtml = d.evolucao_media.length > 0
    ? d.evolucao_media.map((ponto) => `
        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--borda);">
          <span style="font-size:0.9rem; color: var(--texto-secundario);">${formatarMesAno(ponto.mes)}</span>
          <span style="font-size:0.9rem; font-weight:600;">★ ${ponto.media.toFixed(2)}</span>
        </div>
      `).join('')
    : '<p style="color: var(--texto-secundario);">Sem avaliações registradas nos últimos 6 meses.</p>';
  painel.innerHTML = `
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:24px;">
      ${cardPercentil}
      <div class="cartao-formulario" style="margin:0; text-align:center;">
        <p style="font-size:0.9rem; color: var(--texto-secundario); margin-bottom:4px;">Avaliações recebidas</p>
        <p style="font-size:1.6rem; font-weight:700; color: var(--azul-mecon); margin:0;">${d.total_avaliacoes}</p>
        <p style="font-size:0.85rem; color: var(--texto-secundario); margin-top:6px;">Média atual: ★ ${d.media_avaliacao.toFixed(2)}</p>
      </div>
      <div class="cartao-formulario" style="margin:0; text-align:center;">
        <p style="font-size:0.9rem; color: var(--texto-secundario); margin-bottom:4px;">Oficinas favoritas</p>
        <p style="font-size:1.6rem; font-weight:700; color: var(--azul-mecon); margin:0;">${d.total_favoritos}</p>
        <p style="font-size:0.85rem; color: var(--texto-secundario); margin-top:6px;">Motoristas favoritaram sua oficina</p>
      </div>
    </div>
    <h3>Distribuição de notas</h3>
    <div style="margin-bottom:24px;">${barrasDistribuicao}</div>
    <h3>Evolução da média (últimos 6 meses)</h3>
    <div>${evolucaoHtml}</div>
  `;
}
function formatarMesAno(mesIso) {
  const [ano, mes] = mesIso.split('-');
  const nomesMeses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  return `${nomesMeses[Number(mes) - 1]}/${ano}`;
}
/* =========================================================
   Aba: Comentários recebidos (exclusivos desta oficina)
   ========================================================= */
let cacheComentarios = [];
async function renderizarComentarios() {
  painel.innerHTML = '<p>Carregando comentários…</p>';
  try {
    const resposta = await chamarApi('/oficina_auth/list_reviews_oficina.php');
    cacheComentarios = resposta.avaliacoes;
    if (cacheComentarios.length === 0) {
      painel.innerHTML = `
        <div class="estado-vazio">
          <h3>Sua oficina ainda não recebeu avaliações</h3>
          <p>Quando clientes avaliarem sua oficina, os comentários aparecerão aqui.</p>
        </div>
      `;
      return;
    }
    desenharComentarios(cacheComentarios);
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar os comentários: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharComentarios(avaliacoes) {
  const linhas = avaliacoes.map((a) => `
    <div class="avaliacao" data-id-avaliacao="${a.id_avaliacao}">
      <div class="avaliacao__corpo">
        <div class="avaliacao__cabecalho">
          <span class="avaliacao__nome">
            ${escapeHtml(a.nome_usuario)}
            ${a.verificada ? '<span title="Este cliente contratou o serviço pelo MecOn" style="color: var(--verde-sucesso); font-weight:600; font-size:0.78rem; margin-left:6px;">✔ Serviço confirmado</span>' : ''}
          </span>
          <span class="avaliacao__estrelas">${estrelas(a.nota)}</span>
        </div>
        <p class="avaliacao__comentario">${escapeHtml(a.comentario || '')}</p>
        <div class="bloco-resposta" data-id="${a.id_avaliacao}">
          ${desenharBlocoResposta(a)}
        </div>
      </div>
    </div>
  `).join('');
  painel.innerHTML = `<div class="lista-avaliacoes">${linhas}</div>`;
  ligarEventosResposta();
}
function desenharBlocoResposta(a) {
  if (a.resposta_oficina) {
    return `
      <div style="margin-top:10px; padding:10px 14px; background: var(--lilas-claro); border-radius: var(--raio-botao); border-left: 3px solid var(--azul-mecon);">
        <p style="font-weight:600; font-size:0.85rem; margin:0 0 4px;">Sua resposta</p>
        <p style="margin:0 0 8px; font-size:0.9rem;">${escapeHtml(a.resposta_oficina)}</p>
        <div style="display:flex; gap:8px;">
          <button type="button" class="botao botao--secundario botao-editar-resposta" style="padding:6px 12px; font-size:0.85rem;">Editar</button>
          <button type="button" class="botao botao--perigo botao-remover-resposta" style="padding:6px 12px; font-size:0.85rem;">Remover</button>
        </div>
      </div>
    `;
  }
  return `
    <button type="button" class="botao botao--secundario botao-abrir-resposta" style="margin-top:8px; padding:6px 12px; font-size:0.85rem;">
      Responder
    </button>
  `;
}
function abrirFormularioResposta(blocoEl, idAvaliacao, textoAtual = '') {
  blocoEl.innerHTML = `
    <form class="form-resposta" style="margin-top:8px;">
      <textarea class="input-resposta" rows="3" maxlength="2000"
        style="width:100%; padding:10px 12px; border:1px solid var(--borda); border-radius: var(--raio-botao); font-family: var(--fonte-corpo);"
        placeholder="Escreva sua resposta pública a este cliente">${escapeHtml(textoAtual)}</textarea>
      <p class="mensagem-erro erro-resposta"></p>
      <div style="display:flex; gap:8px; margin-top:6px;">
        <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Salvar resposta</button>
        <button type="button" class="botao botao--secundario botao-cancelar-resposta" style="padding:6px 14px; font-size:0.85rem;">Cancelar</button>
      </div>
    </form>
  `;
  blocoEl.querySelector('.botao-cancelar-resposta').addEventListener('click', () => renderizarComentarios());
  blocoEl.querySelector('.form-resposta').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const texto = blocoEl.querySelector('.input-resposta').value.trim();
    const erroEl = blocoEl.querySelector('.erro-resposta');
    try {
      await chamarApi('/avaliacoes/responder_review.php', {
        method: 'POST',
        body: JSON.stringify({ id_avaliacao: idAvaliacao, resposta: texto }),
      });
      renderizarComentarios();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
function ligarEventosResposta() {
  painel.querySelectorAll('.bloco-resposta').forEach((blocoEl) => {
    const idAvaliacao = Number(blocoEl.dataset.id);
    const botaoAbrir = blocoEl.querySelector('.botao-abrir-resposta');
    if (botaoAbrir) {
      botaoAbrir.addEventListener('click', () => abrirFormularioResposta(blocoEl, idAvaliacao));
    }
    const botaoEditar = blocoEl.querySelector('.botao-editar-resposta');
    if (botaoEditar) {
      const avaliacaoAtual = cacheComentarios.find((a) => a.id_avaliacao === idAvaliacao);
      botaoEditar.addEventListener('click', () => abrirFormularioResposta(blocoEl, idAvaliacao, avaliacaoAtual?.resposta_oficina || ''));
    }
    const botaoRemover = blocoEl.querySelector('.botao-remover-resposta');
    if (botaoRemover) {
      botaoRemover.addEventListener('click', async () => {
        if (!confirm('Remover sua resposta a esta avaliação?')) return;
        try {
          await chamarApi('/avaliacoes/responder_review.php', {
            method: 'POST',
            body: JSON.stringify({ id_avaliacao: idAvaliacao, resposta: '' }),
          });
          renderizarComentarios();
        } catch (erro) {
          alert('Não foi possível remover a resposta: ' + erro.message);
        }
      });
    }
  });
}
/* =========================================================
   Aba: Promoções (criar, editar, excluir ofertas temporárias)
   ========================================================= */
let cachePromocoes = [];
const rotulosStatus = {
  ativa: { texto: 'Ativa', cor: 'var(--verde-sucesso)' },
  agendada: { texto: 'Agendada', cor: 'var(--azul-mecon)' },
  expirada: { texto: 'Expirada', cor: 'var(--texto-secundario)' },
};
let cacheServicosProprios = null;
async function renderizarPromocoes() {
  painel.innerHTML = '<p>Carregando promoções…</p>';
  try {
    const [respostaPromocoes, respostaPerfil] = await Promise.all([
      chamarApi('/promocoes/list_promocoes_oficina.php'),
      chamarApi('/oficina_auth/get_perfil_oficina.php'),
    ]);
    cachePromocoes = respostaPromocoes.promocoes;
    cacheServicosProprios = respostaPerfil.oficina.servicos; // [{id_servico, nome, preco}]
    desenharPromocoes();
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar as promoções: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharPromocoes() {
  const linhas = cachePromocoes.map((p) => {
    const rotulo = rotulosStatus[p.status];
    return `
      <div class="avaliacao" data-id="${p.id_promocao}">
        <div class="avaliacao__corpo">
          <div class="avaliacao__cabecalho">
            <span class="avaliacao__nome">${escapeHtml(p.titulo)}</span>
            <span style="color:${rotulo.cor}; font-weight:600; font-size:0.85rem;">${rotulo.texto}</span>
          </div>
          <p class="avaliacao__comentario">${escapeHtml(p.descricao || '')}</p>
          <p style="font-size:0.85rem; color: var(--texto-secundario); margin-top:6px;">
            ${formatarDataBR(p.data_inicio)} até ${formatarDataBR(p.data_fim)}
            ${p.nome_servico ? ' · Serviço: ' + escapeHtml(p.nome_servico) : ''}
          </p>
        </div>
        <div style="display:flex; gap:8px;">
          <button type="button" class="botao botao--secundario botao-editar-promocao" data-id="${p.id_promocao}">Editar</button>
          <button type="button" class="botao botao--perigo botao-excluir-promocao" data-id="${p.id_promocao}">Excluir</button>
        </div>
      </div>
    `;
  }).join('');
  painel.innerHTML = `
    <button type="button" id="botao-nova-promocao" class="botao botao--primario" style="margin-bottom:16px;">+ Nova promoção</button>
    <div id="form-promocao-container"></div>
    ${cachePromocoes.length
      ? `<div class="lista-avaliacoes">${linhas}</div>`
      : '<div class="estado-vazio"><h3>Nenhuma promoção cadastrada</h3><p>Divulgue uma oferta temporária para atrair mais clientes.</p></div>'}
  `;
  document.getElementById('botao-nova-promocao').addEventListener('click', () => abrirFormularioPromocao());
  document.querySelectorAll('.botao-editar-promocao').forEach((b) => {
    b.addEventListener('click', () => {
      const promocao = cachePromocoes.find((p) => p.id_promocao === Number(b.dataset.id));
      abrirFormularioPromocao(promocao);
    });
  });
  document.querySelectorAll('.botao-excluir-promocao').forEach((b) => {
    b.addEventListener('click', () => excluirPromocao(Number(b.dataset.id)));
  });
}
function formatarDataBR(dataIso) {
  const [ano, mes, dia] = dataIso.split('-');
  return `${dia}/${mes}/${ano}`;
}
function abrirFormularioPromocao(promocaoExistente = null) {
  const container = document.getElementById('form-promocao-container');
  const opcoesServico = cacheServicosProprios.map((s) => `
    <option value="${s.id_servico}" ${promocaoExistente?.id_servico === s.id_servico ? 'selected' : ''}>${escapeHtml(s.nome)}</option>
  `).join('');
  container.innerHTML = `
    <form id="form-promocao" class="cartao-formulario" style="margin-bottom:16px;">
      <h3>${promocaoExistente ? 'Editar promoção' : 'Nova promoção'}</h3>
      <p id="erro-promocao" class="mensagem-erro"></p>
      <div class="campo-formulario">
        <label for="input-titulo-promocao">Título</label>
        <input type="text" id="input-titulo-promocao" required maxlength="150"
          value="${escapeHtml(promocaoExistente?.titulo || '')}" placeholder="Ex: 10% de desconto em revisão">
      </div>
      <div class="campo-formulario">
        <label for="input-descricao-promocao">Descrição (opcional)</label>
        <input type="text" id="input-descricao-promocao" value="${escapeHtml(promocaoExistente?.descricao || '')}">
      </div>
      <div class="campo-formulario">
        <label for="input-data-inicio-promocao">Início</label>
        <input type="date" id="input-data-inicio-promocao" required value="${promocaoExistente?.data_inicio || ''}">
      </div>
      <div class="campo-formulario">
        <label for="input-data-fim-promocao">Fim</label>
        <input type="date" id="input-data-fim-promocao" required value="${promocaoExistente?.data_fim || ''}">
      </div>
      <div class="campo-formulario">
        <label for="select-servico-promocao">Vincular a um serviço (opcional)</label>
        <select id="select-servico-promocao">
          <option value="">Nenhum — promoção geral</option>
          ${opcoesServico}
        </select>
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="botao botao--primario">Salvar</button>
        <button type="button" id="botao-cancelar-promocao" class="botao botao--secundario">Cancelar</button>
      </div>
    </form>
  `;
  document.getElementById('botao-cancelar-promocao').addEventListener('click', () => { container.innerHTML = ''; });
  document.getElementById('form-promocao').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const corpo = {
      titulo: document.getElementById('input-titulo-promocao').value.trim(),
      descricao: document.getElementById('input-descricao-promocao').value.trim(),
      data_inicio: document.getElementById('input-data-inicio-promocao').value,
      data_fim: document.getElementById('input-data-fim-promocao').value,
      id_servico: document.getElementById('select-servico-promocao').value || null,
    };
    const erroEl = document.getElementById('erro-promocao');
    try {
      if (promocaoExistente) {
        corpo.id_promocao = promocaoExistente.id_promocao;
        await chamarApi('/promocoes/update_promocao.php', { method: 'POST', body: JSON.stringify(corpo) });
      } else {
        await chamarApi('/promocoes/create_promocao.php', { method: 'POST', body: JSON.stringify(corpo) });
      }
      renderizarPromocoes();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
async function excluirPromocao(idPromocao) {
  if (!confirm('Excluir esta promoção?')) return;
  try {
    await chamarApi('/promocoes/delete_promocao.php', { method: 'POST', body: JSON.stringify({ id_promocao: idPromocao }) });
    renderizarPromocoes();
  } catch (erro) {
    alert('Não foi possível excluir: ' + erro.message);
  }
}
/* =========================================================
   Aba: Agendamentos (aceitar, recusar, concluir)
   ========================================================= */
const rotulosStatusAgendamentoOficina = {
  pendente: { texto: 'Pendente', cor: '#E8A33D' },
  aceito: { texto: 'Aceito', cor: 'var(--verde-sucesso)' },
  recusado: { texto: 'Recusado', cor: '#C0392B' },
  cancelado: { texto: 'Cancelado pelo cliente', cor: 'var(--texto-secundario)' },
  concluido: { texto: 'Concluído', cor: 'var(--azul-mecon)' },
};
async function renderizarAgendamentosOficina() {
  painel.innerHTML = '<p>Carregando agendamentos…</p>';
  try {
    const resposta = await chamarApi('/agendamentos/list_agendamentos_oficina.php');
    if (resposta.agendamentos.length === 0) {
      painel.innerHTML = `
        <div class="estado-vazio">
          <h3>Nenhuma solicitação de agendamento ainda</h3>
        </div>
      `;
      return;
    }
    painel.innerHTML = `<div class="lista-avaliacoes">${resposta.agendamentos.map(desenharAgendamentoOficina).join('')}</div>`;
    ligarEventosAgendamentoOficina();
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar os agendamentos: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharAgendamentoOficina(ag) {
  const rotulo = rotulosStatusAgendamentoOficina[ag.status];
  const dataFormatada = new Date(ag.data_hora_solicitada.replace(' ', 'T')).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
  let acoes = '';
  if (ag.status === 'pendente') {
    acoes = `
      <button type="button" class="botao botao--primario botao-aceitar-agendamento" data-id="${ag.id_agendamento}">Aceitar</button>
      <button type="button" class="botao botao--perigo botao-recusar-agendamento" data-id="${ag.id_agendamento}">Recusar</button>
    `;
  } else if (ag.status === 'aceito') {
    acoes = ag.id_os
      ? `<button type="button" class="botao botao--primario botao-abrir-editor-os" data-id="${ag.id_agendamento}">Ordem de serviço · ${rotulosEtapaOS[ag.etapa_os]}</button>`
      : `<button type="button" class="botao botao--primario botao-abrir-os" data-id="${ag.id_agendamento}">Abrir ordem de serviço</button>
         <button type="button" class="botao botao--secundario botao-concluir-agendamento" data-id="${ag.id_agendamento}" data-valor="${ag.valor_orcamento ?? ''}">Concluir sem OS</button>`;
  } else if (ag.status === 'concluido' && ag.id_os) {
    acoes = `<a class="botao botao--secundario" target="_blank" href="ordem-servico.html?como=oficina&id_agendamento=${ag.id_agendamento}">Ver ordem de serviço</a>`;
  }
  return `
    <div class="avaliacao" data-id="${ag.id_agendamento}">
      <div class="avaliacao__corpo">
        <div class="avaliacao__cabecalho">
          <span class="avaliacao__nome">${escapeHtml(ag.nome_usuario)} — ${escapeHtml(ag.nome_servico)}</span>
          <span style="color:${rotulo.cor}; font-weight:600; font-size:0.85rem;">${rotulo.texto}</span>
        </div>
        <p class="avaliacao__comentario">Veículo ${escapeHtml(ag.placa)} · ${dataFormatada}</p>
        ${ag.mensagem_usuario ? `<p style="font-size:0.85rem; color: var(--texto-secundario);">Mensagem: "${escapeHtml(ag.mensagem_usuario)}"</p>` : ''}
        ${ag.valor_orcamento !== null ? `<p style="font-size:0.8rem; color: var(--texto-secundario);">Veio de um orçamento aprovado: ${formatarReaisCRM(ag.valor_orcamento)}</p>` : ''}
        ${ag.status === 'concluido' && ag.valor_total !== null ? `<p style="font-size:0.85rem; color: var(--verde-sucesso);">Valor cobrado: ${ag.valor_total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</p>` : ''}
        <div class="bloco-concluir-agendamento"></div>
        <div class="bloco-os"></div>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">${acoes}</div>
    </div>
  `;
}
function ligarEventosAgendamentoOficina() {
  document.querySelectorAll('.botao-aceitar-agendamento').forEach((b) => {
    b.addEventListener('click', () => responderAgendamento(Number(b.dataset.id), 'aceitar'));
  });
  document.querySelectorAll('.botao-recusar-agendamento').forEach((b) => {
    b.addEventListener('click', () => responderAgendamento(Number(b.dataset.id), 'recusar'));
  });
  document.querySelectorAll('.botao-concluir-agendamento').forEach((b) => {
    b.addEventListener('click', () => concluirAgendamento(Number(b.dataset.id), b));
  });
  document.querySelectorAll('.botao-abrir-os').forEach((b) => {
    b.addEventListener('click', () => abrirNovaOS(Number(b.dataset.id), b));
  });
  document.querySelectorAll('.botao-abrir-editor-os').forEach((b) => {
    b.addEventListener('click', () => abrirEditorOS(Number(b.dataset.id), b));
  });
}
async function responderAgendamento(idAgendamento, acao) {
  let resposta = '';
  if (acao === 'recusar') {
    resposta = prompt('Motivo da recusa (opcional):') || '';
  }
  try {
    await chamarApi('/agendamentos/responder_agendamento.php', {
      method: 'POST',
      body: JSON.stringify({ id_agendamento: idAgendamento, acao, resposta }),
    });
    renderizarAgendamentosOficina();
  } catch (erro) {
    alert('Não foi possível responder: ' + erro.message);
  }
}
function concluirAgendamento(idAgendamento, botaoEl) {
  const cartao = botaoEl.closest('.avaliacao');
  const bloco = cartao.querySelector('.bloco-concluir-agendamento');
  botaoEl.style.display = 'none';
  bloco.innerHTML = `
    <form class="form-concluir" style="margin-top:10px; border-top:1px solid var(--borda); padding-top:10px;">
      <p style="font-size:0.85rem; font-weight:600; margin-bottom:6px;">Concluir serviço — registro no histórico do cliente</p>
      <div style="display:flex; gap:10px;">
        <div class="campo-formulario" style="flex:1;">
          <label>Valor total cobrado em R$ (opcional)</label>
          <input type="number" class="input-concluir-total" min="0" step="0.01">
        </div>
        <div class="campo-formulario" style="flex:1;">
          <label>Mão de obra em R$ (opcional)</label>
          <input type="number" class="input-concluir-mao" min="0" step="0.01">
        </div>
      </div>
      ${montarEditorPecasHTML([])}
      <p class="mensagem-erro erro-concluir"></p>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Concluir</button>
        <button type="button" class="botao botao--secundario botao-cancelar-concluir" style="padding:6px 14px; font-size:0.85rem;">Cancelar</button>
      </div>
    </form>
  `;
  ligarEditorPecas(bloco);
  if (botaoEl.dataset.valor) bloco.querySelector('.input-concluir-total').value = botaoEl.dataset.valor; // valor do orçamento aprovado
  bloco.querySelector('.botao-cancelar-concluir').addEventListener('click', () => {
    bloco.innerHTML = '';
    botaoEl.style.display = '';
  });
  bloco.querySelector('.form-concluir').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const total = bloco.querySelector('.input-concluir-total').value;
    const mao = bloco.querySelector('.input-concluir-mao').value;
    const erroEl = bloco.querySelector('.erro-concluir');
    try {
      await chamarApi('/agendamentos/concluir_agendamento.php', {
        method: 'POST',
        body: JSON.stringify({
          id_agendamento: idAgendamento,
          valor_total: total !== '' ? Number(total) : null,
          valor_mao_obra: mao !== '' ? Number(mao) : null,
          pecas: lerPecasDoEditor(bloco),
        }),
      });
      renderizarAgendamentosOficina();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}

/* ---------- Ordem de serviço (editor inline no card do agendamento) ---------- */
const rotulosEtapaOS = {
  aberta: 'Aberta',
  em_execucao: 'Em execução',
  aguardando_peca: 'Aguardando peça',
  pronta: 'Pronta',
  entregue: 'Concluída',
};
function abrirNovaOS(idAgendamento, botaoEl) {
  const bloco = botaoEl.closest('.avaliacao').querySelector('.bloco-os');
  bloco.innerHTML = `
    <form class="form-nova-os" style="margin-top:10px; border-top:1px solid var(--borda); padding-top:10px;">
      <p style="font-size:0.85rem; font-weight:600; margin:0 0 6px;">Abrir ordem de serviço</p>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <div class="campo-formulario" style="flex:1 1 180px;"><label>Responsável (opcional)</label>
          <input type="text" class="nova-os-responsavel" maxlength="100" placeholder="Ex: Carlos"></div>
        <div class="campo-formulario" style="flex:1 1 140px;"><label>Km na entrada (opcional)</label>
          <input type="number" class="nova-os-km" min="0" step="1"></div>
      </div>
      <div class="campo-formulario"><label>Diagnóstico inicial (opcional)</label>
        <input type="text" class="nova-os-diagnostico" maxlength="5000"></div>
      <p class="mensagem-erro erro-nova-os"></p>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Abrir</button>
        <button type="button" class="botao botao--secundario nova-os-cancelar" style="padding:6px 14px; font-size:0.85rem;">Cancelar</button>
      </div>
    </form>`;
  bloco.querySelector('.nova-os-cancelar').addEventListener('click', () => { bloco.innerHTML = ''; });
  bloco.querySelector('.form-nova-os').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const erroEl = bloco.querySelector('.erro-nova-os');
    try {
      await chamarApi('/ordens_servico/abrir_os.php', {
        method: 'POST',
        body: JSON.stringify({
          id_agendamento: idAgendamento,
          responsavel: bloco.querySelector('.nova-os-responsavel').value.trim(),
          km_entrada: bloco.querySelector('.nova-os-km').value,
          diagnostico: bloco.querySelector('.nova-os-diagnostico').value.trim(),
        }),
      });
      await renderizarAgendamentosOficina();
      const botaoEditor = document.querySelector(`.avaliacao[data-id="${idAgendamento}"] .botao-abrir-editor-os`);
      if (botaoEditor) botaoEditor.click(); // já abre o editor da OS recém-criada
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
async function abrirEditorOS(idAgendamento, botaoEl) {
  const bloco = botaoEl.closest('.avaliacao').querySelector('.bloco-os');
  if (bloco.innerHTML.trim() !== '') { bloco.innerHTML = ''; return; } // segundo clique fecha
  bloco.innerHTML = '<p>Carregando…</p>';
  try {
    const { agendamento: ag, os } = await chamarApi(`/ordens_servico/get_os.php?como=oficina&id_agendamento=${idAgendamento}`);
    const v = (x) => (x !== null && x !== undefined ? x : '');
    bloco.innerHTML = `
      <form class="form-editor-os" style="margin-top:10px; border-top:1px solid var(--borda); padding-top:10px;">
        <p style="font-size:0.85rem; font-weight:600; margin:0 0 8px;">Ordem de serviço nº ${os.id_os}</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <div class="campo-formulario" style="flex:1 1 160px;"><label>Etapa</label>
            <select class="os-etapa">${['aberta', 'em_execucao', 'aguardando_peca', 'pronta'].map((e) => `<option value="${e}" ${os.etapa === e ? 'selected' : ''}>${rotulosEtapaOS[e]}</option>`).join('')}</select></div>
          <div class="campo-formulario" style="flex:1 1 160px;"><label>Responsável</label>
            <input type="text" class="os-responsavel" maxlength="100" value="${escapeHtml(os.responsavel || '')}"></div>
          <div class="campo-formulario" style="flex:1 1 130px;"><label>Km na entrada</label>
            <input type="number" class="os-km" min="0" step="1" value="${v(os.km_entrada)}"></div>
        </div>
        <div class="campo-formulario"><label>Diagnóstico</label>
          <textarea class="os-diagnostico" rows="3" maxlength="5000" style="width:100%; padding:8px 10px; border:1px solid var(--borda); border-radius: var(--raio-botao); font-family: var(--fonte-corpo);">${escapeHtml(os.diagnostico || '')}</textarea></div>
        ${montarEditorPecasHTML(os.itens)}
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <div class="campo-formulario" style="flex:1 1 150px;"><label>Mão de obra (R$)</label>
            <input type="number" class="os-mao" min="0" step="0.01" value="${v(os.totais.valor_mao_obra)}"></div>
          <div class="campo-formulario" style="flex:1 1 150px;"><label>Desconto (R$)</label>
            <input type="number" class="os-desconto" min="0" step="0.01" value="${v(os.totais.desconto)}"></div>
        </div>
        <div class="campo-formulario"><label>Observações (visíveis ao cliente)</label>
          <input type="text" class="os-observacoes" maxlength="1000" value="${escapeHtml(os.observacoes || '')}"></div>
        <p class="os-total-vivo" style="font-weight:700; color: var(--azul-mecon); margin:6px 0;"></p>
        ${ag.valor_orcamento !== null ? `<p style="font-size:0.8rem; color: var(--texto-secundario); margin:0 0 6px;">Orçamento aprovado: ${formatarReaisCRM(ag.valor_orcamento)}</p>` : ''}
        <p class="mensagem-erro erro-editor-os"></p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Salvar</button>
          <button type="button" class="botao botao--primario os-concluir" style="padding:6px 14px; font-size:0.85rem; background: var(--verde-sucesso);">Salvar e concluir serviço</button>
          <a class="botao botao--secundario" target="_blank" href="ordem-servico.html?como=oficina&id_agendamento=${idAgendamento}" style="padding:6px 14px; font-size:0.85rem;">Imprimir</a>
        </div>
      </form>`;
    const form = bloco.querySelector('.form-editor-os');
    ligarEditorPecas(form);
    const erroEl = form.querySelector('.erro-editor-os');

    const atualizarTotal = () => {
      const pecas = lerPecasDoEditor(form).reduce((soma, p) => soma + (p.valor_unitario !== null ? p.quantidade * p.valor_unitario : 0), 0);
      const mao = Number(form.querySelector('.os-mao').value) || 0;
      const desc = Number(form.querySelector('.os-desconto').value) || 0;
      form.querySelector('.os-total-vivo').textContent =
        `Total: ${formatarReaisGeral(Math.max(0, pecas + mao - desc))} (peças ${formatarReaisGeral(pecas)} + mão de obra ${formatarReaisGeral(mao)} − desconto ${formatarReaisGeral(desc)})`;
    };
    form.addEventListener('input', atualizarTotal);
    form.addEventListener('click', (e) => { if (e.target.classList.contains('botao-remover-peca') || e.target.classList.contains('botao-adicionar-peca')) setTimeout(atualizarTotal, 0); });
    atualizarTotal();

    const salvar = async () => {
      erroEl.classList.remove('mensagem-erro--visivel');
      await chamarApi('/ordens_servico/atualizar_os.php', {
        method: 'POST',
        body: JSON.stringify({
          id_os: os.id_os,
          etapa: form.querySelector('.os-etapa').value,
          responsavel: form.querySelector('.os-responsavel').value.trim(),
          km_entrada: form.querySelector('.os-km').value,
          diagnostico: form.querySelector('.os-diagnostico').value.trim(),
          observacoes: form.querySelector('.os-observacoes').value.trim(),
          valor_mao_obra: form.querySelector('.os-mao').value,
          desconto: form.querySelector('.os-desconto').value,
          pecas: lerPecasDoEditor(form),
        }),
      });
    };
    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      try {
        await salvar();
        renderizarAgendamentosOficina();
      } catch (erro) {
        erroEl.textContent = erro.message;
        erroEl.classList.add('mensagem-erro--visivel');
      }
    });
    form.querySelector('.os-concluir').addEventListener('click', async () => {
      if (form.querySelector('.os-etapa').value !== 'pronta') {
        erroEl.textContent = 'Para concluir, marque a etapa como "Pronta".';
        erroEl.classList.add('mensagem-erro--visivel');
        return;
      }
      if (!confirm('Concluir o serviço? Isso fecha a ordem de serviço e registra tudo no histórico do cliente.')) return;
      try {
        await salvar();
        await chamarApi('/ordens_servico/concluir_os.php', { method: 'POST', body: JSON.stringify({ id_os: os.id_os }) });
        renderizarAgendamentosOficina();
      } catch (erro) {
        erroEl.textContent = erro.message;
        erroEl.classList.add('mensagem-erro--visivel');
      }
    });
  } catch (erro) {
    bloco.innerHTML = `<p>Não foi possível carregar a ordem de serviço: ${escapeHtml(erro.message)}</p>`;
  }
}

/* =========================================================
   Aba: Clientes (mini-CRM — só quem agendou com esta oficina)
   ========================================================= */
let filtroClientes = { busca: '', recontatar: false };
let timeoutBuscaClientes = null;
function formatarReaisCRM(valor) {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
function formatarDataHoraCRM(dataIso) {
  return new Date(dataIso.replace(' ', 'T')).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}
async function renderizarClientes() {
  painel.innerHTML = `
    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
      <input type="search" id="input-busca-cliente" placeholder="Buscar por nome ou email" value="${escapeHtml(filtroClientes.busca)}"
        style="flex:1; min-width:200px; padding:8px 12px; border:1px solid var(--borda); border-radius: var(--raio-botao);">
      <label class="campo-pill" style="gap:8px;">
        <input type="checkbox" id="input-recontatar" ${filtroClientes.recontatar ? 'checked' : ''}>
        Só quem está na hora de voltar
      </label>
    </div>
    <div id="lista-clientes"></div>
    <div id="paginacao-clientes"></div>
  `;
  document.getElementById('input-busca-cliente').addEventListener('input', (evento) => {
    clearTimeout(timeoutBuscaClientes);
    timeoutBuscaClientes = setTimeout(() => {
      filtroClientes.busca = evento.target.value.trim();
      carregarClientes(1);
    }, 300);
  });
  document.getElementById('input-recontatar').addEventListener('change', (evento) => {
    filtroClientes.recontatar = evento.target.checked;
    carregarClientes(1);
  });
  carregarClientes(1);
}
async function carregarClientes(pagina = 1) {
  const lista = document.getElementById('lista-clientes');
  lista.innerHTML = '<p>Carregando clientes…</p>';
  try {
    const parametros = new URLSearchParams({ pagina: String(pagina) });
    if (filtroClientes.busca) parametros.set('busca', filtroClientes.busca);
    if (filtroClientes.recontatar) parametros.set('recontatar', '1');
    const resposta = await chamarApi(`/oficina_auth/list_clientes.php?${parametros.toString()}`);
    if (resposta.clientes.length === 0) {
      lista.innerHTML = `<div class="estado-vazio"><h3>${filtroClientes.busca || filtroClientes.recontatar ? 'Nenhum cliente encontrado' : 'Você ainda não tem clientes'}</h3><p>Clientes aparecem aqui depois de solicitarem um agendamento com a sua oficina.</p></div>`;
      document.getElementById('paginacao-clientes').innerHTML = '';
      return;
    }
    lista.innerHTML = `<div class="lista-avaliacoes">${resposta.clientes.map(desenharClienteCRM).join('')}</div>`;
    lista.querySelectorAll('.botao-ficha-cliente').forEach((b) => {
      b.addEventListener('click', () => alternarFichaCliente(Number(b.dataset.id), b));
    });
    renderizarControlesPaginacao(document.getElementById('paginacao-clientes'), resposta.paginacao, carregarClientes);
  } catch (erro) {
    lista.innerHTML = `<p>Não foi possível carregar os clientes: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharRetornosCRM(retornos) {
  return retornos.map((r) => `
    <span class="tag-servico" style="${r.status === 'atrasado' ? 'background:#FDECEA; color:#C0392B;' : 'background:#FFF4E5; color:#B7791F;'}">
      ↺ ${escapeHtml(r.servico)} (${escapeHtml(r.placa)}) — ${r.meses_desde} meses, recomendado a cada ${r.intervalo}
    </span>
  `).join('');
}
function desenharClienteCRM(c) {
  return `
    <div class="avaliacao" style="flex-direction:column; align-items:stretch;" data-cliente="${c.id_usuario}">
      <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
        <div>
          <span class="avaliacao__nome">${escapeHtml(c.nome)}</span>
          <p style="font-size:0.85rem; color: var(--texto-secundario); margin:2px 0 0;">
            <a href="mailto:${escapeHtml(c.email)}">${escapeHtml(c.email)}</a>
          </p>
        </div>
        <button type="button" class="botao botao--secundario botao-ficha-cliente" data-id="${c.id_usuario}" style="padding:6px 14px; font-size:0.85rem;">Ver ficha</button>
      </div>
      <p style="font-size:0.85rem; margin:8px 0 0;">
        ${c.concluidos} serviço(s) concluído(s) · ${formatarReaisCRM(c.total_gasto)}
        ${c.ultima_visita ? ` · última visita ${formatarDataHoraCRM(c.ultima_visita)}` : ''}
        ${c.proximo_agendamento ? ` · <strong>próximo agendamento ${formatarDataHoraCRM(c.proximo_agendamento)}</strong>` : ''}
      </p>
      ${c.veiculos.length ? `<p style="font-size:0.8rem; color: var(--texto-secundario); margin:4px 0 0;">Veículos: ${c.veiculos.map(escapeHtml).join(', ')}</p>` : ''}
      ${c.retornos_sugeridos.length ? `<div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">${desenharRetornosCRM(c.retornos_sugeridos)}</div>` : ''}
      <div class="ficha-cliente" style="display:none; margin-top:12px; border-top:1px solid var(--borda); padding-top:10px;"></div>
    </div>
  `;
}
async function alternarFichaCliente(idUsuario, botaoEl) {
  const ficha = botaoEl.closest('.avaliacao').querySelector('.ficha-cliente');
  if (ficha.style.display !== 'none') {
    ficha.style.display = 'none';
    botaoEl.textContent = 'Ver ficha';
    return;
  }
  ficha.style.display = 'block';
  botaoEl.textContent = 'Ocultar ficha';
  ficha.innerHTML = '<p>Carregando…</p>';
  try {
    const { cliente } = await chamarApi(`/oficina_auth/get_cliente.php?id_usuario=${idUsuario}`);
    const rotulos = { pendente: 'Pendente', aceito: 'Aceito', recusado: 'Recusado', cancelado: 'Cancelado', concluido: 'Concluído' };
    ficha.innerHTML = `
      ${cliente.servicos_realizados.length ? `<p style="font-size:0.85rem; margin:0 0 8px;"><strong>Serviços realizados:</strong> ${cliente.servicos_realizados.map((s) => `${escapeHtml(s.nome)} (${s.vezes}×)`).join(', ')}</p>` : ''}
      <p style="font-size:0.85rem; font-weight:600; margin:0 0 4px;">Histórico de agendamentos</p>
      ${cliente.agendamentos.map((a) => `
        <p style="font-size:0.85rem; margin:2px 0;">
          ${formatarDataHoraCRM(a.data_hora_solicitada)} — ${escapeHtml(a.nome_servico)} (${escapeHtml(a.placa)}) ·
          <em>${rotulos[a.status]}</em>${a.valor_total !== null ? ` · ${formatarReaisCRM(a.valor_total)}` : ''}
        </p>
      `).join('')}
    `;
  } catch (erro) {
    ficha.innerHTML = `<p>Não foi possível carregar a ficha: ${escapeHtml(erro.message)}</p>`;
  }
}
/* =========================================================
   Aba: Financeiro (faturamento, ticket médio, serviços, picos)
   ========================================================= */
let periodoFinanceiro = '6m';
function barraHorizontalFin(rotulo, valor, maximo, textoValor) {
  const largura = maximo > 0 ? Math.round((valor / maximo) * 100) : 0;
  return `
    <div style="display:flex; align-items:center; gap:8px; margin:4px 0; font-size:0.85rem;">
      <span style="width:110px; flex-shrink:0;">${rotulo}</span>
      <div style="flex:1; background:#eee; border-radius:6px; height:14px; overflow:hidden;">
        <div style="width:${largura}%; background: var(--azul-mecon); height:100%;"></div>
      </div>
      <span style="width:90px; text-align:right; color: var(--texto-secundario);">${textoValor}</span>
    </div>`;
}
async function renderizarFinanceiro() {
  painel.innerHTML = '<p>Carregando relatório…</p>';
  try {
    const r = await chamarApi(`/oficina_auth/relatorio_financeiro.php?periodo=${periodoFinanceiro}`);
    desenharFinanceiro(r);
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar o relatório: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharFinanceiro(r) {
  const t = r.totais;
  const reais = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const opcoes = { '30d': 'Últimos 30 dias', '90d': 'Últimos 90 dias', '6m': 'Últimos 6 meses', '12m': 'Últimos 12 meses', tudo: 'Todo o período' };
  const variacao = t.variacao_pct === null ? '' : `
    <div style="font-size:0.78rem; color:${t.variacao_pct >= 0 ? 'var(--verde-sucesso)' : '#C0392B'};">
      ${t.variacao_pct >= 0 ? '▲' : '▼'} ${Math.abs(t.variacao_pct).toLocaleString('pt-BR')}% vs período anterior
    </div>`;
  const cartao = (rotulo, valor, extra = '') => `
    <div style="flex:1 1 160px; background: var(--lilas-claro); border-radius: var(--raio-botao); padding:12px; text-align:center;">
      <div style="font-size:1.3rem; font-weight:700; color: var(--azul-mecon);">${valor}</div>
      <div style="font-size:0.78rem; color: var(--texto-secundario);">${rotulo}</div>${extra}
    </div>`;

  const maxMes = Math.max(0, ...r.por_mes.map((m) => m.total));
  const nomesMes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  const barrasMes = r.por_mes.map((m) => {
    const [ano, mes] = m.mes.split('-');
    const altura = maxMes > 0 ? Math.max(Math.round((m.total / maxMes) * 100), m.total > 0 ? 4 : 0) : 0;
    return `
      <div title="${nomesMes[Number(mes) - 1]}/${ano}: ${reais(m.total)} (${m.qtd} serviço(s))" style="flex:1; min-width:28px; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:140px;">
        <div style="width:70%; height:${altura}%; background: var(--azul-mecon); border-radius:4px 4px 0 0;"></div>
        <span style="font-size:0.68rem; color: var(--texto-secundario); margin-top:4px;">${nomesMes[Number(mes) - 1]}</span>
      </div>`;
  }).join('');

  const linhasServico = r.por_servico.map((s) => `
    <tr>
      <td style="padding:6px 10px 6px 0;">${escapeHtml(s.servico)}</td>
      <td style="padding:6px 10px;">${s.qtd}</td>
      <td style="padding:6px 10px;">${reais(s.faturamento)}</td>
      <td style="padding:6px 0;">${s.ticket_medio !== null ? reais(s.ticket_medio) : '—'}</td>
    </tr>`).join('');

  const diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
  const maxDia = Math.max(0, ...r.picos.por_dia_semana);
  const barrasDia = r.picos.por_dia_semana.map((q, i) => barraHorizontalFin(diasSemana[i], q, maxDia, `${q} agend.`)).join('');
  const horasComMovimento = r.picos.por_hora.map((q, h) => ({ q, h })).filter((x) => x.q > 0);
  const maxHora = Math.max(0, ...r.picos.por_hora);
  const barrasHora = horasComMovimento.map((x) => barraHorizontalFin(`${String(x.h).padStart(2, '0')}h`, x.q, maxHora, `${x.q} agend.`)).join('');

  painel.innerHTML = `
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
      <h3 style="margin:0;">Relatório financeiro</h3>
      <select id="select-periodo-financeiro" style="padding:6px 10px;">
        ${Object.entries(opcoes).map(([v, rot]) => `<option value="${v}" ${v === r.periodo ? 'selected' : ''}>${rot}</option>`).join('')}
      </select>
    </div>
    ${t.concluidos_sem_valor > 0 ? `
      <p style="background:#FFF4E5; border-left:3px solid #E8A33D; padding:8px 12px; border-radius: var(--raio-botao); font-size:0.85rem;">
        ${t.concluidos_sem_valor} serviço(s) concluído(s) não tiveram valor informado e <strong>não entram</strong> no faturamento nem no ticket médio.
        Informe o valor ao concluir um agendamento para o relatório ficar completo.
      </p>` : ''}
    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
      ${cartao('Faturamento', reais(t.faturamento), variacao)}
      ${cartao('Serviços concluídos', t.servicos_concluidos)}
      ${cartao('Ticket médio', t.ticket_medio !== null ? reais(t.ticket_medio) : '—')}
      ${cartao('Clientes atendidos', t.clientes_atendidos, `<div style="font-size:0.78rem; color: var(--texto-secundario);">${t.clientes_recorrentes} recorrente(s)</div>`)}
    </div>
    <h4>Faturamento por mês</h4>
    <div style="display:flex; align-items:flex-end; gap:4px; margin-bottom:24px; border-bottom:1px solid var(--borda);">${barrasMes}</div>
    <h4>Serviços mais realizados</h4>
    ${r.por_servico.length ? `
      <table style="width:100%; border-collapse:collapse; font-size:0.88rem; margin-bottom:24px;">
        <thead><tr style="text-align:left; color: var(--texto-secundario);"><th>Serviço</th><th>Qtd</th><th>Faturamento</th><th>Ticket médio</th></tr></thead>
        <tbody>${linhasServico}</tbody>
      </table>` : '<p style="color: var(--texto-secundario);">Nenhum serviço concluído no período.</p>'}
    <h4>Dias de maior movimento</h4>
    <p style="font-size:0.8rem; color: var(--texto-secundario); margin:-6px 0 8px;">Agendamentos aceitos e concluídos no período.</p>
    ${maxDia > 0 ? barrasDia : '<p style="color: var(--texto-secundario);">Sem dados no período.</p>'}
    <h4 style="margin-top:20px;">Horários de maior movimento</h4>
    ${maxHora > 0 ? barrasHora : '<p style="color: var(--texto-secundario);">Sem dados no período.</p>'}
  `;
  document.getElementById('select-periodo-financeiro').addEventListener('change', (evento) => {
    periodoFinanceiro = evento.target.value;
    renderizarFinanceiro();
  });
}
/* =========================================================
   Aba: Orçamentos (pedidos recebidos — ver, responder ou recusar)
   ========================================================= */
const rotulosStatusOrcOficina = {
  pendente: { texto: 'Aguardando sua resposta', cor: '#E8A33D' },
  respondido: { texto: 'Enviado ao cliente', cor: 'var(--azul-mecon)' },
  aprovado: { texto: 'Aprovado pelo cliente', cor: 'var(--verde-sucesso)' },
  nao_escolhido: { texto: 'Cliente escolheu outra oficina', cor: 'var(--texto-secundario)' },
  recusado: { texto: 'Você recusou', cor: '#C0392B' },
  cancelado: { texto: 'Cancelado', cor: 'var(--texto-secundario)' },
};
async function renderizarOrcamentosOficina() {
  painel.innerHTML = '<p>Carregando orçamentos…</p>';
  try {
    const { orcamentos } = await chamarApi('/orcamentos/list_orcamentos_oficina.php');
    if (orcamentos.length === 0) {
      painel.innerHTML = '<div class="estado-vazio"><h3>Nenhum pedido de orçamento recebido</h3></div>';
      return;
    }
    painel.innerHTML = `<div class="lista-avaliacoes">${orcamentos.map((o) => {
      const st = rotulosStatusOrcOficina[o.status];
      return `
        <div class="avaliacao" style="flex-direction:column; align-items:stretch;">
          <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
            <div>
              <span class="avaliacao__nome">${escapeHtml(o.nome_cliente)} — ${escapeHtml(o.nome_servico || 'Problema descrito')} (${escapeHtml(o.placa)})</span>
              <p style="font-size:0.85rem; color: var(--texto-secundario); margin:2px 0 0;">${escapeHtml(o.descricao.length > 110 ? o.descricao.slice(0, 110) + '…' : o.descricao)}</p>
              <p style="font-size:0.8rem; color: var(--texto-secundario); margin:4px 0 0;">
                ${formatarDataISO(o.criado_em)}${o.total_anexos ? ` · ${o.total_anexos} anexo(s)` : ''}${o.valor_total !== null ? ` · seu valor: ${formatarReaisGeral(o.valor_total)}` : ''}
              </p>
            </div>
            <div style="text-align:right;">
              <span style="color:${st.cor}; font-weight:600; font-size:0.85rem;">${st.texto}</span><br>
              <button type="button" class="botao botao--secundario botao-abrir-orcamento" data-id="${o.id_orcamento}" style="margin-top:6px; padding:6px 14px; font-size:0.85rem;">Abrir</button>
            </div>
          </div>
          <div class="detalhe-orcamento" style="display:none; margin-top:12px; border-top:1px solid var(--borda); padding-top:12px;"></div>
        </div>`;
    }).join('')}</div>`;
    painel.querySelectorAll('.botao-abrir-orcamento').forEach((b) => {
      b.addEventListener('click', () => alternarDetalheOrcamento(Number(b.dataset.id), b));
    });
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar os orçamentos: ${escapeHtml(erro.message)}</p>`;
  }
}
async function alternarDetalheOrcamento(idOrcamento, botaoEl) {
  const detalhe = botaoEl.closest('.avaliacao').querySelector('.detalhe-orcamento');
  if (detalhe.style.display !== 'none') {
    detalhe.style.display = 'none';
    botaoEl.textContent = 'Abrir';
    return;
  }
  detalhe.style.display = 'block';
  botaoEl.textContent = 'Fechar';
  detalhe.innerHTML = '<p>Carregando…</p>';
  try {
    const { orcamento: o, anexos } = await chamarApi(`/orcamentos/get_orcamento_oficina.php?id_orcamento=${idOrcamento}`);
    const editavel = o.status_pedido === 'aberto' && (o.status === 'pendente' || o.status === 'respondido');
    const v = (x) => (x !== null ? x : '');
    detalhe.innerHTML = `
      <p style="margin:0 0 4px;"><strong>Problema:</strong> ${escapeHtml(o.descricao)}</p>
      <p style="font-size:0.82rem; color: var(--texto-secundario); margin:0 0 6px;">
        Veículo ${escapeHtml(o.placa)}${o.km_atual !== null ? ` · ${o.km_atual.toLocaleString('pt-BR')} km` : ''}
      </p>
      ${montarAnexosHTML(anexos)}
      ${editavel ? `
        <form class="form-orcamento-oficina" style="margin-top:10px;">
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <div class="campo-formulario" style="flex:1 1 140px;"><label>Valor total (R$)</label>
              <input type="number" class="orc-total" min="0" step="0.01" value="${v(o.valor_total)}"></div>
            <div class="campo-formulario" style="flex:1 1 140px;"><label>Peças (R$, opcional)</label>
              <input type="number" class="orc-pecas" min="0" step="0.01" value="${v(o.valor_pecas)}"></div>
            <div class="campo-formulario" style="flex:1 1 140px;"><label>Mão de obra (R$, opcional)</label>
              <input type="number" class="orc-mao" min="0" step="0.01" value="${v(o.valor_mao_obra)}"></div>
          </div>
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <div class="campo-formulario" style="flex:1 1 140px;"><label>Prazo (dias, opcional)</label>
              <input type="number" class="orc-prazo" min="0" max="365" step="1" value="${v(o.prazo_dias)}"></div>
            <div class="campo-formulario" style="flex:1 1 160px;"><label>Válido até (padrão: 7 dias)</label>
              <input type="date" class="orc-validade" value="${o.validade_ate ?? ''}"></div>
          </div>
          <div class="campo-formulario"><label>Detalhes (opcional)</label>
            <input type="text" class="orc-detalhes" maxlength="1000" value="${escapeHtml(o.detalhes || '')}" placeholder="Ex: inclui alinhamento; peças originais"></div>
          <p class="mensagem-erro erro-orc-oficina"></p>
          <div style="display:flex; gap:8px;">
            <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">${o.status === 'respondido' ? 'Atualizar orçamento' : 'Enviar orçamento'}</button>
            <button type="button" class="botao botao--perigo orc-recusar" style="padding:6px 14px; font-size:0.85rem;">Recusar pedido</button>
          </div>
        </form>` : o.valor_total !== null ? `
        <p style="margin-top:8px;">Seu orçamento: <strong>${formatarReaisGeral(o.valor_total)}</strong>${o.prazo_dias !== null ? ` · prazo ${o.prazo_dias} dia(s)` : ''}${o.validade_ate ? ` · válido até ${formatarDataISO(o.validade_ate)}` : ''}</p>` : ''}`;
    const form = detalhe.querySelector('.form-orcamento-oficina');
    if (!form) return;
    const erroEl = form.querySelector('.erro-orc-oficina');
    const enviar = async (corpo) => {
      erroEl.classList.remove('mensagem-erro--visivel');
      try {
        await chamarApi('/orcamentos/responder_orcamento.php', { method: 'POST', body: JSON.stringify({ id_orcamento: idOrcamento, ...corpo }) });
        renderizarOrcamentosOficina();
      } catch (erro) {
        erroEl.textContent = erro.message;
        erroEl.classList.add('mensagem-erro--visivel');
      }
    };
    form.addEventListener('submit', (evento) => {
      evento.preventDefault();
      enviar({
        acao: 'enviar',
        valor_total: form.querySelector('.orc-total').value,
        valor_pecas: form.querySelector('.orc-pecas').value,
        valor_mao_obra: form.querySelector('.orc-mao').value,
        prazo_dias: form.querySelector('.orc-prazo').value,
        validade_ate: form.querySelector('.orc-validade').value,
        detalhes: form.querySelector('.orc-detalhes').value.trim(),
      });
    });
    form.querySelector('.orc-recusar').addEventListener('click', () => {
      if (confirm('Recusar este pedido de orçamento?')) enviar({ acao: 'recusar' });
    });
  } catch (erro) {
    detalhe.innerHTML = `<p>Não foi possível carregar o pedido: ${escapeHtml(erro.message)}</p>`;
  }
}
/* =========================================================
   Inicialização
   ========================================================= */
if (oficinaSessao) {
  monitorarMensagensNaoLidas('oficina');
  selecionarAba('desempenho');
}
