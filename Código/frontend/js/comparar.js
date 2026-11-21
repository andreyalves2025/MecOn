/**
 * MecOn - comparar.js
 * Lê os ids da querystring (?ids=1,2,3), busca cada oficina via
 * get_oficina.php e monta uma tabela comparativa lado a lado.
 */
const container = document.getElementById('conteudo-comparacao');

function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}
function estrelas(nota) {
  if (nota === null) return 'Sem avaliações';
  const cheias = Math.round(nota);
  return '★★★★★☆☆☆☆☆'.slice(5 - cheias, 10 - cheias) + ` (${nota.toFixed(1)})`;
}

async function carregarComparacao() {
  const ids = new URLSearchParams(window.location.search).get('ids');
  if (!ids) {
    container.innerHTML = '<p>Nenhuma oficina selecionada para comparar.</p>';
    return;
  }
  const listaIds = ids.split(',').map(Number).filter((id) => !Number.isNaN(id)).slice(0, 3);
  if (listaIds.length < 2) {
    container.innerHTML = '<p>Selecione pelo menos 2 oficinas na busca para compará-las.</p>';
    return;
  }
  try {
    const respostas = await Promise.all(
      listaIds.map((id) => chamarApi(`/oficinas/get_oficina.php?id_oficina=${id}`))
    );
    desenharTabela(respostas.map((r) => r.oficina));
  } catch (erro) {
    container.innerHTML = `<p>Não foi possível carregar a comparação: ${escapeHtml(erro.message)}</p>`;
  }
}

function desenharTabela(oficinas) {
  const todosServicos = new Map(); // id_servico -> nome, para linhas comuns de preço
  oficinas.forEach((o) => {
    o.servicos.forEach((s) => todosServicos.set(s.id_servico, s.nome));
  });

  const colunas = oficinas.map((o) => `
    <th>
      <a href="oficina.html?id=${o.id_oficina}" style="color: inherit;">${escapeHtml(o.nome)}</a>
      ${o.verificada ? '<div style="color: var(--azul-mecon); font-weight:400; font-size:0.8rem;">✔ Verificada</div>' : ''}
    </th>
  `).join('');

  const linhaAvaliacao = oficinas.map((o) => `<td>${estrelas(o.media_avaliacao)}</td>`).join('');
  const linhaPrecoMedio = oficinas.map((o) => `<td>${o.preco_medio !== null ? 'R$ ' + o.preco_medio.toFixed(2) : '—'}</td>`).join('');
  const linhaLocal = oficinas.map((o) => `<td>${escapeHtml(o.bairro)}, ${escapeHtml(o.cidade)}</td>`).join('');
  const linhaTelefone = oficinas.map((o) => `<td>${escapeHtml(o.telefone)}</td>`).join('');

  const linhasServicos = Array.from(todosServicos.entries()).map(([idServico, nomeServico]) => {
    const celulas = oficinas.map((o) => {
      const servico = o.servicos.find((s) => s.id_servico === idServico);
      return `<td>${servico ? 'R$ ' + servico.preco.toFixed(2) : '—'}</td>`;
    }).join('');
    return `<tr><td>${escapeHtml(nomeServico)}</td>${celulas}</tr>`;
  }).join('');

  container.innerHTML = `
    <div class="tabela-comparar-wrap">
      <table class="tabela-comparar">
        <thead><tr><th></th>${colunas}</tr></thead>
        <tbody>
          <tr><td>Avaliação</td>${linhaAvaliacao}</tr>
          <tr><td>Preço médio</td>${linhaPrecoMedio}</tr>
          <tr><td>Localização</td>${linhaLocal}</tr>
          <tr><td>Telefone</td>${linhaTelefone}</tr>
          ${linhasServicos}
        </tbody>
      </table>
    </div>
  `;
}

carregarComparacao();
