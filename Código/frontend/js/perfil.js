/**
 * MecOn - perfil.js
 * Página de perfil do usuário logado, organizada em 4 abas:
 *  - veiculo      -> CRUD de veículos (placa); cada veículo tem um botão
 *                    "Ver histórico" que expande o histórico de manutenção
 *                    DAQUELE veículo específico (histórico pertence ao
 *                    veículo, não ao usuário em geral)
 *  - favoritos    -> lista de oficinas favoritas (somente leitura + ir para busca)
 *  - comentarios  -> avaliações feitas pelo usuário (somente leitura, imutáveis)
 *  - conta        -> editar nome/email/senha
 *
 * Exige login: usuários deslogados são redirecionados para login.html.
 */
const usuario = exigirLoginUI();
const painel = document.getElementById('painel-abas');
const abas = document.querySelectorAll('.perfil-aba');
function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}
if (usuario) {
  document.getElementById('texto-saudacao').textContent = `Bem-vindo(a), ${usuario.nome}!`;
  if (usuario.email_verificado === false) {
    exibirAvisoEmailNaoVerificado();
  }
  if (usuario.termos_pendentes) {
    exibirAvisoTermosPendentes();
  }
}
/* Contas anteriores à LGPD: pede o aceite dos Termos e da Política (versão atual). */
function exibirAvisoTermosPendentes() {
  const aviso = document.createElement('div');
  aviso.id = 'aviso-termos-pendentes';
  aviso.style.cssText = 'background:#EAF2FF; border-left:3px solid var(--azul-mecon); padding:10px 16px; border-radius: var(--raio-botao); margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;';
  aviso.innerHTML = `
    <span>Atualizamos nossos <a href="termos.html" target="_blank" rel="noopener">Termos de Uso</a> e a
    <a href="privacidade.html" target="_blank" rel="noopener">Política de Privacidade</a> (LGPD). Para continuar usando o MecOn, confirme que leu e aceita.</span>
    <button type="button" id="botao-aceitar-termos" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Li e aceito</button>
  `;
  document.querySelector('.main').prepend(aviso);
  document.getElementById('botao-aceitar-termos').addEventListener('click', async (evento) => {
    evento.target.disabled = true;
    try {
      await chamarApi('/auth/aceitar_termos.php', { method: 'POST' });
      salvarUsuarioLocal({ ...usuario, termos_pendentes: false });
      aviso.remove();
    } catch (erro) {
      alert('Não foi possível registrar o aceite: ' + erro.message);
      evento.target.disabled = false;
    }
  });
}
function exibirAvisoEmailNaoVerificado() {
  const aviso = document.createElement('div');
  aviso.id = 'aviso-email-nao-verificado';
  aviso.style.cssText = 'background:#FFF4E5; border-left:3px solid #E8A33D; padding:10px 16px; border-radius: var(--raio-botao); margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;';
  aviso.innerHTML = `
    <span>Confirme seu email para poder avaliar oficinas. Não recebeu o link?</span>
    <button type="button" id="botao-reenviar-verificacao" class="botao botao--secundario" style="padding:6px 14px; font-size:0.85rem;">Reenviar email</button>
  `;
  document.querySelector('.main').prepend(aviso);
  document.getElementById('botao-reenviar-verificacao').addEventListener('click', async (evento) => {
    const botao = evento.target;
    botao.disabled = true;
    botao.textContent = 'Enviando…';
    try {
      const resposta = await chamarApi('/auth/reenviar_verificacao.php', { method: 'POST' });
      if (resposta.debug_link_verificacao) {
        console.info('[MecOn - modo dev] Link de verificação de email:', resposta.debug_link_verificacao);
        alert('Link reenviado (modo dev, veja o console ou):\n' + resposta.debug_link_verificacao);
      } else {
        alert('Email de verificação reenviado.');
      }
      botao.textContent = 'Reenviar email';
      botao.disabled = false;
    } catch (erro) {
      alert('Não foi possível reenviar: ' + erro.message);
      botao.textContent = 'Reenviar email';
      botao.disabled = false;
    }
  });
}
/* =========================================================
   Navegação entre abas
   ========================================================= */
const renderizadores = {
  veiculo: renderizarVeiculo,
  favoritos: renderizarFavoritos,
  agendamentos: renderizarAgendamentosUsuario,
  orcamentos: renderizarOrcamentosUsuario,
  mensagens: renderizarMensagens,
  comentarios: renderizarComentarios,
  conta: renderizarConta,
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
   Aba: Veículo (lista de veículos, cada um com histórico próprio
   expansível via botão "Ver histórico")
   ========================================================= */
let veiculoExpandidoId = null;
async function renderizarVeiculo() {
  painel.innerHTML = '<p>Carregando veículos…</p>';
  try {
    const [respostaVeiculos, respostaLembretes] = await Promise.all([
      chamarApi('/veiculos/list_veiculos.php'),
      chamarApi('/historico/lembretes_manutencao.php'),
    ]);
    atualizarCacheLembretes(respostaLembretes.lembretes);
    desenharSecaoVeiculos(respostaVeiculos.veiculos, respostaLembretes.lembretes);
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar seus veículos: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharBannerLembretes(lembretes) {
  if (!lembretes || lembretes.length === 0) return '';
  const linhas = lembretes.map((l) => {
    const cor = l.status === 'atrasado' ? '#C0392B' : '#E8A33D';
    return `<p style="margin:8px 0; line-height:1.5;"><span style="color:${cor};" aria-hidden="true">⚠</span> ${descreverLembrete(l)} <a href="oficinas.html?servico=${l.id_servico}" style="white-space:nowrap; font-weight:600;">Encontrar oficinas</a></p>`;
  }).join('');
  return `
    <div style="background:#FFF4E5; border-left:3px solid #E8A33D; padding:12px 16px; border-radius: var(--raio-botao); margin-bottom:16px;">
      <p style="font-weight:700; margin:0 0 6px;">Lembretes de manutenção preventiva</p>
      ${linhas}
    </div>
  `;
}
function desenharSecaoVeiculos(veiculos, lembretes = []) {
  const temVeiculos = veiculos.length > 0;
  const bannerLembretes = desenharBannerLembretes(lembretes);
  const botaoTopo = temVeiculos
    ? `
      <div style="text-align:center; margin-bottom:16px;">
        <button type="button" id="botao-novo-veiculo" class="botao botao--primario">Adicionar veículo</button>
      </div>
    `
    : '';
  const linhasVeiculos = temVeiculos
    ? veiculos.map((v) => `
        <div class="avaliacao" data-id="${v.id_veiculo}" style="flex-direction:column; align-items:stretch;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <span class="avaliacao__nome">
              ${escapeHtml(v.placa)}
              <span style="font-weight:400; color: var(--texto-secundario); font-size:0.85rem; margin-left:8px;">
                ${v.km_atual !== null ? `${v.km_atual.toLocaleString('pt-BR')} km` : 'km não informada'}
              </span>
              <button type="button" class="botao-editar-km" data-id="${v.id_veiculo}" data-km="${v.km_atual ?? ''}"
                style="background:none; border:none; padding:0; margin-left:6px; color: var(--azul-mecon); font-size:0.78rem; text-decoration:underline; cursor:pointer;">
                editar km
              </button>
            </span>
            <div style="display:flex; gap:8px;">
              <button type="button" class="botao botao--secundario botao-ver-historico" data-id="${v.id_veiculo}">
                ${veiculoExpandidoId === v.id_veiculo ? 'Ocultar histórico' : 'Ver histórico'}
              </button>
              <button type="button" class="botao botao--perigo botao-excluir-veiculo" data-id="${v.id_veiculo}">Excluir</button>
            </div>
          </div>
          <div id="historico-veiculo-${v.id_veiculo}" style="margin-top:16px; ${veiculoExpandidoId === v.id_veiculo ? '' : 'display:none;'}"></div>
        </div>
      `).join('')
    : `
      <div class="estado-vazio">
        <h3>Nenhum veículo registrado ainda</h3>
        <p>Use o botão abaixo para adicionar o primeiro.</p>
        <button type="button" id="botao-novo-veiculo" class="botao botao--primario" style="margin-top:16px;">Adicionar veículo</button>
      </div>
    `;
  painel.innerHTML = `
    ${bannerLembretes}
    ${botaoTopo}
    <div id="form-veiculo-container"></div>
    <div class="lista-avaliacoes">${linhasVeiculos}</div>
  `;
  document.getElementById('botao-novo-veiculo').addEventListener('click', () => abrirFormVeiculo());
  document.querySelectorAll('.botao-excluir-veiculo').forEach((botao) => {
    botao.addEventListener('click', () => excluirVeiculo(Number(botao.dataset.id)));
  });
  document.querySelectorAll('.botao-ver-historico').forEach((botao) => {
    botao.addEventListener('click', () => alternarHistorico(Number(botao.dataset.id)));
  });
  document.querySelectorAll('.botao-editar-km').forEach((botao) => {
    botao.addEventListener('click', () => editarKmVeiculo(Number(botao.dataset.id), botao.dataset.km));
  });
  // Se algum veículo já estava expandido (ex: após salvar um histórico), recarrega-o
  if (veiculoExpandidoId !== null && veiculos.some((v) => v.id_veiculo === veiculoExpandidoId)) {
    renderizarHistorico(veiculoExpandidoId);
  }
}
function alternarHistorico(idVeiculo) {
  veiculoExpandidoId = veiculoExpandidoId === idVeiculo ? null : idVeiculo;
  renderizarVeiculo();
}
function abrirFormVeiculo() {
  const container = document.getElementById('form-veiculo-container');
  container.innerHTML = `
    <form id="form-veiculo" class="cartao-formulario" style="margin: 0 0 20px 0; max-width: 100%;">
      <h4>Novo veículo</h4>
      <p id="erro-veiculo" class="mensagem-erro"></p>
      <div class="campo-formulario">
        <label for="input-placa">Placa</label>
        <input type="text" id="input-placa" required maxlength="10" placeholder="ABC1D23" style="text-transform:uppercase;">
      </div>
      <div class="campo-formulario">
        <label for="input-km-veiculo">Quilometragem atual (opcional)</label>
        <input type="number" id="input-km-veiculo" min="0" step="1" placeholder="Ex: 45000">
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="botao botao--primario">Salvar</button>
        <button type="button" id="botao-cancelar-veiculo" class="botao botao--secundario">Cancelar</button>
      </div>
    </form>
  `;
  document.getElementById('botao-cancelar-veiculo').addEventListener('click', () => {
    container.innerHTML = '';
  });
  document.getElementById('form-veiculo').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const placa = document.getElementById('input-placa').value.trim();
    const km = document.getElementById('input-km-veiculo').value;
    const erroEl = document.getElementById('erro-veiculo');
    try {
      await chamarApi('/veiculos/create_veiculo.php', {
        method: 'POST',
        body: JSON.stringify({ placa, km_atual: km !== '' ? Number(km) : null }),
      });
      renderizarVeiculo();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
async function editarKmVeiculo(idVeiculo, kmAtual) {
  const novoKm = prompt('Quilometragem atual do veículo:', kmAtual || '');
  if (novoKm === null || novoKm.trim() === '') return;
  if (!Number.isInteger(Number(novoKm)) || Number(novoKm) < 0) {
    alert('Informe um número inteiro válido.');
    return;
  }
  try {
    await chamarApi('/veiculos/update_veiculo.php', {
      method: 'POST',
      body: JSON.stringify({ id_veiculo: idVeiculo, km_atual: Number(novoKm) }),
    });
    renderizarVeiculo();
  } catch (erro) {
    alert('Não foi possível atualizar: ' + erro.message);
  }
}
async function excluirVeiculo(idVeiculo) {
  if (!confirm('Tem certeza que deseja excluir este veículo? Todo o histórico de manutenção vinculado a ele também será excluído.')) return;
  try {
    await chamarApi('/veiculos/delete_veiculo.php', {
      method: 'POST',
      body: JSON.stringify({ id_veiculo: idVeiculo }),
    });
    if (veiculoExpandidoId === idVeiculo) {
      veiculoExpandidoId = null;
    }
    renderizarVeiculo();
  } catch (erro) {
    alert('Não foi possível excluir: ' + erro.message);
  }
}
/* =========================================================
   Sub-módulo: Histórico de manutenção (CRUD completo)
   Renderizado dentro do card de um veículo específico, somente
   quando o usuário clica em "Ver histórico" daquele veículo.
   ========================================================= */
let cacheOficinas = null;
let cacheServicos = null;
async function carregarOficinasEServicos() {
  if (!cacheOficinas) {
    const r = await chamarApi('/oficinas/list_oficinas.php');
    cacheOficinas = r.oficinas;
  }
  if (!cacheServicos) {
    const r = await chamarApi('/servicos/list_servicos.php');
    cacheServicos = r.servicos;
  }
}
function optionsOficinas(selecionadoId) {
  return cacheOficinas.map((o) =>
    `<option value="${o.id_oficina}" ${o.id_oficina === selecionadoId ? 'selected' : ''}>${escapeHtml(o.nome)}</option>`
  ).join('');
}
function optionsServicos(selecionadoId) {
  return cacheServicos.map((s) =>
    `<option value="${s.id_servico}" ${s.id_servico === selecionadoId ? 'selected' : ''}>${escapeHtml(s.nome)}</option>`
  ).join('');
}
async function renderizarHistorico(idVeiculo) {
  const secao = document.getElementById(`historico-veiculo-${idVeiculo}`);
  if (!secao) return;
  secao.innerHTML = '<p>Carregando histórico…</p>';
  try {
    await carregarOficinasEServicos();
    const resposta = await chamarApi(`/historico/list_historico.php?id_veiculo=${idVeiculo}`);
    desenharListaHistorico(idVeiculo, resposta.historico, resposta.total_gasto);
  } catch (erro) {
    secao.innerHTML = `<p>Não foi possível carregar o histórico: ${escapeHtml(erro.message)}</p>`;
  }
}
function formatarReais(valor) {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
const pecasPorHistorico = {};
function desenharListaHistorico(idVeiculo, historico, totalGasto = 0) {
  historico.forEach((h) => { pecasPorHistorico[h.id_historico] = h.pecas || []; });
  const secao = document.getElementById(`historico-veiculo-${idVeiculo}`);
  if (!secao) return;
  const temHistorico = historico.length > 0;
  const botaoTopo = temHistorico
    ? `
      <div style="text-align:center; margin-bottom:16px;">
        <button type="button" class="botao botao--primario botao-novo-historico" data-veiculo="${idVeiculo}">Adicionar manutenção</button>
      </div>
    `
    : '';
  const linhas = temHistorico
    ? historico.map((h) => `
        <div class="avaliacao" data-id="${h.id_historico}">
          <div class="avaliacao__corpo">
            <span class="avaliacao__nome">${escapeHtml(h.nome_oficina)} — ${escapeHtml(h.nome_servico)}</span>
            <p style="color: var(--texto-secundario); font-size:0.85rem; margin: 4px 0 0;">
              ${formatarData(h.data_servico)}${h.km_servico !== null ? ` · ${h.km_servico.toLocaleString('pt-BR')} km` : ''}${h.valor_total !== null ? ` · ${formatarReais(h.valor_total)}` : ''}
            </p>
            ${(h.pecas || []).length ? `
              <p style="color: var(--texto-secundario); font-size:0.8rem; margin: 2px 0 0;">
                Peças: ${h.pecas.map((p) => `${escapeHtml(p.nome)}${p.quantidade !== 1 ? ' ×' + p.quantidade : ''}`).join(', ')}
              </p>` : ''}
            ${h.valor_pecas !== null || h.valor_mao_obra !== null ? `
              <p style="color: var(--texto-secundario); font-size:0.8rem; margin: 2px 0 0;">
                ${h.valor_pecas !== null ? `Peças: ${formatarReais(h.valor_pecas)}` : ''}${h.valor_pecas !== null && h.valor_mao_obra !== null ? ' · ' : ''}${h.valor_mao_obra !== null ? `Mão de obra: ${formatarReais(h.valor_mao_obra)}` : ''}
              </p>` : ''}
          </div>
          <div style="display:flex; gap:8px;">
            <button type="button" class="botao botao--secundario botao-editar-historico" data-id="${h.id_historico}" data-veiculo="${idVeiculo}" data-oficina="${h.id_oficina}" data-servico="${h.id_servico}" data-data="${h.data_servico}" data-km="${h.km_servico ?? ''}" data-total="${h.valor_total ?? ''}" data-pecas="${h.valor_pecas ?? ''}" data-mao="${h.valor_mao_obra ?? ''}">Editar</button>
            <button type="button" class="botao botao--perigo botao-excluir-historico" data-id="${h.id_historico}" data-veiculo="${idVeiculo}">Excluir</button>
          </div>
        </div>
      `).join('')
    : `
      <div class="estado-vazio">
        <h3>Nenhuma manutenção registrada ainda</h3>
        <p>Use o botão abaixo para adicionar a primeira.</p>
        <button type="button" class="botao botao--primario botao-novo-historico" data-veiculo="${idVeiculo}" style="margin-top:16px;">Adicionar manutenção</button>
      </div>
    `;
  secao.innerHTML = `
    <h4>Histórico de manutenção</h4>
    ${temHistorico && totalGasto > 0 ? `<p style="color: var(--texto-secundario); font-size:0.9rem; margin: -4px 0 12px;">Total gasto com este veículo: <strong>${formatarReais(totalGasto)}</strong></p>` : ''}
    ${botaoTopo}
    <div class="form-historico-container" data-veiculo="${idVeiculo}"></div>
    <div class="lista-avaliacoes">${linhas}</div>
  `;
  secao.querySelectorAll('.botao-novo-historico').forEach((botao) => {
    botao.addEventListener('click', () => abrirFormHistorico(Number(botao.dataset.veiculo)));
  });
  secao.querySelectorAll('.botao-editar-historico').forEach((botao) => {
    botao.addEventListener('click', () => abrirFormHistorico(Number(botao.dataset.veiculo), {
      id_historico: Number(botao.dataset.id),
      id_oficina: Number(botao.dataset.oficina),
      id_servico: Number(botao.dataset.servico),
      data_servico: botao.dataset.data,
      km_servico: botao.dataset.km,
      valor_total: botao.dataset.total,
      valor_pecas: botao.dataset.pecas,
      valor_mao_obra: botao.dataset.mao,
      pecas: pecasPorHistorico[Number(botao.dataset.id)] || [],
    }));
  });
  secao.querySelectorAll('.botao-excluir-historico').forEach((botao) => {
    botao.addEventListener('click', () => excluirHistorico(Number(botao.dataset.id), Number(botao.dataset.veiculo)));
  });
}
function formatarData(dataIso) {
  const [ano, mes, dia] = dataIso.split('-');
  return `${dia}/${mes}/${ano}`;
}
function abrirFormHistorico(idVeiculo, registroExistente = null) {
  const ehEdicao = registroExistente !== null;
  const container = document.querySelector(`.form-historico-container[data-veiculo="${idVeiculo}"]`);
  if (!container) return;
  container.innerHTML = `
    <form class="form-historico cartao-formulario" style="margin: 0 0 20px 0; max-width: 100%;">
      <h4>${ehEdicao ? 'Editar' : 'Novo'} registro de manutenção</h4>
      <p class="erro-historico mensagem-erro"></p>
      <div class="campo-formulario">
        <label>Oficina</label>
        <select class="select-historico-oficina" required style="width:100%; padding:11px 14px; border:1px solid var(--borda); border-radius: var(--raio-botao);">
          ${optionsOficinas(registroExistente?.id_oficina)}
        </select>
      </div>
      <div class="campo-formulario">
        <label>Serviço</label>
        <select class="select-historico-servico" required style="width:100%; padding:11px 14px; border:1px solid var(--borda); border-radius: var(--raio-botao);">
          ${optionsServicos(registroExistente?.id_servico)}
        </select>
      </div>
      <div class="campo-formulario">
        <label>Data do serviço</label>
        <input type="date" class="input-historico-data" required value="${registroExistente?.data_servico ?? ''}">
      </div>
      <div class="campo-formulario">
        <label>Km do veículo nesse serviço (opcional)</label>
        <input type="number" class="input-historico-km" min="0" step="1" placeholder="Ex: 45200" value="${registroExistente?.km_servico ?? ''}">
      </div>
      <div class="campo-formulario">
        <label>Valor total pago em R$ (opcional)</label>
        <input type="number" class="input-historico-total" min="0" step="0.01" placeholder="Ex: 280.00" value="${registroExistente?.valor_total ?? ''}">
      </div>
      <div style="display:flex; gap:10px;">
        <div class="campo-formulario" style="flex:1;">
          <label>Peças em R$ (vazio = soma das peças)</label>
          <input type="number" class="input-historico-pecas" min="0" step="0.01" value="${registroExistente?.valor_pecas ?? ''}">
        </div>
        <div class="campo-formulario" style="flex:1;">
          <label>Mão de obra em R$ (opcional)</label>
          <input type="number" class="input-historico-mao" min="0" step="0.01" value="${registroExistente?.valor_mao_obra ?? ''}">
        </div>
      </div>
      ${montarEditorPecasHTML(registroExistente?.pecas || [])}
      <div style="display:flex; gap:10px;">
        <button type="submit" class="botao botao--primario">Salvar</button>
        <button type="button" class="botao botao--secundario botao-cancelar-historico">Cancelar</button>
      </div>
    </form>
  `;
  ligarEditorPecas(container);
  container.querySelector('.botao-cancelar-historico').addEventListener('click', () => {
    container.innerHTML = '';
  });
  container.querySelector('.form-historico').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const kmValor = container.querySelector('.input-historico-km').value;
    const corpo = {
      id_veiculo: idVeiculo,
      id_oficina: Number(container.querySelector('.select-historico-oficina').value),
      id_servico: Number(container.querySelector('.select-historico-servico').value),
      data_servico: container.querySelector('.input-historico-data').value,
      km_servico: kmValor !== '' ? Number(kmValor) : null,
      valor_total: container.querySelector('.input-historico-total').value,
      valor_pecas: container.querySelector('.input-historico-pecas').value,
      valor_mao_obra: container.querySelector('.input-historico-mao').value,
      pecas: lerPecasDoEditor(container),
    };
    const erroEl = container.querySelector('.erro-historico');
    try {
      if (ehEdicao) {
        corpo.id_historico = registroExistente.id_historico;
        await chamarApi('/historico/update_historico.php', { method: 'POST', body: JSON.stringify(corpo) });
      } else {
        await chamarApi('/historico/create_historico.php', { method: 'POST', body: JSON.stringify(corpo) });
      }
      renderizarHistorico(idVeiculo);
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
async function excluirHistorico(idHistorico, idVeiculo) {
  if (!confirm('Tem certeza que deseja excluir este registro de manutenção?')) return;
  try {
    await chamarApi('/historico/delete_historico.php', {
      method: 'POST',
      body: JSON.stringify({ id_historico: idHistorico }),
    });
    renderizarHistorico(idVeiculo);
  } catch (erro) {
    alert('Não foi possível excluir: ' + erro.message);
  }
}
/* =========================================================
   Aba: Oficinas favoritas
   ========================================================= */
async function renderizarFavoritos() {
  painel.innerHTML = '<p>Carregando favoritos…</p>';
  try {
    const resposta = await chamarApi('/favoritos/list_favoritos.php');
    if (resposta.favoritos.length === 0) {
      painel.innerHTML = `
        <div class="estado-vazio">
          <h3>Você ainda não favoritou nenhuma oficina</h3>
          <p>Busque oficinas e toque no coração para salvá-las aqui.</p>
          <a href="oficinas.html" class="botao botao--primario" style="margin-top:16px; display:inline-block;">Buscar oficinas</a>
        </div>
      `;
      return;
    }
    const cards = resposta.favoritos.map((oficina) => {
      const nota = oficina.media_avaliacao !== null ? oficina.media_avaliacao.toFixed(1) : '—';
      return `
        <a href="oficina.html?id=${oficina.id_oficina}" class="card-oficina">
          <img src="../img/oficina.jpg" alt="Foto da oficina ${oficina.nome}" class="card-oficina__imagem">
          <div class="card-oficina__info">
            <div class="card-oficina__nome">${escapeHtml(oficina.nome)}</div>
            <div class="card-oficina__descricao">${escapeHtml(oficina.bairro)}, ${escapeHtml(oficina.cidade)}</div>
            <div class="card-oficina__avaliacao">★ ${nota}</div>
          </div>
        </a>
      `;
    }).join('');
    painel.innerHTML = `
      <div style="text-align:center; margin-bottom:16px;">
        <a href="oficinas.html" class="botao botao--secundario">Buscar oficinas</a>
      </div>
      <div class="grade-oficinas">${cards}</div>
    `;
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar seus favoritos: ${escapeHtml(erro.message)}</p>`;
  }
}
/* =========================================================
   Aba: Comentários feitos (somente leitura — avaliação é imutável)
   ========================================================= */
function estrelas(nota) {
  return '★★★★★☆☆☆☆☆'.slice(5 - nota, 10 - nota);
}
async function renderizarComentarios() {
  painel.innerHTML = '<p>Carregando comentários…</p>';
  try {
    const resposta = await chamarApi('/avaliacoes/list_user_reviews.php');
    if (resposta.avaliacoes.length === 0) {
      painel.innerHTML = `
        <div class="estado-vazio">
          <h3>Você ainda não avaliou nenhuma oficina</h3>
          <p>Suas avaliações aparecem aqui depois de publicadas e não podem ser editadas.</p>
        </div>
      `;
      return;
    }
    const linhas = resposta.avaliacoes.map((a) => `
      <div class="avaliacao">
        <div class="avaliacao__corpo">
          <div class="avaliacao__cabecalho">
            <a href="oficina.html?id=${a.id_oficina}" class="avaliacao__nome">${escapeHtml(a.nome_oficina)}</a>
            <span class="avaliacao__estrelas">${estrelas(a.nota)}</span>
          </div>
          <p class="avaliacao__comentario">${escapeHtml(a.comentario || '')}</p>
        </div>
      </div>
    `).join('');
    painel.innerHTML = `<div class="lista-avaliacoes">${linhas}</div>`;
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar seus comentários: ${escapeHtml(erro.message)}</p>`;
  }
}
/* =========================================================
   Aba: Dados da conta
   ========================================================= */
function renderizarConta() {
  painel.innerHTML = `
    <div class="cartao-formulario" style="margin: 0 auto; max-width: 480px;">
      <p id="erro-conta" class="mensagem-erro"></p>
      <p id="sucesso-conta" class="mensagem-erro" style="background-color:#EAFBF1; color: var(--verde-sucesso);"></p>
      <form id="form-conta">
        <div class="campo-formulario">
          <label for="input-conta-nome">Nome</label>
          <input type="text" id="input-conta-nome" value="${escapeHtml(usuario.nome)}" required>
        </div>
        <div class="campo-formulario">
          <label for="input-conta-email">Email</label>
          <input type="email" id="input-conta-email" value="${escapeHtml(usuario.email)}" required>
        </div>
        <div class="campo-formulario">
          <label for="input-conta-senha">Nova senha (deixe em branco para não alterar)</label>
          <input type="password" id="input-conta-senha" minlength="6">
        </div>
        <button type="submit" class="botao botao--primario">Salvar alterações</button>
      </form>
      <hr style="margin: 28px 0; border: none; border-top: 1px solid var(--borda, #ddd);">
      <h3>Privacidade e meus dados</h3>
      <p style="font-size:0.9rem; color: var(--texto-secundario);">
        Conforme a LGPD, você pode baixar uma cópia dos seus dados ou excluir sua conta.
        Veja a <a href="privacidade.html" target="_blank" rel="noopener">Política de Privacidade</a> e os
        <a href="termos.html" target="_blank" rel="noopener">Termos de Uso</a>.
      </p>
      <p id="erro-privacidade" class="mensagem-erro"></p>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" id="botao-baixar-dados" class="botao botao--secundario">Baixar meus dados</button>
        <button type="button" id="botao-excluir-conta" class="botao botao--perigo">Excluir minha conta</button>
      </div>
      <form id="form-excluir-conta" style="display:none; margin-top:16px;">
        <p style="font-size:0.9rem;"><strong>Esta ação é permanente.</strong> Serão apagados seus veículos, histórico de manutenção, pedidos de orçamento, agendamentos, avaliações, mensagens e anexos. Digite sua senha para confirmar.</p>
        <div class="campo-formulario">
          <label for="input-excluir-senha">Senha atual</label>
          <input type="password" id="input-excluir-senha" required autocomplete="current-password">
        </div>
        <button type="submit" class="botao botao--perigo">Confirmar exclusão definitiva</button>
        <button type="button" id="botao-cancelar-exclusao" class="botao botao--secundario">Cancelar</button>
      </form>
    </div>
  `;
  ligarPrivacidade();
  document.getElementById('form-conta').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const corpo = {
      id_usuario: usuario.id_usuario,
      nome: document.getElementById('input-conta-nome').value.trim(),
      email: document.getElementById('input-conta-email').value.trim(),
    };
    const senha = document.getElementById('input-conta-senha').value;
    if (senha !== '') corpo.senha = senha;
    const erroEl = document.getElementById('erro-conta');
    const sucessoEl = document.getElementById('sucesso-conta');
    erroEl.classList.remove('mensagem-erro--visivel');
    sucessoEl.classList.remove('mensagem-erro--visivel');
    try {
      await chamarApi('/usuarios/update_usuario.php', { method: 'POST', body: JSON.stringify(corpo) });
      const usuarioAtualizado = { ...usuario, nome: corpo.nome, email: corpo.email };
      salvarUsuarioLocal(usuarioAtualizado);
      sucessoEl.textContent = 'Dados atualizados com sucesso!';
      sucessoEl.classList.add('mensagem-erro--visivel');
      document.getElementById('texto-saudacao').textContent = `Bem-vindo(a) de volta, ${corpo.nome}!`;
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
function ligarPrivacidade() {
  const erroEl = document.getElementById('erro-privacidade');
  const mostrarErro = (msg) => { erroEl.textContent = msg; erroEl.classList.add('mensagem-erro--visivel'); };
  document.getElementById('botao-baixar-dados').addEventListener('click', async () => {
    erroEl.classList.remove('mensagem-erro--visivel');
    try {
      const resposta = await chamarApi('/usuarios/exportar_meus_dados.php');
      const blob = new Blob([JSON.stringify(resposta.dados, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'meus-dados-mecon.json';
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch (erro) {
      mostrarErro(erro.message);
    }
  });
  const formExcluir = document.getElementById('form-excluir-conta');
  document.getElementById('botao-excluir-conta').addEventListener('click', () => {
    formExcluir.style.display = 'block';
    document.getElementById('input-excluir-senha').focus();
  });
  document.getElementById('botao-cancelar-exclusao').addEventListener('click', () => {
    formExcluir.style.display = 'none';
  });
  formExcluir.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    erroEl.classList.remove('mensagem-erro--visivel');
    if (!confirm('Tem certeza? Sua conta e todos os seus dados serão apagados de forma permanente.')) return;
    try {
      await chamarApi('/usuarios/excluir_minha_conta.php', {
        method: 'POST',
        body: JSON.stringify({ senha: document.getElementById('input-excluir-senha').value }),
      });
      limparUsuarioLocal();
      alert('Sua conta foi excluída.');
      window.location.href = '../index.html';
    } catch (erro) {
      mostrarErro(erro.message);
    }
  });
}
/* =========================================================
   Aba: Agendamentos
   ========================================================= */
const rotulosStatusAgendamento = {
  pendente: { texto: 'Aguardando resposta da oficina', cor: '#E8A33D' },
  aceito: { texto: 'Aceito', cor: 'var(--verde-sucesso)' },
  recusado: { texto: 'Recusado', cor: '#C0392B' },
  cancelado: { texto: 'Cancelado', cor: 'var(--texto-secundario)' },
  concluido: { texto: 'Concluído', cor: 'var(--azul-mecon)' },
};
async function renderizarAgendamentosUsuario() {
  painel.innerHTML = '<p>Carregando agendamentos…</p>';
  try {
    const resposta = await chamarApi('/agendamentos/list_agendamentos_usuario.php');
    if (resposta.agendamentos.length === 0) {
      painel.innerHTML = `
        <div class="estado-vazio">
          <h3>Nenhum agendamento ainda</h3>
          <p>Solicite um agendamento na página de uma oficina para acompanhar aqui.</p>
        </div>
      `;
      return;
    }
    painel.innerHTML = `<div class="lista-avaliacoes">${resposta.agendamentos.map(desenharAgendamentoUsuario).join('')}</div>`;
    document.querySelectorAll('.botao-cancelar-agendamento-usuario').forEach((b) => {
      b.addEventListener('click', () => cancelarAgendamentoUsuario(Number(b.dataset.id)));
    });
    document.querySelectorAll('.botao-avaliar-agendamento').forEach((b) => {
      b.addEventListener('click', () => abrirFormularioAvaliacaoAgendamento(b));
    });
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar seus agendamentos: ${escapeHtml(erro.message)}</p>`;
  }
}
const rotulosEtapaOSUsuario = {
  aberta: 'Aberta',
  em_execucao: 'Em execução',
  aguardando_peca: 'Aguardando peça',
  pronta: 'Pronta para retirada',
  entregue: 'Concluída',
};
function desenharAgendamentoUsuario(ag) {
  const rotulo = rotulosStatusAgendamento[ag.status];
  const dataFormatada = new Date(ag.data_hora_solicitada.replace(' ', 'T')).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
  const podeCancelar = ag.status === 'pendente' || ag.status === 'aceito';
  const podeAvaliar = ag.status === 'concluido' && !ag.ja_avaliado;
  return `
    <div class="avaliacao" data-id-agendamento="${ag.id_agendamento}" data-id-oficina="${ag.id_oficina}">
      <div class="avaliacao__corpo">
        <div class="avaliacao__cabecalho">
          <span class="avaliacao__nome">${escapeHtml(ag.nome_oficina)} — ${escapeHtml(ag.nome_servico)}</span>
          <span style="color:${rotulo.cor}; font-weight:600; font-size:0.85rem;">${rotulo.texto}</span>
        </div>
        <p class="avaliacao__comentario">Veículo ${escapeHtml(ag.placa)} · ${dataFormatada}</p>
        ${ag.mensagem_usuario ? `<p style="font-size:0.85rem; color: var(--texto-secundario);">Sua mensagem: "${escapeHtml(ag.mensagem_usuario)}"</p>` : ''}
        ${ag.resposta_oficina ? `<p style="font-size:0.85rem; color: var(--texto-secundario);">Resposta da oficina: "${escapeHtml(ag.resposta_oficina)}"</p>` : ''}
        ${ag.etapa_os ? `<p style="font-size:0.85rem; margin:4px 0;">Ordem de serviço: <strong>${rotulosEtapaOSUsuario[ag.etapa_os]}</strong> · <a href="ordem-servico.html?como=usuario&id_agendamento=${ag.id_agendamento}" target="_blank">ver detalhes</a></p>` : ''}
        ${ag.status === 'concluido' && ag.ja_avaliado ? '<p style="font-size:0.85rem; color: var(--verde-sucesso);">✔ Você já avaliou este serviço</p>' : ''}
        <div class="bloco-avaliar-agendamento"></div>
      </div>
      <div style="display:flex; gap:8px;">
        ${podeCancelar ? `<button type="button" class="botao botao--perigo botao-cancelar-agendamento-usuario" data-id="${ag.id_agendamento}">Cancelar</button>` : ''}
        ${podeAvaliar ? `<button type="button" class="botao botao--primario botao-avaliar-agendamento" data-id="${ag.id_agendamento}" data-oficina="${ag.id_oficina}" data-nome-oficina="${escapeHtml(ag.nome_oficina)}">Avaliar este serviço</button>` : ''}
      </div>
    </div>
  `;
}
function abrirFormularioAvaliacaoAgendamento(botaoEl) {
  const idAgendamento = Number(botaoEl.dataset.id);
  const idOficina = Number(botaoEl.dataset.oficina);
  const cartao = botaoEl.closest('.avaliacao');
  const bloco = cartao.querySelector('.bloco-avaliar-agendamento');
  botaoEl.style.display = 'none';
  bloco.innerHTML = `
    <form class="form-avaliacao-agendamento" style="margin-top:10px; border-top:1px solid var(--borda); padding-top:10px;">
      <p style="font-size:0.85rem; font-weight:600; margin-bottom:6px;">Avaliar ${escapeHtml(botaoEl.dataset.nomeOficina)}</p>
      <select class="select-nota-agendamento" required style="margin-bottom:6px;">
        <option value="">Nota</option>
        <option value="5">5 — Excelente</option>
        <option value="4">4 — Bom</option>
        <option value="3">3 — Regular</option>
        <option value="2">2 — Ruim</option>
        <option value="1">1 — Péssimo</option>
      </select>
      <input type="text" class="input-comentario-agendamento" placeholder="Comentário (opcional)" style="width:100%; margin-bottom:6px; padding:8px 10px;">
      <p class="mensagem-erro erro-avaliacao-agendamento"></p>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Enviar avaliação</button>
        <button type="button" class="botao botao--secundario botao-cancelar-avaliacao-agendamento" style="padding:6px 14px; font-size:0.85rem;">Cancelar</button>
      </div>
    </form>
  `;
  bloco.querySelector('.botao-cancelar-avaliacao-agendamento').addEventListener('click', () => {
    bloco.innerHTML = '';
    botaoEl.style.display = '';
  });
  bloco.querySelector('.form-avaliacao-agendamento').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const nota = bloco.querySelector('.select-nota-agendamento').value;
    const comentario = bloco.querySelector('.input-comentario-agendamento').value.trim();
    const erroEl = bloco.querySelector('.erro-avaliacao-agendamento');
    if (!nota) {
      erroEl.textContent = 'Selecione uma nota.';
      erroEl.classList.add('mensagem-erro--visivel');
      return;
    }
    try {
      await chamarApi('/avaliacoes/create_review.php', {
        method: 'POST',
        body: JSON.stringify({ id_oficina: idOficina, nota: Number(nota), comentario, id_agendamento: idAgendamento }),
      });
      renderizarAgendamentosUsuario();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
async function cancelarAgendamentoUsuario(idAgendamento) {
  if (!confirm('Cancelar este agendamento?')) return;
  try {
    await chamarApi('/agendamentos/cancelar_agendamento.php', {
      method: 'POST',
      body: JSON.stringify({ id_agendamento: idAgendamento }),
    });
    renderizarAgendamentosUsuario();
  } catch (erro) {
    alert('Não foi possível cancelar: ' + erro.message);
  }
}
/* =========================================================
   Aba: Orçamentos (pedidos enviados, comparação e aprovação)
   ========================================================= */
const rotulosStatusPedido = {
  aberto: { texto: 'Em andamento', cor: '#E8A33D' },
  aprovado: { texto: 'Aprovado', cor: 'var(--verde-sucesso)' },
  cancelado: { texto: 'Cancelado', cor: 'var(--texto-secundario)' },
};
const rotulosStatusOrcamento = {
  pendente: { texto: 'Aguardando a oficina', cor: '#E8A33D' },
  respondido: { texto: 'Respondido', cor: 'var(--azul-mecon)' },
  aprovado: { texto: 'Aprovado', cor: 'var(--verde-sucesso)' },
  nao_escolhido: { texto: 'Não escolhido', cor: 'var(--texto-secundario)' },
  recusado: { texto: 'Oficina recusou', cor: '#C0392B' },
  cancelado: { texto: 'Cancelado', cor: 'var(--texto-secundario)' },
};
async function renderizarOrcamentosUsuario() {
  painel.innerHTML = '<p>Carregando orçamentos…</p>';
  try {
    const { pedidos } = await chamarApi('/orcamentos/list_pedidos_usuario.php');
    if (pedidos.length === 0) {
      painel.innerHTML = `<div class="estado-vazio"><h3>Nenhum pedido de orçamento</h3><p>Abra a página de uma oficina e use "Solicitar orçamento" — você pode enviar o mesmo pedido a até 3 oficinas e comparar.</p></div>`;
      return;
    }
    painel.innerHTML = `<div class="lista-avaliacoes">${pedidos.map((p) => {
      const st = rotulosStatusPedido[p.status];
      return `
        <div class="avaliacao" style="flex-direction:column; align-items:stretch;" data-pedido="${p.id_pedido}">
          <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
            <div>
              <span class="avaliacao__nome">${escapeHtml(p.nome_servico || 'Problema descrito')} — ${escapeHtml(p.placa)}</span>
              <p style="font-size:0.85rem; color: var(--texto-secundario); margin:2px 0 0;">${escapeHtml(p.descricao.length > 110 ? p.descricao.slice(0, 110) + '…' : p.descricao)}</p>
              <p style="font-size:0.8rem; color: var(--texto-secundario); margin:4px 0 0;">
                ${formatarDataISO(p.criado_em)} · ${p.respondidos} de ${p.total_orcamentos} oficina(s) responderam${p.total_anexos ? ` · ${p.total_anexos} anexo(s)` : ''}
              </p>
            </div>
            <div style="text-align:right;">
              <span style="color:${st.cor}; font-weight:600; font-size:0.85rem;">${st.texto}</span><br>
              <button type="button" class="botao botao--secundario botao-ver-pedido" data-id="${p.id_pedido}" style="margin-top:6px; padding:6px 14px; font-size:0.85rem;">Ver orçamentos</button>
            </div>
          </div>
          <div class="detalhe-pedido" style="display:none; margin-top:12px; border-top:1px solid var(--borda); padding-top:12px;"></div>
        </div>`;
    }).join('')}</div>`;
    painel.querySelectorAll('.botao-ver-pedido').forEach((b) => {
      b.addEventListener('click', () => alternarDetalhePedido(Number(b.dataset.id), b));
    });
  } catch (erro) {
    painel.innerHTML = `<p>Não foi possível carregar os orçamentos: ${escapeHtml(erro.message)}</p>`;
  }
}
async function alternarDetalhePedido(idPedido, botaoEl) {
  const detalhe = botaoEl.closest('.avaliacao').querySelector('.detalhe-pedido');
  if (detalhe.style.display !== 'none') {
    detalhe.style.display = 'none';
    botaoEl.textContent = 'Ver orçamentos';
    return;
  }
  detalhe.style.display = 'block';
  botaoEl.textContent = 'Ocultar';
  await carregarDetalhePedido(idPedido, detalhe);
}
async function carregarDetalhePedido(idPedido, detalhe) {
  detalhe.innerHTML = '<p>Carregando…</p>';
  try {
    const { pedido, anexos, orcamentos } = await chamarApi(`/orcamentos/get_pedido.php?id_pedido=${idPedido}`);
    const menor = Math.min(...orcamentos.filter((o) => o.status === 'respondido' && o.valor_total !== null && !o.expirado).map((o) => o.valor_total));
    const cartoes = orcamentos.map((o) => {
      const st = rotulosStatusOrcamento[o.status];
      const temValor = o.valor_total !== null;
      const ehMenor = temValor && o.valor_total === menor && o.status === 'respondido' && orcamentos.filter((x) => x.valor_total !== null).length > 1;
      const podeAprovar = pedido.status === 'aberto' && o.status === 'respondido' && !o.expirado;
      return `
        <div data-orcamento="${o.id_orcamento}" style="flex:1 1 240px; border:1px solid ${ehMenor ? 'var(--verde-sucesso)' : 'var(--borda)'}; border-radius: var(--raio-botao); padding:12px;">
          <div style="display:flex; justify-content:space-between; gap:6px; align-items:flex-start;">
            <strong>${escapeHtml(o.nome_oficina)}${o.verificada ? ' <span style="color: var(--azul-mecon);">✔</span>' : ''}</strong>
            <span style="color:${st.cor}; font-size:0.75rem; font-weight:600;">${st.texto}</span>
          </div>
          ${o.media_avaliacao !== null ? `<div style="font-size:0.8rem; color: var(--texto-secundario);">Nota ${o.media_avaliacao.toLocaleString('pt-BR')}</div>` : ''}
          ${temValor ? `
            <div style="font-size:1.4rem; font-weight:700; color: var(--azul-mecon); margin:8px 0 2px;">${formatarReaisGeral(o.valor_total)}</div>
            ${ehMenor ? '<div style="font-size:0.75rem; color: var(--verde-sucesso); font-weight:600;">Menor valor</div>' : ''}
            ${(o.valor_pecas !== null || o.valor_mao_obra !== null) ? `<div style="font-size:0.8rem; color: var(--texto-secundario);">${o.valor_pecas !== null ? 'Peças ' + formatarReaisGeral(o.valor_pecas) : ''}${o.valor_pecas !== null && o.valor_mao_obra !== null ? ' · ' : ''}${o.valor_mao_obra !== null ? 'Mão de obra ' + formatarReaisGeral(o.valor_mao_obra) : ''}</div>` : ''}
            <div style="font-size:0.8rem; margin-top:4px;">
              ${o.prazo_dias !== null ? `Prazo: ${o.prazo_dias} dia(s)<br>` : ''}
              ${o.validade_ate ? `<span style="color:${o.expirado ? '#C0392B' : 'inherit'};">${o.expirado ? 'Venceu em' : 'Válido até'} ${formatarDataISO(o.validade_ate)}</span>` : ''}
            </div>
            ${o.detalhes ? `<p style="font-size:0.82rem; margin:6px 0 0;">${escapeHtml(o.detalhes)}</p>` : ''}
          ` : '<p style="font-size:0.85rem; color: var(--texto-secundario); margin:10px 0 0;">Sem valores ainda.</p>'}
          ${podeAprovar ? `<button type="button" class="botao botao--primario botao-aprovar-orcamento" data-id="${o.id_orcamento}" data-oficina="${o.id_oficina}" style="margin-top:10px; padding:6px 14px; font-size:0.85rem;">Aprovar e agendar</button>` : ''}
          <div class="bloco-aprovar"></div>
        </div>`;
    }).join('');
    detalhe.innerHTML = `
      <p style="margin:0 0 6px;"><strong>Problema:</strong> ${escapeHtml(pedido.descricao)}</p>
      ${montarAnexosHTML(anexos)}
      <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:8px;">${cartoes}</div>
      ${pedido.status === 'aberto' ? '<button type="button" class="botao botao--perigo botao-cancelar-pedido" style="margin-top:14px; padding:6px 14px; font-size:0.85rem;">Cancelar pedido</button>' : ''}
      <p class="mensagem-erro erro-pedido"></p>`;
    detalhe.querySelectorAll('.botao-aprovar-orcamento').forEach((b) => {
      b.addEventListener('click', () => abrirAprovacao(pedido, Number(b.dataset.id), Number(b.dataset.oficina), b, detalhe));
    });
    const cancelar = detalhe.querySelector('.botao-cancelar-pedido');
    if (cancelar) {
      cancelar.addEventListener('click', async () => {
        if (!confirm('Cancelar este pedido? As oficinas deixarão de poder responder.')) return;
        try {
          await chamarApi('/orcamentos/cancelar_pedido.php', { method: 'POST', body: JSON.stringify({ id_pedido: pedido.id_pedido }) });
          renderizarOrcamentosUsuario();
        } catch (erro) {
          const e = detalhe.querySelector('.erro-pedido');
          e.textContent = erro.message;
          e.classList.add('mensagem-erro--visivel');
        }
      });
    }
  } catch (erro) {
    detalhe.innerHTML = `<p>Não foi possível carregar o pedido: ${escapeHtml(erro.message)}</p>`;
  }
}
async function abrirAprovacao(pedido, idOrcamento, idOficina, botaoEl, detalhe) {
  const bloco = botaoEl.parentElement.querySelector('.bloco-aprovar');
  botaoEl.style.display = 'none';
  let opcoesServico = '';
  if (pedido.id_servico === null) {
    try {
      const { oficina } = await chamarApi(`/oficinas/get_oficina.php?id_oficina=${idOficina}`);
      opcoesServico = `
        <div class="campo-formulario">
          <label>Serviço a agendar</label>
          <select class="aprovar-servico" required>
            <option value="">Selecione…</option>
            ${oficina.servicos.map((s) => `<option value="${s.id_servico}">${escapeHtml(s.nome)}</option>`).join('')}
          </select>
        </div>`;
    } catch (erro) {
      bloco.innerHTML = `<p class="mensagem-erro mensagem-erro--visivel">${escapeHtml(erro.message)}</p>`;
      botaoEl.style.display = '';
      return;
    }
  }
  bloco.innerHTML = `
    <form class="form-aprovar" style="margin-top:10px; border-top:1px solid var(--borda); padding-top:10px;">
      <p style="font-size:0.82rem; margin:0 0 6px;">Ao aprovar, os demais orçamentos deste pedido serão marcados como "não escolhidos" e um agendamento será solicitado à oficina.</p>
      ${opcoesServico}
      <div class="campo-formulario">
        <label>Data e hora desejadas</label>
        <input type="datetime-local" class="aprovar-data" required>
      </div>
      <p class="mensagem-erro erro-aprovar"></p>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="botao botao--primario" style="padding:6px 14px; font-size:0.85rem;">Confirmar</button>
        <button type="button" class="botao botao--secundario aprovar-cancelar" style="padding:6px 14px; font-size:0.85rem;">Voltar</button>
      </div>
    </form>`;
  bloco.querySelector('.aprovar-cancelar').addEventListener('click', () => {
    bloco.innerHTML = '';
    botaoEl.style.display = '';
  });
  bloco.querySelector('.form-aprovar').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const erroEl = bloco.querySelector('.erro-aprovar');
    const corpo = {
      id_orcamento: idOrcamento,
      data_hora_solicitada: bloco.querySelector('.aprovar-data').value.replace('T', ' ') + ':00',
    };
    const selServico = bloco.querySelector('.aprovar-servico');
    if (selServico) corpo.id_servico = Number(selServico.value);
    try {
      await chamarApi('/orcamentos/aprovar_orcamento.php', { method: 'POST', body: JSON.stringify(corpo) });
      renderizarOrcamentosUsuario();
      painel.insertAdjacentHTML('afterbegin', '<p class="mensagem-erro mensagem-erro--visivel" style="background-color:#EAFBF1; color: var(--verde-sucesso);">Orçamento aprovado! O agendamento foi solicitado — acompanhe na aba Agendamentos.</p>');
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
/* =========================================================
   Aba: Mensagens (chat com as oficinas)
   ========================================================= */
// Quando o usuário chega pelo botão "Enviar mensagem" da página de uma
// oficina (?aba=mensagens&oficina=ID&nome=...), abre direto a conversa com ela.
let oficinaParaAbrirNoChat = null;
function renderizarMensagens() {
  iniciarChat(painel, { como: 'usuario', abrirOficina: oficinaParaAbrirNoChat });
  oficinaParaAbrirNoChat = null;
}
/* =========================================================
   Inicialização
   ========================================================= */
if (usuario) {
  monitorarMensagensNaoLidas('usuario');
  const parametrosUrl = new URLSearchParams(window.location.search);
  const abaPedida = parametrosUrl.get('aba');
  if (abaPedida && renderizadores[abaPedida]) {
    if (abaPedida === 'mensagens' && parametrosUrl.get('oficina')) {
      oficinaParaAbrirNoChat = { id: Number(parametrosUrl.get('oficina')), nome: parametrosUrl.get('nome') };
    }
    selecionarAba(abaPedida);
  } else {
    selecionarAba('veiculo');
  }
}
