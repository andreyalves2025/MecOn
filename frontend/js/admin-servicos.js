/**
 * MecOn - admin-servicos.js
 * Administração do catálogo de serviços: listar (com opção de mostrar
 * inativos), cadastrar, renomear, ativar/desativar e excluir (só permitido
 * para serviços nunca usados; caso contrário, sugere desativar).
 * Exige login como administrador.
 */
const admin = exigirAdminUI();
const listaContainer = document.getElementById('lista-servicos-admin');
const formContainer = document.getElementById('form-servico-container');
const checkboxInativos = document.getElementById('input-mostrar-inativos');

function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

async function carregarServicos() {
  listaContainer.innerHTML = '<p>Carregando serviços…</p>';
  try {
    const sufixo = checkboxInativos.checked ? '?todos=1' : '';
    const resposta = await chamarApi('/servicos/list_servicos.php' + sufixo);
    desenharLista(resposta.servicos);
  } catch (erro) {
    listaContainer.innerHTML = `<p>Erro ao carregar serviços: ${escapeHtml(erro.message)}</p>`;
  }
}

function desenharLista(servicos) {
  if (servicos.length === 0) {
    listaContainer.innerHTML = '<div class="estado-vazio"><h3>Nenhum serviço encontrado</h3></div>';
    return;
  }
  listaContainer.innerHTML = servicos.map((s) => `
    <div class="avaliacao" data-id="${s.id_servico}">
      <div class="avaliacao__corpo">
        <span class="avaliacao__nome">
          ${escapeHtml(s.nome)}
          ${s.ativo ? '' : '<span style="color: var(--texto-secundario); font-weight:400;"> (inativo)</span>'}
          ${(s.intervalo_recomendado_meses || s.intervalo_recomendado_km) ? `<span style="color: var(--texto-secundario); font-weight:400; font-size:0.85rem;"> · lembrete a cada ${[s.intervalo_recomendado_meses ? s.intervalo_recomendado_meses + ' meses' : null, s.intervalo_recomendado_km ? s.intervalo_recomendado_km.toLocaleString('pt-BR') + ' km' : null].filter(Boolean).join(' ou ')}</span>` : ''}
        </span>
      </div>
      <div style="display:flex; gap:8px;">
        <button type="button" class="botao botao--secundario botao-renomear" data-id="${s.id_servico}" data-nome="${escapeHtml(s.nome)}">Renomear</button>
        <button type="button" class="botao botao--secundario botao-editar-intervalo" data-id="${s.id_servico}" data-intervalo="${s.intervalo_recomendado_meses ?? ''}" data-intervalo-km="${s.intervalo_recomendado_km ?? ''}">Intervalo</button>
        <button type="button" class="botao botao--secundario botao-alternar" data-id="${s.id_servico}" data-ativo="${s.ativo ? '1' : '0'}">
          ${s.ativo ? 'Desativar' : 'Reativar'}
        </button>
        <button type="button" class="botao botao--perigo botao-excluir" data-id="${s.id_servico}">Excluir</button>
      </div>
    </div>
  `).join('');

  document.querySelectorAll('.botao-renomear').forEach((b) => {
    b.addEventListener('click', () => renomearServico(Number(b.dataset.id), b.dataset.nome));
  });
  document.querySelectorAll('.botao-editar-intervalo').forEach((b) => {
    b.addEventListener('click', () => editarIntervalo(Number(b.dataset.id), b.dataset.intervalo, b.dataset.intervaloKm));
  });
  document.querySelectorAll('.botao-alternar').forEach((b) => {
    b.addEventListener('click', () => alternarAtivo(Number(b.dataset.id), b.dataset.ativo === '1'));
  });
  document.querySelectorAll('.botao-excluir').forEach((b) => {
    b.addEventListener('click', () => excluirServico(Number(b.dataset.id)));
  });
}

checkboxInativos.addEventListener('change', carregarServicos);

document.getElementById('botao-novo-servico').addEventListener('click', () => abrirFormulario());

function abrirFormulario() {
  formContainer.innerHTML = `
    <form id="form-servico" class="cartao-formulario" style="margin: 16px 0; max-width: 100%;">
      <h3>Novo serviço</h3>
      <p id="erro-servico" class="mensagem-erro"></p>
      <div class="campo-formulario">
        <label for="input-nome-servico">Nome</label>
        <input type="text" id="input-nome-servico" required maxlength="100" placeholder="Ex: Alinhamento e balanceamento">
      </div>
      <div class="campo-formulario">
        <label for="input-intervalo-servico">Lembrete de manutenção a cada quantos meses? (opcional)</label>
        <input type="number" id="input-intervalo-servico" min="1" step="1" placeholder="Ex: 6">
      </div>
      <div class="campo-formulario">
        <label for="input-intervalo-km-servico">...ou a cada quantos km? (opcional — vale o que vier primeiro)</label>
        <input type="number" id="input-intervalo-km-servico" min="1" step="1" placeholder="Ex: 10000">
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="botao botao--primario">Cadastrar</button>
        <button type="button" id="botao-cancelar-servico" class="botao botao--secundario">Cancelar</button>
      </div>
    </form>
  `;
  document.getElementById('botao-cancelar-servico').addEventListener('click', () => {
    formContainer.innerHTML = '';
  });
  document.getElementById('form-servico').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const nome = document.getElementById('input-nome-servico').value.trim();
    const intervalo = document.getElementById('input-intervalo-servico').value;
    const intervaloKm = document.getElementById('input-intervalo-km-servico').value;
    const erroEl = document.getElementById('erro-servico');
    try {
      await chamarApi('/servicos/create_servico.php', {
        method: 'POST',
        body: JSON.stringify({ nome, intervalo_recomendado_meses: intervalo ? Number(intervalo) : null, intervalo_recomendado_km: intervaloKm ? Number(intervaloKm) : null }),
      });
      formContainer.innerHTML = '';
      carregarServicos();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}

async function editarIntervalo(idServico, mesesAtual, kmAtual) {
  const respMeses = prompt('A cada quantos MESES este serviço deve ser refeito? (em branco = sem critério de tempo)', mesesAtual);
  if (respMeses === null) return;
  const respKm = prompt('A cada quantos KM este serviço deve ser refeito? (em branco = sem critério de km)', kmAtual);
  if (respKm === null) return;
  const meses = respMeses.trim();
  const km = respKm.trim();
  const invalido = (v) => v !== '' && (!Number.isInteger(Number(v)) || Number(v) <= 0);
  if (invalido(meses) || invalido(km)) {
    alert('Informe números inteiros positivos, ou deixe em branco.');
    return;
  }
  try {
    await chamarApi('/servicos/update_servico.php', {
      method: 'POST',
      body: JSON.stringify({
        id_servico: idServico,
        intervalo_recomendado_meses: meses === '' ? null : Number(meses),
        intervalo_recomendado_km: km === '' ? null : Number(km),
      }),
    });
    carregarServicos();
  } catch (erro) {
    alert('Não foi possível atualizar: ' + erro.message);
  }
}

async function renomearServico(idServico, nomeAtual) {
  const novoNome = prompt('Novo nome do serviço:', nomeAtual);
  if (novoNome === null || novoNome.trim() === '' || novoNome.trim() === nomeAtual) {
    return;
  }
  try {
    await chamarApi('/servicos/update_servico.php', {
      method: 'POST',
      body: JSON.stringify({ id_servico: idServico, nome: novoNome.trim() }),
    });
    carregarServicos();
  } catch (erro) {
    alert('Não foi possível renomear: ' + erro.message);
  }
}

async function alternarAtivo(idServico, ativoAtual) {
  try {
    await chamarApi('/servicos/update_servico.php', {
      method: 'POST',
      body: JSON.stringify({ id_servico: idServico, ativo: !ativoAtual }),
    });
    carregarServicos();
  } catch (erro) {
    alert('Não foi possível alterar o status: ' + erro.message);
  }
}

async function excluirServico(idServico) {
  if (!confirm('Excluir este serviço definitivamente?')) {
    return;
  }
  try {
    await chamarApi('/servicos/delete_servico.php', {
      method: 'POST',
      body: JSON.stringify({ id_servico: idServico }),
    });
    carregarServicos();
  } catch (erro) {
    // Serviço em uso (409) recebe uma orientação mais clara que um alert genérico
    alert(erro.message);
  }
}

if (admin) {
  carregarServicos();
}
