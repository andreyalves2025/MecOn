/**
 * MecOn - admin-oficinas.js
 * Administração de oficinas: listar (com busca), cadastrar (com login
 * próprio da oficina) e desativar/reativar (exclusão lógica, preserva o histórico). Edição NÃO é permitida pelo admin --
 * após criada, somente a própria oficina pode editar seus dados,
 * via login em login.html e acesso a perfil-oficina.html.
 * Exige login como administrador.
 */
const admin = exigirAdminUI();
const listaContainer = document.getElementById('lista-oficinas-admin');
const formContainer = document.getElementById('form-oficina-container');
const inputBusca = document.getElementById('input-busca-oficina');
let cacheServicos = null;
let cacheOficinas = [];
function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}
async function carregarServicos() {
  if (!cacheServicos) {
    const r = await chamarApi('/servicos/list_servicos.php');
    cacheServicos = r.servicos;
  }
  return cacheServicos;
}
async function carregarOficinas() {
  listaContainer.innerHTML = '<p>Carregando oficinas…</p>';
  try {
    const resposta = await chamarApi('/oficinas/list_oficinas.php?incluir_inativas=1');
    cacheOficinas = resposta.oficinas;
    desenharLista(cacheOficinas);
  } catch (erro) {
    listaContainer.innerHTML = `<p>Erro ao carregar oficinas: ${escapeHtml(erro.message)}</p>`;
  }
}
function desenharLista(oficinas) {
  if (oficinas.length === 0) {
    listaContainer.innerHTML = '<div class="estado-vazio"><h3>Nenhuma oficina encontrada</h3></div>';
    return;
  }
  listaContainer.innerHTML = oficinas.map((o) => `
    <div class="avaliacao">
      <div class="avaliacao__corpo">
        <span class="avaliacao__nome">
          ${escapeHtml(o.nome)}
          ${o.verificada ? '<span title="Oficina verificada" style="color: var(--azul-mecon);">✔ Verificada</span>' : ''}
          ${o.ativa === false ? '<span title="Oficina desativada: fora das buscas, histórico dos clientes preservado" style="color: var(--texto-secundario);">⏸ Desativada</span>' : ''}
        </span>
        <p class="avaliacao__comentario">Tel: ${escapeHtml(o.telefone)} · Preço médio: ${o.preco_medio !== null ? 'R$ ' + Number(o.preco_medio).toFixed(2) : '—'}</p>
        <p style="font-size:0.85rem; color: var(--texto-secundario); margin: 4px 0 0;">${escapeHtml(o.bairro)} - ${escapeHtml(o.cidade)}</p>
      </div>
      <div style="display:flex; gap:8px;">
        <button type="button" class="botao botao--secundario botao-alternar-verificacao" data-id="${o.id_oficina}" data-verificada="${o.verificada ? '1' : '0'}">
          ${o.verificada ? 'Remover selo' : 'Verificar oficina'}
        </button>
        ${o.ativa === false
          ? `<button type="button" class="botao botao--primario botao-reativar-oficina" data-id="${o.id_oficina}">Reativar</button>`
          : `<button type="button" class="botao botao--perigo botao-excluir-oficina" data-id="${o.id_oficina}">Desativar</button>`}
      </div>
    </div>
  `).join('');
  document.querySelectorAll('.botao-excluir-oficina').forEach((b) => {
    b.addEventListener('click', () => excluirOficina(Number(b.dataset.id)));
  });
  document.querySelectorAll('.botao-reativar-oficina').forEach((b) => {
    b.addEventListener('click', () => reativarOficina(Number(b.dataset.id)));
  });
  document.querySelectorAll('.botao-alternar-verificacao').forEach((b) => {
    b.addEventListener('click', () => alternarVerificacao(Number(b.dataset.id), b.dataset.verificada === '1'));
  });
}
async function alternarVerificacao(idOficina, verificadaAtual) {
  try {
    await chamarApi('/oficinas/verificar_oficina.php', {
      method: 'POST',
      body: JSON.stringify({ id_oficina: idOficina, verificada: !verificadaAtual }),
    });
    carregarOficinas();
  } catch (erro) {
    alert('Não foi possível alterar a verificação: ' + erro.message);
  }
}
inputBusca.addEventListener('input', () => {
  const termo = inputBusca.value.trim().toLowerCase();
  const filtradas = cacheOficinas.filter((o) =>
    o.nome.toLowerCase().includes(termo)
  );
  desenharLista(filtradas);
});
document.getElementById('botao-nova-oficina').addEventListener('click', () => abrirFormulario());
async function abrirFormulario() {
  await carregarServicos();
  const linhasServicos = cacheServicos.map((s) => `
    <label style="display:flex; align-items:center; gap:8px; margin: 6px 0;">
      <input type="checkbox" class="checkbox-servico" value="${s.id_servico}">
      <span style="flex:1;">${escapeHtml(s.nome)}</span>
      <input type="number" class="input-preco-servico" data-id="${s.id_servico}" min="0" step="0.01"
        placeholder="Preço (R$)" style="width:120px; padding:6px 10px;" disabled>
    </label>
  `).join('');
  formContainer.innerHTML = `
    <form id="form-oficina" class="cartao-formulario" style="margin: 16px 0; max-width: 100%;">
      <h3>Nova oficina</h3>
      <p style="color: var(--texto-secundario); font-size:0.85rem; margin-top:-8px; margin-bottom:16px;">
        O email e senha definidos aqui serão usados pela própria oficina para fazer login
        e editar seus dados depois. Após criada, você não poderá mais editar estas informações.
      </p>
      <p id="erro-oficina" class="mensagem-erro"></p>
      <div class="campo-formulario">
        <label for="input-nome">Nome</label>
        <input type="text" id="input-nome" required>
      </div>
      <div class="campo-formulario">
        <label for="input-email">Email (login da oficina)</label>
        <input type="email" id="input-email" required autocomplete="off">
      </div>
      <div class="campo-formulario">
        <label for="input-senha">Senha (login da oficina)</label>
        <input type="password" id="input-senha" required minlength="6" autocomplete="new-password">
      </div>
      <div class="campo-formulario">
        <label for="input-cidade">Cidade</label>
        <input type="text" id="input-cidade" required>
      </div>
      <div class="campo-formulario">
        <label for="input-bairro">Bairro</label>
        <input type="text" id="input-bairro" required>
      </div>
      <div class="campo-formulario">
        <label for="input-endereco">Endereço</label>
        <input type="text" id="input-endereco" required>
      </div>
      <div class="campo-formulario">
        <label for="input-telefone">Telefone</label>
        <input type="text" id="input-telefone" required>
      </div>
      <div class="campo-formulario">
        <label for="input-descricao">Descrição</label>
        <input type="text" id="input-descricao">
      </div>
      <div class="campo-formulario">
        <span style="display:block; font-weight:600; margin-bottom:8px;">Serviços oferecidos e preços</span>
        <p style="color: var(--texto-secundario); font-size:0.85rem; margin-top:-4px;">
          Marque cada serviço oferecido e informe quanto esta oficina cobra por ele.
        </p>
        ${linhasServicos}
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="botao botao--primario">Cadastrar oficina</button>
        <button type="button" id="botao-cancelar-oficina" class="botao botao--secundario">Cancelar</button>
      </div>
    </form>
  `;
  formContainer.querySelectorAll('.checkbox-servico').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      const inputPreco = formContainer.querySelector(`.input-preco-servico[data-id="${checkbox.value}"]`);
      inputPreco.disabled = !checkbox.checked;
      inputPreco.required = checkbox.checked;
      if (!checkbox.checked) inputPreco.value = '';
    });
  });
  document.getElementById('botao-cancelar-oficina').addEventListener('click', () => {
    formContainer.innerHTML = '';
  });
  document.getElementById('form-oficina').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const servicosMarcados = Array.from(formContainer.querySelectorAll('.checkbox-servico:checked'))
      .map((c) => {
        const inputPreco = formContainer.querySelector(`.input-preco-servico[data-id="${c.value}"]`);
        return { id_servico: Number(c.value), preco: Number(inputPreco.value) };
      });
    const corpo = {
      nome: document.getElementById('input-nome').value.trim(),
      email: document.getElementById('input-email').value.trim(),
      senha: document.getElementById('input-senha').value,
      cidade: document.getElementById('input-cidade').value.trim(),
      bairro: document.getElementById('input-bairro').value.trim(),
      endereco: document.getElementById('input-endereco').value.trim(),
      telefone: document.getElementById('input-telefone').value.trim(),
      descricao: document.getElementById('input-descricao').value.trim(),
      servicos: servicosMarcados,
    };
    const erroEl = document.getElementById('erro-oficina');
    if (servicosMarcados.length === 0) {
      erroEl.textContent = 'Selecione ao menos um serviço e informe seu preço.';
      erroEl.classList.add('mensagem-erro--visivel');
      return;
    }
    try {
      await chamarApi('/oficinas/create_oficina.php', { method: 'POST', body: JSON.stringify(corpo) });
      formContainer.innerHTML = '';
      carregarOficinas();
    } catch (erro) {
      erroEl.textContent = erro.message;
      erroEl.classList.add('mensagem-erro--visivel');
    }
  });
}
async function excluirOficina(idOficina) {
  if (!confirm('Desativar esta oficina? Ela deixa de aparecer nas buscas e não recebe novos pedidos, mas o histórico de manutenção e os agendamentos dos clientes são preservados. Você pode reativá-la depois.')) {
    return;
  }
  try {
    await chamarApi('/oficinas/delete_oficina.php', {
      method: 'POST',
      body: JSON.stringify({ id_oficina: idOficina }),
    });
    carregarOficinas();
  } catch (erro) {
    alert('Não foi possível desativar: ' + erro.message);
  }
}
async function reativarOficina(idOficina) {
  try {
    await chamarApi('/oficinas/reativar_oficina.php', {
      method: 'POST',
      body: JSON.stringify({ id_oficina: idOficina }),
    });
    carregarOficinas();
  } catch (erro) {
    alert('Não foi possível reativar: ' + erro.message);
  }
}
if (admin) {
  carregarOficinas();
}
